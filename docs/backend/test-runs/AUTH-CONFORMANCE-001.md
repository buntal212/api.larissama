# AUTH-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-102

Test ID: subset T-AUTH-01/02/04/05 dan T-API-02/04

Commit yang diuji: `e89c2f0e90e68e0845b955999884d686f24ca5b4`

Status run: **PASS untuk response dan status yang diuji di bawah**

## Environment

- Docker Compose test-only, project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` disposable; fixture memakai `RefreshDatabase`.
- Container database dan network dihapus setelah run.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/AuthApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| `AuthApiTest.php` | PASS, 20 test / 2985 assertions; tanpa warning |
| Suite `composer test` | PASS, 81 test / 5386 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti conformance response runtime

Test menggunakan `assertOperationResponseMatchesOpenApi()` di `tests/TestCase.php` untuk membaca operasi/status, schema, dan `$ref` lokal dari `docs/api/openapi.yaml`, kemudian memeriksa response HTTP aktual.

| Operasi | Status yang response-nya dicocokkan |
| --- | --- |
| `POST /auth/login` | 200, 401, 403, 422, 429 |
| `GET /auth/me` | 200, 401, 403 |
| `POST /auth/logout` | 204 tanpa body, karena OpenAPI tidak mendefinisikan `content` untuk status itu |

Pemeriksaan ikut mencakup login empat role, identitas tanpa secret, expiry token, logout yang hanya mencabut bearer aktif, tanggal aktif lokal/NULL, akun atau warung nonaktif, serta limiter per username+IP. Status runtime response di atas cocok dengan schema OpenAPI yang berlaku.

Percobaan pertama gagal karena checker belum mendukung keyword schema `const` pada `token_type`. Checker kini membandingkan `const` dengan strict equality. Checker juga menegaskan body kosong jika response OpenAPI tidak memiliki schema body. Kegagalan awal adalah keterbatasan checker; tidak ditemukan mismatch response API pada run ini.

## Batas bukti

- Checker mendukung subset keyword schema yang dipakai response slice ini dan gagal eksplisit untuk keyword yang belum didukung. Ini bukan validator OpenAPI 3.1 umum.
- Request body/schema login belum dibandingkan otomatis. Login 400/500, `/auth/me` 400/500, serta response error `POST /auth/logout` belum dicocokkan dalam run ini.
- Keputusan D08 untuk timezone NULL/invalid dan D12 untuk normalisasi identitas tetap terbuka. Login, me, dan logout tetap berstatus `DRAFT`; tidak ada operasi yang diserahkan untuk integrasi frontend.
- Run ini tidak menutup T-API-01/02/03/04 penuh, G1, konfigurasi deployment CORS/HTTPS, atau base URL handoff.

## File dan hash

- `tests/TestCase.php`: `3d03df7d59b817689294d71aab2b692f4773675532b7a23c92096de03f19dd88`
- `tests/Feature/AuthApiTest.php`: `2872698228a2e59819bfdd1ab67296e1a7fa1346fc0f4ad673d36a2feca1eb3e`
