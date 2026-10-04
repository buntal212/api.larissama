# API-END-TO-END-READBACK-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Test commit: `48c45d2b3627d667635869d0bff6760d4face051`
- SHA-256 `tests/Feature/ApiEndToEndWorkflowTest.php`: `ebddf6417ffd8c76cd21d49e7d4f434bf44d248a9e13b3b741c163c1ce1d4a57`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `ApiEndToEndWorkflowTest`, 1 test / 3143 assertions — PASS
- Suite penuh: 300 test / 31671 assertions — PASS
- Cleanup: container MySQL dan network Compose disposable dihapus

Setelah transaksi dibuat melalui API pada `API-END-TO-END-WORKFLOW-001`, manager mengambil list penjualan dan detail sale yang dibuat kasir, list pembelian, serta detail purchase ringkas dan rinci. Query page/per_page dan response HTTP terpilih dicocokkan ke OpenAPI. Test memastikan ID string, tenant, total, snapshot nama/harga menu, nama/subtotal item pembelian, urutan list berdasarkan tanggal, dan qty/unit/harga NULL pada pembelian ringkas. Laporan agregat dan logout tetap diperiksa dalam workflow yang sama.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/ApiEndToEndWorkflowTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Run ini menutup readback sukses untuk resource yang dibuat tenant dalam satu workflow terpilih. Error GET, semua filter, cross-tenant pada setiap kombinasi role, dan seluruh status/query belum ditutup oleh E2E ini; bukti terpisah masih diperlukan. Operasi tetap `DRAFT`, T-E2E-01 parsial, dan gate G1–G4 tetap terbuka.
