/**
 * ============================================================
 *  CHICKEN COOP IoT — NodeMCU ESP8266 #1
 *  Modul: Kontrol Pakan & Air Minum
 *  Komunikasi: MQTT (Eclipse Mosquitto — AWS EC2)
 * ============================================================
 *
 *  PINOUT HARDWARE:
 *    D1 → RTC DS3231 SCL
 *    D2 → RTC DS3231 SDA
 *    D0 → Servo Hopper Pakan
 *    D6 → Servo Pipa Distribusi
 *    D7 → Trigger Ultrasonik HC-SR04
 *    D5 → Echo Ultrasonik HC-SR04
 *    D3 → Relay Pompa Air
 *    D4 → Buzzer
 *
 *  CHANGELOG PERBAIKAN:
 *    [FIX] reconnect() blocking diganti handleMqttReconnect() non-blocking
 *          → loop() tidak pernah freeze, jadwal pakan tidak terlewat
 *    [FIX] Buzzer non-blocking: setBuzzer()+delay() diganti buzzerTrigger()
 *          → controlWater() tidak blocking selama 200–300ms
 *    [FIX] Publish telemetri air segera saat pompa berubah state
 *    [FIX] setKeepAlive(60) + setSocketTimeout(10) untuk koneksi MQTT stabil
 *    [FIX] updateBuzzer() dipanggil pertama di setiap loop() iterasi
 * ============================================================
 */
#include <Wire.h>
#include <RTClib.h>
#include <Servo.h>
#include <ESP8266WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

#define RTC_SDA           D2
#define RTC_SCL           D1

#define SERVO_HOPPER_PIN  D0
#define SERVO_PIPE_PIN    D6

#define TRIG_PIN          D7
#define ECHO_PIN          D5

#define RELAY_PUMP_PIN    D3
#define BUZZER_PIN        D4

// ============================================================
// KONFIGURASI WIFI & MQTT BROKER
// ============================================================
const char* ssid        = "Dikajarazaki";
const char* password    = "Apasandinya?";
const char* mqtt_server = "23.21.15.160"; // IP komputer yang menjalankan Mosquitto Broker
const int   mqtt_port   = 1883;
const char* mqtt_user   = "laravel_worker";  // Username MQTT (kosongkan jika tanpa auth)
const char* mqtt_pass   = "rezatugasakhir09";// Password MQTT (sesuai setup-mqtt-auth.sh)

WiFiClient   espClient;
PubSubClient client(espClient);

RTC_DS3231 rtc;
Servo      servoHopper;
Servo      servoPipe;

#define RELAY_ACTIVE_LOW  true

#define HOPPER_CLOSE      0
#define HOPPER_OPEN       70
#define PIPE_NORMAL       0
#define PIPE_TILT         60

// ============================================================
// KALIBRASI SENSOR ULTRASONIK — WADAH BAMBU AIR MINUM
// Ukur jarak sensor saat wadah KOSONG dan saat wadah PENUH,
// lalu masukkan nilainya di bawah ini.
// ============================================================
#define DISTANCE_EMPTY  4.0f   // Jarak sensor → permukaan air saat wadah KOSONG (cm)
#define DISTANCE_FULL   3.0f   // Jarak sensor → permukaan air saat wadah PENUH  (cm)

// ============================================================
// THRESHOLD — Dapat diperbarui dari website via MQTT
// ============================================================
float waterMin  = 25.0f;  // % level air minimum sebelum pompa ON  (default: 25%)
float waterFull = 75.0f;  // % level air penuh sebelum pompa OFF   (default: 75%)

// ============================================================
// STATE SISTEM
// ============================================================
bool pumpState    = false;  // Status relay pompa saat ini
bool isManualMode = false;  // true = mode manual, false = mode auto

// ============================================================
// STRUKTUR JADWAL PAKAN DINAMIS (Sinkron dengan Website via RTC)
// ============================================================
#define MAX_SCHEDULES 15

struct FeedSchedule {
  int  id;
  int  hour;
  int  minute;
  int  portionGrams;
  bool isActive;
  char label[32];
};

