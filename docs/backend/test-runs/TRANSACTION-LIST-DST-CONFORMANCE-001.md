# TRANSACTION-LIST-DST-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `c12ffd114a02edd8099a9394ad2991b47902a487`
- SHA-256 `PenjualanApiTest.php`: `1f8d7633e7e567473d64028942bfdf12f702299acaca1ce109cd4cfd695c6139`
- SHA-256 `PembelianApiTest.php`: `1a820754fd97db8cef9450c860a28a739d2589c3fba4769938e2005b38abeec7`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `PenjualanApiTest` + `PembelianApiTest`, 31 test / 4613 assertions — PASS
- Suite penuh: 308 test / 32945 assertions — PASS
- Cleanup: container MySQL dan network Compose disposable dihapus

Untuk timezone `America/New_York`, GET list sale dan purchase dengan tanggal lokal `2026-03-08` mengembalikan hanya dua transaksi di jendela lokal 23 jam. Awal hari `2026-03-08 05:00:00Z` masuk; satu detik sebelumnya tidak. Detik terakhir `2026-03-09 03:59:59Z` masuk; akhir eksklusif `2026-03-09 04:00:00Z` tidak masuk. Query, urutan/ID wire, total, pagination, dan response dicocokkan ke OpenAPI.

Ini melengkapi uji batas list `Asia/Jakarta` dengan hari yang melewati transisi DST. Bukti hanya mencakup satu hari DST musim semi di New York, bukan semua zona/peralihan DST, create, laporan, atau timezone NULL/invalid. Tidak ada perubahan runtime/schema/dependency; semua operasi tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml up -d test-db
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```
