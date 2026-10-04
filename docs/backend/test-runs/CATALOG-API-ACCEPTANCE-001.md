# CATALOG-API-ACCEPTANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-202/BE-203

Test ID: T-CAT-01; bagian T-CAT-02; subset T-TEN-01/02/03, T-RBAC-01, T-API-02/03/04

Commit yang diuji: `1dc9cf302425ef8d440b6724ebfc25da84abbf21`

Status run: **PASS untuk cakupan yang diuji; T-CAT-02 tetap parsial terkait D06**

## Environment

- Docker Compose project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` disposable; tidak memakai database development atau production.
- Fixture dibuat lewat factory dan diisolasi `RefreshDatabase`.
- Container DB dan Compose network dihapus setelah run.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner sh -lc 'vendor/bin/pint --dirty --format agent'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/KategoriMenuApiTest.php tests/Feature/MenuApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| Acceptance kategori/menu | PASS, 17 test / 286 assertions; tanpa warning |
| Suite `composer test` | PASS, 75 test / 868 assertions; tanpa warning |
| Pembersihan stack | PASS, container DB dan network disposable dihapus |

## Bukti

- Manager membuat, membaca daftar/detail, dan memperbarui kategori; field resource cocok dengan ID string dan status boolean. List kategori menguji pencarian, filter aktif, sort allowlist, halaman 1/2, dan metadata.
- Manager membuat, membaca daftar/detail, dan memperbarui menu; resource mengirim harga sebagai decimal string dan tidak memuat `harga_modal` atau `gambar`. Test memeriksa pencarian, filter kategori/status, sort, pagination halaman 1–3, dan metadata.
- Manager tidak dapat membaca atau memperbarui kategori/menu warung lain; kedua detail/update menghasilkan 404 dan row target tidak berubah.
- Manager menolak ID tenant pada kategori dan field yang tidak didukung pada menu. Kategori menu dari tenant lain ditolak 422 saat create/update. Kode menu sama diterima pada tenant berbeda tetapi duplikat dalam satu tenant mendapat 422.
- Kasir hanya mendapat kategori aktif dan menu aktif dalam kategori aktif; permintaan `aktif=false` tidak memperluas hasil. Detail nonaktif mendapat 404, dan filter kategori nonaktif mendapat 422.
- Hanya manager dapat membuat/memperbarui kategori/menu. Owner, kasir, dan superadmin mendapat 403. Harga yang tidak memenuhi format decimal ditolak 422.
- Foreign key membatasi hard delete kategori yang masih memiliki menu.
- Error response yang diuji memakai exact top-level D13 `code/message/errors/request_id`, termasuk status 403/404/422. Success resource dan pagination diperiksa manual terhadap bentuk schema.

## Batas bukti

- T-CAT-01 lulus untuk skenario create/read/update/filter/tenant/pricing katalog yang direncanakan.
- T-CAT-02 parsial: kategori/menu dapat dinonaktifkan, query kasir menyembunyikan item nonaktif, dan kategori berisi menu tidak dapat dihapus. Dampak menu/kategori nonaktif pada pencatatan transaksi tidak diuji karena D06 belum final.
- Test ini tidak menutup T-DB-01/03, matriks penuh T-TEN/T-RBAC, atau validator runtime T-API-01/02/03/04. Field set diperiksa manual dan halaman berhasil, tetapi semua kombinasi query/status belum diverifikasi otomatis ke OpenAPI.
- Seluruh operasi katalog tetap `DRAFT`; G2 dan keputusan D04/D06 tetap terbuka.

SHA-256:

- `tests/Feature/KategoriMenuApiTest.php`: `5db725f5a72535f6434b96af9d1d3d42b930eda5889fabf2f713f92b841abef2`
- `tests/Feature/MenuApiTest.php`: `a1092fc020583da7ce51f47a4bdc1368d83dbb79a6a565d4f2626a23f951c02f`
