# Referensi API SalesGo v1

Dokumen ini menjelaskan endpoint yang **saat ini terdaftar** pada Laravel API.
Base URL: `https://<host>/api/v1` atau `http://127.0.0.1:8000/api/v1` saat lokal.
Kontrak lintas aplikasi Flutter yang lebih naratif tersedia di
[`../../SalesGo/docs/api-contract.md`](../../SalesGo/docs/api-contract.md).

## Konvensi

- Request/response menggunakan JSON kecuali upload multipart dan file download.
- Semua endpoint selain login/refresh memakai header:

  ```http
  Authorization: Bearer <accessToken>
  Accept: application/json
  ```

- Seluruh endpoint tulis harus mengirim `Idempotency-Key` UUID.

  ```http
  Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000
  ```

- Waktu memakai ISO-8601; nominal dan jumlah berupa number, bukan string berformat.
- Scope cabang dan role selalu ditentukan token Sanctum, bukan field cabang dari client.
- Kesalahan validasi umumnya `422`; tidak login `401`; tidak berhak `403`; konflik bisnis/idempotensi `409`.

Contoh error:

```json
{
  "message": "The given data was invalid.",
  "errors": { "field": ["Pesan validasi"] }
}
```

## Autentikasi

| Method | Path | Auth | Body/parameter |
|---|---|---|---|
| POST | `/auth/login` | Tidak | `username`, `password`, `deviceId` UUID instalasi, `platform` |
| POST | `/auth/refresh` | Tidak | `refreshToken` |
| POST | `/auth/logout` | Ya | Opsional `refreshToken` |

```json
POST /auth/login
{ "username": "andi.pratama", "password": "password", "deviceId": "550e8400-e29b-41d4-a716-446655440000", "platform": "android" }
```

```json
{
  "accessToken": "...",
  "refreshToken": "...",
  "expiresAt": "2026-08-22T12:00:00Z",
  "user": {
    "id": "1",
    "name": "Andi Pratama",
    "employeeCode": "0010010101",
    "branchId": "1",
    "branchCode": "001",
    "role": "sales"
  }
}
```

Setiap user memiliki `max_devices`, default **1**. Login dari perangkat lain
akan mendapat `409 DEVICE_LIMIT_REACHED` selama sesi perangkat sebelumnya
masih aktif. Logout melepaskan slot perangkat; sesi yang berakhir pada tengah
malam juga tidak lagi dihitung aktif. `deviceId` adalah UUID acak per instalasi
aplikasi, bukan IMEI atau identitas fisik perangkat.

| Method | Path | Hak akses | Keterangan |
|---|---|---|---|
| GET | `/users/{user}/device-sessions` | Pemilik sesi atau IT | Daftar sesi perangkat tanpa mengekspos device ID/token. |
| PUT | `/users/{user}/max-devices` | IT | Body `{ "maxDevices": 1 }`; batas 1–10 perangkat. |

## Master, dashboard, dan state

| Method | Path | Query/body | Keterangan |
|---|---|---|---|
| GET | `/master/products` | — | Produk branch aktif, harga dan stok. |
| GET | `/master/outlets` | — | Outlet branch aktif dan koordinat/geofence. |
| GET | `/master/promotions` | — | Alias master promosi. |
| GET | `/master/outlets/{outlet}/performance` | — | Target, achievement, top/unsold/potential product. |
| GET | `/master/snapshot` | — | Snapshot atomik untuk refresh master offline. |
| GET | `/promotions` | — | Daftar promosi. |
| GET | `/promotions/{promotion}` | — | Detail promosi. |
| GET | `/dashboard` | — | Omzet, target, visit, insentif, chart. |
| GET | `/dashboard/branches` | `from`, `to`, `branchIds[]` | Dashboard lintas cabang; role berwenang. |
| GET | `/sync/state` | — | Snapshot server-confirmed untuk restore state perangkat. |

### Katalog produk dan order