// Jadwal Default (Fallback sebelum disinkronkan dari Web)
FeedSchedule feedSchedules[MAX_SCHEDULES] = {
  { 1, 6,  0, 500, true, "Makan Pagi" },
  { 2, 12, 0, 500, true, "Makan Siang" },
  { 3, 17, 0, 500, true, "Makan Sore" }
};
int totalSchedules = 3;

int  lastFeedDay    = -1;
int  lastFeedHour   = -1;
int  lastFeedMinute = -1;
int  lastFeedId     = -1;

unsigned long lastTelemetryMs    = 0;
const long    TELEMETRY_INTERVAL = 3000; // 3 detik → lebih responsif di website

// --- Non-blocking buzzer ---
bool          buzzerActive       = false;
unsigned long buzzerOnUntilMs    = 0;
unsigned long buzzerPauseUntilMs = 0;
int           buzzerBeepsLeft    = 0;
int           buzzerOnDurationMs = 100;

// --- Non-blocking MQTT reconnect ---
unsigned long lastReconnectAttemptMs = 0;
const long    RECONNECT_INTERVAL     = 5000;
int           reconnectRetries       = 0;
const int     MAX_RECONNECT_RETRIES  = 10;

// --- Debounce sensor ultrasonik ---
uint8_t       ultrasonicFailCount      = 0;
const uint8_t ULTRASONIC_FAIL_THRESHOLD = 3;


// ============================================================
// FORWARD DECLARATIONS
// ============================================================
void feedChicken(bool isManual, int portionGrams = 500, int scheduleId = 0, const char* label = "Manual");
void publishWaterTelemetry(float waterPercent);
void syncRtcTime(int year, int month, int day, int hour, int minute, int second);

// ============================================================
// HELPER: RELAY & BUZZER
// ============================================================
void setPump(bool state) {
  pumpState = state;
  digitalWrite(RELAY_PUMP_PIN, RELAY_ACTIVE_LOW ? (state ? LOW : HIGH) : (state ? HIGH : LOW));
}

void setBuzzer(bool state) {
  digitalWrite(BUZZER_PIN, state ? HIGH : LOW);
}

/**
 * Mulai urutan beep non-blocking.
 * Panggil sekali; updateBuzzer() di loop() yang menyelesaikannya.
 */
void buzzerTrigger(int count, int durationMs = 100) {
  if (count <= 0) return;
  buzzerBeepsLeft    = count;
  buzzerOnDurationMs = durationMs;
  buzzerActive       = true;
  buzzerPauseUntilMs = 0;
  setBuzzer(true);
  buzzerOnUntilMs    = millis() + durationMs;
}

/**
 * Harus dipanggil di setiap iterasi loop().
 * Mengelola on/off buzzer tanpa delay().
 */
void updateBuzzer() {
  if (!buzzerActive) return;
  unsigned long now = millis();

  if (buzzerOnUntilMs > 0 && now >= buzzerOnUntilMs) {
    setBuzzer(false);
    buzzerOnUntilMs = 0;
    buzzerBeepsLeft--;
    if (buzzerBeepsLeft > 0) {
      buzzerPauseUntilMs = now + 100; // jeda 100ms antar beep
    } else {
      buzzerActive = false;
    }
  }

  if (buzzerPauseUntilMs > 0 && now >= buzzerPauseUntilMs) {
    buzzerPauseUntilMs = 0;
    setBuzzer(true);
    buzzerOnUntilMs = now + buzzerOnDurationMs;
  }
}

// ============================================================
// HELPER: PUBLISH MQTT
// ============================================================
void mqttPublish(const char* topic, const char* payload) {
  if (client.connected()) {
    client.publish(topic, payload);
    Serial.print("[MQTT PUBLISH] ");
    Serial.print(topic);
    Serial.print(" → ");
    Serial.println(payload);
  }
}

