# OWNER-TENANT-ACCESS-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Baseline runtime: commit `ea1610e412f03f7cae9e6c0f70dada662cd92da0` (RBAC owner D04); test owner ditambahkan pada run ini
- Lingkungan: Docker Compose terisolasi, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test`
- SHA-256 `OwnerTenantAccessConformanceTest.php`: `6c6c3bfb5962d70285c306d2a26518fd6e514c46aba5291c56d99fb7bb838d48`
- SHA-256 `UserApiTest.php`: `90dbd14c8636b8b840f23eebeff68903b94122aa5350a9e5b93e043ca6e36ab3`
- `composer install` tanpa perubahan dependency: PASS
- `composer validate --no-check-publish`: PASS
- Pint: PASS, 139 file
- `OwnerTenantAccessConformanceTest`: 1 test / 2043 assertions — PASS
- `UserApiTest --filter=delegate_owner_role`: 1 test / 196 assertions — PASS
- Suite penuh: 316 test / 36098 assertions — PASS
- Database dev dan test berada di project/volume Compose berbeda

Owner dapat membaca profil warungnya, membuat/mencantumkan/mengubah kategori dan menu, membuat serta membaca list/detail transaksi penjualan dan pembelian, dan melihat kedua laporan. List/detail transaksi dibatasi ke `warung_id` pada token: data warung lain tidak muncul dan detailnya memberi 404. Response/request yang diperiksa dalam alur ini cocok dengan schema OpenAPI.

Owner dapat mendelegasikan role `owner` ke user lain pada warung yang sama. Token owner kedua dapat mengelola daftar user di warung tersebut dan tidak melihat user tenant lain. Tidak ada akses superadmin atau lintas-warung yang diberikan.

## Command

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner sh -lc 'php artisan test tests/Feature/OwnerTenantAccessConformanceTest.php'
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner sh -lc 'php artisan test tests/Feature/UserApiTest.php --filter=delegate_owner_role'
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner vendor/bin/pint --test
docker compose -f compose.test.yaml --project-name larissama-backend-test run --rm test-runner sh -lc 'composer install --no-interaction --prefer-dist && composer validate --no-check-publish && php artisan test --no-progress'
```

## Batas bukti

Run ini membuktikan jalur positif owner dan tenant boundary pada operasi yang dicakup, termasuk kedua laporan serta delegasi owner. Ini belum menguji seluruh kombinasi role, status HTTP, validasi, dan bentuk payload setiap operasi. Karena gate tersebut dan keputusan bisnis lain masih terbuka, seluruh operasi OpenAPI tetap `DRAFT`.
