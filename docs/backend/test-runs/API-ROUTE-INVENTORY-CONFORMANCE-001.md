# API-ROUTE-INVENTORY-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `d3f71937234e02930f28d965000d31c012301943`
- SHA-256 `ApiRouteOpenApiConformanceTest.php`: `7d132c0f5adc33a2a999e90071a1ae7361302d60bd6310586322330b8e499057`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `ApiRouteOpenApiConformanceTest`, 1 test / 5 assertions — PASS
- Suite penuh: 282 test / 27262 assertions — PASS
- Cleanup: service MySQL dan network Compose disposable dihapus

Test membaca operasi method/path dari OpenAPI, memakai path prefix `/api/v1` dari server kontrak, lalu membandingkannya dua arah dengan route Laravel yang terdaftar. Seluruh 28 operasi dan 18 path cocok; tiap pasangan runtime unik, tidak ada operasi kontrak tanpa route, dan tidak ada route API tanpa dokumentasi. Alias `HEAD` yang ikut pada route `GET` Laravel dilewati. Test focused tidak mengakses database; suite penuh dijalankan pada MySQL 8.0.40 disposable.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm --no-deps test-runner php artisan test --display-warnings tests/Feature/ApiRouteOpenApiConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Run ini hanya memastikan inventaris HTTP method/path tersinkron dua arah dan tidak duplikat. Ia tidak membandingkan controller/action, authorization, request/response schema, seluruh status atau payload runtime, dan tidak menyelesaikan keputusan bisnis maupun gate T-API-01/02 secara penuh. Semua 28 operasi tetap `DRAFT` dan 0/28 siap frontend.
