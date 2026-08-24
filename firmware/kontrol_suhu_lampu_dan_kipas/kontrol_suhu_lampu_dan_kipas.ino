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
const char* mqtt_server = "192.168.1.4"; // IP lokal komputer Mosquitto
const int   mqtt_port   = 1883;
const char* mqtt_user   = "esp_device";  // Username MQTT (kosongkan jika tanpa auth)
const char* mqtt_pass   = "changeme_esp";// Password MQTT (sesuai setup-mqtt-auth.sh)

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

unsigned long lastTelemetryMs  = 0;
const long    TELEMETRY_INTERVAL = 5000; // Kirim telemetri setiap 5 detik

// ============================================================
// HELPER: KONTROL RELAY & BUZZER
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

void setBuzzer(bool state) {
  digitalWrite(BUZZER_PIN, state ? HIGH : LOW);
}

void buzzerBeep(int count, int durationMs = 100) {
  for (int i = 0; i < count; i++) {
    setBuzzer(true);
    delay(durationMs);
    setBuzzer(false);
    if (i < count - 1) delay(100);
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
      buzzerBeep(2);
      Serial.println("[KONTROL] Lampu → ON (Manual Direct)");
    } else if (status == "OFF") {
      setLamp(false);
      manualLampOverride = true;
      Serial.println("[KONTROL] Lampu → OFF (Manual Direct)");
    }
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
      buzzerBeep(2);
      Serial.println("[KONTROL] Kipas → ON (Manual Direct)");
    } else if (status == "OFF") {
      setFan(false);
      manualFanOverride = true;
      Serial.println("[KONTROL] Kipas → OFF (Manual Direct)");
    }
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
        buzzerBeep(2);
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
        buzzerBeep(2);
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
      isManualMode = (cm == "manual");
      if (!isManualMode) {
        manualLampOverride = false;
        manualFanOverride  = false;
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
// MQTT: Reconnect jika koneksi terputus
// ============================================================
void reconnect() {
  int retries = 0;
  while (!client.connected()) {
    Serial.print("[MQTT] Menghubungkan ke broker...");
    String clientId = "ChickenCoop-Node2-" + String(random(0xFFFF), HEX);

    bool connected = (strlen(mqtt_user) > 0)
      ? client.connect(clientId.c_str(), mqtt_user, mqtt_pass)
      : client.connect(clientId.c_str());

    if (connected) {
      Serial.println(" Terhubung! ✓");
      // Subscribe topik kontrol lampu, kipas, dan settings
      client.subscribe("chickencoop/device/lamp");
      client.subscribe("chickencoop/device/fan");
      client.subscribe("chickencoop/device/control");
      client.subscribe("chickencoop/config/settings");
      Serial.println("[MQTT] Subscribe: device/lamp | device/fan | device/control | config/settings");
    } else {
      Serial.printf(" Gagal (rc=%d). Retry %d/5...\n", client.state(), retries + 1);
      retries++;
      delay(5000);
      if (retries >= 5) {
        Serial.println("[MQTT] Terlalu banyak kegagalan, restart ESP...");
        ESP.restart();
      }
    }
  }
}

// ============================================================
// KONTROL SUHU: Baca DHT22 + Logika Auto/Manual + Telemetri
// ============================================================
void controlTemperature() {
  float temp = dht.readTemperature();
  float hum  = dht.readHumidity();

  // ----------------------------------------------------------
  // PENANGANAN SENSOR RUSAK / GAAL DIBACA
  // ----------------------------------------------------------
  if (isnan(temp) || isnan(hum)) {
    sensorError = true;
    Serial.println("[ERROR] Sensor DHT22 gagal dibaca / rusak!");
    
    // Bunyikan buzzer alarm pendek untuk pemberitahuan hardware error
    buzzerBeep(2, 80);

    // KETIKA SENSOR RUSAK: Otomatis masuk mode MANUAL untuk keamanan.
    // Lampu & kipas bisa dikontrol secara manual lewat tombol website!
    Serial.println("[SAFETY] Sensor Error → Mode dikunci ke MANUAL.");
    Serial.printf("[STATUS] Lampu: %s | Kipas: %s (Kontrol Manual Website)\n",
      lampState ? "ON" : "OFF", fanState ? "ON" : "OFF");

    // Tetap publish telemetri dengan status error agar website dapat info
    publishTemperatureTelemetry(NAN, NAN);
    return;
  }

  // Jika sensor dibaca dengan sukses
  sensorError = false;
  Serial.printf("[SUHU] %.1f°C | [KELEMBABAN] %.1f%%\n", temp, hum);

  // ----------------------------------------------------------
  // LOGIKA AUTO (Hanya berjalan jika mode AUTO & tanpa override)
  // ----------------------------------------------------------
  if (!isManualMode) {
    // Evaluasi Lampu Pemanas
    if (!manualLampOverride) {
      if (temp < tempMin) {
        if (!lampState) {
          setLamp(true);
          Serial.println("[AUTO] Suhu dingin → Lampu Pemanas ON");
        }
      } else if (temp >= tempMin + 1.0f) { // Histeresis 1°C agar tidak oscillating
        if (lampState) {
          setLamp(false);
          Serial.println("[AUTO] Suhu cukup → Lampu Pemanas OFF");
        }
      }
    }

    // Evaluasi Kipas Pendingin
    if (!manualFanOverride) {
      if (temp > tempMax) {
        if (!fanState) {
          setFan(true);
          Serial.println("[AUTO] Suhu panas → Kipas Pendingin ON");
        }
      } else if (temp <= tempMax - 1.0f) { // Histeresis 1°C
        if (fanState) {
          setFan(false);
          Serial.println("[AUTO] Suhu normal → Kipas Pendingin OFF");
        }
      }
    }

    // Proteksi Keamanan: Jangan biarkan Lampu dan Kipas menyala bersamaan di mode auto
    if (fanState && lampState) {
      setLamp(false);
      Serial.println("[SAFETY] Kipas ON → Lampu dipaksa OFF");
    }
  }

  Serial.printf("[STATUS] Lampu: %s | Kipas: %s | Mode: %s\n",
    lampState ? "ON" : "OFF",
    fanState  ? "ON" : "OFF",
    isManualMode ? "MANUAL" : "AUTO");

  // Publish telemetri suhu ke MQTT
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
  client.setKeepAlive(30);

  // Sinyal buzzer 2x tanda Node #2 siap
  buzzerBeep(2, 150);

  Serial.println(F("=======================================================\n"));
}

// ============================================================
// MAIN LOOP
// ============================================================
void loop() {
  // Pastikan selalu terhubung ke MQTT Broker
  if (!client.connected()) {
    reconnect();
  }
  client.loop();

  // Kirim telemetri suhu & status aktuator setiap TELEMETRY_INTERVAL
  unsigned long now = millis();
  if (now - lastTelemetryMs >= TELEMETRY_INTERVAL) {
    lastTelemetryMs = now;
    controlTemperature(); // Baca DHT22 + logika relay + publish ke MQTT
  }
}