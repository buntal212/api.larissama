# D11-JSON-BODY-CONFORMANCE-001

- Tanggal: 2026-10-05
- Task: BE-003/404; T-API-02/04; D11/D13
- Commit dasar saat run: `b21f5c0`; perluasan tes D11 dilakukan pada working tree.
- Lingkungan: Docker Compose test disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40.

## Cakupan

`ApiMalformedJsonConformanceTest` kini memasukkan `PATCH /pembelians/{id}` dan `POST /pembelians/{id}/pembatalan` pada tiga jalur:

- JSON syntax rusak menghasilkan HTTP 400 `BAD_REQUEST` schema-conformant.
- Body kosong menghasilkan HTTP 422 `VALIDATION_ERROR` schema-conformant.
- Root JSON `null`, array kosong, array berisi, string, angka, dan boolean masing-masing ditolak HTTP 422 schema-conformant.

Setiap request menggunakan ID pembelian dan bearer owner yang valid serta key idempotency sah. Snapshot header, status, hitungan detail, tabel transaksi lain, dan tabel audit dikonfirmasi tidak berubah.

## Hasil

| Pemeriksaan | Hasil |
| --- | --- |
| Focused `ApiMalformedJsonConformanceTest` | PASS: 3 test / 2.815 assertions |
| Laravel Pint | PASS |
| Suite Laravel lengkap | PASS: 492 test / 72.390 assertions, 42,58 detik |
| Validator OpenAPI 3.1 | PASS; `docs/api/openapi.yaml: OK` |
| Cleanup Compose | PASS; database test disposable dan network dihapus |

Dengan run ini seluruh 13 operasi yang mewajibkan request body memiliki cakupan ketiga bentuk JSON tersebut.

## Batas bukti

Ini hanya mencakup parsing JSON, body kosong, dan bentuk root; bukan seluruh validasi bisnis setiap request. Semua operasi tetap `DRAFT`; keputusan durasi D09 tujuh hari baru diputuskan setelah run ini; bukti batas expiry tercatat terpisah pada `IDEMPOTENCY-7-DAY-EXPIRY-CONFORMANCE-001`.
