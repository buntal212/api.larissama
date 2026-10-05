# TRANSACTION-TIMESTAMP-FRACTION-PRECISION-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `55745594b2694bcdfa251202c81ffafa66a73a0f`
- Lingkungan: Docker Compose disposable, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`)
- SHA-256 OpenAPI setelah deskripsi presisi diperbarui: `dc4f356a38b16d91e0daf48ff950bdc70bbe624387dad34b22921c1338c8b3bf`
- SHA-256 `TransactionTimestampRequestConformanceTest.php`: `a2aada87ea1db4668edbe63e02a49484478ac3aed615d78317d89b7690bcbf9f`
- Feature timestamp: 32 test / 4.422 assertions — PASS
- Timestamp + penjualan + pembelian: 73 test / 12.156 assertions — PASS
- Suite penuh: 405 test / 61.928 assertions dalam 36.16 detik — PASS
- Pint `--dirty --format agent`: PASS
- `openapi-spec-validator` 0.9.0: PASS (`docs/api/openapi.yaml: OK`)

Pada `POST /penjualans` dan `POST /pembelians`, timestamp `2026-10-04T23:30:00.123Z` diterima, disimpan sebagai `2026-10-04 23:30:00`, dan dikembalikan sebagai `2026-10-04T23:30:00.000000Z`. Bentuk ber-offset `2026-10-04T23:30:00.999999999-04:00` disimpan sebagai `2026-10-05 03:30:00` dan dikembalikan sebagai `2026-10-05T03:30:00.000000Z`. Request serta response cocok dengan subset schema OpenAPI; nilai raw MySQL dan instant UTC di response cocok. Ini membuktikan pecahan diterima lalu dipotong, bukan dibulatkan, sesuai `DATETIME_PRECISION=0` dan konversi UTC yang sudah dirancang.

Tidak ada perubahan runtime atau schema database. Deskripsi `Timestamp` OpenAPI dan handoff README kini menyebut perilaku presisi detik. Semua operasi tetap DRAFT.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-fraction-precision run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-fraction-precision run --rm test-runner php artisan test tests/Feature/TransactionTimestampRequestConformanceTest.php tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php --no-progress
docker compose -f compose.test.yaml --project-name larissama-fraction-precision run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-fraction-precision run --rm test-runner php artisan test --no-progress
docker compose -f compose.openapi.yaml --project-name larissama-fraction-openapi run --rm openapi-validator
docker compose -f compose.test.yaml --project-name larissama-fraction-precision down --remove-orphans
docker compose -f compose.openapi.yaml --project-name larissama-fraction-openapi down --remove-orphans
```

Kedua project telah dihentikan dengan `down --remove-orphans`. Pengecekan `docker ps -a` dan `docker network ls` atas nama project tidak menemukan container atau network tersisa. Compose test tidak membuat volume database persisten.
