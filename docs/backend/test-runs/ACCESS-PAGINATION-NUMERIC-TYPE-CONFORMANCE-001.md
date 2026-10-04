# ACCESS-PAGINATION-NUMERIC-TYPE-CONFORMANCE-001

Tanggal: 2026-10-05 (Asia/Jakarta)

Task: BE-003/104/202/203/303/403, subset T-API-02/03 dan D13.

Commit kode yang diuji: `5a16cec47b4dfabcbe5d82151230aefaa2d150ac`.

Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` di Compose test-only disposable. Container database dan network dibersihkan setelah run.

Source hash:

- `tests/Feature/ApiPaginationNumericTypeConformanceTest.php`: `f4ab03fb6c39054d4af2851ae0d05158255c26788433289641aeb8b22a0d71f7`

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --filter=ApiPaginationNumericTypeConformanceTest --display-warnings
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Numeric pagination conformance | PASS, 36 test / 1512 assertions |
| Suite penuh | PASS, 209 test / 25240 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti penolakan nilai

Pada enam GET list warung, user, kategori, menu, penjualan, dan pembelian, ketiga representasi `1.0`, `1.5`, dan `1e2` dikirim terpisah ke `page` dan `per_page` (36 request). Parameter OpenAPI bertipe integer menolak masing-masing serialisasi. Runtime mengembalikan HTTP 422 dengan `code=VALIDATION_ERROR`, response cocok schema OpenAPI, dan `errors` menunjuk parameter yang diuji.

Test hanya memakai autentikasi dan fixture sintetis; tidak mengubah validator, API, controller, database, tenant, role, atau dependencies. Semua 28 operasi tetap `DRAFT`; T-API-02/03 dan gate lain tetap terbuka.

`composer.lock` sudah berisi marker konflik Git sehingga bukan JSON valid. File itu tidak diubah dan `composer install` tidak dijalankan; runner memakai `vendor` yang sudah tersedia.
