# SUPERADMIN-TENANT-CATALOG-WRITE-RBAC-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status: PASS
- Commit: `d7f73b1`
- Baseline sebelum test: `55f584a`
- Test: `tests/Feature/KategoriMenuApiTest.php`
- SHA-256 test: `9c40bc9702c2bf06401045d0a5c13556c5dee4c9cc721c8086cfdc24467891c0`
- Lingkungan: Docker Compose terisolasi, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40
- Pint: PASS, 140 file
- Focused: 9 test / 1210 assertions — PASS
- Suite penuh: 325 test / 38373 assertions dalam 33.31 detik — PASS
- Cleanup: service database test dan network Compose dibersihkan
- Perubahan runtime/schema: tidak ada

## Cakupan

Dengan data katalog warung tersedia dan token superadmin `warung_id = NULL`, test mengirim payload valid menurut OpenAPI untuk POST dan PATCH kategori serta menu. Keempat request mendapat HTTP 403 `FORBIDDEN` dan response cocok schema OpenAPI. Jumlah row kategori/menu tetap sama, kategori dan menu awal tidak berubah, dan item baru tidak tersimpan.

Payload request dan response error dicocokkan dengan contract helper. Ini membuktikan jalur otorisasi superadmin untuk empat write katalog pada tenant yang ada; test tidak mencakup seluruh matriks role/status maupun konformance semua operasi.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner sh -lc 'composer install --no-interaction --prefer-dist && php artisan test tests/Feature/KategoriMenuApiTest.php'
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner vendor/bin/pint --test
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner php artisan test --no-progress
docker compose -f compose.test.yaml --project-name larissama-backend-test down --remove-orphans
```

Semua operasi API tetap `DRAFT`; matriks D04 dan gate G2 masih terbuka.