// ============================================================
// HELPER: SINKRONISASI JAM RTC DS3231
// ============================================================
void syncRtcTime(int year, int month, int day, int hour, int minute, int second) {
  if (year >= 2024 && month >= 1 && month <= 12 && day >= 1 && day <= 31 &&
      hour >= 0 && hour <= 23 && minute >= 0 && minute <= 59) {
    rtc.adjust(DateTime(year, month, day, hour, minute, second));
    Serial.printf("[RTC SYNC] Waktu RTC berhasil diperbarui: %04d-%02d-%02d %02d:%02d:%02d\n",
      year, month, day, hour, minute, second);
  } else {
    Serial.println("[RTC ERROR] Format waktu sync tidak valid!");
  }
}

// ============================================================
// MQTT CALLBACK — Menerima perintah & jadwal dari Website
// ============================================================
void mqttCallback(char* topic, byte* payload, unsigned int length) {
  String message;
  message.reserve(length);
  for (unsigned int i = 0; i < length; i++) {
    message += (char)payload[i];
  }

  Serial.print("[MQTT TERIMA] ");
  Serial.print(topic);
  Serial.print(" → ");
  Serial.println(message);

  // Ukuran buffer JSON disesuaikan untuk menampung array jadwal
  DynamicJsonDocument doc(2048);
  DeserializationError error = deserializeJson(doc, message);
  if (error) {
    Serial.print("[ERROR] Gagal parse JSON: ");
    Serial.println(error.c_str());
    return;
  }

  String topicStr = String(topic);

  // ----------------------------------------------------------
  // TOPIK: chickencoop/device/control
  // Perintah kontrol aktuator & feed manual dari Website
  // ----------------------------------------------------------
  if (topicStr == "chickencoop/device/control") {
    String device = doc["device"] | "";
    String action = doc["action"] | "";
    String mode   = doc["mode"]   | "";

    // Ubah mode global (auto / manual)
    if (mode == "manual") {
      isManualMode = true;
      Serial.println("[MODE] Beralih ke MANUAL.");
    } else if (mode == "auto") {
      isManualMode = false;
      Serial.println("[MODE] Beralih ke AUTO.");
    }

    // Kontrol Pompa
    if (device == "pump") {
      if (action == "ON") {
        setPump(true);
        isManualMode = true;
        Serial.println("[AKTUATOR] Pompa → ON (Manual)");
      } else if (action == "OFF") {
        setPump(false);
        isManualMode = true;
        Serial.println("[AKTUATOR] Pompa → OFF (Manual)");
      } else if (action == "AUTO") {
        isManualMode = false;
        Serial.println("[AKTUATOR] Pompa → kembali ke AUTO");
      }
    }

    // Trigger Pemberian Pakan Manual dari Website
    if (device == "feed" && action == "FEED") {
      int portion = doc["portion_grams"] | 500;
      Serial.printf("[PAKAN] Perintah pakan manual dari Website diterima (%d gram).\n", portion);
      feedChicken(true, portion, 0, "Manual (Website)");
    }
  }

  // ----------------------------------------------------------
  // TOPIK: chickencoop/feed/schedules/sync
  // Menerima seluruh daftar jadwal pakan aktif sekaligus dari Web + Waktu Server
  // ----------------------------------------------------------
  else if (topicStr == "chickencoop/feed/schedules/sync") {
    // 1. Sinkronkan waktu RTC jika data server_time disertakan
    if (doc.containsKey("server_time")) {
      JsonObject st = doc["server_time"];
      syncRtcTime(
        st["year"]   | 2026,
        st["month"]  | 1,
        st["day"]    | 1,
        st["hour"]   | 0,
        st["minute"] | 0,
        st["second"] | 0
      );
    }

    // 2. Parse array jadwal pakan
    if (doc.containsKey("schedules")) {
      JsonArray schedArr = doc["schedules"].as<JsonArray>();
      int count = 0;

      for (JsonObject s : schedArr) {
        if (count >= MAX_SCHEDULES) break;

        feedSchedules[count].id           = s["id"] | (count + 1);
        feedSchedules[count].hour         = s["hour"] | 0;
        feedSchedules[count].minute       = s["minute"] | 0;
        feedSchedules[count].portionGrams = s["portion_grams"] | 500;
        feedSchedules[count].isActive     = s["is_active"] | true;

        const char* lbl = s["label"] | "Pakan";
        strncpy(feedSchedules[count].label, lbl, sizeof(feedSchedules[count].label) - 1);
        feedSchedules[count].label[sizeof(feedSchedules[count].label) - 1] = '\0';

        Serial.printf("[JADWAL #%d] %s → %02d:%02d | %dg | Aktif: %s\n",
          feedSchedules[count].id,
          feedSchedules[count].label,
          feedSchedules[count].hour,
          feedSchedules[count].minute,
          feedSchedules[count].portionGrams,
          feedSchedules[count].isActive ? "YA" : "TIDAK");

        count++;
      }

      totalSchedules = count;
      Serial.printf("[JADWAL] Berhasil sinkron %d jadwal pakan dari Website!\n", totalSchedules);
    }
  }

  // ----------------------------------------------------------
  // TOPIK: chickencoop/feed/schedule (Aksi Satuan: CREATE, UPDATE, DELETE)
  // ----------------------------------------------------------
  else if (topicStr == "chickencoop/feed/schedule") {
    String action = doc["action"] | "";
    int id = doc["id"] | 0;

    if (action == "DELETE" && id > 0) {
      for (int i = 0; i < totalSchedules; i++) {
        if (feedSchedules[i].id == id) {
          for (int j = i; j < totalSchedules - 1; j++) {
            feedSchedules[j] = feedSchedules[j + 1];
          }
          totalSchedules--;
          Serial.printf("[JADWAL DELETE] Jadwal ID %d dihapus. Sisa: %d jadwal.\n", id, totalSchedules);
          break;
        }
      }
    } else if ((action == "CREATE" || action == "UPDATE") && id > 0) {
      String timeStr = doc["time"] | "00:00";
      int h = 0, m = 0;
      sscanf(timeStr.c_str(), "%d:%d", &h, &m);

      int portion = doc["portion_grams"] | 500;
      bool active = doc["is_active"] | true;
      const char* lbl = doc["label"] | "Pakan";

      bool found = false;
      for (int i = 0; i < totalSchedules; i++) {
        if (feedSchedules[i].id == id) {
          feedSchedules[i].hour         = h;
          feedSchedules[i].minute       = m;
          feedSchedules[i].portionGrams = portion;
          feedSchedules[i].isActive     = active;
          strncpy(feedSchedules[i].label, lbl, sizeof(feedSchedules[i].label) - 1);
          feedSchedules[i].label[sizeof(feedSchedules[i].label) - 1] = '\0';
          found = true;
          Serial.printf("[JADWAL UPDATE] ID %d diperbarui → %02d:%02d | %dg\n", id, h, m, portion);
          break;
        }
      }

      if (!found && totalSchedules < MAX_SCHEDULES) {
        feedSchedules[totalSchedules].id           = id;
        feedSchedules[totalSchedules].hour         = h;
        feedSchedules[totalSchedules].minute       = m;
        feedSchedules[totalSchedules].portionGrams = portion;
        feedSchedules[totalSchedules].isActive     = active;
        strncpy(feedSchedules[totalSchedules].label, lbl, sizeof(feedSchedules[totalSchedules].label) - 1);
        feedSchedules[totalSchedules].label[sizeof(feedSchedules[totalSchedules].label) - 1] = '\0';
        totalSchedules++;
        Serial.printf("[JADWAL CREATE] ID %d ditambahkan → %02d:%02d | %dg\n", id, h, m, portion);
      }
    }
  }

  // ----------------------------------------------------------
  // TOPIK: chickencoop/rtc/sync
  // Sinkronisasi manual waktu RTC DS3231
  // ----------------------------------------------------------
  else if (topicStr == "chickencoop/rtc/sync") {
    int y  = doc["year"]   | 0;
    int mo = doc["month"]  | 0;
    int d  = doc["day"]    | 0;
    int h  = doc["hour"]   | 0;
    int mi = doc["minute"] | 0;
    int s  = doc["second"] | 0;

    syncRtcTime(y, mo, d, h, mi, s);
  }

  // ----------------------------------------------------------
  // TOPIK: chickencoop/config/settings
  // Update threshold dari halaman Settings Website
  // ----------------------------------------------------------
  else if (topicStr == "chickencoop/config/settings") {
    bool updated = false;

    if (doc.containsKey("water_min")) {
      waterMin = doc["water_min"].as<float>();
      updated  = true;
    }
    if (doc.containsKey("control_mode")) {
      String cm = doc["control_mode"] | "auto";
      isManualMode = (cm == "manual");
      updated = true;
    }

    if (updated) {
      Serial.printf("[SETTINGS] Update → waterMin=%.1f%% | mode=%s\n",
        waterMin, isManualMode ? "manual" : "auto");
    }
  }
}

