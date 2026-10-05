# D11-PURCHASE-CONTRACT-CONFORMANCE-001

- Tanggal: 2026-10-05
- Task: BE-404, T-API-02/04, D09/D11/D13
- Commit dasar saat run: `a46ec73`; perubahan run ini menambahkan conformance test pada working tree.
- Lingkungan: Docker Compose test disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40.

## Cakupan dan hasil

`ApiRequestUnknownFieldsConformanceTest` menambahkan PATCH koreksi dan POST pembatalan pembelian: body valid ditambah `warung_id` ditolak request schema dan menghasilkan 422 `VALIDATION_ERROR` yang cocok OpenAPI; header, rincian, status dan tabel audit tidak berubah.

`IdempotencyKeyHeaderConformanceTest` menambahkan kedua operasi D11. Header hilang, kosong, lebih dari 255 karakter, atau whitespace di tepi ditolak oleh OpenAPI/runtime dengan 422 schema-conformant tanpa event audit. Key 255 karakter cocok schema request, diterima 201 schema-conformant, dan tercatat bersama event koreksi/pembatalan yang benar.

| Pemeriksaan | Hasil |
| --- | --- |
| Focused conformance | PASS: 5 test / 1.878 assertions |
| Laravel Pint | PASS |
| Suite Laravel lengkap | PASS: 492 test / 71.961 assertions, 39,21 detik |
| Validator OpenAPI 3.1 | PASS; `docs/api/openapi.yaml: OK` |
| Cleanup Compose | PASS; database test disposable dan network dihapus |

## Batas bukti

Request/response D11 kini memiliki bukti untuk body valid, field asing, batas header, sukses, replay, dan beberapa response error serta role. Belum seluruh kombinasi status/payload diuji. Kebijakan D09 tentang retensi key setelah header dihapus masih terbuka. OperationId D11 tetap `DRAFT`; hasil ini tidak menyatakan kesiapan integrasi API live.