`GET /master/products` mengembalikan `divisionCode`, `divisionName`, `sku`,
`barcode`, `brand`, `variant`, `size`, `uom`, `unitsPerCase`, `category`,
`price`, `stock`, `imageUrl`, serta `uoms`. Setiap record `uoms` memuat `id`,
`code`, `name`, `conversionToBase`, `price`, `minimumQuantity`,
`maximumQuantity`, dan `isDefault`. Stok selalu disimpan dalam **satuan dasar**;
misalnya `1 DUS` dengan `conversionToBase: 12` membutuhkan dan mengurangi 12
stok dasar. Untuk role Sales, daftar produk dibatasi ke
divisi sales yang melekat pada token pengguna. Branch Manager dan Supervisor
melihat katalog aktif pada cabangnya sesuai hak akses.

Endpoint gambar privat tersedia di `GET /master/products/{product}/image`.
Gambar disimpan lokal pada `storage/app/private/products`; kolom
`products.image_path` menyimpan path relatif, misalnya
`products/mie-sedaap.jpg`. Aplikasi dapat menyimpan metadata katalog ke cache
offline, tetapi pengambilan gambar tetap memakai bearer token saat online.

Validasi divisi diterapkan kembali saat sales order dikirim. Karena itu Sales
tidak dapat memesan produk divisi lain hanya dengan memodifikasi payload lokal.
Produk tanpa divisi sales tidak valid, tidak dikirim oleh API, dan dibersihkan
oleh migrasi katalog. Saat **Download Data Terbaru**, Flutter memvalidasi lalu
menimpa cache `master_products` secara atomik; katalog yang sudah diunduh tetap
ditampilkan saat perangkat offline tanpa data duplikat.

Snapshot juga memuat seluruh pilihan UOM produk sehingga katalog dan pemilihan
satuan tetap bekerja ketika offline. Snapshot memuat `datasets.orderPolicy`: `minimumUnits`, `maximumUnits`,
`minimumAmount`, dan `maximumAmount`. Mobile menyimpannya bersama master
offline, tetapi Laravel tetap menolak order yang melanggar batas tersebut.

Produk memiliki satu divisi, sedangkan Sales dan Outlet dapat memiliki lebih
dari satu divisi melalui relasi master. `GET /master/outlets` menyertakan
`divisions` serta `salesSchedules` (sales, hari, minggu) agar detail outlet
dapat menjelaskan siapa yang berkunjung. Sales hanya menerima dan dapat
menjual produk dari seluruh divisi yang ditugaskan kepadanya.

## Pengajuan outlet baru

| Method | Path | Body | Keterangan |
|---|---|---|---|
| POST | `/outlets` | `name`, `address`, `type`, `latitude`, `longitude`; opsional `ownerName`, `contactName`, `phone` | Khusus Sales/Supervisor. Membuat outlet `Pending Approval`, mengirim notifikasi ke Branch Manager, dan menghasilkan approval `new_outlet`. Setelah disetujui, outlet menjadi `Active` dan dapat dipilih pada Master Rute. |

Keputusan memakai `POST /approvals/{approval}/decision` dengan `status` `Approved` atau `Rejected`; penolakan wajib menyertakan `comment`. Approval outlet hanya dapat diputuskan oleh Branch Manager. Pemohon menerima notifikasi keputusan.

## File dan attachment

| Method | Path | Body | Keterangan |
|---|---|---|---|
| POST | `/attachments` | `multipart/form-data`: `file`, opsional `clientUuid` | JPEG/PNG/PDF, maksimal 10 MB; proses finalisasi attachment. |
| GET | `/attachments/{attachment}` | — | Metadata attachment milik branch/user. |
| GET | `/files` | — | Metadata file penting. |
| POST | `/files` | multipart: `file`, opsional `name`, `type`, `description`, `replaceFileId` | Upload file penting, maksimal 50 MB, role berwenang. |
| POST | `/files/{importantFile}/download` | — | Membuat signed download URL. |
| GET | `/files/{importantFile}/download/signed/{user}` | Signed URL | Download file privat; URL diberikan endpoint sebelumnya. |

Upload attachment:

```text
POST /attachments
Content-Type: multipart/form-data
file=<binary>
clientUuid=<uuid-opsional>
```

Response attachment memuat minimal `id`, `contentType`, `size`, `status`, dan
`createdAt`. Gunakan `id` yang berstatus `Finalized` sebagai `photoId` check-in
atau retur.

