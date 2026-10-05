# TRANSACTION-RFC3339-CALENDAR-DATE-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test yang diuji: `6cd0e1d8c5338d0ef537d502d86c3c9f177899c6`
- File: `tests/TestCase.php`, `tests/Feature/TransactionTimestampRequestConformanceTest.php`
- SHA-256 TestCase: `b77e58dab0714b6a05f02f5b453fce823126f63061c3bcacdcaf16ed7f0ef54d`
- SHA-256 TransactionTimestampRequestConformanceTest: `0660ee0523bae822dc8aa09b23939ae67c3cdb18e6195ecab368d44e1941f60a`
- Lingkungan: Docker Compose disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`)
- Feature timestamp: 20 test / 2.538 assertions — PASS
- Focused timestamp + sale + purchase feature tests: 61 test / 10.272 assertions — PASS
- Pint `--dirty --format agent`: PASS
- Suite penuh: 393 test / 60.044 assertions dalam 35,46 detik — PASS

Probe parser menunjukkan `new DateTimeImmutable('2026-02-30T12:00:00Z')` menghasilkan tanggal 2 Maret 2026. Helper OpenAPI sebelumnya hanya memeriksa apakah constructor melempar exception sehingga menganggap tanggal tersebut valid. Helper kini memeriksa komponen kalender lewat `createFromFormat`, error/warning parser, dan round-trip format sebelum menguji timestamp lengkap.

`POST /penjualans` dan `POST /pembelians` menolak `2026-02-30T12:00:00Z` dan `2026-02-31T12:00:00Z` pada pemeriksaan request schema serta HTTP 422 `VALIDATION_ERROR`. Keduanya menunjuk `tanggal`; header dan rincian transaksi tetap kosong. Runtime tidak berubah karena rule Laravel `date` telah memvalidasi tanggal dengan `checkdate`.

Hari kabisat `2024-02-29T12:00:00Z` lolos schema dan menghasilkan HTTP 201 pada kedua POST. Response memakai `2024-02-29T12:00:00.000000Z`, dan nilai MySQL mentah `2024-02-29 12:00:00`. Tidak ada perubahan runtime, OpenAPI, schema database, dependency, atau keputusan bisnis. Semua operasi tetap DRAFT.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-calendar-date run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-calendar-date run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-calendar-date run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-calendar-date run --rm test-runner php artisan test --no-progress
docker compose -f compose.test.yaml --project-name larissama-calendar-date down --remove-orphans
```

Sesudah cleanup, `docker ps -a` dan daftar network tidak menunjukkan resource bernama `larissama-calendar-date`. Tidak ada volume database persisten pada Compose test.
