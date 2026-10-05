# SUPERADMIN-TRANSACTION-DETAIL-RBAC-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status akhir: PASS
- Commit test/docs: `6f4c379dcada1d5586841e4921a6bc5a35198c0e`
- Test: `tests/Feature/PenjualanApiTest.php`, `tests/Feature/PembelianApiTest.php`
- Lingkungan: Docker Compose project `larissama-backend-test`, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test` disposable)
- Pint: PASS
- Focused dua file: 39 test / 6761 assertions — PASS
- Suite penuh: 324 test / 38152 assertions, 34.09 detik — PASS
- Cleanup: container MySQL test dan network Compose test dibersihkan

## Cakupan

Dengan sale dan purchase tenant sudah tersimpan, superadmin tanpa `warung_id` mendapat 403 `FORBIDDEN` pada GET detail kedua resource. Kedua response detail cocok schema OpenAPI. Test list yang sama juga membuktikan GET list sale/purchase mendapat 403 schema-conformant. Header transaksi tetap tersimpan setelah penolakan.

## Batas bukti

Policy/controller sudah menolak sebelum mengekspos detail; tidak ada perubahan runtime, schema, atau dependency. Cakupan ini membuktikan penolakan superadmin pada list/detail transaksi. Aksi tenant lain, seluruh matriks D04, dan gate G1/G3/G4 tetap terbuka; semua operasi OpenAPI masih `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact
docker compose -f compose.test.yaml --project-name larissama-backend-test down --volumes --remove-orphans
```
