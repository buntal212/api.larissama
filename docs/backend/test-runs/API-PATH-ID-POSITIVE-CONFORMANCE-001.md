# API-PATH-ID-POSITIVE-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit runtime/test: `8a3e4824e7b7a26d4597e92b53addd44b368b0b5`
- SHA-256 `ApiRouteOpenApiConformanceTest.php`: `eb179444e2d9454b613b303975ca8d64899ece86a3f63dae2199e60a5a90f919`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `ApiRouteOpenApiConformanceTest`, 11 test / 735 assertions — PASS
- Suite penuh: 292 test / 27992 assertions — PASS
- Cleanup: service MySQL dan network Compose disposable dihapus

Probe awal gagal pada sepuluh operasi OpenAPI berparameter `{id}`: route `whereNumber()` menerima `0`, sehingga request tanpa bearer mencapai middleware dan menerima 401, sementara schema `Id` mensyaratkan string positif tanpa nol di depan. Keenam grup route kini memakai pola `[1-9][0-9]*`. Test memastikan parameter operasi merujuk `components/parameters/Id`, parameter wajib berada di path, dan schema string memakai pola `^[1-9][0-9]*$`. Pada seluruh sepuluh operasi, ID `1` mencapai middleware (401); `0`, `01`, dan `abc` tidak cocok route (404), dengan response 404 cocok schema OpenAPI. Fokus test tidak mengakses DB.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm --no-deps test-runner php artisan test --display-warnings tests/Feature/ApiRouteOpenApiConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Run membuktikan validasi format ID path pada sepuluh operasi detail/update saat ini dan inventaris method/path pada 28 operasi. Ia tidak menguji ID positif yang tidak ada dengan bearer, policy tenant, batas kapasitas `BIGINT`, request payload detail/update, atau seluruh conformance T-API-02. Tidak ada perubahan pada schema database, tenant, role, atau bentuk payload. Semua operasi tetap `DRAFT` dan 0/28 siap frontend.
