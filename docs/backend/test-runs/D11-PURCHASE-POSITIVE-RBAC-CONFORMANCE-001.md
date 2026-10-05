# D11-PURCHASE-POSITIVE-RBAC-CONFORMANCE-001

- Tanggal: 2026-10-05
- Task/test: BE-404, T-BUY-06, T-RBAC-01, D04/D11/D13
- Base commit: `9282644`
- `tests/Feature/PembelianApiTest.php` SHA-256: `881d2fbfa484151171bf8c3c343c77c0f415857bb209bd5bb88a6698b552eb03`
- Runtime: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, Compose test-only

## Hasil

**PASS:** owner dan manager masing-masing dapat mengoreksi dan membatalkan pembelian pada warungnya sendiri. Body request dan response 201 dicocokkan dengan schema OpenAPI. Audit menyimpan aktor, alasan, serta snapshot yang benar; koreksi memperbarui catatan pembelian dan pembatalan mempertahankan header dengan status `dibatalkan`.

- `PembelianApiTest`: **30 test / 6.455 assertions**, PASS, 3.24 detik.
- Laravel Pint (`--dirty`): PASS.
- OpenAPI 3.1 validator 0.9.0: PASS (`docs/api/openapi.yaml: OK`).
- Stack MySQL Compose disposable dihentikan dan dihapus setelah run.

## Command

```sh
docker compose -f compose.test.yaml run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/PembelianApiTest.php
docker compose -f compose.openapi.yaml run --build --rm openapi-validator
docker compose -f compose.test.yaml down -v
docker compose -f compose.test.yaml ps -a
```

## Batas

Run ini menutup dua jalur sukses role yang belum ada: manager melakukan koreksi dan owner melakukan pembatalan. Dikombinasikan dengan `D11-CANCEL-RBAC-CONFORMANCE-001`, kedua aksi punya bukti sukses owner/manager serta penolakan kasir, superadmin, dan tenant lain pada subset yang tercakup. Semua operationId tetap `DRAFT` sampai sisa request/status/API gate dipenuhi.
