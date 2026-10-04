# ACCESS-SORT-VALID-ENUM-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-003/104/202/203/303/403, subset T-API-02/03 dan D13.

Commit kode yang diuji: `26d23b572bbc0baac0917709f06198a7881abe9f`.

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` di Compose test-only disposable. Container database dan network dibersihkan setelah run.

Source hash:

- `tests/Feature/ApiPaginationQueryConformanceTest.php`: `2ed60d5f2d66af7a740ce4ac68ed91429fcd8497dcb40345f49aa5190ff4faa4`

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --filter=ApiPaginationQueryConformanceTest --display-warnings
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Feature test pagination dan sort | PASS, 51 test / 3381 assertions |
| Suite penuh | PASS, 133 test / 14246 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti enum

Semua nilai enum diambil dari kontrak OpenAPI dan dipakai dalam query GET. Checker memastikan map query cocok dengan schema parameter, lalu request runtime memakai role yang sesuai menghasilkan HTTP 200 dengan response schema valid.

| Operasi | Nilai sort yang diuji |
| --- | --- |
| `GET /admin/warungs` | `nama`, `-nama` |
| `GET /users` | `nama`, `-nama` |
| `GET /kategori-menus` | `urutan`, `-urutan`, `nama`, `-nama` |
| `GET /menus` | `nama`, `-nama` |
| `GET /penjualans` | `-tanggal`, `tanggal` |
| `GET /pembelians` | `-tanggal`, `tanggal` |

Run mengonfirmasi 14 kombinasi terdokumentasi, tanpa mengubah endpoint, validator, query, database, tenant scope, atau authorization.

## Batas bukti

Cakupan menguji penerimaan setiap value enum dan response 200; fixture tidak membuktikan urutan row, arah urutan, tie-breaker id, setiap role/status, atau seluruh kombinasi filter. Semua 28 operasi tetap `DRAFT`; T-API-02/03, G1, G2, G3, dan G4 tetap terbuka.

`composer.lock` sudah berisi marker konflik Git sehingga bukan JSON valid. File itu tidak diubah dan `composer install` tidak dijalankan; runner memakai `vendor` yang sudah tersedia. Suite dijalankan langsung dengan `php artisan test`.
