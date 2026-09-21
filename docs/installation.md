# Instalasi dan Menjalankan SalesGo API

Panduan ini untuk backend Laravel di direktori `SalesGo-Api`. API memakai PHP 8.2+, Laravel 11, MySQL, Sanctum, database queue, penyimpanan file privat lokal, dan Firebase Cloud Messaging (FCM) HTTP v1 opsional.

## Prasyarat

- PHP 8.2+ dengan extension `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `json`, dan `zip`.
- Composer 2.
- MySQL 8+ atau MariaDB kompatibel.
- Node.js tidak diperlukan untuk API ini.

```powershell
php -v
php -m
composer --version
```

## Instalasi lokal Windows (Laragon)

```powershell
cd D:\SalesGo\SalesGo-Api
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Buat database MySQL `salesgo_api`, lalu isi `.env` minimal berikut. Jangan commit `.env`.

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=salesgo_api
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
CACHE_STORE=file
```

Jalankan migration dan seed development:

```powershell
php artisan migrate --seed
php artisan config:clear
```

Seeder hanya untuk non-production dan aman dijalankan ulang.

| Username | Password | Role | Kode user |
|---|---|---|---|
| `andi.pratama` | `password` | Sales | `0010010101` |
| `supervisor.andi` | `password` | Supervisor | `0010020101` |
| `manager.andi` | `password` | Branch Manager | `0010030101` |

Ganti password dan data seed sebelum staging/production.

### Kebijakan sales order

Atur batas order di `.env`, lalu jalankan `php artisan optimize:clear` setiap
kali nilainya berubah. Nilai ini ikut dikirim pada snapshot master berikutnya
dan tetap divalidasi server-side saat order disinkronkan.

```dotenv
ORDER_MINIMUM_UNITS=1
ORDER_MAXIMUM_UNITS=1000
ORDER_MINIMUM_AMOUNT=0
ORDER_MAXIMUM_AMOUNT=0
```

`ORDER_MAXIMUM_AMOUNT=0` berarti tidak dibatasi maksimum nominal.

## Menjalankan proses lokal

Gunakan terminal terpisah:

```powershell
# Terminal 1: HTTP API
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2: sync, attachment, dan push queue
php artisan queue:work --tries=5 --timeout=120

# Terminal 3: scheduler retensi/agregasi/arsip
php artisan schedule:work
```

Base URL lokal: `http://127.0.0.1:8000/api/v1`.

`CACHE_STORE=file` direkomendasikan untuk local agar queue worker tidak
memerlukan tabel cache. Untuk production gunakan Redis atau database cache yang
memiliki migration tabel `cache`; jangan memakai file cache pada beberapa
instance server.

## Mengakses API lokal melalui ngrok

Gunakan ngrok bila aplikasi Flutter berjalan pada perangkat fisik atau URL API
perlu diakses dari luar jaringan lokal. Tunnel ini hanya untuk development;
jangan gunakan URL ngrok sementara sebagai URL production.

1. Instal ngrok dari https://ngrok.com/download dan login/autentikasi sekali:

   ```powershell
   ngrok config add-authtoken <TOKEN_NGROK_ANDA>
   ```

2. Jalankan Laravel pada terminal pertama. Host `0.0.0.0` membuat server dapat
   menerima koneksi dari proses tunnel:

   ```powershell
   php artisan serve --host=0.0.0.0 --port=8000
   ```

3. Pada terminal kedua, buat tunnel HTTPS:

   ```powershell
   ngrok http 8000
   ```

   Salin forwarding HTTPS, misalnya
   `https://abc123.ngrok-free.app`. Selama memakai URL tersebut, set di `.env`
   backend lalu bersihkan config:

   ```dotenv
   APP_URL=https://abc123.ngrok-free.app
   ```

   ```powershell
   php artisan config:clear
   ```

4. Jalankan Flutter dengan URL API penuh (termasuk `/api/v1`):

   ```powershell
   flutter run --dart-define=API_BASE_URL=https://abc123.ngrok-free.app/api/v1
   ```

   Nilai ini tidak disimpan pada source code. URL tunnel gratis biasanya
   berubah setiap kali ngrok dihentikan; ulangi langkah 3--4 bila berubah.

5. Uji login dan dashboard dari perangkat fisik. Semua request API memakai
   bearer token, sehingga tidak memerlukan konfigurasi cookie Sanctum atau CORS
   untuk aplikasi Android. Jika menjalankan Flutter Web, tambahkan origin web
   development ke konfigurasi CORS Laravel.

Flutter SalesGo menambahkan header `ngrok-skip-browser-warning: true` secara
otomatis untuk domain `ngrok-free.dev` dan `ngrok.io`. Header ini wajib pada
tunnel ngrok gratis karena tanpa itu ngrok mengembalikan halaman peringatan
HTML (`ERR_NGROK_6024`), bukan JSON API.

## Firebase Cloud Messaging

Tanpa konfigurasi FCM, delivery dicatat sebagai `Skipped`; API dan feed notifikasi tetap berjalan.

1. Firebase Console → **Project settings** → **Service accounts** → **Generate new private key**.
2. Simpan JSON key di luar repository, hanya dapat dibaca proses PHP/queue, misalnya `D:/SalesGo/secure/firebase-service-account.json`.
3. Tambahkan pada `.env`:

   ```dotenv
   FCM_ENABLED=true
   FIREBASE_PROJECT_ID=salesgo-dca78
   GOOGLE_APPLICATION_CREDENTIALS=D:/SalesGo/secure/firebase-service-account.json
   ```

4. Reload config dan mulai worker:

   ```powershell
   php artisan config:clear
   php artisan config:cache
   php artisan queue:work --tries=5
   ```

FCM memakai OAuth token pendek dari service account. Private key tidak boleh berada di Git, `.env.example`, response API, log, atau aplikasi Flutter.

## Uji cepat API

```powershell
$login = Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8000/api/v1/auth/login -ContentType application/json -Body '{"username":"andi.pratama","password":"password"}'
$token = $login.accessToken
Invoke-RestMethod -Headers @{ Authorization = "Bearer $token" } -Uri http://127.0.0.1:8000/api/v1/dashboard
```

Untuk uji FCM, jalankan Flutter pada Android fisik, login agar token perangkat terdaftar, buat event approval/transaksi, lalu amati tabel `push_deliveries` dan output `queue:work`.

## Perintah pemeliharaan

```powershell
php vendor/bin/pint --test
php artisan test
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan route:list --path=api/v1
```

## Deployment ringkas

1. Isi `APP_KEY`, database, dan Firebase credential path melalui secret manager/environment server.
2. Gunakan `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, dan database backup.
3. Jalankan `composer install --no-dev --optimize-autoloader`, migration, `php artisan optimize`, queue worker supervisor/systemd, dan scheduler cron.
4. Pastikan `storage/` dapat ditulis proses PHP, tetapi attachment privat tidak dapat diakses langsung dari web server.
5. Monitor failed job, log, kapasitas storage, dan retensi backup.

Untuk distribusi APK Android internal tanpa Play Store, baca
[internal-android-app-update.md](internal-android-app-update.md).

Lihat [api-reference.md](api-reference.md) untuk endpoint aktual dan [qa-e2e.md](qa-e2e.md) untuk alur uji offline–sync–GPS–approval.
