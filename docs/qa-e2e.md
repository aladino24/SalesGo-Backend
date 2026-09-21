# QA end-to-end

Panduan ini merupakan implementasi backend dari `qa-offline-sync-gps-approval.md` di Flutter.

1. Login Sales `andi.pratama`, ambil access token.
2. Matikan internet pada aplikasi Flutter, lakukan check-in GPS/foto; pastikan queue lokal bertambah.
3. Aktifkan internet dan lakukan sync; request `POST /visits/check-in` wajib membawa `Idempotency-Key`.
4. Ulangi request dengan key dan payload sama; respons harus sama tanpa membuat visit kedua. Payload berbeda harus `409 IDEMPOTENCY_PAYLOAD_MISMATCH`.
5. Lakukan check-in di atas radius 100 m dengan alasan; endpoint membuat approval `Pending`.
6. Login Supervisor, ambil `GET /approvals`, lalu `POST /approvals/{id}/decision` dengan status `Approved` atau `Rejected`. Reject wajib memiliki komentar.
7. Panggil `GET /sync/state` dari perangkat baru dan pastikan visit server-confirmed serta approval dikembalikan hanya untuk cabang user.

Catat request ID/idempotency key, waktu UTC, status HTTP, dan user/branch pada audit log selama pengujian.
