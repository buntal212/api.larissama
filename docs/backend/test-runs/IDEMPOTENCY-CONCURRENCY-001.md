# IDEMPOTENCY-CONCURRENCY-001

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Task/test: BE-304/BE-404, T-RET-03, D09
- Commit yang diuji: `7a6919a7ef9c42ac75797f62d1d53bb181582427`
- `tests/Feature/IdempotencyConcurrencyTest.php` SHA-256: `58dd5d00ff959d8316af563fe784583d28e9a5b125c721780730b2079ff8e621`
- `tests/Support/concurrent-http-worker.php` SHA-256: `4e828ee2c3a943743d476b6796528d51522b60b193ea6663f5a6f9374340df5d`
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 di Compose test disposable, DB `larissama_test`

## Hasil

**PASS untuk overlap retry payload identik sale dan purchase.** Pint lulus. `IdempotencyConcurrencyTest` lulus 2 test / 17 assertions; suite penuh lulus 239 test / 26314 assertions.

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/IdempotencyConcurrencyTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

Dua proses PHP menjalankan Laravel HTTP Kernel dengan bearer token dan koneksi MySQL masing-masing. File barrier dibuat sebelum HTTP request; kedua proses harus hadir sebelum melanjutkan. Trigger MySQL sementara memberi delay satu detik sebelum insert header. Untuk sale dan purchase, kedua request mendapat HTTP 201 dengan ID dan nomor transaksi sama. Assertion memverifikasi dua arrival, elapsed kurang dari tiga detik, tepat satu header dan satu rincian. Trigger, fixture, file barrier, container, dan network disposable dibersihkan.

Run awal setelah worker dibuat generik mengembalikan 404 pada kedua operasi karena worker menyusun path singular (`/penjualan` dan `/pembelian`). Assertion gagal sebelum konfirmasi record. Allowlist worker kemudian memetakan tipe transaksi ke route plural; rerun terarah dan suite penuh lulus. Kegagalan itu berasal dari harness test, bukan hasil request aplikasi.

## Batas bukti

Run membuktikan overlap retry untuk payload identik oleh user/tenant/endpoint yang sama. Konflik payload berbeda yang bersamaan, tabrakan key lintas actor/tenant/endpoint, operasi crash/restart, dan keunikan nomor pada request berlainan tetap belum diuji. D09/T-RET-03 hanya partial; endpoint transaksi tetap DRAFT.
