# API-PATH-ID-OVERFLOW-CONFORMANCE-001

Status: **PASS**
Task: T-API-02/04, D13
Plan commit: `7d0b7aa`
Test commit: `8df7ec57fc23ada9135ee53835cf8de58ef8bf7f`
Lingkungan: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`), Docker Compose project `larissama-backend-test`.

## Cakupan

Feature test memeriksa enam route detail dan empat route update: warung admin, user, kategori, menu, penjualan, dan pembelian. Setiap operasi dicoba dengan `9223372036854775808` (di atas rentang signed integer PHP) dan ID 40 digit positif (di atas rentang unsigned BIGINT MySQL), memakai bearer role yang sah. Body PATCH juga divalidasi terhadap request schema OpenAPI.

Semua 20 kombinasi ID dan operasi valid menurut parameter path `Id` OpenAPI, lalu mendapat HTTP 404 `NOT_FOUND` yang cocok dengan Error404 schema dan envelope D13. Snapshot memastikan row domain, rincian, serta jumlah/isi token tidak berubah. Snapshot mengabaikan `last_used_at` dan `updated_at` pada token karena Sanctum memperbarui metadata tersebut saat bearer digunakan.

## Hasil

- Focused `ApiPathIdOverflowConformanceTest`: 20 test / 1.116 assertions, PASS.
- Pint: PASS.
- Suite penuh: 349 test / 53.274 assertions dalam 34,39 detik, PASS.
- Versi: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40; Compose disposable dibersihkan setelah run.
- `git diff --check`: PASS.

Tidak ditemukan overflow maupun mismatch response, sehingga runtime dan OpenAPI tidak berubah. Batas ini hanya mencakup detail/update dan dua nilai ID yang diuji; seluruh operasi tetap DRAFT sampai gate dan conformance lengkap. Test: [`ApiPathIdOverflowConformanceTest.php`](../../../tests/Feature/ApiPathIdOverflowConformanceTest.php).
