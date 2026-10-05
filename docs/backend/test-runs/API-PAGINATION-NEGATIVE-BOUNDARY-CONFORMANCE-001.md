# API-PAGINATION-NEGATIVE-BOUNDARY-CONFORMANCE-001

Status: **PASS**
Task: T-API-02/03, D13
Plan commit: `6f73695`
Test commit: `1dbd0dabc14f2aa4db40dc4b7ae3b2974e79df61`
Lingkungan: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`), Docker Compose project `larissama-backend-test`.

## Cakupan

`ApiPaginationQueryConformanceTest` kini mencakup `page=-1` dan `per_page=-1` sebagai kasus terpisah pada keenam GET list: warung admin, user, kategori, menu, penjualan, dan pembelian. Setiap query memakai bearer role yang sah.

Semua 12 request ditolak oleh schema integer OpenAPI `minimum: 1`, lalu runtime merespons HTTP 422 `VALIDATION_ERROR`, Error422 schema-conformant, dan error field yang sesuai. Nilai nol yang sudah diuji sebelumnya tetap menjadi kasus terpisah. Tidak ada perubahan runtime atau kontrak.

## Hasil

- Focused `ApiPaginationQueryConformanceTest`: 63 test / 3.885 assertions, PASS.
- Probe lower-bound (nilai 0 dan -1): 24 test / 1.008 assertions, PASS.
- Pint: PASS.
- Suite penuh: 361 test / 53.778 assertions dalam 42,13 detik, PASS.
- Versi: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40; Compose disposable dibersihkan.
- `git diff --check`: PASS.

Hasil hanya membuktikan pagination negatif pada enam GET list. Operasi tetap DRAFT sampai conformance dan gate lengkap. Test: [`ApiPaginationQueryConformanceTest.php`](../../../tests/Feature/ApiPaginationQueryConformanceTest.php).
