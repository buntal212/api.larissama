# UNKNOWN-BODY-FIELDS-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit kode/test yang diuji: `9c20ea7` (`test: cover unknown fields across request bodies`)
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test`
- Operasi yang diperiksa: POST/PATCH admin warung, POST/PATCH user tenant, POST/PATCH kategori, POST/PATCH menu, POST sale, dan POST purchase.
- `ApiRequestUnknownFieldsConformanceTest`: PASS, 2 test / 502 assertions. Setiap request menambahkan `unexpected_field`; request schema OpenAPI menolaknya dan runtime mengembalikan 422 `VALIDATION_ERROR` dengan field yang sama.
- No-write: warung/user/kategori/menu tetap pada nilai awal; tidak ada user/master baru, header, atau rincian sale/purchase.
- `vendor/bin/pint --dirty --format agent`: PASS
- `php artisan test --no-progress`: PASS, 465 test / 65.595 assertions dalam 38.26 detik
- Project Compose `larissama-unknown-body-conformance` dihentikan; volume/network disposable dihapus dan `ps -a` kosong.

Tidak ada perubahan runtime, schema database, dependency, atau bentuk OpenAPI. Cakupan ini membuktikan field tambahan untuk sepuluh operasi dengan request body selain login; login punya bukti tersendiri di [LOGIN-UNKNOWN-FIELD-CONFORMANCE-001](LOGIN-UNKNOWN-FIELD-CONFORMANCE-001.md). Status operasi tetap `DRAFT` sampai seluruh conformance dan keputusan terkait selesai.
