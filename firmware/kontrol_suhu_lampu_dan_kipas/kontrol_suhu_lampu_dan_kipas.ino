/**
 * ============================================================
 *  CHICKEN COOP IoT — NodeMCU ESP8266 #2
 *  Modul: Kontrol Suhu, Lampu Pemanas & Kipas Pendingin
 *  Komunikasi: MQTT (Eclipse Mosquitto — Lokal)
 * ============================================================
 *
 *  TOPIK MQTT:
 *
 *  PUBLISH (Alat → Web):
 *    chickencoop/sensor/temperature → { temperature, humidity, lamp_status, fan_status, sensor_error }
 *
 *  SUBSCRIBE (Web → Alat):
 *    chickencoop/device/lamp        → { status } (ON / OFF)
 *    chickencoop/device/fan         → { status } (ON / OFF)
 *    chickencoop/device/control     → { device, action, mode }
 *    chickencoop/config/settings    → { temp_min, temp_max, control_mode }
 *
 *  PINOUT HARDWARE:
 *    D5 → Sensor DHT22 (Suhu & Kelembaban)
 *    D1 → Relay Lampu Pemanas
 *    D2 → Relay Kipas Pendingin
 *    D8 → Buzzer Alarm
 * ============================================================
 *
 *  CHANGELOG PERBAIKAN:
 *    [FIX] Buzzer non-blocking (millis-based), tidak lagi pakai delay()
 *    [FIX] MQTT reconnect non-blocking, tidak memblokir loop() & telemetri
 *    [FIX] Auto-mode kipas stabil: safety check konflik lampu+kipas
 *          dilakukan SEBELUM evaluasi histeresis individu
 *    [FIX] Debounce sensor DHT22: alarm hanya setelah 3x gagal berturut
 *    [FIX] Interval telemetri 3 detik (lebih responsif di website)
 *    [FIX] manualLampOverride & manualFanOverride di-reset saat mode AUTO
 *          diterima via chickencoop/config/settings (Bug #2 Fix)
 *    [FIX] Publish telemetri segera setelah perintah manual diterima
 *          (device/lamp, device/fan, device/control) → web UI update instan
 *    [ARCH] Server tidak lagi override relay — firmware adalah master kontrol
 * ============================================================
 */

#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>
#include <DHT.h>

// ============================================================
// PINOUT HARDWARE
// ============================================================
#define DHT_PIN       D5
#define DHT_TYPE      DHT22

#define RELAY_LAMP    D1
#define RELAY_FAN     D2
#define BUZZER_PIN    D8

// ============================================================
// WiFi & MQTT CONFIGURATION
// ============================================================
const char* ssid        = "Dikajarazaki";
const char* password    = "Apasandinya?";
const char* mqtt_server = "23.21.15.160";
const int   mqtt_port   = 1883;
const char* mqtt_user   = "laravel_worker";
const char* mqtt_pass   = "rezatugasakhir09";

WiFiClient   espClient;
PubSubClient client(espClient);

// ============================================================
// SENSOR DHT22
// ============================================================
DHT dht(DHT_PIN, DHT_TYPE);

// ============================================================
// CONFIG & THRESHOLD
// ============================================================
const bool RELAY_ACTIVE_LOW = true;

float tempMin = 27.0f;  // Batas suhu dingin (di bawah ini lampu ON)
float tempMax = 30.0f;  // Batas suhu panas  (di atas ini kipas ON)

// ============================================================
// STATE SISTEM
// ============================================================
bool lampState    = false; // Status relay lampu (true = ON, false = OFF)
bool fanState     = false; // Status relay kipas (true = ON, false = OFF)
bool isManualMode = false; // true = mode manual, false = mode auto
bool sensorError  = false; // true jika DHT22 rusak / gagal dibaca

// Mode manual override individu untuk lampu dan kipas
bool manualLampOverride = false;
bool manualFanOverride  = false;

// Debounce sensor: hitung kegagalan berturut-turut sebelum alarm
uint8_t       sensorFailCount      = 0;
const uint8_t SENSOR_FAIL_THRESHOLD = 3; // alarm setelah 3x gagal berturut

// ============================================================
// TIMING NON-BLOCKING
// ============================================================
unsigned long lastTelemetryMs   = 0;
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

