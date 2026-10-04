# ACCESS-CATALOG-WRITE-REQUEST-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-103/104/202/203, subset T-API-02 dan D13

Commit kode: `226b332b9c63b92c40bf7ef038ff62383b26f99e` (checker; feature fixture di `bd8e83e78995c59f0fff9127ecda8f1b0cea7ebf`). Hasil final juga diulang pada HEAD merge `88a442bce492da0e202128995d98669d03eac3ef`, yang membawa perubahan `composer.lock` saja di atas slice ini.

Environment: PHP 8.3.35, Composer 2.10.3, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` dalam Compose test-only disposable. Stack database/network sudah dibersihkan.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/AdminWarungApiTest.php tests/Feature/AdminWarungManagementApiTest.php tests/Feature/UserApiTest.php tests/Feature/KategoriMenuApiTest.php tests/Feature/MenuApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner composer test
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Lima feature test admin/katalog | PASS, 34 test / 4307 assertions; tanpa warning |
| Suite `composer test` | PASS, 82 test / 10781 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Request body yang dicocokkan

`assertOperationRequestMatchesOpenApi()` membaca requestBody JSON operasi dari `docs/api/openapi.yaml` dan memeriksa payload test terhadap schema. Map yang diperiksa adalah variabel yang sama yang dikirim melalui `postJson()` atau `patchJson()`.

- `POST /admin/warungs`: provision warung dan owner.
- `PATCH /admin/warungs/{id}`: update metadata/status warung.
- `POST /users` dan `PATCH /users/{id}`: create/update user tenant.
- `POST /kategori-menus` dan `PATCH /kategori-menus/{id}`: create/update kategori.
- `POST /menus` dan `PATCH /menus/{id}`: create/update menu; `kategori_menu_id` dikirim sebagai string sesuai D13.

Checker menerima `writeOnly` sebagai anotasi OpenAPI dan memeriksa `minProperties` untuk object. Regression assertion membuktikan body kosong gagal schema `WarungUpdate`. Tidak ditemukan mismatch pada payload sukses yang diperiksa.

## Run awal dan batas bukti

Percobaan awal sebelum dukungan schema diperluas menghasilkan 28 test lulus / 5 gagal. Seluruh kegagalan berasal dari checker yang menolak keyword `writeOnly` pada password dan `minProperties` pada tiga schema PATCH; tidak ada kegagalan perilaku endpoint. Setelah perbaikan checker, focused suite lulus 34/4307 dan full suite lulus 82/10781 pada HEAD yang tercatat.

Checker memeriksa fixture payload yang diberikan test, bukan mengintersep request HTTP aktual, dan bukan validator OpenAPI umum. Cakupan hanya request positif terpilih pada delapan operasi serta regression checker body kosong; field opsional/semua variasi, request invalid, status lain, dan operasi lain belum dicakup. Tidak ada perubahan endpoint, schema, controller, DB, tenant/role policy, atau business behavior. Semua 28 operasi tetap `DRAFT`; T-API-02 penuh serta gate G1/G2 tetap terbuka.

Source hashes yang diuji:

- `tests/TestCase.php`: `241575803cebb61cd80009a4d4908838dd13ba0248aadec4ee763b0aef0c45d2`
- `tests/Feature/AdminWarungApiTest.php`: `7c3e63788832c11d2db184f97834929acabd31b1177850ff0fda562f1e07aa28`
- `tests/Feature/AdminWarungManagementApiTest.php`: `ed50c6eced8cb18af0a203d75bd006d0ff31c1da37204a061ed0fc008267c382`
- `tests/Feature/UserApiTest.php`: `f54d7b49693f5ac1fac06b1257f346f473115a2d4848d4f2ea48bae6774b0157`
- `tests/Feature/KategoriMenuApiTest.php`: `e8882f5fc61c347be1825d2163446b7170384cec8326dbda0d51dff11a9658cc`
- `tests/Feature/MenuApiTest.php`: `622d1a0b38bb21cdd880974a9cef3f04418ef346a767d32668119c2279d660e9`
