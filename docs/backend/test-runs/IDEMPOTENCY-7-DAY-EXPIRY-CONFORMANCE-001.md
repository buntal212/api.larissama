# IDEMPOTENCY-7-DAY-EXPIRY-CONFORMANCE-001

- Tanggal: 2026-10-05
- Task/test: BE-304/BE-404, T-RET-05, D09
- Target: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 pada `compose.test.yaml`
- Base commit sebelum perubahan: `95342295ed461a64ee2d2761f40a072b38052e05`
- `tests/Feature/IdempotencyExpiryTest.php` SHA-256: `e4dd84782dea90af77c69734de333f6aa8c437ddcb9350b721c2e019e587deed`
- Migration SHA-256: `e320078f3a5366b869e2f4ed807d4e0cd9161e856a241b996512716858c4d8b1`
- `app/Support/IdempotencyKeyWindow.php` SHA-256: `d24d9b0d547fd6703d945d4dd2853485cd8decc2c24b066279241968dab17224`

## Hasil

**PASS.** Window idempotency berlaku tujuh hari dari pencatatan transaksi atau event audit. Payload identik me-replay hasil awal sebelum expiry; payload berbeda dengan key aktif yang sama menghasilkan 409. Request pada atau sesudah expiry dapat memakai key yang sama sebagai request baru. Saat itu metadata retry yang lama dilepas secara lazy. Tidak ada background cleanup. Header transaksi, detail, status, dan event audit lama tetap tersimpan.

- Sale: replay tepat sebelum batas tujuh hari, payload conflict sebelum expiry, key reuse tepat pada expiry, metadata key lama dibersihkan, transaksi lama dipertahankan, dan transaksi kedua tersimpan.
- Purchase: key sama dipakai ulang tepat pada expiry; metadata lama dibersihkan dan dua header transaksi tetap tersimpan.
- Koreksi purchase: event pertama di-replay sebelum expiry; key yang sama membuat event koreksi kedua tepat pada expiry. Snapshot dan alasan event pertama tetap utuh setelah metadata retry-nya dibersihkan.
- Fresh schema MySQL: sembilan tabel bisnis sesuai metadata, termasuk `idempotency_expires_at DATETIME(6)` serta key/hash nullable pada sale, purchase, dan koreksi.

## Verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| Focused `IdempotencyExpiryTest` + `BusinessSchemaMigrationConformanceTest` | PASS: 8 test / 274 assertions, 2.09 detik |
| Suite penuh `php artisan test --display-warnings` | PASS: 495 test / 72.426 assertions, 37.74 detik |
| Laravel Pint (`--dirty`) | PASS |
| OpenAPI 3.1 validator 0.9.0 | PASS: `docs/api/openapi.yaml: OK` |
| Compose test stack cleanup | PASS; dijalankan setelah verifikasi |

Commands:

```sh
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/IdempotencyExpiryTest.php tests/Feature/BusinessSchemaMigrationConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.openapi.yaml run --build --rm openapi-validator
docker compose -f compose.test.yaml down -v
```

## Batas

Expiry dicek ketika key yang sama dikirim ulang. Tidak ada scheduler yang menghapus metadata untuk key yang tidak dipakai lagi; fakta transaksi/audit maupun metadata yang belum direferensikan tetap berada pada row. Ini membatasi masa replay/conflict menjadi tujuh hari tanpa menghapus histori. Seluruh operationId tetap `DRAFT` karena gate request/response, role, serta acceptance API lain masih berjalan.
