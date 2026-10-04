# ACCESS-SORT-ORDERING-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-003/104/202/203/303/403, subset T-API-02/03 dan D13.

Commit kode yang diuji: `f0880178c0a2088bf9f2202c11b2b11276e383dc`.

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` di Compose test-only disposable. Container database dan network dibersihkan setelah run.

Source hash:

- `tests/Feature/ApiSortOrderingConformanceTest.php`: `67d4dfedf532c251778a1833d2cfc690bbf0c96207577e3fd4d4137d4f07039c`

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --filter=ApiSortOrderingConformanceTest --display-warnings
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Sort ordering conformance | PASS, 14 test / 3492 assertions |
| Suite penuh | PASS, 167 test / 22010 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti urutan

Enam list yang diperiksa adalah `GET /admin/warungs` sebagai superadmin, `GET /users` sebagai owner, serta `GET /kategori-menus`, `/menus`, `/penjualans`, dan `/pembelians` sebagai manager. Seluruh 14 pasangan kolom/arah sort pada OpenAPI diuji dengan tiga row yang nilainya berbeda pada kolom sort utama. Query dan response setiap request dicocokkan dengan schema OpenAPI.

Urutan insert fixture sengaja `C`, `A`, `B`, sementara nilai kolom sort utama terurut `A`, `B`, `C`; jadi hasil benar tidak bisa dicapai hanya dengan mengurutkan ID. Sort ascending mengembalikan row menurut nilai kolom utama dari rendah ke tinggi dan opsi `-...` membalik urutan. Semua 14 opsi menghasilkan urutan yang diharapkan.

Test hanya membaca daftar dengan fixture sintetis. Tidak ada perubahan API, controller, query, database, tenant, filter, policy, role, atau dependencies. Semua 28 operasi tetap `DRAFT`; T-API-02/03 dan gate lain tetap terbuka.

`composer.lock` sudah berisi marker konflik Git sehingga bukan JSON valid. File itu tidak diubah dan `composer install` tidak dijalankan; runner memakai `vendor` yang sudah tersedia.
