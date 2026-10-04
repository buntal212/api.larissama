# TRANSACTION-LIST-QUERY-REJECTION-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Test commit: `e35c67b9c85d0b4348415e78c5c66d4e3b8d620e`
- SHA-256 `PenjualanApiTest.php`: `dcb2418afdb2cd1fbb29112518be245e35452df77c6bf93ec9060b73e4f0905a`
- SHA-256 `PembelianApiTest.php`: `2df8df4e34558472ed2b64bc466bd44c87af53c130945cbd96897cbdc59ea431`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `PenjualanApiTest` + `PembelianApiTest`, 23 test / 3339 assertions — PASS
- Suite penuh: 300 test / 30737 assertions — PASS
- Cleanup: container MySQL dan network Compose disposable dihapus

Manager terautentikasi mengirim query tak valid lewat HTTP GET. Sale menerima tiga rejection case: status di luar enum, `date_from` berformat bukan `Y-m-d`, dan tanggal awal sesudah tanggal akhir. Purchase menerima dua kasus tanggal terakhir. Semua respons HTTP 422 `VALIDATION_ERROR` cocok schema OpenAPI `Error422`, mengandung error pada field yang sesuai, dan tidak menulis header/detail. Test menggunakan tenant kosong sehingga count penjualan/pembelian serta rinciannya tetap nol.

Tidak ada perubahan runtime, schema database, atau dependency. `PenjualanIndexRequest` dan `PembelianIndexRequest` yang sudah ada menolak input tersebut sebelum query periode dijalankan.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Cakupan hanya satu invalid enum status pada sale, format tanggal malformed, dan rentang terbalik pada kedua transaction list. Nilai valid `batal`, seluruh query/status/role, keputusan timezone NULL/invalid D08, dan filter lain belum dibuktikan di run ini. Semua operasi tetap `DRAFT`; ini bukti parsial T-API-02/03, bukan conformance API penuh.
