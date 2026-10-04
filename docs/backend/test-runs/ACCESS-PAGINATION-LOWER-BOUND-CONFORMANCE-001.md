# ACCESS-PAGINATION-LOWER-BOUND-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-003/104/202/203/303/403, subset T-API-02/03 dan D13.

Commit kode yang diuji: `f996502309650bffdc1dd194183945078f979175`.

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` di Compose test-only disposable. Container database dan network dibersihkan setelah run.

Source hash:

- `tests/Feature/ApiPaginationQueryConformanceTest.php`: `203878459903c3436a5e2faec8fbce9d5efac5a2db8b36af1a86ca5caab3ef5f`

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --test tests/Feature/ApiPaginationQueryConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/ApiPaginationQueryConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint targeted pada file test | PASS |
| Feature test pagination | PASS, 19 test / 1597 assertions |
| Suite penuh | PASS, 101 test / 12462 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti penolakan nilai di bawah batas

Data provider membuat satu request per operasi/parameter dengan role yang sesuai. Setiap case memeriksa parameter OpenAPI memiliki `minimum: 1`, schema menolak angka 0, response HTTP aktual berstatus 422 dan cocok schema response, `code` adalah `VALIDATION_ERROR`, pesan menyatakan data tidak valid, dan errors menyebut parameter yang diuji.

- `GET /admin/warungs?page=0` dan `GET /admin/warungs?per_page=0` dengan role superadmin.
- `GET /users?page=0` dan `GET /users?per_page=0` dengan role owner.
- `GET /kategori-menus`, `GET /menus`, `GET /penjualans`, dan `GET /pembelians`, masing-masing dengan `page=0` serta `per_page=0`, memakai role manager.

Tidak ditemukan mismatch pada dua belas kasus. Tidak ada perubahan endpoint, controller, validasi aplikasi, business behavior, tenant scope, authorization, schema DB, atau dependencies.

## Batas bukti

Run membuktikan penolakan nilai nol pada enam GET list dan kesesuaian error 422 yang diuji; nilai query invalid/unknown lainnya, semua kombinasi filter/sort, seluruh status/role, serta conformance seluruh operasi masih di luar cakupan. Semua 28 operasi tetap `DRAFT`; T-API-02/03, G1, G2, G3, dan G4 tetap terbuka.

`composer.lock` sudah berisi marker konflik Git sehingga bukan JSON valid. File itu tidak diubah dan `composer install` tidak dijalankan; runner memakai `vendor` yang sudah tersedia. Suite dijalankan langsung dengan `php artisan test`.