// ============================================================
// HELPER: KONTROL RELAY
// ============================================================
void setRelay(uint8_t pin, bool state) {
  if (RELAY_ACTIVE_LOW) {
    digitalWrite(pin, state ? LOW : HIGH);
  } else {
    digitalWrite(pin, state ? HIGH : LOW);
  }
}

void setLamp(bool state) {
  lampState = state;
  setRelay(RELAY_LAMP, state);
}

void setFan(bool state) {
  fanState = state;
  setRelay(RELAY_FAN, state);
}

// ============================================================
// BUZZER: Non-blocking (tidak menggunakan delay)
// ============================================================
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
// TELEMETRI: Publish Data Suhu ke MQTT
// ============================================================
void publishTemperatureTelemetry(float temp, float hum) {
  StaticJsonDocument<256> doc;
  doc["temperature"]  = isnan(temp) ? 0.0 : (round(temp * 10) / 10.0);
  doc["humidity"]     = isnan(hum)  ? 0.0 : (round(hum * 10) / 10.0);
  doc["lamp_status"]  = lampState ? "ON" : "OFF";
  doc["fan_status"]   = fanState  ? "ON" : "OFF";
  doc["sensor_error"] = sensorError;
  doc["control_mode"] = isManualMode ? "manual" : "auto";

  char buffer[256];
  serializeJson(doc, buffer);
  mqttPublish("chickencoop/sensor/temperature", buffer);
}

// ============================================================
// MQTT CALLBACK — Menerima Perintah dari Website
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

  StaticJsonDocument<256> doc;
  if (deserializeJson(doc, message) != DeserializationError::Ok) {
    Serial.println("[ERROR] Gagal parse JSON dari MQTT!");
    return;
  }

  String topicStr = String(topic);

  // ----------------------------------------------------------
  // TOPIK: chickencoop/device/lamp
  // Direct Control Lampu Pemanas
  // ----------------------------------------------------------
  if (topicStr == "chickencoop/device/lamp") {
    String status = doc["status"] | "";
    if (status == "ON") {
      setLamp(true);
      manualLampOverride = true;
      buzzerTrigger(1, 80); // 1 beep singkat, non-blocking
      Serial.println("[KONTROL] Lampu → ON (Manual Direct)");
    } else if (status == "OFF") {
      setLamp(false);
      manualLampOverride = true;
      Serial.println("[KONTROL] Lampu → OFF (Manual Direct)");
    }
    // [IMPROVEMENT] Publish telemetri segera agar web UI langsung terupdate
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    publishTemperatureTelemetry(isnan(t) ? 0.0f : t, isnan(h) ? 0.0f : h);
    return;
  }

  // ----------------------------------------------------------
  // TOPIK: chickencoop/device/fan
  // Direct Control Kipas Pendingin
  // ----------------------------------------------------------
  if (topicStr == "chickencoop/device/fan") {
    String status = doc["status"] | "";
    if (status == "ON") {
      setFan(true);
      manualFanOverride = true;
      buzzerTrigger(1, 80); // 1 beep singkat, non-blocking
      Serial.println("[KONTROL] Kipas → ON (Manual Direct)");
    } else if (status == "OFF") {
      setFan(false);
      manualFanOverride = true;
      Serial.println("[KONTROL] Kipas → OFF (Manual Direct)");
    }
    // [IMPROVEMENT] Publish telemetri segera agar web UI langsung terupdate
    float t = dht.readTemperature();
    float h = dht.readHumidity();
    publishTemperatureTelemetry(isnan(t) ? 0.0f : t, isnan(h) ? 0.0f : h);
    return;
  }


  // ----------------------------------------------------------
  // TOPIK: chickencoop/device/control
  // Perintah kontrol aktuator & mode dari Website
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
      manualLampOverride = false;
      manualFanOverride  = false;
      Serial.println("[MODE] Beralih ke AUTO.");
    }

    // Kontrol Lampu
    if (device == "lamp") {
      if (action == "ON") {
        setLamp(true);
        manualLampOverride = true;
        buzzerTrigger(1, 80); // non-blocking
        Serial.println("[AKTUATOR] Lampu → ON (Manual)");
      } else if (action == "OFF") {
        setLamp(false);
        manualLampOverride = true;
        Serial.println("[AKTUATOR] Lampu → OFF (Manual)");
      } else if (action == "AUTO") {
        manualLampOverride = false;
        Serial.println("[AKTUATOR] Lampu → AUTO");
      }
    }

    // Kontrol Kipas
    if (device == "fan") {
      if (action == "ON") {
        setFan(true);
        manualFanOverride = true;
        buzzerTrigger(1, 80); // non-blocking
        Serial.println("[AKTUATOR] Kipas → ON (Manual)");
      } else if (action == "OFF") {
        setFan(false);
        manualFanOverride = true;
        Serial.println("[AKTUATOR] Kipas → OFF (Manual)");
      } else if (action == "AUTO") {
        manualFanOverride = false;
        Serial.println("[AKTUATOR] Kipas → AUTO");
      }
    }

    // [IMPROVEMENT] Publish telemetri segera setelah state aktuator berubah
    {
      float t = dht.readTemperature();
      float h = dht.readHumidity();
      publishTemperatureTelemetry(isnan(t) ? 0.0f : t, isnan(h) ? 0.0f : h);
    }
    return;
  }

  // ----------------------------------------------------------
  // TOPIK: chickencoop/config/settings
  // Update threshold dari halaman Settings Website
  // ----------------------------------------------------------
  if (topicStr == "chickencoop/config/settings") {
    bool updated = false;

    if (doc.containsKey("temp_min")) {
      tempMin = doc["temp_min"].as<float>();
      updated = true;
    }
    if (doc.containsKey("temp_max")) {
      tempMax = doc["temp_max"].as<float>();
      updated = true;
    }
    if (doc.containsKey("control_mode")) {
      String cm = doc["control_mode"] | "auto";
      bool wasManual = isManualMode;
      isManualMode = (cm == "manual");
      if (!isManualMode) {
        // [FIX #2] Reset semua flag override manual saat beralih ke AUTO
        manualLampOverride = false;
        manualFanOverride  = false;
        if (wasManual) {
          Serial.println("[FIX] Reset manualLampOverride & manualFanOverride → AUTO mode aktif.");
        }
      }
      updated = true;
    }

    if (updated) {
      Serial.printf("[SETTINGS] Update → tempMin=%.1f°C | tempMax=%.1f°C | mode=%s\n",
        tempMin, tempMax, isManualMode ? "manual" : "auto");
    }
    return;
  }
}

