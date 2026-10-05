# TRANSACTION-RFC3339-OFFSET-BOUNDS-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit implementasi/test yang diuji: `f3d989f`
- File runtime: `app/Http/Requests/Api/V1/PenjualanStoreRequest.php`, `app/Http/Requests/Api/V1/PembelianStoreRequest.php`
- File helper/test: `tests/TestCase.php`, `tests/Feature/TransactionTimestampRequestConformanceTest.php`
- SHA-256 PenjualanStoreRequest: `ec4260241bdcb2c2ed6c784f21e5e4b5db18f97922ccd062245905d8980ae2a9`
- SHA-256 PembelianStoreRequest: `314239020f93de1f40c00395ed924901731ee360e46ece273521e3060001656c`
- SHA-256 TestCase: `2a69e5e927860c24febd373b86f0bbada089422132647d3dfcc317b5d358cf8b`
- SHA-256 TransactionTimestampRequestConformanceTest: `123de0e65241d68028fb498f2c57cab78f0b4628d360f104c4a4280f79777165`
- Lingkungan: Docker Compose disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`)
- Feature timestamp: 14 test / 1.748 assertions — PASS
- Focused timestamp + sale + purchase feature tests: 55 test / 9.482 assertions — PASS
- Pint `--dirty --format agent`: PASS
- Suite penuh: 387 test / 59.254 assertions dalam 35,71 detik — PASS

Probe parser PHP menunjukkan `DateTimeImmutable` menerima `2026-10-04T23:30:00+24:00`, sedangkan `+00:60` gagal. Test awal kemudian mengonfirmasi helper OpenAPI juga meloloskan `+24:00`. Helper `date-time` dan regex kedua FormRequest diperketat sehingga offset numerik mengikuti batas RFC3339: jam `00`–`23`, menit `00`–`59`.

Pada penjualan dan pembelian, input `2026-10-04T23:30:00Z` disimpan sebagai `2026-10-04 23:30:00` UTC. Batas valid `2026-10-04T23:30:00+23:59` disimpan sebagai `2026-10-03 23:31:00` UTC. Response ISO-8601 UTC dan nilai DB mentah cocok dengan instant input. Kedua request lolos pemeriksaan schema OpenAPI dan menghasilkan HTTP 201.

Offset `+24:00` dan `+00:60` ditolak oleh checker OpenAPI dan oleh kedua endpoint. Masing-masing merespons HTTP 422 `VALIDATION_ERROR`, menunjuk `tanggal`, dan tidak membuat header/rincian. Tidak ada perubahan OpenAPI, schema DB, dependency, atau keputusan D08 selain validasi format; semua operasi tetap DRAFT.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-rfc3339-offset run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-rfc3339-offset run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-rfc3339-offset run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-rfc3339-offset run --rm test-runner php artisan test --no-progress
docker compose -f compose.test.yaml --project-name larissama-rfc3339-offset down --remove-orphans
```

Sesudah cleanup, `docker ps -a` dan daftar network tidak menunjukkan resource bernama `larissama-rfc3339-offset`. Tidak ada volume database persisten pada Compose test.
