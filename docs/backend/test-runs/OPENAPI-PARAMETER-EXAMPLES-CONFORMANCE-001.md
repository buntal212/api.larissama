# OPENAPI-PARAMETER-EXAMPLES-CONFORMANCE-001

Status: **PASS**
Task: T-API-01/D13
Rencana dicatat: `bd8154c`
Commit test: `c4cbcf923568e12d887334f61fc7f667f0be9e07`
Lingkungan: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, Docker Compose project `larissama-backend-test`.

## Cakupan dan hasil

`ApiOpenApiExamplesConformanceTest` sekarang memeriksa contoh requestBody/response yang sudah ada serta contoh parameter di komponen dan parameter inline path/operasi. Contoh reusable `DateFrom` dan `DateTo` masing-masing cocok dengan schema `Date` (`YYYY-MM-DD`). Referensi parameter yang dipakai operasi diperiksa lewat definisi komponen sehingga tidak menggandakan hasil.

- Focused `ApiOpenApiExamplesConformanceTest`: 2 test / 12.542 assertions, PASS.
- Pint: 144 file, PASS.
- Suite penuh: 329 test / 51.846 assertions dalam 36,79 detik, PASS.
- Full suite menggunakan MySQL 8.0.40 di Compose disposable; stack dibersihkan dan tidak ada container tersisa.
- `git diff --check`: PASS.

Tidak ada perubahan OpenAPI, runtime, database, dependency, atau keputusan bisnis. Pemeriksaan memakai subset schema helper yang ada; validator umum OpenAPI 3.1 telah dicatat terpisah. Seluruh operasi tetap DRAFT. Lihat test [`ApiOpenApiExamplesConformanceTest.php`](../../../tests/Feature/ApiOpenApiExamplesConformanceTest.php).
