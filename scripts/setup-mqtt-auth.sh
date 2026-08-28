#!/bin/bash
# ============================================================
# SmartCoop — Setup MQTT Authentication
# Jalankan script ini SEKALI setelah pertama kali deploy
# Usage: bash scripts/setup-mqtt-auth.sh
# ============================================================

set -e

CONF_DIR="mosquitto_eclipse_conf"
PASSWD_FILE="$CONF_DIR/passwd"

# Load environment variables dari .env.docker jika ada
if [ -f ".env.docker" ]; then
    export $(grep -v '^#' .env.docker | grep -v '^$' | xargs)
fi

MQTT_USER_WORKER="${MQTT_USERNAME:-laravel_worker}"
MQTT_PASS_WORKER="${MQTT_PASSWORD:-changeme_mqtt}"
MQTT_USER_ESP="esp_device"
MQTT_PASS_ESP="${MQTT_ESP_PASSWORD:-changeme_esp}"

echo "Membuat file password MQTT..."

# Gunakan container mosquitto yang sudah berjalan untuk generate passwd
docker exec chicken_coop_mosquitto mosquitto_passwd -c -b /mosquitto/config/passwd \
    "$MQTT_USER_WORKER" "$MQTT_PASS_WORKER"

docker exec chicken_coop_mosquitto mosquitto_passwd -b /mosquitto/config/passwd \
    "$MQTT_USER_ESP" "$MQTT_PASS_ESP"

echo "Mengaktifkan proteksi password di mosquitto.conf..."
cat << 'EOF' > "$CONF_DIR/mosquitto.conf"
# ── Listener MQTT ──────────────────────────────────────────────
listener 1883 0.0.0.0

# ── Akses MQTT (Password Protected) ───────────────────────────
allow_anonymous false
password_file /mosquitto/config/passwd

# ── Logging ────────────────────────────────────────────────────
log_dest file /mosquitto/log/mosquitto.log
log_type error
log_type warning
log_type notice
EOF

echo "Restart container Mosquitto..."
docker restart chicken_coop_mosquitto
docker restart chicken_coop_mqtt_worker

echo ""
echo "✅ MQTT Authentication berhasil dikonfigurasi dan aktif!"
echo "   User Laravel Worker : $MQTT_USER_WORKER"
echo "   User ESP Device     : $MQTT_USER_ESP"
echo ""
echo "⚠️  Update firmware ESP8266 dengan kredensial:"
echo "   const char* mqtt_user = \"$MQTT_USER_ESP\";"
echo "   const char* mqtt_pass = \"$MQTT_PASS_ESP\";"
