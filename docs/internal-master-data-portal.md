# Portal Master Data SFA

URL login portal: `http://HOST/internal/master-data/login`.

Portal memakai halaman login internal berbasis session Laravel, bukan dialog
HTTP Basic Authentication browser. Masuk menggunakan username atau email dan
password akun SFA aktif dengan role `operational` atau `it`.

## Hak akses

| Role | Akses |
|---|---|
| Operasional | Hanya cabang akun sendiri: divisi, outlet, produk, promosi, rute, serta pembuatan pengguna Sales/Marketing/Operasional. |
| IT | Seluruh cabang, cabang baru, seluruh jenis user termasuk IT/Branch Manager, dan seluruh master pada portal. |
| Branch Manager | Tidak memakai portal ini; approval dan master rute tetap melalui aplikasi sesuai scope cabangnya. |

Operasional tidak dapat mengganti parameter `branch_id` untuk membuka data
cabang lain atau membuat akun Branch Manager/IT. Semua pembuatan dan perubahan
status dicatat dalam tabel `audit_logs`.

## Master yang tersedia

- Pengguna: kode karyawan dibentuk otomatis dari kode cabang + role + divisi + increment.
- Cabang (IT saja) dan divisi sales.
- Outlet: kode outlet dibentuk otomatis dengan prefiks kode cabang.
- Produk: SKU, divisi, merek/varian/UOM, harga, stok, kategori, barcode.
- Promosi: periode, diskon, minimum order, kuota, budget, dan status.
- Rute: outlet, sales, hari, dan minggu dalam bulan.

## Setelah perubahan master

1. Sales memilih **Pengaturan → Unduh Data Terbaru** pada aplikasi.
2. Data lama di perangkat diganti berdasarkan ID/kode, sehingga tidak menumpuk.
3. Produk, outlet, promosi, serta rute baru dapat dipakai offline setelah
   unduhan berhasil.

## Pemeriksaan lokal

```powershell
php artisan optimize:clear
php artisan serve
```

Lalu buka `http://127.0.0.1:8000/internal/master-data/login` dan masuk dengan
akun ber-role `operational` atau `it`.
