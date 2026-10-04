# IDEMPOTENCY-CONFLICT-RACE-001

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Task/test: BE-304/BE-404, T-RET-03, D09
- Commit kode yang diuji: `1423242097c4e6e4a60afb7e4b5d06dcc0bc6b9a`
- `tests/Feature/IdempotencyConcurrencyTest.php` SHA-256: `dfe9420c5163d15f9b5e7c2cd1438b89fcfd1a03e76c34154b088929ae41d857`
- Worker bersama: `tests/Support/concurrent-http-worker.php` pada commit `7a6919a7ef9c42ac75797f62d1d53bb181582427`
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 pada Compose test disposable, DB `larissama_test`

## Hasil

**PASS untuk konflik payload bersamaan pada penjualan dan pembelian.** Pint lulus. `IdempotencyConcurrencyTest` lulus 4 test / 37 assertions; suite penuh lulus 241 test / 26334 assertions.

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/IdempotencyConcurrencyTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

Dua worker menjalankan HTTP Kernel dengan bearer token dan koneksi MySQL masing-masing. Untuk setiap jenis transaksi, keduanya memakai `Idempotency-Key` sama dan payload `catatan` yang berbeda. Barrier mencatat dua worker sebelum request; trigger MySQL sementara menahan insert header selama satu detik. Penjualan dan pembelian masing-masing menghasilkan tepat satu HTTP 201 dan satu HTTP 409 dengan `code=IDEMPOTENCY_KEY_REUSED`. Tepat satu header dan satu detail tersimpan, ID pada respons 201 cocok dengan row tersimpan, kedua worker melewati barrier, dan waktu run kurang dari tiga detik. Pemenang 201 tidak ditentukan. Dua skenario retry identik yang sudah ada juga tetap lulus. Trigger, barrier, fixture, container, dan network disposable dibersihkan.

## Batas bukti

Run membuktikan dua payload berbeda yang bertabrakan dengan key sama dalam scope user/warung/endpoint yang sama, untuk create sale dan purchase. Belum diuji isolasi key lintas actor, tenant, atau endpoint; crash/restart; retensi record; dan keunikan nomor untuk request berbeda. Karena itu D09/T-RET-03 tetap PARTIAL dan operasi transaksi tetap DRAFT.
