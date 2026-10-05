# TRANSACTION-UTC-INSTANT-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test yang diuji: `53c2b47c29592f060bbfef4a1cab423c7c99d6c7`
- File: `tests/Feature/PenjualanApiTest.php`, `tests/Feature/PembelianApiTest.php`
- SHA-256 `PenjualanApiTest.php`: `ad2239866e194f117105666e4abecbcfdb4803bfe773f5b32d56628a1213809b`
- SHA-256 `PembelianApiTest.php`: `c7765c938a066f0c80bbd681141b29b60fc8506388442c119c2ac973336383cd`
- Lingkungan: Docker Compose disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`)
- Pint `--dirty --format agent`: PASS
- Kasus baru terpilih: 2 test / 950 assertions — PASS
- File penjualan dan pembelian: 41 test / 7.734 assertions — PASS
- Suite penuh: 373 test / 57.506 assertions dalam 37,91 detik — PASS

Kedua POST menerima `2026-10-04T23:30:00-04:00`. Response dan raw value kolom MySQL menunjukkan instant identik: `2026-10-05T03:30:00.000000Z` dan `2026-10-05 03:30:00` UTC. Untuk timezone tenant `Asia/Jakarta`, filter list tanggal lokal `2026-10-04` tidak mengembalikan transaksi, sedangkan `2026-10-05` mengembalikannya. Request dan response POST serta kedua variasi GET cocok dengan schema OpenAPI.

Assertion pertama membaca nilai melalui model Eloquent yang meng-cast kolom ke Carbon. Test diperbaiki agar membaca nilai database mentah melalui query builder; seluruh rerun kemudian lulus. Tidak ada perubahan runtime, OpenAPI, schema database, dependency, atau keputusan bisnis. Keputusan D08 masih PARTIAL untuk kebijakan tenant tanpa timezone, backdate, dan future date; seluruh operasi tetap DRAFT.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-utc-conformance run --rm test-runner sh -lc 'php artisan test tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php --filter=timestamp_is_stored --no-progress'
docker compose -f compose.test.yaml --project-name larissama-utc-conformance run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-utc-conformance run --rm test-runner sh -lc 'php artisan test tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php --no-progress'
docker compose -f compose.test.yaml --project-name larissama-utc-conformance run --rm test-runner php artisan test --no-progress
docker compose -f compose.test.yaml --project-name larissama-utc-conformance down --remove-orphans
```

Sesudah cleanup, `docker ps -a --filter name=larissama-utc-conformance` tidak menampilkan container tersisa. Tidak ada volume database persisten pada Compose test.