// ============================================================
// MQTT: Non-blocking reconnect
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
  String clientId = "ChickenCoop-Node2-" + String(random(0xFFFF), HEX);

  bool connected = (strlen(mqtt_user) > 0)
    ? client.connect(clientId.c_str(), mqtt_user, mqtt_pass)
    : client.connect(clientId.c_str());

  if (connected) {
    reconnectRetries = 0;
    Serial.println(" Terhubung! ✓");
    client.subscribe("chickencoop/device/lamp");
    client.subscribe("chickencoop/device/fan");
    client.subscribe("chickencoop/device/control");
    client.subscribe("chickencoop/config/settings");
    Serial.println("[MQTT] Subscribe: device/lamp | device/fan | device/control | config/settings");
  } else {
    reconnectRetries++;
    Serial.printf(" Gagal (rc=%d). Retry dalam %lds...\n",
      client.state(), RECONNECT_INTERVAL / 1000);
  }
}

// ============================================================
// KONTROL SUHU: Baca DHT22 + Logika Auto/Manual + Telemetri
// ============================================================
void controlTemperature() {
  float temp = dht.readTemperature();
  float hum  = dht.readHumidity();

  // ----------------------------------------------------------
  // PENANGANAN SENSOR ERROR — Debounce 3 kegagalan berturut
  // ----------------------------------------------------------
  if (isnan(temp) || isnan(hum)) {
    sensorFailCount++;
    Serial.printf("[WARN] DHT22 gagal dibaca (%d/%d).\n",
      sensorFailCount, SENSOR_FAIL_THRESHOLD);

    if (sensorFailCount >= SENSOR_FAIL_THRESHOLD) {
      if (!sensorError) {
        // Transisi pertama ke error state → bunyikan alarm (non-blocking)
        sensorError = true;
        buzzerTrigger(3, 80);
        Serial.println("[ERROR] Sensor DHT22 rusak! Masuk mode SAFE.");
      }
      // Tetap publish telemetri error agar website terupdate
      publishTemperatureTelemetry(NAN, NAN);
    }
    return;
  }

  // Sensor OK: reset debounce & error flag
  sensorFailCount = 0;
  if (sensorError) {
    sensorError = false;
    Serial.println("[OK] Sensor DHT22 pulih kembali.");
  }

  Serial.printf("[SUHU] %.1f°C | [KELEMBABAN] %.1f%%\n", temp, hum);

  // ----------------------------------------------------------
  // LOGIKA AUTO
  // PERBAIKAN URUTAN:
  //   1. Safety check konflik DULU sebelum evaluasi individu,
  //      sehingga histeresis tidak bisa saling override.
  //   2. Evaluasi lampu hanya jika kipas mati (dan sebaliknya).
  // ----------------------------------------------------------
  if (!isManualMode) {

    // — Langkah 1: Cegah konflik lampu + kipas nyala bersamaan —
    // Kipas prioritas lebih tinggi (pendinginan lebih kritis)
    if (fanState && lampState && !manualLampOverride) {
      setLamp(false);
      Serial.println("[SAFETY] Konflik terdeteksi: Kipas ON → Lampu dipaksa OFF");
    }

    // — Langkah 2: Evaluasi Lampu Pemanas (hanya jika kipas MATI) —
    if (!manualLampOverride && !fanState) {
      if (temp < tempMin && !lampState) {
        setLamp(true);
        Serial.println("[AUTO] Suhu dingin → Lampu Pemanas ON");
      } else if (temp >= (tempMin + 1.0f) && lampState) {
        // Histeresis +1°C: lampu baru mati setelah suhu naik 1°C di atas tempMin
        setLamp(false);
        Serial.println("[AUTO] Suhu cukup → Lampu Pemanas OFF");
      }
    }

    // — Langkah 3: Evaluasi Kipas Pendingin (hanya jika lampu MATI) —
    if (!manualFanOverride && !lampState) {
      if (temp > tempMax && !fanState) {
        setFan(true);
        Serial.println("[AUTO] Suhu panas → Kipas Pendingin ON");
      } else if (temp <= (tempMax - 1.0f) && fanState) {
        // Histeresis -1°C: kipas baru mati setelah suhu turun 1°C di bawah tempMax
        setFan(false);
        Serial.println("[AUTO] Suhu normal → Kipas Pendingin OFF");
      }
    }
  }

  Serial.printf("[STATUS] Lampu: %s | Kipas: %s | Mode: %s\n",
    lampState ? "ON" : "OFF",
    fanState  ? "ON" : "OFF",
    isManualMode ? "MANUAL" : "AUTO");

  publishTemperatureTelemetry(temp, hum);
}