## Visit, peta, dan timeline

| Method | Path | Body/query |
|---|---|---|
| GET | `/visits` | — |
| POST | `/routes/estimate` | `origin.latitude`, `origin.longitude`, `destination.latitude`, `destination.longitude` |
| POST | `/visits/check-in` | lihat contoh di bawah |
| POST | `/visits/check-out` | `visitId`, opsional `notes` |
| POST | `/visits/defer` | `visitId`, `reason` |
| POST | `/visits/cancel` | `visitId`, `reason` |
| GET | `/outlets/{outlet}/visit-activities` | — |

```json
POST /visits/check-in
{
  "visitId": "client-visit-uuid",
  "outletId": 1,
  "photoId": 12,
  "location": { "latitude": -7.2575, "longitude": 112.7521 },
  "distanceMeters": 18,
  "notes": "Display sudah dicek",
  "outOfRadiusOverride": { "reason": "Koordinat outlet belum akurat" }
}
```

Check-in di luar geofence menghasilkan status `Pending` dan approval. Check-out
menyelesaikan visit aktif milik user.

## Transaksi, invoice, dan riwayat outlet

### Ship-to order

| Method | Path | Keterangan |
|---|---|---|
| GET | `/master/ship-to-locations` | Daftar alamat/lokasi tujuan pengiriman aktif pada cabang untuk cache offline. |
| POST | `/outlets/{outlet}/ship-to-locations` | Pengajuan lokasi pengiriman baru; Sales/SPV menunggu approval BM, BM/IT langsung aktif. |

Saat membuat `POST /sales-orders`, kirim pilihan tujuan sebagai berikut. Bila
`shipTo.id` kosong, server menggunakan alamat utama outlet sebagai tujuan dan
menyimpan salinan alamat tersebut di metadata order untuk kebutuhan riwayat.

```json
{
  "shipTo": { "id": 12 }
}
```

## Pembayaran dan penagihan

| Method | Path | Keterangan |
|---|---|---|
| GET | `/outlets/{outlet}/payment-summary` | Ringkasan piutang/kredit dan faktur outlet. |
| GET | `/payments/receivables-snapshot` | Snapshot piutang seluruh outlet cabang untuk cache offline perangkat. |
| GET | `/payments` | Riwayat pembayaran sesuai scope user. |
| POST | `/payments` | Pembayaran multi-faktur/multi-metode; wajib idempotency key. |
| POST | `/payments/{payment}/verify` | Verifikasi atau tolak pembayaran; Finance/BM/IT. |
| POST | `/collection-activities` | Janji bayar, gagal tagih, atau sengketa faktur. |

