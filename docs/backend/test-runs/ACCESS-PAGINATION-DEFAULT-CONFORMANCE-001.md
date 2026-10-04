# ACCESS-PAGINATION-DEFAULT-CONFORMANCE-001

Tanggal: 2026-10-05 (Asia/Jakarta)

Task: BE-003/104/202/203/303/403, subset T-API-02/03 dan D13.

Commit kode yang diuji: `87156fdcfc8d8e30c53076ac98acf4bb1f4818eb`.

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` di Compose test-only disposable. Container database dan network dibersihkan setelah run.

Source hash:

- `tests/Feature/ApiListDefaultsConformanceTest.php`: `5e6c04412004b9b02013f7496f801f29422046479cd1552674ac9fa8954b0193`

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --filter=ApiListDefaultsConformanceTest --display-warnings
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Default list conformance | PASS, 6 test / 1718 assertions |
| Suite penuh | PASS, 173 test / 23728 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti default

Keenam route yang diuji tanpa query string adalah `GET /admin/warungs` sebagai superadmin, `GET /users` sebagai owner, serta `GET /kategori-menus`, `/menus`, `/penjualans`, dan `/pembelians` sebagai manager. Test memastikan parameter OpenAPI mendeklarasikan default `page=1`, `per_page=20`, dan sort: `nama` untuk warung/user/menu, `urutan` untuk kategori, serta `-tanggal` untuk penjualan/pembelian.

Semua response aktual memberi metadata `page=1`, `per_page=20`, dan `last_page=1`, serta cocok dengan schema OpenAPI. Urutan row cocok dengan sort default pada nilai primer berbeda. Fixture dibuat dalam urutan C/A/B agar urutan ID tidak dapat menyamarkan sort kolom. List user juga memuat owner aktor sebagai row keempat, sesuai scope endpoint.

Test hanya memakai data sintetis dan tidak mengubah API, controller, query, database, tenant, policy, role, atau dependencies. Semua 28 operasi tetap `DRAFT`; T-API-02/03 dan gate lain tetap terbuka.

`composer.lock` sudah berisi marker konflik Git sehingga bukan JSON valid. File itu tidak diubah dan `composer install` tidak dijalankan; runner memakai `vendor` yang sudah tersedia.
