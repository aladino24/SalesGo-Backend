# Master Rute dan Lifecycle Kunjungan

Branch Manager (Kacab) menentukan penugasan rute sales. Satu record master
memetakan satu outlet ke satu sales pada kombinasi hari ISO (`dayOfWeek` 1--7,
Senin--Minggu) dan minggu dalam bulan (`weekOfMonth` 1--4).

## Endpoint Kacab

- `GET /api/v1/master/route-assignments?salesId={id}`
- `POST /api/v1/master/route-assignments`
- `DELETE /api/v1/master/route-assignments/{id}`

Payload create:

```json
{"outletId": 12, "salesId": 5, "dayOfWeek": 2, "weekOfMonth": 3, "isActive": true}
```

## Perjalanan dan daftar kunjungan

1. Sales membuat journey dengan `startsAt` dan `endsAt`.
2. Sales memulai journey: `POST /api/v1/journeys/{serverId}/start`.
3. Server membuat rencana `visits` untuk setiap tanggal:
   - `isRequired=true`: outlet yang day/week-nya sesuai tanggal.
   - `isRequired=false`: seluruh outlet master route sales lainnya yang tidak
     termasuk daftar wajib tanggal tersebut.
4. `GET /api/v1/visits` tanpa parameter mengembalikan visit untuk journey aktif
   sales pada tanggal hari ini. Respons berisi `isRequired`, `plannedFor`,
   detail outlet, dan status.
5. `GET /api/v1/journeys/current?date=YYYY-MM-DD` adalah endpoint pemulihan
   state. Ia mengembalikan Journey aktif serta daftar outlet untuk tanggal yang
   berada di dalam periodenya; jika tanggal di luar periode, `journey` bernilai
   `null` dan daftar kunjungan kosong.
6. Check-in memakai `visitId` rencana tersebut; server mengubah record Planned
   menjadi In Progress. Check-out, tunda, atau batal menyelesaikan lifecycle.

Client harus memakai `serverId` pada payload journey saat memanggil endpoint
`start`; `id` dapat berupa client id untuk idempotensi offline.

## Reset periode kunjungan

Jika sales salah memilih rentang tanggal saat membuat perjalanan, gunakan:

```http
POST /api/v1/journeys/{serverId}/reset-visits
Authorization: Bearer <token>
Idempotency-Key: <uuid>

{
  "startsAt": "2026-09-02T00:00:00.000",
  "endsAt": "2026-09-04T00:00:00.000"
}
```

Reset harus online dan hanya dapat dilakukan pemilik journey. Server tidak
menghapus data: journey lama ditandai `Cancelled`, visit lama berstatus
`Planned`/`Pending` ditandai `Cancelled`, dan history check-in/check-out tetap
utuh. Server lalu membuat journey pengganti berstatus `Planned`; sales perlu
menekan **Mulai Perjalanan** untuk mengunduh rute periode baru.

Reset ditolak jika ada visit `In Progress`. Selesaikan, tunda, atau batalkan
kunjungan aktif terlebih dahulu.
