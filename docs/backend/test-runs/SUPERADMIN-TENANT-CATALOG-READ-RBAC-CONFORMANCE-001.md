# SUPERADMIN-TENANT-CATALOG-READ-RBAC-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status akhir: PASS
- Commit test/docs: `bb529f09356c064bbc2c0f3e834b86cbd6e9e052`
- Test: `tests/Feature/KategoriMenuApiTest.php`
- Lingkungan: Docker Compose project `larissama-backend-test`, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test` disposable)
- Pint: PASS
- Focused `KategoriMenuApiTest`: 8 test / 1012 assertions — PASS
- Suite penuh: 324 test / 38108 assertions, 35.73 detik — PASS
- Cleanup: container MySQL test dan network Compose test dibersihkan

## Cakupan

Ketika kategori dan menu tenant tersedia, superadmin dengan `warung_id = NULL` menerima 403 `FORBIDDEN` pada list dan detail kategori serta menu. Keempat response cocok schema OpenAPI dan envelope D13. Query pagination/sort pada kedua list cocok dengan parameter OpenAPI. Fixture kategori/menu tetap ada sesudah penolakan.

## Batas bukti

Policy/controller sudah menolak sebelum query katalog; tidak ada perubahan runtime, schema, atau dependency. Cakupan hanya akses baca kategori/menu untuk superadmin. Role/action lain, seluruh matriks D04, dan gate G2 tetap terbuka; semua operasi OpenAPI tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact tests/Feature/KategoriMenuApiTest.php
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --compact
docker compose -f compose.test.yaml --project-name larissama-backend-test down --volumes --remove-orphans
```
