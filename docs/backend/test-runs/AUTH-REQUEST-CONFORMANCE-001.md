# AUTH-REQUEST-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-102, subset T-AUTH-01 dan T-API-02/D13

Commit kode: `ec43d1be34081903b14df23ac4a775bdd27345f1`.

Environment: PHP 8.3.35, Composer 2.10.3, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` dalam Compose test-only disposable. Database dan network Compose sudah dibersihkan.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/AuthApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner composer test
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| `AuthApiTest` | PASS, 20 test / 3069 assertions |
| `composer test` | PASS, 82 test / 10865 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Request body yang dicocokkan

`assertOperationRequestMatchesOpenApi()` mencocokkan map payload login dengan `requestBody` JSON `POST /auth/login` (`LoginRequest`). Tujuh body schema-valid diperiksa: login sukses dengan empat role (superadmin, owner, manager, kasir), username terdaftar dengan password salah, dan username tak dikenal dengan password salah. Map yang diperiksa adalah variabel yang sama yang dikirim dengan `postJson()`.

Tidak ditemukan mismatch. Tidak ada perubahan controller, perilaku autentikasi/token/rate limit, schema OpenAPI, atau database.

## Batas bukti

Checker memvalidasi map fixture yang diberikan test; ia tidak mengintersep payload HTTP dan bukan validator OpenAPI umum. Body kosong yang menghasilkan 422 memang invalid terhadap `LoginRequest` dan tidak dicap schema-conformant. Variasi batas string, field tambahan, request/status lain, serta keputusan D08/D12 belum ditutup. Response runtime login 200/401/403/422/429, `/auth/me` 200/401/403, dan logout 204 sudah dicatat terpisah pada `AUTH-CONFORMANCE-001`. Semua 28 operasi tetap `DRAFT`; T-API-02 penuh dan gate G1 masih terbuka.

Source hash yang diuji:

- `tests/Feature/AuthApiTest.php`: `3b75a5ea02d078efe9baba6aaa6112e7d94daaba5bea113823404ad9375e9c18`
