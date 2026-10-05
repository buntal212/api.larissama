# D11-ROUTE-AND-ID-CONFORMANCE-001

- Tanggal: 2026-10-05
- Task/test: BE-003/405, T-API-01/02, D11/D13
- Base commit: `030a7e9`
- `ApiRouteOpenApiConformanceTest.php` SHA-256: `51286de92b109b6247fd06e09bacc1c072707a5476aa94caf239a2b155f7f029`
- Runtime: PHP 8.3.35, Laravel 13.34.0, Docker Compose test-runner tanpa MySQL

## Hasil

**PASS:** seluruh **30 method/path OpenAPI** pada 19 path cocok satu-ke-satu dengan route Laravel. Tidak ada route duplikat, route kontrak hilang, atau route Laravel tanpa dokumentasi. Uji format ID kini mencakup 12 operasi dengan `{id}`, termasuk `updatePembelian` dan `cancelPembelian`: `0`, angka berawalan nol, dan teks mendapat 404 schema-conformant; ID positif `1` mencapai middleware autentikasi.

Focused `ApiRouteOpenApiConformanceTest`: **13 test / 881 assertions**, PASS, 0.89 detik. Pint tidak dijalankan karena tidak ada perubahan PHP.

## Command dan cleanup

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner php artisan test --display-warnings tests/Feature/ApiRouteOpenApiConformanceTest.php
docker compose -f compose.test.yaml down -v
docker compose -f compose.test.yaml ps -a
```

Runner `--no-deps` tidak memulai database; container runner dan network Compose test-only dibersihkan.

## Batas

Run ini memverifikasi method/path dan pola ID, bukan controller/action, otorisasi role, request body/query, atau seluruh response/status. Semua operationId tetap `DRAFT` sampai gate kontrak penuh terpenuhi.
