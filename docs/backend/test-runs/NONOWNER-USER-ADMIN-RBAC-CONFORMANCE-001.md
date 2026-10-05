# NONOWNER-USER-ADMIN-RBAC-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status: PASS
- Test: `tests/Feature/UserApiTest.php`
- Lingkungan: Docker Compose project `larissama-backend-test`, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test` disposable)
- Pint: PASS
- Focused `UserApiTest`: 7 test / 1143 assertions — PASS
- Suite penuh: 321 test / 37085 assertions, 32.80 detik — PASS
- Cleanup: container MySQL test dan network Compose test dibersihkan

## Cakupan

Untuk tiap role `manager`, `kasir`, dan `superadmin`, test memverifikasi HTTP 403 sesuai response schema OpenAPI pada:

- `GET /api/v1/users`
- `POST /api/v1/users`
- `GET /api/v1/users/{id}`
- `PATCH /api/v1/users/{id}`

Payload PATCH yang ditolak valid menurut request schema OpenAPI. Setelah semua penolakan, jumlah user tetap, username create yang dicoba tidak ada, dan user sasaran tidak berubah.

## Batas bukti

Implementasi policy dan FormRequest yang ada sudah menegakkan penolakan, sehingga tidak ada perubahan runtime atau schema. Run ini menutup empat operasi administrasi user untuk tiga role non-owner saja. Hak owner di operasi lain, variasi status/payload, matriks role D04 penuh, G1, dan readiness operasi tetap terbuka; semua operasi OpenAPI tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact tests/Feature/UserApiTest.php
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact
docker compose -f compose.test.yaml --project-name larissama-backend-test down --volumes --remove-orphans
```
