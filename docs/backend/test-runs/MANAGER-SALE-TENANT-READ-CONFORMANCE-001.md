# MANAGER-SALE-TENANT-READ-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status akhir: PASS
- Test: `tests/Feature/PenjualanApiTest.php`
- Lingkungan: Docker Compose project `larissama-backend-test`, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test` disposable)
- Pint: PASS
- Focused `PenjualanApiTest`: 17 test / 3268 assertions — PASS
- Suite penuh: 322 test / 37614 assertions, 32.66 detik — PASS
- Cleanup: container MySQL test dan network Compose test dibersihkan

## Cakupan

Manager A membaca `GET /penjualans` dan melihat sale yang dicatat kasir pada warung sama. Response list memiliki `meta.total=1` dan `user_id` kasir tersebut. Manager mendapat 200 schema-conformant pada detail sale dengan rincian dan pencatat yang benar.

Sale warung lain tidak muncul di list dan detailnya memberi 404 schema-conformant. Row sale lintas tenant tetap ada. Query list dan response list/detail dicocokkan dengan kontrak OpenAPI.

## Batas bukti

Policy dan query runtime sudah sesuai sehingga tidak ada perubahan runtime, schema, atau dependency. Cakupan ini membuktikan hak baca sale lintas pencatat untuk manager dan scope tenant pada list/detail. Ia tidak menutup seluruh matriks D04, gate BE-303/G1, keputusan lain, atau kesiapan handoff; semua operasi OpenAPI tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact tests/Feature/PenjualanApiTest.php
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact
docker compose -f compose.test.yaml --project-name larissama-backend-test down --volumes --remove-orphans
```
