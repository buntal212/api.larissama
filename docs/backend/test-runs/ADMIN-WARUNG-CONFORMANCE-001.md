# ADMIN-WARUNG-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-103/BE-104

Test ID: subset T-API-02/03/04, T-ADM-03, T-TEN-01/02, T-RBAC-01

Commit yang diuji: `278e27c4635932a872512b4419e8af6af445abc4`

Status run: **PASS untuk response dan status yang diuji di bawah**

## Environment

- Docker Compose test-only, service `test-runner`, database disposable `larissama_test`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Feature test memakai `RefreshDatabase`; container database dan network dibersihkan setelah run.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/AdminWarungManagementApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| Test terarah `AdminWarungManagementApiTest` | PASS, 5 test / 976 assertions; tanpa warning |
| Suite `composer test` penuh | PASS, 80 test / 1844 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti conformance runtime

Test membaca operasi, status HTTP, dan response schema langsung dari `docs/api/openapi.yaml`; `$ref` lokal diselesaikan sebelum payload JSON dibandingkan dengan schema.

- `GET /admin/warungs`: response 200 untuk list, search, filter, pagination; 403 untuk role tenant; 422 untuk `per_page` dan sort invalid.
- `GET /admin/warungs/{id}`: response 200 detail, 403 untuk role tenant, 404 untuk ID yang tidak ada.
- `PATCH /admin/warungs/{id}`: response 200 update, 403 untuk role tenant, 422 untuk update kosong dan rentang tanggal invalid.
- Validator mengecek required fields, field tambahan, tipe, nullability, pattern, enum, format date/date-time, batas numerik/string/array, item, `anyOf`, dan `additionalProperties` untuk schema yang dilalui operasi tersebut. Kata kunci schema yang tidak didukung menghasilkan kegagalan eksplisit.
- Test validator sendiri membuktikan bahwa properti tak terdokumentasi ditolak.
- Percobaan pertama gagal karena `ApiErrorResponse` mengubah array PHP kosong menjadi `errors: []`; schema D13 menetapkan object. `ApiErrorResponse` sekarang mengirim `errors: {}` saat kosong. Response error validasi dengan field tetap berupa object berisi array pesan.
- Error tetap memakai field set `code`, `message`, `errors`, `request_id`; tes terarah memverifikasi code, tipe, UUID, dan field validation yang relevan.

## Batas bukti

- Hanya response body untuk operasi/status yang tercantum di atas yang dicocokkan otomatis. Validator ini mendukung subset keyword schema yang dipakai di response tersebut; bukan validator OpenAPI 3.1 umum.
- Request body/query schema, status 400/401/429/500, POST provisioning, user, profil `/warung`, seluruh API lain, dan semua kombinasi role belum tercakup dalam run ini.
- T-API-02/03/04 untuk seluruh sistem dan gate G1 belum lulus. Semua operasi OpenAPI tetap `DRAFT` dan belum siap integrasi frontend live.

## File dan hash

- `tests/Feature/AdminWarungManagementApiTest.php`: `d0d2f5df4119354d61a6ef69b75717642fb3abf7d9af49a9fbc08b1f3346dce6`
- `app/Http/Responses/ApiErrorResponse.php`: `3ec25b5b1b030ba78fc3354eecad687584c3e0a18af6f08ea05041ffa8644242`
