# ADMIN-API-ACCEPTANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-103/BE-104

Test ID: T-ADM-01/02; subset T-TEN-01/02/03, T-RBAC-01, T-API-02/04

Commit yang diuji: `7fed9ae0cb7807da9a203c738a9b0f74bdf34d1e`

Status run: **PASS untuk cakupan acceptance yang dicatat di bawah**

## Environment

- Docker Compose project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` disposable; tidak memakai database development atau production.
- Fixture feature test memakai `RefreshDatabase`; test atomicity membuat lalu menghapus trigger MySQL sementara dan membersihkan fixture.
- Container DB dan Compose network dihapus setelah run.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner sh -lc 'vendor/bin/pint --dirty --format agent'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/AdminWarungApiTest.php tests/Feature/ProvisionWarungAtomicityTest.php tests/Feature/UserApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| Acceptance admin/user | PASS, 11 test / 139 assertions; tanpa warning |
| Suite `composer test` | PASS, 58 test / 582 assertions; tanpa warning |
| Pembersihan stack | PASS, container DB dan network disposable dihapus |

## Bukti

- Superadmin menerima 201 saat membuat warung beserta owner. Resource memuat field yang diharapkan; `id` string, owner mempunyai role `owner` dan `warung_id` yang sama dengan warung; password tidak keluar di response dan tersimpan sebagai hash.
- Owner, manager, dan kasir mendapat 403 ketika mencoba provisioning warung; tidak ada warung atau user tambahan.
- Test atomicity memasang trigger yang menggagalkan insert owner sesudah action membuat warung. API memberi 500 generik tanpa pesan trigger, dan database tidak menyisakan warung maupun owner.
- Owner dapat membuat manager dalam tenant sendiri; response tanpa password dan DB menyimpan hash. Daftar hanya berisi user tenant itu, detail terbaca, dan update dapat mengubah nama/role ke kasir.
- ID user milik warung lain menghasilkan 404 pada detail dan update; row user target tetap utuh.
- Payload create/update dengan `warung_id` atau role `superadmin` menghasilkan 422 dan tidak membuat/memindahkan/menaikkan user.
- Manager, kasir, dan superadmin tidak dapat memakai endpoint daftar atau create user owner.
- Success resource dan error envelope diuji manual terhadap subset D13. Error yang dicakup memiliki exact top-level `code/message/errors/request_id` dan UUID `request_id`.

## Batas bukti

- T-ADM-01/02 lulus berdasarkan acceptance yang tertulis di TEST_PLAN dan skenario di atas.
- Test belum mencakup GET daftar/detail atau PATCH `/admin/warungs`, maupun `GET /warung`.
- Pagination/filter/sort user dan seluruh 28 operasi belum dicocokkan otomatis ke OpenAPI; test ini bukan T-API-01/02/03/04 menyeluruh.
- Matriks role lintas seluruh fitur, rincian izin D04, normalisasi identitas/migrasi lama D12, serta G1 masih terbuka.
- Seluruh operasi terkait tetap `DRAFT` dan belum siap integrasi frontend live.

SHA-256:

- `tests/Feature/AdminWarungApiTest.php`: `6af386669e0e1ed54ef1e4fea6a9413489a7ab0d394af6a470286d0e2489c432`
- `tests/Feature/ProvisionWarungAtomicityTest.php`: `292a2536408dfa89e3f044f94fa55cfe2e523f6a95e4832533e1552df5788b71`
- `tests/Feature/UserApiTest.php`: `f1f2107c4091929f724ea5459e4c92c174f18f787d0b285f52f2ed1e03731f2c`