// ============================================================
// MQTT: Non-blocking reconnect — tidak memblokir loop()
// Dipanggil di loop(); TIDAK menggunakan delay() sama sekali.
// ============================================================
void handleMqttReconnect() {
  unsigned long now = millis();
  if (now - lastReconnectAttemptMs < RECONNECT_INTERVAL) return;
  lastReconnectAttemptMs = now;

  if (reconnectRetries >= MAX_RECONNECT_RETRIES) {
    Serial.println("[MQTT] Terlalu banyak kegagalan, restart ESP...");
    ESP.restart();
  }

  Serial.printf("[MQTT] Mencoba reconnect (#%d)...", reconnectRetries + 1);
  String clientId = "ChickenCoopFeed-" + String(random(0xFFFF), HEX);

  bool connected = (strlen(mqtt_user) > 0)
    ? client.connect(clientId.c_str(), mqtt_user, mqtt_pass)
    : client.connect(clientId.c_str());

  if (connected) {
    reconnectRetries = 0;
    Serial.println(" Terhubung! ✓");

    // Subscribe ke topik kontrol & sinkronisasi
    client.subscribe("chickencoop/device/control");
    client.subscribe("chickencoop/config/settings");
    client.subscribe("chickencoop/feed/schedule");
    client.subscribe("chickencoop/feed/schedules/sync");
    client.subscribe("chickencoop/rtc/sync");

    Serial.println("[MQTT] Berlangganan topik kontrol, jadwal pakan & sinkronisasi RTC.");

    // Minta website mengirimkan jadwal & jam server terkini
    mqttPublish("chickencoop/feed/request_sync", "{\"request\":\"sync_schedules\"}");
  } else {
    reconnectRetries++;
    Serial.printf(" Gagal (rc=%d). Retry dalam %lds...\n",
      client.state(), RECONNECT_INTERVAL / 1000);
  }
}

