# MANAGER-PURCHASE-TENANT-READ-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status akhir: PASS
- Test: `tests/Feature/PembelianApiTest.php`
- Lingkungan: Docker Compose project `larissama-backend-test`, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test` disposable)
- Pint: PASS
- Focused `PembelianApiTest`: 21 test / 3150 assertions — PASS
- Suite penuh: 322 test / 37382 assertions, 35.20 detik — PASS
- Cleanup: container MySQL test dan network Compose test dibersihkan
- Percobaan awal: focused test sempat gagal karena `PembelianRinci` belum di-import; import ditambahkan dan semua verifikasi akhir di atas lulus

## Cakupan

Manager A membaca `GET /pembelians` dan melihat pembelian yang dibuat manager B di warung yang sama, dengan `meta.total=1`. Manager A juga mendapat 200 schema-conformant pada detail pembelian B, dengan `user_id` dan rincian yang benar.

Pembelian warung lain tidak muncul di list dan detailnya mendapat 404 schema-conformant. Row purchase warung lain tetap ada. Query list dan response list/detail dicocokkan dengan kontrak OpenAPI.

## Batas bukti

Policy dan query runtime sudah sesuai sehingga tidak ada perubahan runtime, schema, atau dependency. Cakupan ini hanya membuktikan pembacaan purchase lintas pencatat untuk manager dan tenant scope. Seluruh kombinasi role/status, matriks D04, gate BE-403, dan kesiapan operasi tetap terbuka; semua operasi OpenAPI tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact
docker compose -f compose.test.yaml --project-name larissama-backend-test down --volumes --remove-orphans
```
