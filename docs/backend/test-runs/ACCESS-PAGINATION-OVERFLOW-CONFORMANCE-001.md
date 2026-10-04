# ACCESS-PAGINATION-OVERFLOW-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-003/104/202/203/303/403, subset T-API-02/03 dan D13.

Commit kode yang diuji: `9570255e2effc925a90dc5d1ae8982f8faead96b`.

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` di Compose test-only disposable. Container database dan network dibersihkan setelah run.

Source hash:

- `tests/Feature/ApiPaginationQueryConformanceTest.php`: `dc8c0db8cb49314aae08b4049629ef0742ecba5cf1110fb1f283dba0ab78562e`

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --test tests/Feature/ApiPaginationQueryConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/ApiPaginationQueryConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint targeted pada file test | PASS |
| Feature test pagination | PASS, 7 test / 1093 assertions |
| Suite penuh | PASS, 89 test / 11958 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti penolakan overflow

Data provider membuat satu request per operasi dan memakai role yang sesuai. Setiap case memeriksa parameter `per_page` pada operasi OpenAPI memiliki `maximum: 100`, schema OpenAPI menolak nilai 101, response HTTP aktual berstatus 422 dan cocok dengan schema response, `code` adalah `VALIDATION_ERROR`, pesan menyatakan data tidak valid, dan errors memuat field `per_page` serta batas 100.

- `GET /admin/warungs?per_page=101` dengan role superadmin.
- `GET /users?per_page=101` dengan role owner.
- `GET /kategori-menus?per_page=101` dan `GET /menus?per_page=101` dengan role manager.
- `GET /penjualans?per_page=101` dan `GET /pembelians?per_page=101` dengan role manager.

Tidak ditemukan mismatch pada enam kasus. Tidak ada perubahan endpoint, controller, validasi aplikasi, business behavior, tenant scope, authorization, schema DB, atau dependencies.

## Batas bukti

Run membuktikan penolakan nilai 101 pada enam GET list dan kesesuaian error 422 yang diuji; nilai query invalid/unknown lainnya, semua kombinasi filter/sort, seluruh status/role, serta conformance seluruh operasi masih di luar cakupan. Semua 28 operasi tetap `DRAFT`; T-API-02/03, G1, G2, G3, dan G4 tetap terbuka.

`composer.lock` sudah berisi marker konflik Git sehingga bukan JSON valid. File itu tidak diubah dan `composer install` tidak dijalankan; runner memakai `vendor` yang sudah tersedia. Suite dijalankan langsung dengan `php artisan test`.