// ============================================================
// SENSOR: Baca Jarak Ultrasonik (HC-SR04)
// ============================================================
float readDistance() {
  const int samples = 5;
  float total = 0;
  int   validSamples = 0;

  for (int i = 0; i < samples; i++) {
    digitalWrite(TRIG_PIN, LOW);
    delayMicroseconds(2);
    digitalWrite(TRIG_PIN, HIGH);
    delayMicroseconds(10);
    digitalWrite(TRIG_PIN, LOW);

    long  duration = pulseIn(ECHO_PIN, HIGH, 30000);
    float distance = (duration * 0.0343f) / 2.0f;

    if (distance >= 2.0f && distance <= 400.0f) {
      total += distance;
      validSamples++;
    }
    delay(30);
  }

  return (validSamples == 0) ? -1.0f : (total / validSamples);
}

// ============================================================
// TELEMETRI: Publish Data Air ke MQTT
// ============================================================
void publishWaterTelemetry(float waterPercent) {
  StaticJsonDocument<200> doc;
  doc["water_level"]  = round(waterPercent * 10) / 10.0;
  doc["pump_status"]  = pumpState ? "ON" : "OFF";
  doc["control_mode"] = isManualMode ? "manual" : "auto";

  char buffer[256];
  serializeJson(doc, buffer);
  mqttPublish("chickencoop/sensor/water", buffer);
}

