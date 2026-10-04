# ACCESS-PAGINATION-MAX-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-003/104/202/203/303/403, subset T-API-02/03 dan D13.

Commit kode yang diuji: `40e7c87ce2c83ae6d6f94961331c42c04d3088fa`
Commit test dalam slice: `bae0367`, `0b0b14a`, `40e7c87`.

Environment: PHP 8.3.35, Composer 2.10.3, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` dalam Compose test-only disposable. Stack database dan network sudah dibersihkan.

Source hash:

- `tests/Feature/ApiPaginationQueryConformanceTest.php`: `02f2697ca5ab071ea744cc0d825c52f65d50bb65541c48a8e365ede35266daf2`

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/ApiPaginationQueryConformanceTest.php'
docker compose -f compose.test.yaml run --rm test-runner composer test
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Feature test pagination | PASS, 1 test / 817 assertions |
| Suite `composer test` | PASS, 83 test / 11682 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti batas pagination

Satu test menggunakan query fixture `per_page=100` yang dicocokkan dengan parameter GET OpenAPI, lalu map yang sama membentuk URL HTTP. Keenam endpoint merespons 200, lolos checker schema response, dan mengembalikan `meta.per_page=100` serta `meta.page=1`:

- `GET /admin/warungs` dengan role superadmin.
- `GET /users` dengan role owner.
- `GET /kategori-menus` dan `GET /menus` dengan role manager.
- `GET /penjualans` dan `GET /pembelians` dengan role manager.

Tidak ditemukan mismatch pada query batas maksimum dan response yang diuji. Tidak ada perubahan endpoint, controller, request validation, database, business behavior, authorization, atau tenant scope.

## Catatan run awal

Percobaan awal mendapat 403 pada request kedua, `GET /users`, ketika satu test mengganti bearer identity beberapa kali. Pemeriksaan menunjukkan guard auth Laravel yang di-cache oleh test harness masih membawa identity request sebelumnya. Test kemudian memanggil `Auth::forgetGuards()` sebelum berganti token; ini hanya memperbaiki isolasi request dalam harness dan tidak mengubah policy atau kode auth produksi. Rerun terarah dan suite penuh lulus.

## Batas bukti

Checker memvalidasi map query fixture yang sama dengan map pembangun URL, bukan mengintersep request object Laravel aktual. Response schema diperiksa dari HTTP response aktual. Run ini hanya membuktikan nilai batas 100 pada enam GET list tersebut; nilai invalid/unknown, semua kombinasi filter/sort, semua status/role, dan conformance seluruh operasi belum dicakup. Seluruh 28 operasi tetap `DRAFT`; T-API-02/03, G1, G2, G3, dan G4 tetap terbuka.
