# ADMIN-USER-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-103/BE-104

Test ID: subset T-API-02/04, T-ADM-01/02, T-TEN-01/02/03, T-RBAC-01

Commit yang diuji: `bad42b6affecce8d61a8669f14d441b7cccde8f8`

Status run: **PASS untuk response dan status yang diuji di bawah**

## Environment

- Docker Compose test-only, service `test-runner`, database disposable `larissama_test`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Feature test memakai `RefreshDatabase`; test atomicity memasang dan menghapus trigger sementara dalam database uji.
- Container database dan network dibersihkan setelah run.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/AdminWarungApiTest.php tests/Feature/ProvisionWarungAtomicityTest.php tests/Feature/UserApiTest.php tests/Feature/AdminWarungManagementApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| Acceptance/conformance terarah | PASS, 17 test / 1902 assertions; tanpa warning |
| Suite `composer test` penuh | PASS, 81 test / 2631 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti conformance runtime

Pemeriksa response OpenAPI berada di `tests/TestCase.php`. Ia membaca operation, status HTTP, schema, dan `$ref` lokal dari `docs/api/openapi.yaml`, lalu mencocokkan JSON aktual dengan subset schema yang didukung.

- `POST /admin/warungs`: response schema status 201, 403, 422, dan 500 diuji. Test juga membuktikan owner terikat pada warung, password di-hash dan tidak dikirim, serta rollback saat insert owner gagal.
- `GET /users`: response schema status 200 dan 403 diuji; daftar tetap terbatas pada tenant owner.
- `POST /users`: response schema status 201, 403, dan 422 diuji; user hanya dapat dibuat oleh owner untuk tenant sendiri dan field `warung_id`/role superadmin ditolak.
- `GET /users/{id}`: response schema status 200 dan 404 diuji; user tenant lain tidak terbaca.
- `PATCH /users/{id}`: response schema status 200, 404, dan 422 diuji; update tidak dapat memindahkan tenant atau menaikkan role.
- Run turut mengulang conformance admin warung dari `ADMIN-WARUNG-CONFORMANCE-001` untuk memastikan assertion helper tetap lulus setelah dipindah ke base test case.
- Percobaan awal gagal karena validator belum mendukung `format: email` pada schema User. Validator ditambah dukungan email; rerun lulus. Error `errors` kosong sudah berupa object `{}` sesuai D13 sejak perbaikan sebelumnya.

## Batas bukti

- Validator ini hanya mendukung kata kunci schema yang digunakan response di atas dan gagal eksplisit pada keyword yang belum didukung. Ini bukan validator OpenAPI 3.1 umum.
- Request body/query schema, 401/409/429, detail/patch user 403, user endpoint status lain, profil `/warung`, seluruh matriks T-TEN/T-RBAC, dan seluruh 28 operasi belum dicocokkan otomatis.
- T-API-02/04 dan G1 belum lengkap; seluruh operasi tetap `DRAFT` dan belum siap integrasi frontend live.

## File dan hash

- `tests/TestCase.php`: `255653cfe769abd62d418bd1f00b91305d8ff0aa0c28b1da7c37f4f13e61ec00`
- `tests/Feature/AdminWarungApiTest.php`: `c968cfb375216c66126fcdeec40d1531fcba7d2cbe7e358966a8f8742e5d9f72`
- `tests/Feature/ProvisionWarungAtomicityTest.php`: `4a07836c75c501e4fbac97e30cb9bf246fef92d545e773269308b1fac24cec36`
- `tests/Feature/UserApiTest.php`: `0e80dd6abe1789878e213783fed54b5e5a35d4ef15be46717950d8b16a041a41`
