# IDEMPOTENCY-KEY-HEADER-CONFORMANCE-001

Status: **PASS**
Task: T-API-02/04, BE-302/402
Rencana dicatat: `2feb215`
Commit test: `e8069b5a5400a5981898b10d363b8e38a1881df3`
Lingkungan: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`), Docker Compose project `larissama-backend-test`.

## Cakupan

`POST /penjualans` dan `POST /pembelians` dipanggil oleh owner menggunakan body valid menurut OpenAPI. Untuk masing-masing operasi:

- Header hilang, string kosong, dan string 256 karakter ditolak dengan HTTP 422 `VALIDATION_ERROR`.
- Response error cocok dengan schema OpenAPI dan memiliki `errors.Idempotency-Key`.
- Header/detail transaksi tetap nol pada seluruh request negatif.
- Header sepanjang tepat 255 karakter cocok schema parameter OpenAPI, diterima dengan HTTP 201, dan tersimpan bersama satu detail.

Test memeriksa parameter header pada OpenAPI (`required`, tipe string, `minLength: 1`, `maxLength: 255`) serta body/response sukses pada kedua operasi.

## Hasil

- Focused `IdempotencyKeyHeaderConformanceTest`: 1 test / 625 assertions, PASS.
- Pint: 144 file, PASS.
- Suite penuh: 328 test / 51.801 assertions dalam 36,77 detik, PASS.
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40; Docker Compose disposable.
- `git diff --check`: PASS; Compose project dibersihkan dan tidak ada container test tersisa.

Tidak ada perubahan runtime, schema database, dependency, atau aturan whitespace/retry/retensi. Ini hanya membuktikan batas header yang terdokumentasi; keputusan D09 masih PARTIAL untuk kebijakan retensi dan semua operasi tetap DRAFT. Detail test ada di [`IdempotencyKeyHeaderConformanceTest.php`](../../../tests/Feature/IdempotencyKeyHeaderConformanceTest.php).
