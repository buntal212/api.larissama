# ACCESS-CATALOG-QUERY-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-103/104/202/203, subset T-API-02/03 dan D13

Commit yang diuji: `0c15ff51051152983d3645dc7a317cae64cea9b0`

Environment: PHP 8.3.35, Composer 2.10.3, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` dalam Compose test-only disposable. Stack database/network sudah dibersihkan.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/AdminWarungManagementApiTest.php tests/Feature/UserApiTest.php tests/Feature/KategoriMenuApiTest.php tests/Feature/MenuApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner composer test
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Empat feature test akses/katalog | PASS, 28 test / 3808 assertions; tanpa warning |
| Suite `composer test` | PASS, 81 test / 10554 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti query yang dicocokkan

`assertOperationQueryMatchesOpenApi()` membaca parameter query operasi dari `docs/api/openapi.yaml`, menyelesaikan `$ref`, memeriksa nama parameter dan required, mengubah scalar ter-serialize sesuai tipe OpenAPI, lalu menjalankan schema checker yang tersedia. Query fixture yang diperiksa juga digunakan untuk membangun URL feature test.

- `GET /admin/warungs`: query terpilih `page`, `per_page`, `sort`, `q`, dan `aktif`.
- `GET /users`: query terpilih `page`, `per_page`, dan `sort`.
- `GET /kategori-menus`: query terpilih `page`, `per_page`, `sort`, `q`, serta `aktif=true/false`.
- `GET /menus`: query terpilih `q`, `kategori_menu_id` sebagai ID string, `per_page`, `sort`, halaman 1/2/3, dan `aktif=false`.

Tidak ditemukan mismatch pada query terpilih. Tidak ada perubahan controller, DB, tenant scope, authorization, filter/business behavior, atau operasi tulis.

## Batas bukti

Checker memvalidasi map fixture sebelum request, bukan mengambil request object Laravel aktual. Ia mendukung tipe/schema yang digunakan oleh parameter tersebut; ini bukan validator OpenAPI umum. Run tidak menguji seluruh nilai/kombinasi, `per_page=100`, parameter invalid/unknown sebagai conformance request, semua status atau operasi lain. Uji Laravel yang sudah ada untuk validasi input invalid tetap terpisah dari checker schema. Semua 28 operasi tetap `DRAFT`; T-API-02/03 penuh, G1, dan G2 tetap terbuka.

Source hashes yang diuji:

- `tests/Feature/AdminWarungManagementApiTest.php`: `82835e1bd0b330448d54695934e7174f4a7bcb05ebe773d8fa63c0f0d4d62a99`
- `tests/Feature/UserApiTest.php`: `fedc4c335340e6d9054599e7f306e30085fbadf8e990ae5f8313142fb1d0558a`
- `tests/Feature/KategoriMenuApiTest.php`: `b9840b2e650d5bf8623f0cefcb049430739a439fa290024c463ed108e2701eb0`
- `tests/Feature/MenuApiTest.php`: `14709ee1cb4a9ab8409be485278a9f2907f7313efcd1d616f7caceb2f39a87cd`
