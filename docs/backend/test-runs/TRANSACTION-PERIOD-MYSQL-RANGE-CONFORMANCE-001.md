# TRANSACTION-PERIOD-MYSQL-RANGE-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit implementasi/test: `4654272371130358870f7871da9c5cc98d31d568`
- Lingkungan: Docker Compose disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`)
- SHA-256 OpenAPI: `92b6a5781a713f762d21b15e626a38094c2a8b9e7dd242523e22fa4072ddf40e`
- SHA-256 `LocalPeriodUtcMysqlRange.php`: `579e4d8edc55be0eff9660868410e016a0a63d4ca5a1f495c882bd386c7689f7`
- SHA-256 `TransactionPeriodMysqlRangeConformanceTest.php`: `c78f19ae58f7b2ad090fde04b82f05b33b6c580fb19e69526463792e2b1d414e`
- Feature test terarah: 3 test / 967 assertions — PASS
- Gabungan period/sale/purchase/report: 51 test / 9.759 assertions — PASS
- Suite penuh terbaru: 419 test / 64.739 assertions dalam 40,78 detik — PASS
- Pint `--test`: PASS (150 file)
- `openapi-spec-validator` 0.9.0: PASS (`docs/api/openapi.yaml: OK`)
- Tidak ada migration atau perubahan schema database.

Keempat endpoint GET daftar/laporan transaksi memvalidasi batas tanggal lokal setelah dikonversi ke UTC, sebelum query ke tabel `penjualans` atau `pembelians`. Periode `1000-01-01` pada `Asia/Jakarta` menghasilkan batas awal UTC tahun 0999 dan ditolak HTTP 422 pada `date_from`. Periode `9999-12-31` pada `Etc/GMT+12` menghasilkan batas akhir-eksklusif UTC tahun 10000 dan ditolak HTTP 422 pada `date_to`. Semua error cocok dengan Error422 OpenAPI dan tidak menjalankan query bisnis. Batas aman UTC `date_from=1000-01-01` serta `date_to=9999-12-30` tetap memberi 200 dan hasil kosong pada kedua list/laporan.

OpenAPI menjelaskan batas representable MySQL `DATETIME` dan parameter yang menerima 422. Hasil hanya mencakup filter tanggal empat endpoint transaksi/laporan ini; tanggal masa aktif warung, operasi lain, dan seluruh contract gate belum berubah. Seluruh operasi tetap DRAFT.

## Command

```sh
docker compose -f compose.test.yaml run --rm test-runner php artisan test --filter=TransactionPeriodMysqlRangeConformanceTest
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml run --rm test-runner ./vendor/bin/pint --test
docker compose -f compose.openapi.yaml run --rm openapi-validator
docker compose -f compose.test.yaml down --remove-orphans
docker compose -f compose.openapi.yaml down --remove-orphans
```
