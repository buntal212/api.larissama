# TRANSACTION-REQUEST-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-302/BE-402, subset T-API-02 / D13

Commit yang diuji: `a6f1ff34fbc693166ae7962dcd95c9558d62a087`

Environment: PHP 8.3.35, Composer 2.10.3, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` dalam Compose test-only disposable. Stack database/network sudah dibersihkan.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php tests/Feature/LaporanApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner composer test
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Tiga feature test transaksi/laporan | PASS, 22 test / 3270 assertions; tanpa warning |
| Suite `composer test` | PASS, 81 test / 9748 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Cakupan yang diperiksa

Helper `assertOperationRequestMatchesOpenApi()` mencocokkan JSON request body dengan `requestBody` dan header operasi dengan parameter OpenAPI yang wajib, termasuk `$ref` lokal dan `oneOf` tepat satu cabang.

- `POST /penjualans`: body dan `Idempotency-Key` cocok untuk create, replay identik, key conflict dengan HTTP 409, dan payload tenant-crossing schema-valid yang ditolak HTTP 422. `menu_id` dikirim sebagai string sesuai D13.
- `POST /pembelians`: body dan `Idempotency-Key` cocok untuk pembelian ringkas, rinci, replay identik, serta key conflict HTTP 409. Bentuk ringkas/rinci masing-masing cocok tepat satu cabang `oneOf`.
- Request pembelian parsial yang tidak memenuhi `oneOf` D10 tetap mendapat HTTP 422; request tersebut sengaja tidak dinyatakan schema-conformant.

Tidak ada mismatch pada subset body/header yang diperiksa. Tidak ada perubahan controller, business rule, keputusan D10, atau skema database pada slice ini.

## Batas bukti

Checker mendukung subset keyword schema yang digunakan dan bukan validator OpenAPI 3.1 umum. Run ini memvalidasi payload fixture yang dikirim tes, bukan seluruh kemungkinan request runtime. Query schema, body invalid lain, status/missing-header lain, semua operasi lain, serta T-API-02 penuh belum teruji. D13 tetap PARTIAL dan semua 28 operasi tetap `DRAFT`; gate G3/G4 tetap terbuka.

Source hashes yang diuji:

- `tests/TestCase.php`: `caf769c4dbb8449d82adccc6b1cb6af12556d59b8d002ed5d0b8fafad52ceed3`
- `tests/Feature/PenjualanApiTest.php`: `200b3ef1edc12a60d276b00f3f514574e52f082da16bee62e99f329fc904d5da`
- `tests/Feature/PembelianApiTest.php`: `84efe821a3e9d9f5919f83662313447b58321cbc4ae174e99b886b7354e0f326`
