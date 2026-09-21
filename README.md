# SalesGo API

Backend Laravel API v1 untuk aplikasi Flutter SalesGo. Implementasi ini mengikuti `docs/api-contract.md`, `docs/backend-implementation-guide.md`, `docs/qa-offline-sync-gps-approval.md`, serta requirements SFA dari proyek Flutter.

## Prasyarat

- PHP 8.2 atau lebih baru
- Composer 2
- MySQL 8 / MariaDB 10.6 atau PostgreSQL 15

## Menjalankan proyek

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

`migrate --seed` aman untuk local/development/staging. Seeder akan menolak berjalan di environment production.

## Dokumen

- [Panduan instalasi](docs/installation.md)
- [Referensi API endpoint v1](docs/api-reference.md)
- [Arsitektur dan keamanan](docs/architecture.md)
- [Checklist implementasi](docs/implementation-checklist.md)
- [Skenario QA end-to-end](docs/qa-e2e.md)
- [API contract sumber](../SalesGo/docs/api-contract.md)

Base API: `http://localhost:8000/api/v1`.
