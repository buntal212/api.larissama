# TRANSACTION-500-ERROR-CONFORMANCE-001

Status: **PASS**
Task: T-API-02/04, T-SAL-06/T-BUY-06
Rencana dicatat: `7293286`
Commit test: `b63ac3cf82239e4f940a2d411b693a4f50dc1c8a`
Lingkungan: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`), Docker Compose project `larissama-backend-test`.

## Cakupan

Test MySQL atomicity memasang trigger yang melempar kesalahan pada insert detail kedua untuk `POST /penjualans` dan `POST /pembelians`. Payload serta header idempotency yang dikirim cocok dengan request schema OpenAPI. Response harus HTTP 500, `code=INTERNAL_ERROR`, pesan generik server, `errors` kosong, UUID `request_id`, dan cocok dengan schema Error500 OpenAPI. Isi response tidak boleh membocorkan teks exception/SQL. Sesudah kegagalan, tabel header/rincian tetap kosong dan trigger selalu dibersihkan.

## Hasil

- Focused `TransactionAtomicityTest`: 2 test / 220 assertions, PASS.
- Pint: 144 file, PASS.
- Suite penuh: 329 test / 52.058 assertions dalam 34,88 detik, PASS.
- Database: MySQL 8.0.40 Compose disposable. Stack dibersihkan dan tidak ada container test tersisa.
- `git diff --check`: PASS.

Tidak ada perubahan runtime, database, atau dependency. Bukti ini hanya mencakup HTTP 500 saat kegagalan insert detail sale/purchase; semua operasi tetap DRAFT. Detail test: [`TransactionAtomicityTest.php`](../../../tests/Feature/TransactionAtomicityTest.php).
