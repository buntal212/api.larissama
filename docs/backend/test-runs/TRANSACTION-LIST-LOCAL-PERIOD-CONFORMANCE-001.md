# TRANSACTION-LIST-LOCAL-PERIOD-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `319125fe394988b844abf4e1c6087101533e11a6`
- SHA-256 `PenjualanApiTest.php`: `2a86d58777d3e408907f27377766e1e2ca7946e8b25a00196796c3067186a049`
- SHA-256 `PembelianApiTest.php`: `dc029c470235d19bcb9e1406ea3fc387fb67632db3dc326e07bec1763185b977`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `PenjualanApiTest` + `PembelianApiTest`, 27 test / 3975 assertions — PASS
- Suite penuh: 304 test / 32307 assertions — PASS
- Cleanup: container MySQL dan network Compose disposable dihapus

Untuk warung `Asia/Jakarta`, query tanggal lokal `2026-10-04` pada GET `/penjualans` dan `/pembelians` hanya mengembalikan dua transaksi di dalam hari tersebut. Timestamp yang disimpan sebelum awal lokal (`2026-10-03 16:59:59`) dan tepat pada akhir eksklusif (`2026-10-04 17:00:00`) tidak masuk; timestamp tepat pada awal (`2026-10-03 17:00:00`) dan satu detik sebelum akhir (`2026-10-04 16:59:59`) masuk. Query dan respons dicocokkan dengan OpenAPI, termasuk ID, jumlah hasil, dan metadata pagination.

Fixture header dibuat langsung untuk mengisolasi filter baca. Bukti ini mencakup batas hari untuk zona `Asia/Jakarta`, bukan create transaksi, report, timezone NULL/invalid, zona lain, atau seluruh variasi query. Tidak ada perubahan runtime, schema, atau dependency. Semua operasi tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```