Contoh pembayaran tunai untuk beberapa faktur:

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "outletId": 1,
  "amount": 8000000,
  "components": [
    { "method": "Cash", "amount": 8000000 }
  ],
  "allocations": [
    { "invoiceId": 10, "amount": 3000000 },
    { "invoiceId": 11, "amount": 5000000 }
  ],
  "confirmed": true
}
```

Metode pada kontrak: `Cash`, `Transfer`, `VirtualAccount`, `Qris`, `Giro`,
`Cheque`, `Deposit`, dan `CreditNote`. Rilis Tahap 1 mengaktifkan manual
`Cash` dan `Transfer`; `Transfer` wajib attachment final. QRIS/VA ditolak
sampai gateway/callback tersedia. Nilai faktur tidak berubah pada submit;
server memperbarui `paidAmount` hanya pada keputusan `VERIFIED`.

Semua create transaction memakai body dasar berikut. Harga, diskon, total, dan
stok final dihitung/ditentukan server.

```json
{
  "id": "client-transaction-uuid",
  "outletId": 1,
  "items": [{ "productId": 1, "productUomId": 4, "quantity": 2 }],
  "discount": 0,
  "notes": "Catatan opsional",
  "paymentType": "cash",
  "createdAt": "2026-08-22T10:00:00Z"
}
```

`productUomId` bersifat opsional hanya untuk kompatibilitas order lama; bila
tidak dikirim, server memakai UOM default produk. Untuk order baru, mobile
wajib mengirim UOM yang dipilih. Harga, konversi ke satuan dasar, subtotal,
batas quantity UOM, dan stok dihitung kembali oleh server; nilai dari client
tidak dipercaya.

| Method | Path | Catatan tambahan |
|---|---|---|
| POST | `/sales-orders` | `items` wajib; dapat memakai `paymentType: credit`. |
| PATCH | `/sales-orders/{transaction}/status` | `status`: `Committed`, `Completed`, atau `Cancelled`; opsional `reason`. |
| GET | `/sales-orders/{transaction}/receipt` | Receipt JSON. |
| GET | `/sales-orders/{transaction}/receipt.pdf` | Receipt PDF. |
| POST | `/purchases` | Purchase menambah stok saat committed. |
| POST | `/returns` | Tambahkan `returnReason`, `itemCondition`, `photoId` bila diwajibkan. |
| POST | `/supplier-returns` | Retur ke supplier. |
| POST | `/gifts` | Gift menunggu approval sebelum stok bergerak. |
| POST | `/outlet-notes` | Catatan aktivitas outlet. |
| GET | `/outlets/{outlet}/transactions` | Riwayat transaksi outlet. |
| GET | `/outlets/{outlet}/receivables` | Invoice/piutang outlet. |
| POST | `/receivables/{invoice}/payments` | `amount`, opsional `referenceNumber`, `notes`, `paidAt`. |

## Journey dan surat jalan

| Method | Path | Body/query |
|---|---|---|
| GET | `/journeys` | — |
| POST | `/journeys` | `type` (`in_city`/`out_of_town`), `destination`, `startsAt`, opsional `endsAt`, `reason`, `id`. |
| PATCH | `/journeys/{journey}/status` | `status` (`Active`, `Completed`, `Cancelled`), opsional `reason`. |
| GET | `/delivery-notes` | — |
| POST | `/delivery-notes` | `number`, `destination`, `items[]`, opsional `journeyId`, `outletId`, `date`. |
| POST | `/delivery-notes/{deliveryNote}/submit` | Ajukan dan reserve stok. |
| POST | `/delivery-notes/{deliveryNote}/use` | Gunakan setelah approval; stok dikurangi. |
| POST | `/delivery-notes/{deliveryNote}/cancel` | Batalkan dan lepas reserve stok. |

Contoh surat jalan:

```json
{
  "number": "SJ-2026-001",
  "destination": "Toko Maju Jaya",
  "outletId": 1,
  "items": [{ "productId": 1, "quantity": 10, "unit": "karton" }]
}
```

## Approval

| Method | Path | Body/query |
|---|---|---|
| GET | `/approvals` | Opsional `status`, default sesuai controller. |
| POST | `/approvals/{approval}/decision` | `status` (`Approved`/`Rejected`), opsional `comment`; reject mengikuti policy alasan. |

Keputusan approval menjalankan workflow domain terkait: check-in luar radius,
gift/return, surat jalan, journey luar kota, atau transaksi yang menunggu approval.
Untuk `visit_out_of_radius`, respons `GET /approvals` juga membawa `visit`
berisi outlet, jarak, koordinat, `photoAttachmentId`, dan `photoUrl`. Foto privat
dibuka melalui `GET /attachments/{attachment}/content` dengan Bearer token.
Keputusan `Approved` mengubah visit menjadi `In Progress` dan mengirim deep link
`/visit?visitId=...&outletId=...`; keputusan `Rejected` mengembalikan visit ke
`Planned` sehingga sales dapat mengajukan override baru.

## Monitoring dan laporan

Endpoint monitoring dibatasi Supervisor/Branch Manager dan seluruh hasil di-scope
ke cabang pengguna pada token. Supervisor hanya dapat melihat Sales. Branch
Manager dapat melihat Sales, Supervisor, dan lokasi dirinya sendiri.

| Method | Path | Query/body |
|---|---|---|
| POST | `/monitoring/locations` | Header `Idempotency-Key`; body: `location.latitude`, `location.longitude`, opsional `location.accuracyMeters`, `recordedAt`, `source` (`foreground`, `background`, `check_in`, `check_out`). Identitas user/cabang diambil dari token. |
| GET | `/monitoring/team` | — |
| GET | `/monitoring/activities` | `salesId` opsional (harus anggota tim yang diizinkan), `search`, `page`, `perPage`. Mengembalikan audit aktivitas dengan nama, kode, dan role pengguna. |
| GET | `/monitoring/team/{sales}/locations` | `page`, `perPage`, `from`, `to` bila tersedia. |
| GET | `/reports/transactions` | `from`, `to`, `salesId`, `outletId`, `type`, `status`, `search`, `page`, `perPage`. |
| GET | `/reports/summary` | `from`, `to`, `salesId`, `outletId`. |
| GET | `/reports/archives` | `branchId`, `year`, `page`, `perPage`. |

Lokasi pada `GET /monitoring/team/{sales}/locations` diurutkan aplikasi secara
kronologis memakai `recordedAt`, lalu dihubungkan menjadi polyline. Mobile
mengantrekan setiap ping GPS terlebih dahulu dengan UUID yang sama sebagai
`Idempotency-Key`; ketika offline data tersimpan lokal dan dikirim ulang oleh
sync queue saat koneksi kembali tersedia.
| GET | `/reports/transactions/export.csv` | Filter sama transaksi; response CSV. |
| GET | `/reports/transactions/export.pdf` | Filter sama transaksi; response PDF. |

## Meeting online

| Method | Path | Body/query |
|---|---|---|
| GET | `/meetings` | Opsional `status`. |
| POST | `/meetings` | `title`, opsional `description`, `startsAt`, `endsAt`, opsional `participantIds[]`. |
| POST | `/meetings/{meeting}/join` | —; response `joinUrl`. |
| POST | `/meetings/join-by-code` | `meetingId`; response `joinUrl`. |

## Notifikasi dan device FCM

| Method | Path | Body/query |
|---|---|---|
| GET | `/notifications` | `unreadOnly`, `limit`, dan pagination Laravel `page`. |
| POST | `/notifications/{notification}/read` | — |
| POST | `/notifications/devices` | `token`, `platform` (`android`, `ios`, `web`). |
| DELETE | `/notifications/devices/{device}` | — |

```json
POST /notifications/devices
{ "token": "fcm-registration-token", "platform": "android" }
```

Feed menggunakan Laravel paginator dengan records di `data`, serta metadata
pagination seperti `current_page`, `last_page`, dan `per_page`. Saat notifikasi
dibuka, client menandai read lalu hanya menerima `deepLink` internal yang telah
di-whitelist.

## Pagination, idempotensi, dan sinkronisasi

- List notifikasi memakai paginator Laravel `?page=1&limit=20`.
- Laporan menggunakan `page`/`perPage`; batas maksimum mengikuti validasi endpoint.
- Semua transaksi offline wajib membawa `Idempotency-Key`; request dengan key
  sama mengembalikan hasil pertama, bukan membuat record baru.
- Retry hanya untuk kegagalan jaringan/5xx. Konflik bisnis `409` tidak boleh
  diproses ulang otomatis.
- Gunakan `/sync/state` untuk pemulihan state server-confirmed; gunakan
  `/master/snapshot` untuk refresh cache master. Keduanya berbeda tujuan.

## Update APK internal Android

| Method | Path | Otorisasi | Keterangan |
|---|---|---|---|
| GET | `/app-updates/android/check` | User login | Query `versionCode`, `versionName`, `md5`; mengembalikan metadata rilis dan `updateAvailable`. |
| GET | `/app-updates/android/{release}/download` | User login | Mengunduh APK private. |
| POST | `/app-updates/android` | IT | Multipart: `apk`, `versionName`, `versionCode`, `releaseNotes`, `mandatory`, `publish`. |

`downloadPath` dari respons check bersifat relatif terhadap API `/api/v1` dan
harus diunduh menggunakan bearer token. Detail operasional tersedia pada
[internal-android-app-update.md](internal-android-app-update.md).

## Contoh cURL

```bash
curl -X GET 'http://127.0.0.1:8000/api/v1/notifications?unreadOnly=1&limit=20' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <accessToken>'
```

Jika route, validasi, atau response diubah, perbarui dokumen ini dan kontrak
Flutter pada pull request yang sama. Breaking change harus memakai versi API baru.
