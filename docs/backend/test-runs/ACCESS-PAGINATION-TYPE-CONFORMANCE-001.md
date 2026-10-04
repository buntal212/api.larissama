# ACCESS-PAGINATION-TYPE-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-003/104/202/203/303/403, subset T-API-02/03 dan D13.

Commit kode yang diuji: `12aa8b69f92f4d314e4c54c29abe151d1ecf18b6`.

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` di Compose test-only disposable. Container database dan network dibersihkan setelah run.

Source hash:

- `tests/Feature/ApiPaginationQueryConformanceTest.php`: `abe7d4d4ce6e3040fecb0add838f2d48615cda072afc0ef89e189e0ea86db49c`

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/ApiPaginationQueryConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Feature test pagination | PASS, 31 test / 2101 assertions |
| Suite penuh | PASS, 113 test / 12966 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti penolakan tipe

Data provider membuat satu request per operasi/parameter dengan role yang sesuai. Setiap case memeriksa parameter OpenAPI bertipe `integer`, checker schema menolak string `abc`, request HTTP menghasilkan 422 yang cocok schema response, `code=VALIDATION_ERROR`, pesan menyatakan data tidak valid, dan errors menyebut field terkait dengan pesan validasi integer.

- `GET /admin/warungs?page=abc` dan `GET /admin/warungs?per_page=abc` dengan role superadmin.
- `GET /users?page=abc` dan `GET /users?per_page=abc` dengan role owner.
- `GET /kategori-menus`, `GET /menus`, `GET /penjualans`, dan `GET /pembelians`, masing-masing dengan `page=abc` serta `per_page=abc`, memakai role manager.

Tidak ditemukan mismatch pada dua belas kasus. Tidak ada perubahan endpoint, controller, request validator, business behavior, tenant scope, authorization, schema DB, atau dependencies.

## Batas bukti

Run membuktikan penolakan nilai non-integer `abc` pada enam GET list dan kesesuaian error 422 yang diuji; variasi numeric string lain, nilai query invalid/unknown lainnya, kombinasi filter/sort, seluruh status/role, serta conformance seluruh operasi masih di luar cakupan. Semua 28 operasi tetap `DRAFT`; T-API-02/03, G1, G2, G3, dan G4 tetap terbuka.

`composer.lock` sudah berisi marker konflik Git sehingga bukan JSON valid. File itu tidak diubah dan `composer install` tidak dijalankan; runner memakai `vendor` yang sudah tersedia. Suite dijalankan langsung dengan `php artisan test`.
