# TRANSACTION-INACTIVE-CATALOG-SALE-CONFORMANCE-001

## Hasil

- Tanggal keputusan dan verifikasi: 2026-10-05 (Asia/Jakarta)
- Keputusan user D06: tolak penjualan baru bila menu atau kategorinya nonaktif.
- Commit implementasi/test: `4654272371130358870f7871da9c5cc98d31d568`
- Lingkungan: Docker Compose disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`)
- Test khusus: 1 test / 422 assertions — PASS
- Gabungan period/sale/purchase/report: 51 test / 9.759 assertions — PASS
- Suite penuh: 419 test / 64.739 assertions dalam 40,78 detik — PASS
- Pint: PASS, 150 file
- `openapi-spec-validator` 0.9.0: PASS (`docs/api/openapi.yaml: OK`)
- SHA-256 `SaleInactiveCatalogConformanceTest.php`: `4bb9002eac684bdb9eb90424b8b5d69ba699bbd673420ec198358d98d1cebe73`
- SHA-256 OpenAPI: `92b6a5781a713f762d21b15e626a38094c2a8b9e7dd242523e22fa4072ddf40e`

Request sale yang selain itu valid diuji dengan menu tenant yang nonaktif, menu aktif dalam kategori nonaktif, dan menu aktif dalam kategori aktif. Dua kondisi nonaktif menghasilkan HTTP 422 `VALIDATION_ERROR` schema-conformant dengan error `rincian.0.menu_id`; jumlah header penjualan dan rincian tetap nol. Menu/kategori aktif menghasilkan 201 dan satu header serta detail tersimpan.

`CreatePenjualan` mengunci row menu dan kategori di dalam transaksi, memastikan keduanya berada pada warung actor dan aktif, lalu menghitung serta menyimpan snapshot. Lock membuat perubahan status katalog menunggu transaksi penjualan selesai. Perilaku ini untuk penjualan baru; snapshot transaksi lama tidak diubah. Pembatalan/koreksi sale, audit dan dampaknya pada laporan tetap belum diputuskan. Tidak ada perubahan schema database. Semua operasi OpenAPI tetap DRAFT.

## Command

```sh
docker compose -f compose.test.yaml run --rm test-runner php artisan test --filter=SaleInactiveCatalogConformanceTest
docker compose -f compose.test.yaml run --rm test-runner php artisan test tests/Feature/TransactionPeriodMysqlRangeConformanceTest.php tests/Feature/SaleInactiveCatalogConformanceTest.php tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php tests/Feature/LaporanApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml run --rm test-runner ./vendor/bin/pint --test
docker compose -f compose.openapi.yaml run --rm openapi-validator
docker compose -f compose.test.yaml down --remove-orphans
docker compose -f compose.openapi.yaml down --remove-orphans
```
