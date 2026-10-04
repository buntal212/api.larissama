# CATALOG-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-202/BE-203

Test ID: subset T-CAT-01, T-TEN-01/02/03, T-RBAC-01, T-API-02/04, D13

Commit yang diuji: `9075c351836263cff10490f3f31b64a17bafd921`

Status run: **PASS untuk response dan status yang diuji di bawah**

## Environment

- Docker Compose test-only, project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` disposable; feature test memakai `RefreshDatabase`.
- Container database dan network dihapus setelah run.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/KategoriMenuApiTest.php tests/Feature/MenuApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| `KategoriMenuApiTest.php` dan `MenuApiTest.php` | PASS, 17 test / 1573 assertions; tanpa warning |
| Suite `composer test` | PASS, 81 test / 6673 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti conformance response runtime

`tests/TestCase.php` mengambil operasi, status, schema response, dan `$ref` lokal dari `docs/api/openapi.yaml`. Test memasang assertion pada response HTTP yang sudah digunakan acceptance dan menguji schema untuk delapan operasi katalog:

| Operasi | Status yang response-nya dicocokkan |
| --- | --- |
| `GET /kategori-menus` dan `GET /menus` | 200, 422 |
| `POST /kategori-menus` dan `POST /menus` | 201, 403, 422 |
| `GET /kategori-menus/{id}` dan `GET /menus/{id}` | 200, 404 |
| `PATCH /kategori-menus/{id}` dan `PATCH /menus/{id}` | 200, 403, 404, 422 |

Acceptance kategori/menu tetap membuktikan tenant scope, manager-only writes, cashier active-only visibility, pagination/filter/sort, kategori silang tenant, dan field yang tidak tersedia. Run ini tidak menemukan mismatch response API.

Run pertama gagal di empat test karena response schema memakai keyword `default` pada field kategori/menu dan checker memperlakukan keyword itu sebagai tidak didukung. Checker kini menerima `default` sebagai anotasi metadata yang tidak membatasi nilai, lalu run terarah dan full suite lulus.

## Batas bukti

- Checker memvalidasi subset keyword JSON Schema yang digunakan pada response terpilih dan gagal eksplisit untuk keyword lain yang belum didukung. Ini bukan validator OpenAPI 3.1 umum.
- Request body/query schema belum dicocokkan otomatis. Status yang tidak tercantum di atas, termasuk 400, 401, 409, 429, dan 500, belum dibandingkan runtime untuk katalog.
- D04 role detail, D05, D06 untuk transaksi atas menu nonaktif, dan D16 gate belum ditutup. Seluruh operasi tetap `DRAFT`; run ini tidak menyerahkan katalog untuk integrasi frontend.
- Run ini tidak menutup T-API-01/02/03/04 penuh atau G2.

## File dan hash

- `tests/TestCase.php`: `8e4018a4310b0624e48ac819c2cc841bd458679da4ca271d7485fd5949b375e7`
- `tests/Feature/KategoriMenuApiTest.php`: `bfa5875d5a9b9dc5fce8009c4e059cde382c6eb79aed1bf798d41d4e2d3953c4`
- `tests/Feature/MenuApiTest.php`: `93d388c9a4724670a1cdaebb1090d66161c6651b00cb399e4e5a6d125c12f293`
