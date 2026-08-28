#!/bin/bash
# ============================================================
# SmartCoop — Setup Let's Encrypt SSL with Certbot
# Jalankan script ini di server EC2 setelah Nginx berjalan
# Usage: bash scripts/setup-ssl.sh <your-email>
# ============================================================

set -e

DOMAIN="chicken-choop.duckdns.org"
EMAIL="${1:-admin@smartcoop.com}"

echo "============================================================"
echo "Memulai setup SSL Let's Encrypt untuk domain: $DOMAIN"
echo "Email: $EMAIL"
echo "============================================================"

# Pastikan certbot terinstall di host EC2
if ! command -v certbot &> /dev/null; then
    echo "Menginstall certbot..."
    sudo apt-get update
    sudo apt-get install -y certbot
fi

echo "Membuat sertifikat SSL via ACME challenge..."
# Menggunakan webroot yang diarahkan ke container Nginx
sudo certbot certonly --webroot \
    -w ./chicken-choop/public \
    -d "$DOMAIN" \
    --email "$EMAIL" \
    --agree-tos \
    --no-eff-email \
    --non-interactive

if sudo test -f "/etc/letsencrypt/live/$DOMAIN/fullchain.pem"; then
    echo "✅ Sertifikat SSL valid dan siap digunakan!"
    
    # Berikan izin baca agar container Nginx dapat membaca file sertifikat
    sudo chmod -R 755 /etc/letsencrypt/live /etc/letsencrypt/archive

    echo "Menerapkan konfigurasi HTTPS ke Nginx..."
    cp nginx/nginx-ssl.conf.example nginx/nginx.conf

    echo "Reload Nginx container..."
    docker exec chicken_coop_nginx nginx -s reload

    echo "============================================================"
    echo "🎉 HTTPS aktif! Akses web di: https://$DOMAIN"
    echo "============================================================"
else
    echo "❌ Gagal membuat sertifikat SSL. Pastikan domain $DOMAIN sudah mengarah ke IP EC2 ini."
    exit 1
fi
