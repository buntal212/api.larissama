# ADMIN-WARUNG-MANAGEMENT-ACCEPTANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-103/BE-104

Test ID: T-ADM-03; subset T-TEN-01/02, T-RBAC-01, T-API-02/03/04

Commit yang diuji: `d554d5aa452001365054e493fcb6dcf510e7c8bf`

Status run: **PASS untuk cakupan acceptance yang dicatat di bawah**

## Environment

- Docker Compose test-only menggunakan `compose.test.yaml` dan service `test-runner`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` disposable; tidak memakai database development atau production.
- Feature test memakai `RefreshDatabase`; stack test dibersihkan sesudah run.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner sh -lc 'vendor/bin/pint --dirty --format agent'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/AdminWarungManagementApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| Acceptance admin warung | PASS, 4 test / 113 assertions; tanpa warning |
| Suite `composer test` | PASS, 79 test / 981 assertions; tanpa warning |
| Pembersihan stack | PASS, container DB dan network disposable dihapus |

## Bukti

- Superadmin dapat membuka daftar semua warung dengan pencarian, filter status aktif, sort allowlist, pagination, dan metadata yang sesuai. ID pada resource berupa string.
- Superadmin dapat membaca detail warung dengan exact resource field set yang diuji.
- Superadmin dapat mengubah metadata, timezone, dan status warung; nilai tersimpan sesuai request.
- ID tidak ada menghasilkan 404. `per_page=101`, sort tidak didukung, body PATCH kosong, dan tanggal berakhir sebelum tanggal mulai menghasilkan 422; row tetap tidak berubah pada input yang ditolak.
- Owner, manager, dan kasir mendapat 403 untuk list/detail/update; row target tetap utuh.
- Subset field sukses/error D13 (`data/meta` dan `code/message/errors/request_id`) diperiksa manual. Pemeriksaan ini bukan validator OpenAPI otomatis.

## Batas bukti

- T-ADM-03 lulus untuk skenario yang dicatat di TEST_PLAN dan di atas.
- Test ini tidak mencakup `GET /warung` profil tenant sendiri.
- Conformance request/response otomatis ke OpenAPI, seluruh matriks T-TEN/T-RBAC, rincian D04/D12, dan gate G1 masih terbuka.
- Seluruh operasi admin tetap `DRAFT`; belum siap untuk integrasi frontend live.

SHA-256:

- `tests/Feature/AdminWarungManagementApiTest.php`: `ce93152954b6c1ad51b87692f1aa150f2304c6c259044f584b4dae90740b6d9d`
