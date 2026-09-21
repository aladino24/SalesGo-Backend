# Production Deployment Guide

## 1. Persiapan server Debian 13

Jalankan script berikut di server sebagai root:

```bash
bash -s
```

```bash
curl -fsSL https://raw.githubusercontent.com/<owner>/<repo>/main/scripts/server/install-production-stack.sh | bash
```

Atau unggah file script dari repositori ke server lalu jalankan:

```bash
chmod +x scripts/server/install-production-stack.sh
sudo ./scripts/server/install-production-stack.sh
```

Script di atas akan menginstal:
- Nginx
- PHP 8.2 + extension Laravel yang dibutuhkan
- Composer
- MySQL Server
- Supervisor
- Certbot + plugin nginx

## 2. Buat database MySQL

```bash
sudo mysql -u root
CREATE DATABASE salesgo_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'salesgo'@'localhost' IDENTIFIED BY 'password_baru';
GRANT ALL PRIVILEGES ON salesgo_api.* TO 'salesgo'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Setup `.env` pada server

Buat file `.env` di `/var/www/salesgo-api` dengan isi yang sesuai environment production:

```bash
APP_NAME="SalesGo API"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
LOG_CHANNEL=stack
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=salesgo_api
DB_USERNAME=salesgo
DB_PASSWORD=password_baru
CACHE_STORE=file
QUEUE_CONNECTION=database
SANCTUM_EXPIRATION=1440
FCM_ENABLED=true
FIREBASE_PROJECT_ID=your_project_id
GOOGLE_APPLICATION_CREDENTIALS=/var/www/salesgo-api/storage/app/firebase/service-account.json
```

## 4. Deploy otomatis via GitHub Actions

Tambahkan secret berikut di repository GitHub:

- `VPS_HOST`
- `VPS_PORT`
- `VPS_USER`
- `VPS_SSH_KEY`
- `APP_ENV`
- `APP_KEY`
- `APP_URL`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `FIREBASE_PROJECT_ID`
- `GOOGLE_APPLICATION_CREDENTIALS`

Setelah push ke branch `main`, workflow akan:
- menyalin source code melalui `rsync`
- menjalankan `composer install --no-dev`
- menjalankan migrasi database
- meng-cache config/route/view
- restart queue worker dan Nginx

## 5. SSL

Setelah domain valid mengarah ke IP server, jalankan:

```bash
sudo certbot --nginx -d api.example.com
```

## 6. Verifikasi aplikasi

```bash
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status supervisor
sudo supervisorctl status
php /var/www/salesgo-api/artisan --version
```

Cek endpoint:

```bash
curl -I https://api.example.com
```
