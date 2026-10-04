# ACCESS-SORT-ALLOWLIST-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-003/104/202/203/303/403, subset T-API-02/03 dan D13.

Commit kode yang diuji: `d0d16f24e812123b2e046ea5896c0c9bc6040de6`.

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` di Compose test-only disposable. Container database dan network dibersihkan setelah run.

Source hash:

- `tests/Feature/ApiPaginationQueryConformanceTest.php`: `6e272175d781c8274e3427bd54fc3b3e6919d14e81c66603f89c4b38f07acb65`

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --filter=ApiPaginationQueryConformanceTest --display-warnings
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Feature test pagination dan sort | PASS, 37 test / 2395 assertions |
| Suite penuh | PASS, 119 test / 13260 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti allowlist

Enam kasus memakai value `sort=created_at`, yang bukan anggota enum pada operasi OpenAPI terkait. Checker memastikan tiap operasi memiliki enum dan menolak value tersebut. Request runtime memakai role endpoint yang sesuai dan menghasilkan HTTP 422 dengan schema error valid, `code=VALIDATION_ERROR`, pesan validasi standar, serta field `errors.sort`.

- `GET /admin/warungs` dengan role superadmin.
- `GET /users` dengan role owner.
- `GET /kategori-menus`, `GET /menus`, `GET /penjualans`, dan `GET /pembelians` dengan role manager.

Tidak ada mismatch pada enam kasus. Tidak ada perubahan endpoint, request validator, database, tenant scope, authorization, atau allowlist sort.

## Batas bukti

Run menguji satu value yang tidak didukung pada enam list; ia tidak membuktikan setiap enum valid, tiap kombinasi filter, seluruh role/status, atau seluruh operasi OpenAPI. Semua 28 operasi tetap `DRAFT`; T-API-02/03, G1, G2, G3, dan G4 tetap terbuka.

`composer.lock` sudah berisi marker konflik Git sehingga bukan JSON valid. File itu tidak diubah dan `composer install` tidak dijalankan; runner memakai `vendor` yang sudah tersedia. Suite dijalankan langsung dengan `php artisan test`.
