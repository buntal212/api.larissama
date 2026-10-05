# CASHIER-PURCHASE-DETAIL-RBAC-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status akhir: PASS
- Test: `tests/Feature/PembelianApiTest.php`
- Lingkungan: Docker Compose project `larissama-backend-test`, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test` disposable)
- Pint: PASS
- Focused `PembelianApiTest`: 22 test / 3494 assertions — PASS
- Suite penuh: 324 test / 38175 assertions, 33.91 detik — PASS
- Cleanup: container MySQL test dan network Compose test dibersihkan

## Cakupan

Dengan satu purchase header dan rincian milik manager yang tersimpan, kasir menerima 403 `FORBIDDEN` schema-conformant pada GET list dan detail purchase. POST purchase dengan payload valid juga mendapat 403 schema-conformant. Header/rincian awal tetap ada dan request kasir tidak menambah row.

## Batas bukti

Policy runtime sudah sesuai sehingga tidak ada perubahan runtime, schema, atau dependency. Cakupan ini hanya menolak akses baca/create pembelian bagi kasir; seluruh matriks D04 dan gate BE-403/G1/G4 tetap terbuka. Semua operasi OpenAPI tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact
docker compose -f compose.test.yaml --project-name larissama-backend-test down --volumes --remove-orphans
```