// ============================================================
// KONTROL AIR: Baca Sensor + Logika Auto/Manual + Telemetri
// ============================================================
void controlWater() {
  float distance = readDistance();

  if (distance < 0) {
    Serial.println("[ERROR] Sensor ultrasonik gagal dibaca!");
    buzzerTrigger(1, 200); // [FIX] non-blocking, ganti delay(200)
    publishWaterTelemetry(0.0f);
    return;
  }

  // Hitung persentase air berdasarkan kalibrasi dua titik:
  //   distance == DISTANCE_EMPTY  →  0%  (wadah kosong)
  //   distance == DISTANCE_FULL   →  100% (wadah penuh)
  float waterPercent = (DISTANCE_EMPTY - distance) / (DISTANCE_EMPTY - DISTANCE_FULL) * 100.0f;
  if (waterPercent < 0.0f)   waterPercent = 0.0f;
  if (waterPercent > 100.0f) waterPercent = 100.0f;

  Serial.printf("[AIR] Jarak: %.1f cm | Level: %.1f%%\n",
    distance, waterPercent);

  // --- Logika AUTO ---
  if (!isManualMode) {
    if (waterPercent <= waterMin && !pumpState) {
      setPump(true);
      buzzerTrigger(1, 300); // [FIX] non-blocking, ganti delay(300)
      Serial.println("[AUTO] Air rendah → Pompa ON");
      // [IMPROVEMENT] Publish segera saat pompa berubah
      publishWaterTelemetry(waterPercent);
      return;
    } else if (waterPercent >= waterFull && pumpState) {
      setPump(false);
      Serial.println("[AUTO] Air penuh → Pompa OFF");
      // [IMPROVEMENT] Publish segera saat pompa berubah
      publishWaterTelemetry(waterPercent);
      return;
    }
  }

  Serial.printf("[STATUS] Pompa=%s | Mode=%s\n",
    pumpState ? "ON" : "OFF",
    isManualMode ? "MANUAL" : "AUTO");

  publishWaterTelemetry(waterPercent);
}

// ============================================================
// PAKAN: Gerakkan Servo Sesuai Porsi + Lapor ke Website via MQTT
// ============================================================
void feedChicken(bool isManual, int portionGrams, int scheduleId, const char* label) {
  Serial.printf("[PAKAN] Memulai pakan: %s (%d gram)...\n", label, portionGrams);

  // Bunyikan buzzer sebagai sinyal
  setBuzzer(true);
  delay(300);
  setBuzzer(false);

  // 1. Miringkan servo pipa distribusi pakan
  servoPipe.write(PIPE_TILT);
  delay(1000);

  // 2. Hitung durasi pembukaan servo hopper pakan berdasarkan porsi (misal 50g-3000g -> 1s-6s)
  int openDuration = map(constrain(portionGrams, 50, 3000), 50, 3000, 1000, 6000);
  Serial.printf("[SERVO] Membuka Hopper selama %d ms...\n", openDuration);

  servoHopper.write(HOPPER_OPEN);
  delay(openDuration);
  servoHopper.write(HOPPER_CLOSE);

  // 3. Kembalikan servo pipa ke posisi normal
  delay(800);
  servoPipe.write(PIPE_NORMAL);

  Serial.println("[PAKAN] Selesai pemberian pakan.");

  // 4. Lapor status pakan ke Website via MQTT
  StaticJsonDocument<256> doc;
  doc["status"]        = "Selesai";
  doc["source"]        = isManual ? "Manual (dari Website)" : "Terjadwal (RTC Alat)";
  doc["schedule_id"]   = scheduleId > 0 ? scheduleId : (int)NULL;
  doc["label"]         = label;
  doc["portion_grams"] = portionGrams;

  char buffer[256];
  serializeJson(doc, buffer);
  mqttPublish("chickencoop/sensor/feed_done", buffer);
}

