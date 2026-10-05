# TRANSACTION-MONEY-DECIMAL-CAPACITY-CONFORMANCE-001

- Tanggal: 2026-10-05
- Task/test: BE-302, BE-402, T-SAL-05, D05/D13
- Base commit: `59b1238`
- Test: `tests/Feature/ApprovedTransactionRulesConformanceTest.php`
- SHA-256: `9134770b40b2eaa2f10433feb12ca936c3770630ca6eb752fdc3107071cec9e8`
- Runtime: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, Docker Compose test-only

## Hasil

**PASS:** batas maksimum nominal satu kolom `DECIMAL(15,2)`, `9999999999999.99`, diterima sebagai total penjualan dan pembelian, dengan response yang cocok schema OpenAPI serta nilai header tersimpan sama persis di MySQL. Total sale dan purchase yang terbentuk dari rincian sah tetapi melampaui kapasitas kolom ditolak dengan HTTP 422 `VALIDATION_ERROR`; tidak ada header maupun rincian parsial.

- `ApprovedTransactionRulesConformanceTest`: **14 test / 3.227 assertions**, PASS, 2.45 detik.
- Laravel Pint (`--dirty`): PASS.
- Stack MySQL Compose disposable dibersihkan; `docker compose -f compose.test.yaml ps -a` kosong.
- Tidak ada perubahan runtime, OpenAPI schema, migration, atau dependency.

## Commands

```sh
docker compose -f compose.test.yaml run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/ApprovedTransactionRulesConformanceTest.php
docker compose -f compose.test.yaml down -v
docker compose -f compose.test.yaml ps -a
```

## Batas

Run ini menutup nilai maksimum total satu transaksi dan overflow agregasi rincian pada sale/purchase. Ia tidak menggantikan suite penuh atau gate request/status/role untuk operasi terkait. Semua operasi API tetap `DRAFT`.
