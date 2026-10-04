# ACCESS-SORT-TIE-BREAKER-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-003/104/202/203/303/403, subset T-API-02/03 dan D13.

Commit kode yang diuji: `144cc994013acac3993daae755d562ffee5039b4`.

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` di Compose test-only disposable. Container database dan network dibersihkan setelah run.

Source hash:

- `tests/Feature/ApiSortTieBreakerTest.php`: `793e8fad82ee43daf46f29839e3f218725d759429ec3ea0a052c2ee47cde1c60`

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --filter=ApiSortTieBreakerTest --display-warnings
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Tie-breaker sort | PASS, 14 test / 3514 assertions |
| Suite penuh | PASS, 147 test / 17760 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti tie-breaker

Setiap case membuat dua row dengan kolom primary sort yang sama, kemudian meminta dua halaman (`per_page=1`). Ascending mengurutkan ID terkecil pada page 1; descending mengurutkan ID terbesar pada page 1. Page 2 berisi row satunya dan kedua response melaporkan `total=2`, `last_page=2`; schema response cocok OpenAPI.

| Operasi | Nilai sort yang diuji |
| --- | --- |
| `GET /admin/warungs` | `nama`, `-nama` |
| `GET /users` | `nama`, `-nama` |
| `GET /kategori-menus` | `urutan`, `-urutan`, `nama`, `-nama` |
| `GET /menus` | `nama`, `-nama` |
| `GET /penjualans` | `tanggal`, `-tanggal` |
| `GET /pembelians` | `tanggal`, `-tanggal` |

Semua 14 opsi dan 28 respons halaman lulus. Test hanya menggunakan data sintetis dan tidak mengubah API, query, controller, database, policy, tenant, atau role.

## Batas bukti

Cakupan membuktikan primary-key tie-breaker arah ascending/descending saat nilai sort utama sama dan hasil dibagi ke dua halaman. Ini tidak membuktikan perilaku pengurutan data dengan nilai utama berbeda, beban konkurensi saat data berubah antar request, semua role/status, atau seluruh OpenAPI contract. Semua 28 operasi tetap `DRAFT`; T-API-02/03, G1, G2, G3, dan G4 tetap terbuka.

`composer.lock` sudah berisi marker konflik Git sehingga bukan JSON valid. File itu tidak diubah dan `composer install` tidak dijalankan; runner memakai `vendor` yang sudah tersedia. Suite dijalankan langsung dengan `php artisan test`.
