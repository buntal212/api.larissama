# PROTECTED-OPERATIONS-401-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `dce10139a7d55e722cd54924722e985e1991540f`
- SHA-256 `ProtectedOperation401ConformanceTest.php`: `5b56d384dc39441807313f8fcfea29fce575f554cfa62d91151f9d4e0c174cb7`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `ProtectedOperation401ConformanceTest`, 27 operasi / 783 assertions — PASS
- Suite penuh: 281 test / 27257 assertions — PASS
- MySQL trigger tersisa: tidak ada; Compose/network dibersihkan

Provider test membaca seluruh operasi dari OpenAPI dan mengecualikan hanya `operationId: login`, yang memang publik. Untuk setiap operasi lain, test memastikan bearer security dan response 401 didefinisikan, mengirim request tanpa token, lalu memeriksa HTTP 401, `code=UNAUTHENTICATED`, `request_id` non-kosong, serta body terhadap schema response OpenAPI. Cakupan termasuk operasi list/detail/create/update/admin/katalog/transaksi/laporan dan `/auth/me` serta `/auth/logout`.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/ProtectedOperation401ConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml exec -T test-db mysql -N -ularissama_test -plarissama-test-only larissama_test -e "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()"
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Hasil ini membuktikan respons anonim 401 untuk 27 operasi yang membutuhkan bearer pada kontrak OpenAPI saat run. Login publik tidak termasuk; login 401 tetap diuji pada `AUTH-CONFORMANCE-001`. Ini bukan conformance sukses, 403/404/422/429/500, semua request body/query, matriks role, atau seluruh T-API-02/04. Semua operasi tetap `DRAFT` sampai gate lain terpenuhi.
