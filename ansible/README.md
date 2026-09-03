# Ansible Automation Deployment untuk AWS EC2 (Production Ready)

Dokumentasi ini menjelaskan langkah-langkah otomatisasi menggunakan **Ansible** untuk mendeploy aplikasi SmartCoop ke instance **AWS EC2 (Ubuntu 22.04 / 24.04 LTS)**.

Skrip ini akan secara otomatis melakukan:
1. Update paket & instalasi dependensi sistem di server EC2.
2. Instalasi **Docker CE** & **Docker Compose Plugin** resmi.
3. Konfigurasi user permission & service Docker.
4. Menyinkronkan file project ke EC2 (mengabaikan folder `firmware`, `.git`, `node_modules`, `vendor`).
5. Menyiapkan environment production (`.env.docker`) dan file konfigurasi.
6. Menjalankan stack container production:
   - **Nginx Web Server** (Reverse Proxy & Static Asset Handler di Port 80)
   - **Laravel PHP-FPM Application** (Port internal 9000)
   - **MySQL 8.0 Database** (Internal only, port 3306 tidak terbuka ke publik)
   - **phpMyAdmin UI** (Database Management Tool di Port 8080)
   - **Mosquitto MQTT Broker v2.0** (Port 1883 dengan Autentikasi)
   - **MQTT Background Worker** (`php artisan mqtt:listen`)
   - **Monitoring Stack**:
     - **Grafana** (Dashboard Monitoring Server di Port 3000)
     - **Prometheus** (Time-series Scraper di Port 9090)
     - **Node Exporter** (Host Metrics Collector di Port internal 9100)

---

## 🛠️ Prasyarat

1. **Instance AWS EC2**: Ubuntu Server 22.04 / 24.04 LTS.
2. **Security Group AWS EC2**: Pastikan Inbound Rules mengizinkan:
   - **Port 22 (SSH)**: Untuk koneksi Ansible.
   - **Port 80 (HTTP)**: Untuk akses Aplikasi Web SmartCoop via Nginx.
   - **Port 8080 (Custom TCP)**: Untuk akses antarmuka database **phpMyAdmin**.
   - **Port 3000 (Custom TCP)**: Untuk akses Dashboard Monitoring **Grafana**.
   - **Port 1883 (MQTT)**: Untuk protokol komunikasi sensor IoT ESP8266.
3. **Ansible Terinstall** di mesin lokal / controller Anda.

---

## 🚀 Cara Penggunaan

### 1. Konfigurasi Inventory & SSH Key
Buka file `ansible/inventory.ini` dan sesuaikan IP Public EC2 serta lokasi SSH Private Key (`.pem`):

```ini
[ec2]
ec2_instance ansible_host=YOUR_EC2_PUBLIC_IP ansible_user=ubuntu ansible_ssh_private_key_file=~/.ssh/my-key.pem
```

Pastikan permission file SSH Key Anda sudah aman (`chmod 400 ~/.ssh/my-key.pem`).

### 2. Tes Koneksi Ansible
Jalankan tes ping ke EC2 untuk memastikan koneksi SSH berhasil:

```bash
cd ansible
ansible ec2 -m ping
```

Jika berhasil, output akan menampilkan `"ping": "pong"`.

### 3. Jalankan Playbook Deployment
Jalankan skrip deployment dengan perintah berikut:

```bash
ansible-playbook deploy.yml
```

### 4. Setup Autentikasi MQTT (Opsional / Disarankan)
Setelah container berjalan di EC2, masuk ke server via SSH dan jalankan script setup akun MQTT:

```bash
cd ~/smartcoop
bash scripts/setup-mqtt-auth.sh
```

---

## 📊 Hasil Deployment
Setelah playbook selesai dijalankan:
- **Aplikasi Web**: `http://<IP_EC2_PUBLIC>` (Port 80 via Nginx).
- **Grafana Monitoring**: `http://<IP_EC2_PUBLIC>:3000` (User: `admin`, Pass: `admin123` atau sesuai `.env.docker`).
- **phpMyAdmin**: `http://<IP_EC2_PUBLIC>:8080` (Kelola database MySQL via browser).
- **Prometheus UI**: `http://<IP_EC2_PUBLIC>:9090`.
- **Mosquitto MQTT**: `<IP_EC2_PUBLIC>:1883`.
- **MySQL 8.0, Node Exporter & Worker MQTT**: Berjalan otomatis secara terisolasi di dalam container Docker.
