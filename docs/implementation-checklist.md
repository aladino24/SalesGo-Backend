# Checklist Implementasi Backend SalesGo

Legenda: `[x]` source tersedia, `[~]` fondasi tersedia tetapi perlu perluasan bisnis, `[ ]` belum diimplementasikan.

## Foundation

- [x] Laravel 11 + Sanctum project manifest dan bootstrap source.
- [x] Login, refresh token, logout, dan user payload branch/role/division.
- [x] Batas sesi perangkat per user (`max_devices`, default 1), UUID instalasi aplikasi, blokir login perangkat kedua, serta pelepasan slot saat logout/kedaluwarsa. IT dapat mengatur batas 1–10 melalui API.
- [x] Scope cabang pada master, visit, approval, meeting, dan state restore.
- [x] Kontrak kode `BBBRRRDDNN`, prefix outlet `BBB-OTL-NNNN`, dan seed non-production idempotent.
- [x] Database core: cabang, role, divisi, user, produk, outlet, visit, approval, meeting, idempotency, audit.
- [x] Idempotency request write dengan replay response dan conflict payload.
- [x] Pengajuan outlet baru oleh Sales/Supervisor dengan titik GPS/peta, approval khusus Branch Manager, notifikasi pengajuan/keputusan, serta outlet hanya bisa dipilih ke master rute setelah status `Active`.

## Master, dashboard, dan state

- [x] `GET /master/products`, `/master/outlets`, `/master/snapshot`.
- [x] Performance outlet menghitung target dan achievement bulanan dari sales order committed, top product, produk belum terjual, dan produk potensial dari master produk aktif.
- [x] `GET /sync/state` untuk products, outlets, visits, approvals, dashboard ringkas.
- [x] Dashboard omzet, target, achievement, growth, chart harian, dan insentif dari sales order `Committed`/`Completed`; target serta rule insentif dikelola database.

## Visit dan approval

- [x] Daftar visit sendiri, check-in, checkout, tunda, batal.
- [x] Validasi radius dasar 100 m dan approval override.
- [x] Approval list + keputusan Supervisor/Branch Manager dengan alasan reject wajib.
- [x] Attachment finalisasi, route estimate fallback, timeline visit append-only ber-GPS, audit event lifecycle, dan geofence per outlet/cabang tersedia.

## Meeting

- [x] List, buat jadwal, join, join-by-code, dan branch scope.
- [~] Peserta, provider token/signed join URL, agenda, notifikasi, dan join audit database perlu diselesaikan.

## Transaksi dan modul lain

- [x] Sales order multi-item dan **Multi-UOM** memakai harga master, total server-side, konversi stok ke satuan dasar saat committed, receipt JSON/PDF, dan invoice otomatis untuk order kredit. Master `product_uoms` menyimpan UOM, konversi, harga, dan batas quantity; snapshot master mengirimnya untuk order offline. Promo aktif divalidasi serta diskon dihitung server-side. Purchase menambah stok saat committed; retur outlet (kondisi + attachment final), gift, dan retur supplier menunggu approval sebelum stok bergerak. Piutang memiliki saldo, overdue, dan riwayat pembayaran tervalidasi.
- [x] Pembayaran dan penagihan Tahap 1: snapshot piutang offline, pembayaran multi-faktur/komponen, alokasi server-side, kelebihan pembayaran tercatat, bukti transfer, antrean sinkronisasi/idempotensi, audit, dan verifikasi sebelum nilai faktur berubah. Penyetoran, rekonsiliasi, gateway QRIS/VA, reversal, dan permission scope generik masih perlu perluasan.
- [x] Journey memiliki transisi Planned → Active → Completed/Cancelled, timestamp aktual, dan approval luar kota. Surat jalan menormalisasi item dari master produk, mereservasi stok saat diajukan, menahan stok selama approval, mengurangi stok saat digunakan, serta melepas reservasi saat ditolak/dibatalkan.
- [x] Promosi aktif/mendatang dan detailnya tersedia dengan scope cabang. File penting disimpan pada disk lokal privat, metadata memuat ukuran/versi/waktu update, dan URL download bertanda tangan berlaku 10 menit. Revision master stabil berbasis perubahan dataset untuk invalidasi cache offline.
- [~] Notification feed/read state, pagination, producer approval/status transaksi, registrasi perangkat, push queue retry, deep link, dan pengiriman langsung FCM HTTP v1 tersedia. Kredensial service account tetap harus dipasang melalui `GOOGLE_APPLICATION_CREDENTIALS` pada environment deployment; producer visit/meeting masih perlu perluasan.
- [x] Monitoring Supervisor/Branch Manager: lokasi terakhir, visit aktif, riwayat ping ber-pagination, audit aktivitas, dan retensi terjadwal tersedia. Supervisor hanya melihat Sales; Branch Manager melihat Sales, Supervisor, dan dirinya sendiri pada cabang token. Flutter mengantrekan ping foreground tiap 3 menit dengan retry/idempotency dan menampilkan polyline berdasarkan waktu. Laporan transaksi serta ringkasan mendukung filter tanggal/sales/outlet/type/status, pencarian, pagination, export CSV, dan PDF multi-halaman. Agregasi penjualan harian, dashboard lintas cabang dengan grant eksplisit, serta arsip laporan bulanan ber-retensi tersedia.
- [~] Upload multipart ke storage lokal privat, `photoId` finalization, hash, retry job, serta audit attachment/idempotency tersedia. Perlu backup storage lokal, antivirus/MIME scanner, endpoint conflict-resolution eksplisit, dan observability worker.

## Quality dan release

- [~] Feature test skeleton perlu dijalankan setelah PHP/Composer tersedia.
- [ ] CI pipeline: Pint, PHPStan/Larastan, PHPUnit, migration test, security scan.
- [ ] OpenAPI generated spec, rate limiting, CORS, observability, backup/retention policy.
- [ ] Production deployment, HTTPS, queue worker, scheduler, secret manager, database backup.