// ============================================================
// PAKAN: Cek Jadwal Berdasarkan Modul RTC DS3231 setiap loop
// ============================================================
void checkFeedingSchedule() {
  DateTime now = rtc.now();

  for (int i = 0; i < totalSchedules; i++) {
    if (!feedSchedules[i].isActive) continue;

    if (now.hour()   == feedSchedules[i].hour &&
        now.minute() == feedSchedules[i].minute) {

      // Pastikan jadwal ini belum dieksekusi pada menit yang sama hari ini
      if (lastFeedDay    != now.day()    ||
          lastFeedHour   != now.hour()   ||
          lastFeedMinute != now.minute() ||
          lastFeedId     != feedSchedules[i].id) {

        lastFeedDay    = now.day();
        lastFeedHour   = now.hour();
        lastFeedMinute = now.minute();
        lastFeedId     = feedSchedules[i].id;

        Serial.printf("[PAKAN TERJADWAL RTC] Waktu: %02d:%02d | Label: %s | Porsi: %dg\n",
          now.hour(), now.minute(), feedSchedules[i].label, feedSchedules[i].portionGrams);

        feedChicken(false, feedSchedules[i].portionGrams, feedSchedules[i].id, feedSchedules[i].label);
      }
    }
  }
}

// ============================================================
// SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  Serial.println("\n\n===== CHICKEN COOP IOT — MODUL AIR & PAKAN =====");

  Wire.begin(RTC_SDA, RTC_SCL);

  pinMode(TRIG_PIN,       OUTPUT);
  pinMode(ECHO_PIN,       INPUT);
  pinMode(RELAY_PUMP_PIN, OUTPUT);
  pinMode(BUZZER_PIN,     OUTPUT);

  setPump(false);
  setBuzzer(false);

  servoHopper.attach(SERVO_HOPPER_PIN);
  servoPipe.attach(SERVO_PIPE_PIN);
  servoHopper.write(HOPPER_CLOSE);
  servoPipe.write(PIPE_NORMAL);

  if (!rtc.begin()) {
    Serial.println("[ERROR] Modul RTC DS3231 tidak ditemukan! Cek koneksi I2C D1/D2.");
  } else {
    DateTime now = rtc.now();
    Serial.printf("[RTC READY] Waktu saat ini: %04d-%02d-%02d %02d:%02d:%02d\n",
      now.year(), now.month(), now.day(),
      now.hour(), now.minute(), now.second());
  }

  // ---- Koneksi WiFi ----
  Serial.printf("[WiFi] Menghubungkan ke '%s'", ssid);
  WiFi.begin(ssid, password);
  int wifiRetry = 0;
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
    if (++wifiRetry > 40) {
      Serial.println("\n[WiFi] Gagal terhubung! Restart...");
      ESP.restart();
    }
  }
  Serial.printf("\n[WiFi] Terhubung ✓ | IP: %s\n", WiFi.localIP().toString().c_str());

  // ---- Setup MQTT ----
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(mqttCallback);
  client.setKeepAlive(60);      // [FIX] Tingkatkan ke 60s, konsisten dengan Node #2
  client.setSocketTimeout(10);  // [FIX] Tambah socket timeout

  // Sinyal ready: buzzer 2x via non-blocking (updateBuzzer() di loop)
  buzzerTrigger(2, 150);

  Serial.println("=================================================\n");
}

// ============================================================
// MAIN LOOP
// ============================================================
void loop() {
  // [1] Update buzzer non-blocking — harus paling awal
  updateBuzzer();

  // [2] Kelola koneksi MQTT secara non-blocking
  if (!client.connected()) {
    handleMqttReconnect();
  } else {
    reconnectRetries = 0; // reset counter jika sudah connected
    client.loop();        // proses paket MQTT masuk/keluar
  }

  // [3] Kirim telemetri air setiap TELEMETRY_INTERVAL ms
  unsigned long now = millis();
  if (now - lastTelemetryMs >= TELEMETRY_INTERVAL) {
    lastTelemetryMs = now;
    controlWater(); // Baca ultrasonik + logika pompa + publish ke MQTT
  }

  // [4] Evaluasi jadwal pakan terhadap modul RTC DS3231
  checkFeedingSchedule();
}