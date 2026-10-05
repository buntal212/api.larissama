# OPENAPI-DOCUMENT-INTEGRITY-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status: PASS
- Test: `tests/Feature/ApiOpenApiDocumentIntegrityTest.php`
- Lingkungan: Docker Compose project `larissama-backend-test`, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test` disposable)
- Pint: PASS
- Focused: 1 test / 116 assertions — PASS
- Suite penuh: 321 test / 36836 assertions, 35.59 detik — PASS
- Cleanup: container MySQL test dan network Compose test dibersihkan

## Cakupan

Test membaca `docs/api/openapi.yaml` dan memverifikasi 28 operasi, `operationId` non-kosong dan unik, mapping `responses` non-kosong pada tiap operasi, serta resolusi semua `$ref` JSON Pointer lokal. Test ini memperluas pemeriksaan inventaris method/path yang sudah ada tanpa mengubah runtime, database, atau dependency.

## Batas bukti

Pemeriksaan ini khusus untuk invariant struktur yang disebut di atas. Ia bukan validator umum OpenAPI 3.1, tidak memeriksa semantic schema, contoh request/response, external reference, atau kesesuaian runtime endpoint. Karena itu T-API-01 masih parsial, conformance keseluruhan belum lulus, dan semua operasi tetap `DRAFT`. Commit test: `85ae00fc5a2b53295688d4285c2fe0c4ce0c1969`.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm --no-deps test-runner php artisan test --compact tests/Feature/ApiOpenApiDocumentIntegrityTest.php
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact
docker compose -f compose.test.yaml --project-name larissama-backend-test down --volumes --remove-orphans
```
