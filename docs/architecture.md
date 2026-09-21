# Arsitektur dan keamanan

## Layer

- `routes/api.php`: kontrak HTTP `/api/v1`.
- Controller: validasi request, response camelCase, dan authorization scope.
- Model: persistence Eloquent.
- `IdempotencyService`: replay response aman untuk request tulis offline.
- Seeder: dummy data idempotent khusus non-production.

## Scope cabang

Setiap request terautentikasi memperoleh cabang dari `user.branch_id`. Parameter `branchId` dari client tidak digunakan untuk memperluas akses. Master response menyertakan `branchId` dan `branchCode`, tetapi backend tetap melakukan query scoped branch.

## Kode bisnis

- User/sales: `BBBRRRDDNN` (10 digit).
- Outlet: `BBB-OTL-NNNN`.
- Product SKU branch: `BBB-PRD-NNNN` bila SKU tidak bersifat pusat.
- UUID/idempotency key dari client tetap dipakai untuk keamanan sync; kode bisnis bukan primary key transaksi.

## Operasional

Gunakan HTTPS, simpan secret pada environment/secret manager, aktifkan queue worker untuk sync/attachment/notifikasi, dan audit semua approval, join meeting, attachment, serta replay idempotency. Jangan menyimpan URL provider yang sensitif atau attachment binary dalam audit log.

## Monitoring dan laporan

Sales mengirim lokasi secara sadar melalui `POST /api/v1/monitoring/locations` dengan `Idempotency-Key`; backend mencatat ping terikat cabang dan audit tanpa menyimpan koordinat pada audit payload. Hanya Supervisor dan Branch Manager pada cabang yang sama dapat melihat `GET /monitoring/team` dan riwayat lokasi per sales. Tidak ada background location yang dipaksakan oleh backend: aplikasi mobile harus meminta persetujuan pengguna dan mengirim `source=background` hanya bila kebijakan perusahaan telah disetujui.

`GET /reports/transactions` dan `GET /reports/summary` juga dibatasi untuk Supervisor/Branch Manager. Keduanya menerima filter `from`, `to`, `salesId`, dan `outletId`; transaksi menambah `type`, `status`, `search`, `page`, serta `perPage`.

Ping lokasi dipangkas oleh scheduler sesuai `SALES_LOCATION_RETENTION_DAYS` (default 90 hari). Scheduler juga menjalankan agregasi penjualan committed harian. Export tersedia melalui `GET /reports/transactions/export.csv` dan `export.pdf`; PDF memecah detail menjadi beberapa halaman secara otomatis.

Dashboard lintas cabang (`GET /dashboard/branches`) hanya untuk Branch Manager. Akses cabang tambahan harus dicatat pada tabel `user_branch_access`; tanpa grant, seorang manager hanya melihat cabang asalnya.

Scheduler mengarsipkan ringkasan transaksi bulan sebelumnya ke `report_archives` dan memangkasnya sesuai `REPORT_ARCHIVE_RETENTION_DAYS` (default 2.555 hari/±7 tahun). Arsip dapat dibaca melalui `GET /reports/archives` dengan scope cabang yang sama seperti laporan aktif.

## Attachment production

`POST /attachments` menerima multipart dengan `Idempotency-Key` dan `clientUuid`, menyimpan file pada disk lokal privat `storage/app/private`, lalu menjalankan job `FinalizeAttachment`. Hanya attachment berstatus `Finalized` dan dimiliki user/cabang yang sama yang boleh direferensikan sebagai `photoId` oleh check-in. Jalankan `php artisan queue:work --tries=5` pada worker terpisah.

## Journey dan surat jalan

Journey luar kota tetap berstatus `Planned` sampai approval disetujui dan hanya dapat berjalan melalui transisi `Planned → Active → Completed/Cancelled`. Surat jalan memvalidasi item terhadap master produk cabang. Saat diajukan, kuantitas disimpan pada `products.reserved_stock`; stok fisik baru berkurang ketika surat jalan dipakai. Approval yang ditolak atau pembatalan pengaju melepas reservasi kembali. Seluruh operasi berjalan dalam transaksi idempotent agar retry offline tidak menggandakan reservasi atau pengurangan stok.

## Promosi, file penting, dan cache

`GET /promotions` mengembalikan promosi aktif maupun mendatang dalam scope cabang; `GET /promotions/{id}` memberikan syarat transaksi lebih lengkap. File penting berada pada disk lokal privat dan hanya Branch Manager yang dapat menambah atau menggantinya. `POST /files/{id}/download` memverifikasi token/cabang lalu menerbitkan signed URL selama 10 menit; endpoint signed tidak memakai token karena URL itu sendiri adalah bearer capability berumur pendek.

Revision `master-<hash>` dihitung dari jumlah serta `updated_at` produk, outlet, promosi, dan file penting pada cabang. Karena revision tidak menggunakan waktu request, client hanya mengunduh ulang metadata/cache bila dataset benar-benar berubah.

## Notifikasi dan push

Perangkat mendaftar melalui `POST /notifications/devices`; token disimpan per user/cabang dan dapat dinonaktifkan lewat endpoint `DELETE`. Token FCM mentah disimpan tanpa indeks, sedangkan hash SHA-256 uniknya dipakai untuk deduplikasi agar panjang token tidak melampaui batas indeks MySQL. Producer notifikasi mencakup pengajuan/keputusan approval serta perubahan status sales order. Setiap notifikasi tetap tersimpan di feed terlebih dahulu, lalu job queue mengirimkannya langsung ke FCM HTTP v1 dengan retry. Access token OAuth pendek diperoleh melalui Application Default Credentials dari service account yang dirujuk oleh `GOOGLE_APPLICATION_CREDENTIALS`; key tidak pernah disimpan dalam repository. Payload memuat `deepLink` internal seperti `/approval?approvalId=...`. Keputusan override check-in menggunakan `/visit?visitId=...&outletId=...` agar aplikasi memuat ulang state visit lalu membuka detail outlet aktif saat notifikasi dibuka.
