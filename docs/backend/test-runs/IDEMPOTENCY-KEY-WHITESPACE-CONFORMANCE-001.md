# IDEMPOTENCY-KEY-WHITESPACE-CONFORMANCE-001

Status: **PASS**
Task: T-API-01/02, D09/D13
Rencana dicatat: `bd757e1`
Commit kontrak/test: `c6f5aa1771783cb20dc87035dc8cbcdc61243d21`
Lingkungan: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`), Docker Compose project `larissama-backend-test`.

## Cakupan

OpenAPI `Idempotency-Key` pada `POST /penjualans` dan `POST /pembelians` kini memiliki pattern `^\S(?:.*\S)?$`, selaras dengan controller yang menolak `trim($key) !== $key`. Parameter tetap string 1–255 karakter. Test kedua endpoint membuktikan:

- Leading/trailing whitespace ditolak oleh schema dan runtime dengan HTTP 422 `VALIDATION_ERROR`, field `Idempotency-Key`, dan no-write.
- Key sepanjang tepat 255 karakter dengan spasi internal tetap valid menurut schema dan diterima HTTP 201 untuk kedua jenis transaksi.
- Header hilang, kosong, dan panjang 256 yang sudah diuji sebelumnya tetap ditolak; validasi batas tersebut tidak berubah.

## Hasil

- Focused `IdempotencyKeyHeaderConformanceTest`: 1 test / 725 assertions, PASS.
- Pint: 144 file, PASS.
- `openapi-spec-validator` 0.9.0: `docs/api/openapi.yaml: OK`.
- Suite penuh: 329 test / 52.158 assertions dalam 36,15 detik, PASS.
- MySQL 8.0.40 Compose disposable; test dan validator stack dibersihkan, tidak ada container tersisa.
- `git diff --check`: PASS.

Perubahan hanya menyelaraskan pola kontrak dengan perilaku yang telah ada pada runtime; tidak ada perubahan runtime/schema database/dependency maupun aturan retry/retensi. D09 tetap PARTIAL dan seluruh operasi DRAFT. Test: [`IdempotencyKeyHeaderConformanceTest.php`](../../../tests/Feature/IdempotencyKeyHeaderConformanceTest.php).
