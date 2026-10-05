# D11-PROTECTED-401-CONFORMANCE-001

- Tanggal: 2026-10-05
- Task/test: BE-003/405, T-API-02/04, D11/D13
- Base commit: `fcd57bdf9d01a8705a6c1cf09b773f14d0b7f0a1`
- `ProtectedOperation401ConformanceTest.php` SHA-256: `5b56d384dc39441807313f8fcfea29fce575f554cfa62d91151f9d4e0c174cb7`
- Runtime: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, Compose test-only

## Hasil

**PASS:** 29 operasi yang mewajibkan bearer token mengembalikan HTTP 401 tanpa token, `code=UNAUTHENTICATED`, `request_id` non-kosong, dan body yang cocok dengan response schema OpenAPI. Cakupan dinamis mencakup seluruh 30 operationId selain login publik, termasuk `updatePembelian` dan `cancelPembelian` yang ditambahkan D11.

Focused `ProtectedOperation401ConformanceTest`: **29 test / 841 assertions**, PASS, 1.11 detik. OpenAPI meminta bearer pada semua operasi terlindungi serta mendokumentasikan response 401.

## Command dan cleanup

```sh
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/ProtectedOperation401ConformanceTest.php
docker compose -f compose.test.yaml down -v
docker compose -f compose.test.yaml ps -a
```

Stack Compose test-only dihentikan dan dihapus; tidak ada container tersisa pada project test.

## Batas

Login publik tidak termasuk; auth conformance menguji login. Run ini membuktikan jalur anonim 401 saja, bukan role, sukses, body/query lengkap, status error lain, atau kesiapan operasi untuk frontend. Seluruh operationId masih `DRAFT`.
