# TRANSACTION-TIMESTAMP-CLOCK-RANGE-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit implementasi/test yang diuji: `4f89ab3f470217a7209180135faf521025dadb9a`
- File runtime: `app/Http/Requests/Api/V1/PenjualanStoreRequest.php`, `app/Http/Requests/Api/V1/PembelianStoreRequest.php`
- File kontrak/helper/test: `docs/api/openapi.yaml`, `tests/TestCase.php`, `tests/Feature/TransactionTimestampRequestConformanceTest.php`
- SHA-256 OpenAPI: `88683b48c5c1e85d9a159aaedb8c7c9e84f7a8bb8ff01e81eae4e52a4f43e373`
- SHA-256 PenjualanStoreRequest: `cd39b55618029a3b8791804d59a96aed4268b10f4d23491a00f3057a211ccdd3`
- SHA-256 PembelianStoreRequest: `1ccd8811a7c996b3c2a3dceb06a02c763a9a03619037cad801ad6b5faff996ae`
- SHA-256 TestCase: `3d7b8081b02193be89aff1e998d5634a3d00ac268c7289c815c75c2828040d74`
- SHA-256 TransactionTimestampRequestConformanceTest: `b99d19de48bf573dccef923757ca7cd1b074f947e41f4608c8b4440cda8d048d`
- Lingkungan: Docker Compose disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`)
- Feature timestamp: 28 test / 3.490 assertions — PASS
- Focused timestamp + sale + purchase feature tests: 69 test / 11.224 assertions — PASS
- `openapi-spec-validator` 0.9.0: PASS
- Pint `--dirty --format agent`: PASS
- Suite penuh: 401 test / 60.996 assertions dalam 39,09 detik — PASS

Probe awal menunjukkan PHP mengubah `2026-10-04T23:59:60Z` menjadi tengah malam tanggal berikutnya; helper OpenAPI juga menganggapnya valid. Kontrak `Timestamp` kini menyebut profil yang didukung: jam `00`–`23`, menit dan detik `00`–`59`, pecahan opsional, serta `Z` atau offset legal. Leap second ditolak karena penyimpanan transaksi MySQL tidak dapat mempertahankan nilai detiknya. Pattern OpenAPI, regex kedua FormRequest, dan helper schema test sudah selaras.

Pada `POST /penjualans` dan `POST /pembelians`, input `25:00:00`, `23:60:00`, dan `23:59:60` ditolak oleh request schema serta menghasilkan HTTP 422 `VALIDATION_ERROR` pada `tanggal`; header dan rincian tidak tersimpan. Input batas atas biasa `2026-10-04T23:59:59Z` lolos schema dan menghasilkan HTTP 201; response `2026-10-04T23:59:59.000000Z` dan raw MySQL `2026-10-04 23:59:59` menunjukkan instant yang sama.

Validasi OpenAPI 3.1 lulus. Tidak ada perubahan schema database atau dependency. Semua operasi tetap DRAFT.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-clock-range run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-clock-range run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-clock-range run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-clock-range run --rm test-runner php artisan test --no-progress
docker compose -f compose.test.yaml --project-name larissama-clock-range down --remove-orphans
docker compose -f compose.openapi.yaml --project-name larissama-clock-openapi run --rm openapi-validator
docker compose -f compose.openapi.yaml --project-name larissama-clock-openapi down --remove-orphans
```

Sesudah cleanup, daftar container dan network tidak menunjukkan resource bernama `larissama-clock-range` atau `larissama-clock-openapi`. Tidak ada volume database persisten pada Compose test.
