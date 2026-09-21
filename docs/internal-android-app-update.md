# Update APK Internal Android

SalesGo tidak memakai Play Store. Backend menyimpan APK rilis pada disk privat
Laravel dan aplikasi Android memeriksa rilis tersebut dari menu **Pengaturan >
Periksa update aplikasi**.

## Prinsip penting

- Android mengganti aplikasi melalui installer sistem; aplikasi Flutter tidak
  dapat memasang APK secara diam-diam.
- APK baru **wajib** mempunyai `applicationId` yang sama (`com.example.salesgo`)
  dan ditandatangani dengan signing certificate yang sama seperti APK terpasang.
  Jika salah satu berbeda, Android menolak update.
- Install replace Android mempertahankan database/Hive, `flutter_secure_storage`,
  file cache, dan data lokal aplikasi. Jangan uninstall aplikasi lama.
- MD5 digunakan untuk membandingkan APK terpasang dan memastikan file unduhan
  sama dengan artefak backend. Backend juga menyimpan SHA-256 untuk audit.

## Persiapan server

Jalankan migration setelah deploy kode backend:

```powershell
php artisan migrate
php artisan optimize:clear
```

APK disimpan secara privat di `storage/app/private/app-releases/android/YYYY/MM`.
Jangan taruh APK di `public/` atau commit ke Git.

## Portal web internal

Portal hanya menerima **username/email dan password user database ber-role
`it`**. Tidak ada kredensial portal terpisah dan akun Sales, Supervisor, atau
Branch Manager akan menerima HTTP 401. Login portal juga tercatat sebagai
`uploaded_by` serta audit trail rilis.

Setelah membuat akun IT, bersihkan konfigurasi dan buka:

```powershell
php artisan optimize:clear
```

```text
https://domain-backend/internal/app-releases
```

Browser menampilkan dialog Basic Authentication. Masukkan akun IT. Portal menyediakan form APK,
versi aplikasi, build number, keterangan rilis, opsi **Publikasikan sekarang**,
serta opsi **Wajib update**. Batas form adalah 200 MB; sesuaikan juga
`upload_max_filesize` dan `post_max_size` PHP, misalnya menjadi `256M`.

## Upload rilis

Endpoint upload API dibatasi untuk role `it` sebagai operator internal. Gunakan
akun IT khusus pada production; Branch Manager tidak dapat mengunggah APK.

```http
POST /api/v1/app-updates/android
Authorization: Bearer <token>
Content-Type: multipart/form-data

apk: SalesGo-release.apk
versionName: 1.0.1
versionCode: 2
releaseNotes: Perbaikan check-in dan katalog produk.
mandatory: false
publish: true
```

`versionCode` harus selalu naik. Satu kombinasi Android + `versionCode` hanya
boleh memiliki satu rilis. Server menghitung MD5, SHA-256, ukuran, dan path;
jangan mengirim hash dari client.

## Kontrak API Android

### Cek rilis

```http
GET /api/v1/app-updates/android/check?versionCode=1&versionName=1.0.0&md5=<md5-apk-terpasang>
Authorization: Bearer <token>
```

Contoh respons:

```json
{
  "updateAvailable": true,
  "versionName": "1.0.1",
  "versionCode": 2,
  "md5": "0123456789abcdef0123456789abcdef",
  "sha256": "...",
  "sizeBytes": 45810234,
  "mandatory": false,
  "releaseNotes": "Perbaikan check-in dan katalog produk.",
  "downloadPath": "/app-updates/android/1/download"
}
```

Backend menawarkan update bila `versionCode` rilis tidak lebih rendah dan MD5
APK terpasang berbeda. `updateAvailable: false` berarti tidak ada rilis
published atau APK terpasang identik.

### Download APK

```http
GET /api/v1/app-updates/android/{release}/download
Authorization: Bearer <token>
```

File hanya dapat diunduh user aktif yang terautentikasi. Aksi masuk audit trail
`app_release_downloaded`.

## Pengujian perangkat

1. Build lalu pasang APK rilis versi 1 (`versionCode` 1).
2. Upload APK versi 2 dengan signing key yang sama dan `publish: true`.
3. Login lalu buka **Pengaturan > Periksa update aplikasi**.
4. Setujui unduhan dan amati progres melingkar. Setelah MD5 cocok, installer
   Android dibuka.
5. Jika muncul *Install unknown apps*, izinkan SalesGo sekali lalu tekan update
   lagi.
6. Konfirmasi Update pada installer; sesi serta data lokal tetap tersedia.

Jika installer menyatakan paket konflik, periksa `applicationId`, signing key,
dan pastikan `versionCode` baru lebih besar daripada aplikasi terpasang.

## Role IT

Migration menambahkan role `it` dengan kode role `004`. Untuk membuat akun
secara aman, isi `.env` terlebih dahulu:

```dotenv
IT_ADMIN_USERNAME=it.admin
IT_ADMIN_EMAIL=it@example.test
IT_ADMIN_PASSWORD=password-kuat-minimal-12-karakter
IT_ADMIN_INCREMENT=1
```

Kemudian jalankan:

```powershell
php artisan migrate
php artisan db:seed --class=ItAdministratorSeeder
php artisan optimize:clear
```

Seeder menolak berjalan jika password tidak ada atau kurang dari 12 karakter.
Kode user dibentuk dari kode cabang + `004` + kode divisi + increment, misalnya
`0010040101`. Role IT dapat membuka seluruh menu mobile, mengelola master rute,
monitoring, laporan, dan approval; pengajuan outlet serta override check-in
oleh IT langsung aktif tanpa approval. Tindakan stok/transaksi tetap memakai
workflow komit yang ada agar stok dan audit tidak berubah tanpa jejak.
