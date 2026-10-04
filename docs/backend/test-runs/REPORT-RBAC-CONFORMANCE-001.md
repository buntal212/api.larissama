# REPORT-RBAC-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit runtime/test: `9d42e359b67848e55029219a40559d65b72d95d3`
- SHA-256 `LaporanApiTest.php`: `b4483e7366f363965c1911ae54b9b4d670f3a2282b924615444c6fa9c6c037f8`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `LaporanApiTest`, 6 test / 636 assertions — PASS
- Suite penuh: 293 test / 28082 assertions — PASS
- Cleanup: service MySQL dan network Compose disposable dihapus

Inspeksi awal menemukan controller kedua laporan tidak memeriksa role sebelum memanggil query. Kini kedua operasi hanya mengizinkan user `manager` dengan `warung_id` terisi; kasir dan superadmin mendapat HTTP 403, `code=FORBIDDEN`, dan response schema OpenAPI `Error403`. Empat pasangan kasir/superadmin × penjualan/pembelian diuji. Test manager sukses yang sudah ada tetap menjadi kontrol positif. Jumlah row penjualan/pembelian tetap sama sesudah request ditolak.

Run awal gagal hanya karena helper error test untuk validasi 422 mengharapkan `VALIDATION_ERROR`; helper itu tidak sesuai untuk 403. Assertion diperbaiki menjadi HTTP/code 403 dan validasi schema OpenAPI, lalu focused test serta full suite lulus.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/LaporanApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Owner tidak diuji karena izin laporan owner di luar pengelolaan user masih terbuka pada D04; implementasi saat ini menolak selain manager sebagai default-deny sampai izin disepakati. Run tidak menutup seluruh matriks role, business decisions, atau conformance semua status operasi. Kedua operasi laporan dan seluruh kontrak tetap `DRAFT`.