// ============================================================
// SETUP
// ============================================================
void setup() {
  Serial.begin(115200);
  delay(100);
  Serial.println(F("\n\n===== CHICKEN COOP IOT — NODE #2 (Kontrol Suhu) ====="));

  pinMode(RELAY_LAMP, OUTPUT);
  pinMode(RELAY_FAN,  OUTPUT);
  pinMode(BUZZER_PIN, OUTPUT);

  // Kondisi awal: semua relay dan buzzer OFF
  setLamp(false);
  setFan(false);
  setBuzzer(false);

  // Inisialisasi sensor DHT22
  dht.begin();
  Serial.println(F("[DHT22] Sensor suhu & kelembaban diinisialisasi (Pin D5)."));

  // Koneksi WiFi
  Serial.printf("[WiFi] Menghubungkan ke '%s'", ssid);
  WiFi.begin(ssid, password);
  int wifiRetry = 0;
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(F("."));
    if (++wifiRetry > 40) {
      Serial.println(F("\n[WiFi] Gagal terhubung! Restart..."));
      ESP.restart();
    }
  }
  Serial.printf("\n[WiFi] ✓ Terhubung | IP: %s\n", WiFi.localIP().toString().c_str());

  // Setup MQTT
  client.setServer(mqtt_server, mqtt_port);
  client.setCallback(mqttCallback);
  client.setKeepAlive(60);
  client.setSocketTimeout(10);

  Serial.println(F("=======================================================\n"));

  // Sinyal ready: buzzer 2x via non-blocking (updateBuzzer() di loop)
  buzzerTrigger(2, 150);
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

  // [3] Kirim telemetri + jalankan logika kontrol setiap TELEMETRY_INTERVAL
  unsigned long now = millis();
  if (now - lastTelemetryMs >= TELEMETRY_INTERVAL) {
    lastTelemetryMs = now;
    controlTemperature(); // Baca DHT22 + logika relay + publish ke MQTT
  }
}