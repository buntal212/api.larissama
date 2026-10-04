# SUPERADMIN-TRANSACTION-LIST-RBAC-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `03f923527ffc22366c8a59effb538dd5c05b3804`
- SHA-256 `PenjualanApiTest.php`: `0f5dcdcacdc76c4e0e657f52cb097adffa0ed25e717d49d37f7ac55e6548614c`
- SHA-256 `PembelianApiTest.php`: `fb49b16c9fd5d3b7ba2246ba163360371076592d5e8840c014cfc5f747b385ef`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `PenjualanApiTest` + `PembelianApiTest`, 29 test / 4079 assertions — PASS
- Suite penuh: 306 test / 32411 assertions — PASS
- Cleanup: container MySQL dan network Compose disposable dihapus

Setiap endpoint diuji saat transaksi tenant memang tersedia. Token superadmin tidak memiliki `warung_id`; GET `/penjualans` dan `/pembelians` keduanya mengembalikan HTTP 403 `FORBIDDEN` sesuai schema OpenAPI Error403. Fixture sale dan purchase tetap tersimpan. Query serta response dicocokkan ke OpenAPI, jadi payload transaksi tidak keluar melalui daftar yang ditolak.

Test mengonfirmasi implementasi kandidat least-privilege, bukan penutupan matriks role D04. Akses owner di luar pengelolaan user dan cakupan riwayat kasir tetap belum diputuskan. Tidak ada perubahan runtime, schema, atau dependency; semua operasi tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml up -d test-db
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```
