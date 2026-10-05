# D11-CANCEL-RBAC-CONFORMANCE-001

- Tanggal: 2026-10-05
- Task/test: BE-404, T-BUY-06, T-RBAC-01, D04/D11/D13
- Base commit: `c3f71d3`
- `tests/Feature/PembelianApiTest.php` SHA-256: `a9a1e99679fe5d584dacb8500b54a57af93e27281e3153d1cc3e8fa7068afdba`
- Runtime: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, Compose test-only

## Hasil

**PASS:** `POST /api/v1/pembelians/{id}/pembatalan` menolak kasir dan superadmin dengan HTTP 403, serta manager dari tenant lain dengan HTTP 404. Setiap request memakai body dan `Idempotency-Key` yang valid menurut OpenAPI. Semua response cocok dengan schema yang didokumentasikan. Sesudah seluruh percobaan, status/total header pembelian dan rincian tetap, tanpa event audit tambahan.

- `PembelianApiTest`: **28 test / 6.157 assertions**, PASS, 3.15 detik.
- Pint (`--dirty`): PASS.

## Command dan cleanup

```sh
docker compose -f compose.test.yaml run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml down -v
docker compose -f compose.test.yaml ps -a
```

Stack Compose test-only dihapus setelah run.

## Batas

Run ini menambah role dan tenant boundary untuk pembatalan. Ia tidak menguji seluruh kombinasi body/status atau menjadikan `cancelPembelian` READY; semua operasi tetap `DRAFT`.
