# D18-SALES-ORDER-PAYMENT-CONFORMANCE-001

- Tanggal: 2026-10-07
- Task: BE-307; keputusan D04, D06, D08, D18; T-SAL-09 dan T-REP-05
- Commit kode diuji: `a29e2e75800a503a2564f4780eb413c97398f44e`
- Lingkungan: Docker Compose test disposable, PHP 8.3, MySQL 8.0.40.

## Hasil

| Pemeriksaan | Hasil |
| --- | --- |
| Feature/regression fokus | PASS: 78 test, 30.684 assertions, 6.58 detik; sembilan feature test files |
| Laravel Pint | PASS: 201 files (`docker compose -f compose.test.yaml run --rm test-runner vendor/bin/pint --test`) |
| OpenAPI 3.1 | PASS (`docker compose -f compose.openapi.yaml run --rm openapi-validator`; `docs/api/openapi.yaml: OK`) |
| Route/OpenAPI inventory | PASS: 37 operationId dan 25 path cocok dengan route terdaftar |
| Migration development | Migration D18 tercatat telah diterapkan di database development oleh Artisan |

Command feature suite:

```sh
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings \
  tests/Feature/SalesOrderPaymentApiTest.php \
  tests/Feature/PenjualanApiTest.php \
  tests/Feature/LaporanApiTest.php \
  tests/Feature/BusinessSchemaMigrationConformanceTest.php \
  tests/Feature/PenjualanCorrectionApiTest.php \
  tests/Feature/ApprovedTransactionRulesConformanceTest.php \
  tests/Feature/ApiOpenApiDocumentIntegrityTest.php \
  tests/Feature/ApiOpenApiExamplesConformanceTest.php \
  tests/Feature/ApiRouteOpenApiConformanceTest.php
```

## Cakupan

Test memeriksa pembuatan pesanan pending dengan `nama_pelanggan` opsional dan `no_transaksi`; daftar/filter transaksi yang dibuat pencatat lain dalam warung; pembayaran penuh cash dengan kembalian, penyimpanan waktu/petugas pembayaran, dan replay idempotent; edit serta pembatalan pending dengan alasan dan audit; laporan periode berdasarkan hari lokal pembayaran; backfill/status dan metadata migration; serta koreksi penjualan pada tepat 72 jam dari waktu pembayaran dan penolakan sesudah batas.

## Batas bukti

Run ini fokus pada alur inti D18 bersama regresi modul terkait. Suite penuh G3/G4, stress/concurrency pembayaran, seluruh variasi role/status/validation, dan production deployment belum dijalankan di slice ini. Status per operationId tetap `READY_FOR_FRONTEND` untuk alur utama; pemeriksaan lebih luas tercatat di `x-deferred-verification` OpenAPI dan dapat dilanjutkan bersama integrasi frontend. Migration development sudah diterapkan; verifikasi status migration tambahan tidak tersedia pada penutupan run ini karena Docker daemon tidak dapat diakses dari sesi terminal.
