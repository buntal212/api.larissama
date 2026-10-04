# IDEMPOTENCY-SCOPE-NUMBER-001

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Task/test: BE-304/BE-404, T-RET-03, D09
- Commit yang diuji: `c62953c98d24f5a5bd450e27edf2eb5b27efe063`
- `tests/Feature/IdempotencyConcurrencyTest.php` SHA-256: `ad0fb9d3f34c0dbc0e3c32d6a0c9062249d08bc0f227e80b2dceb830a6adda50`
- `tests/Support/concurrent-http-worker.php` SHA-256: `f330314653af91501c9ce3013336c541c5a0dece48188a2e309b19ffcca92d8d`
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 pada Compose test disposable, DB `larissama_test`

## Hasil

**PASS untuk scope key praktis dan nomor transaksi concurrent pada kedua endpoint.** Pint lulus. `IdempotencyConcurrencyTest` lulus 9 test / 115 assertions; suite penuh lulus 246 test / 26412 assertions.

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/IdempotencyConcurrencyTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

Worker membangun Laravel Kernel dan Request sebelum menunggu barrier bersama. Kedua worker harus tiba sebelum request dilepas. Output worker mencatat awal/akhir `Kernel::handle()`; tiap test mengassert interval kedua HTTP request beririsan. Tidak memakai durasi total proses sebagai ukuran overlap.

Untuk key sama dan payload sama, dua request menghasilkan dua HTTP 201 dan dua transaksi tersimpan mandiri pada setiap konteks yang sah: dua kasir dalam satu warung untuk penjualan; dua manager dari warung berbeda untuk pembelian; serta kasir dan manager dalam satu warung lewat endpoint penjualan/pembelian masing-masing. Setiap resource kembali dari tabel, actor, dan tenant yang sesuai, dengan satu header/detail per request. Pair pada tabel yang sama memiliki ID serta nomor transaksi berbeda. Pair lintas endpoint juga menghasilkan resource terpisah; nomor memakai prefix transaksi masing-masing. Trigger delay dua detik hanya digunakan untuk pair lintas tabel.

Untuk nomor transaksi, dua key berbeda dengan payload valid berbeda dibuat bersamaan oleh actor dan tenant yang sama pada tiap endpoint. Kedua request mendapat 201, ID dan nomor berbeda, tepat dua header/detail tersimpan, serta nomor pada response sama dengan row masing-masing.

## Batas bukti

Test membuktikan perilaku untuk kombinasi scope yang dapat dipanggil lewat role dan integritas data yang berlaku. `user_id` unik global dan setiap user tenant terikat pada satu `warung_id`, sehingga tenant tidak dapat divariasikan secara independen dari actor yang sah. Role yang disetujui juga membatasi penjualan ke kasir dan pembelian ke manager; karena itu endpoint diuji lewat actor sah masing-masing dan tabel/action terpisah, bukan satu actor yang melanggar policy. Crash/restart dan retensi record key belum diuji; D09/T-RET-04 masih PARTIAL dan operasi tetap DRAFT.
