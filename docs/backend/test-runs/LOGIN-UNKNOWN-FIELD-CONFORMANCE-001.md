# LOGIN-UNKNOWN-FIELD-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit implementasi/test yang diuji: `6409643` (`fix: reject unknown login request fields`)
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test`
- Probe merah sebelum fix: login dengan kredensial valid dan field tambahan `warung_id` mengembalikan HTTP 200, padahal schema OpenAPI menetapkan `additionalProperties: false`.
- Fix: `LoginRequest` memakai atribut Laravel `FailOnUnknownFields`; field tak dikenal sekarang menghasilkan 422 sebelum autentikasi/token dibuat.
- `vendor/bin/pint --dirty --format agent`: PASS
- `php artisan test --compact tests/Feature/AuthApiTest.php`: PASS, 27 test / 3.668 assertions
- `php artisan test --no-progress`: PASS, 463 test / 65.093 assertions dalam 40.21 detik
- Project Compose test-only `larissama-login-unknown-field` dihentikan dan resource disposable dihapus.

Test baru mengirim field tambahan bersama kredensial valid, memeriksa 422 `VALIDATION_ERROR`, dan memastikan token Sanctum tidak terbit. Login valid dan kasus auth lainnya pada `AuthApiTest` tetap lulus. OpenAPI, database, dependency, dan operasi lain tidak berubah.

Kasus ini menutup satu mismatch field tambahan pada request login; seluruh matriks payload/status login dan conformance seluruh operasi belum selesai. Semua operasi OpenAPI tetap `DRAFT`.
