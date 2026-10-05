# TRANSACTION-RFC3339-DATE-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit implementasi/test yang diuji: `420de1d8bacb1ea4dfe915953186604e7ada41a4`
- File runtime: `app/Http/Requests/Api/V1/PenjualanStoreRequest.php`, `app/Http/Requests/Api/V1/PembelianStoreRequest.php`
- File test: `tests/Feature/TransactionTimestampRequestConformanceTest.php`
- SHA-256 PenjualanStoreRequest: `6b5f2f3e6a8a5f856615e184cfc9c9928d4d145269f5ec8cf5dcd48fd30fd2c3`
- SHA-256 PembelianStoreRequest: `abc681e961ef2ba7804d129bbf2718ce45de23437aad1b97956faa56172aa2e0`
- SHA-256 TransactionTimestampRequestConformanceTest: `18a80c836dc11a24eb241e4c39258864741f5c5df32a69b9dca8f4f679ee21e2`
- Lingkungan: Docker Compose disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`)
- Pint `--dirty --format agent`: PASS
- Focused timestamp + sale + purchase feature tests: 47 test / 8.226 assertions — PASS
- Suite penuh: 379 test / 57.998 assertions dalam 37,52 detik — PASS

Probe awal mengirim tanggal saja, datetime tanpa zona, dan datetime dengan separator spasi pada `POST /penjualans` serta `POST /pembelians`. Keenam request yang ditolak schema OpenAPI tersebut semula diterima runtime sebagai HTTP 201. Setelah validasi format ditambahkan pada kedua FormRequest, keenamnya memberi HTTP 422 `VALIDATION_ERROR`, menunjuk `tanggal`, cocok dengan Error422, dan tidak membuat header atau rincian.

Validasi mensyaratkan bentuk timestamp dengan separator `T` dan zona `Z` atau `±HH:MM`, serta mempertahankan rule `date` untuk tanggal yang dapat diparse. Timestamp RFC3339 ber-offset tetap diterima pada kedua endpoint; tes UTC instant yang sudah ada memverifikasi konversi offset ke UTC, raw value MySQL, response, dan filter tanggal lokal. Request/response positif dan negatif yang dipilih cocok schema OpenAPI.

Tidak ada perubahan OpenAPI, schema database, timezone tenant, dependency, atau kebijakan backdate/future. D08 tetap PARTIAL untuk tenant tanpa timezone, backdate, dan future date; semua operasi tetap DRAFT.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-rfc3339-date run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-rfc3339-date run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-rfc3339-date run --rm test-runner php artisan test --no-progress
docker compose -f compose.test.yaml --project-name larissama-rfc3339-date down --remove-orphans
```

Setelah cleanup, `docker ps -a` tidak menampilkan container dengan nama `larissama-rfc3339-date`; network Compose juga sudah dihapus. Tidak ada volume database persisten pada Compose test.
