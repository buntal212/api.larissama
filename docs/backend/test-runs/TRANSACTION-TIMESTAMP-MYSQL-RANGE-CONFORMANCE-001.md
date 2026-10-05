# TRANSACTION-TIMESTAMP-MYSQL-RANGE-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit implementasi/test: `131ba0e2f7bc2dddda37f827edf1f86bae6b8f07`
- Lingkungan: Docker Compose disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`)
- SHA-256 OpenAPI: `e7b68e9f8950fff7e661d6e9f60d55ab96d8af30e4d2165abf48a6a24f76b5ff`
- SHA-256 `TransactionTimestampRequestConformanceTest.php`: `3a5c5611ba5555aeaca16f8998836e6245a73adaed914c22fbe423bcc2ced54a`
- SHA-256 `UtcMysqlDateTimeRange.php`: `3c8fd2615bc18b2b2535e696d2b38e5a9b30881789d806ec55ea9f4e47c89007`
- Feature timestamp: 42 test / 5.844 assertions — PASS
- Timestamp + penjualan + pembelian: 83 test / 13.578 assertions — PASS
- Suite penuh: 415 test / 63.350 assertions dalam 40.02 detik — PASS
- Pint `--dirty --format agent`: PASS
- `openapi-spec-validator` 0.9.0: PASS (`docs/api/openapi.yaml: OK`)

Probe awal mengungkap tahun `0001` cocok pola OpenAPI lama dan offset yang menggeser instant UTC melewati rentang storage tidak dicegah sebelum insert. Percobaan pertama validasi memperbaiki batas lokal tetapi masih membiarkan tahun UTC `10000` karena perbandingan string tanggal tahun lima digit tidak berurutan lexicographically. Rule bersama kini memeriksa tahun numerik UTC dahulu, kemudian tanggal/waktu fixed-width untuk batas dalam tahun 1000–9999.

Pada kedua POST, `0001-01-01T00:00:00Z` tidak cocok schema dan memberi 422. `1000-01-01T00:00:00+00:01` dan `9999-12-31T23:59:59-00:01` tetap cocok bentuk RFC3339 schema, tetapi konversi UTC keluar rentang DATETIME; runtime menolak dengan 422 pada `tanggal`, tanpa menulis header atau rincian. Batas sah `1000-01-01T00:00:00Z` dan `9999-12-31T23:59:59Z` lolos request/response schema, HTTP 201, dan raw MySQL persis sama dengan instant response UTC.

Timestamp OpenAPI membatasi tahun empat digit ke 1000–9999. Kedua FormRequest memakai rule bersama yang menolak UTC instant di luar `1000-01-01 00:00:00` hingga `9999-12-31 23:59:59`, sehingga kegagalan konversi/insertion tidak menjadi 500. Tidak ada perubahan migration/schema DB. Semua operasi tetap DRAFT.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-datetime-range run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-datetime-range run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-datetime-range run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-datetime-range run --rm test-runner php artisan test --no-progress
docker compose -f compose.openapi.yaml --project-name larissama-datetime-openapi run --rm openapi-validator
docker compose -f compose.test.yaml --project-name larissama-datetime-range down --remove-orphans
docker compose -f compose.openapi.yaml --project-name larissama-datetime-openapi down --remove-orphans
```

Kedua Compose project dibersihkan. Sesudahnya `docker ps -a` dan `docker network ls` dengan filter `larissama-datetime` tidak menampilkan container maupun network. Test Compose tidak membuat volume database persisten.
