# TRANSACTION-REPORT-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-305, BE-405

Test ID: T-API-02 (partial, dua operasi laporan; response 200/422)

Commit yang diuji: `3e411fb`

Status run: **PASS untuk field set D13 yang diuji**

## Environment

- Docker Compose project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` dari service `test-db`; `RefreshDatabase` mengisolasi migration dan fixtures. Tidak memakai development/production DB.
- Container DB dan Compose network dihapus setelah test selesai.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner sh -lc 'vendor/bin/pint --dirty --format agent'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/LaporanApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| Test laporan terarah | PASS, 4 test / 96 assertions; tanpa warning |
| Suite `composer test` | PASS, 25 test / 191 assertions; tanpa warning |
| Pembersihan stack | PASS, container DB dan network disposable dihapus |

## Bukti conformance parsial

Test membandingkan field set HTTP dengan schema response report dan `ApiError` di `docs/api/openapi.yaml`; ini assertion terarah dalam feature test, bukan validasi JSON Schema otomatis.

| Response | Assertions |
| --- | --- |
| Kedua report HTTP 200 | Top-level hanya `data`; data hanya berisi `period`, `jumlah_transaksi`, dan field total report; period hanya `date_from`, `date_to`, `timezone`; count bertipe integer dan nominal bertipe string decimal. Nilai juga diverifikasi pada boundary DST dan periode kosong. |
| Kedua report HTTP 422 | Top-level persis `code`, `message`, `errors`, `request_id`; code `VALIDATION_ERROR`; error menyebut field yang invalid; pesan berupa string; request_id berformat UUID. Tiga skenario invalid dijalankan pada masing-masing endpoint. |

Expected cocok dengan schema OpenAPI dan D13 yang disetujui user. Tidak ada perubahan runtime/API pada slice ini karena implementasi yang diuji sudah mengembalikan field set tersebut.

## Batas bukti

- Hanya dua operasi laporan, success 200, dan validation 422 yang dibandingkan. 401/403/404/429/500, endpoint lain, pagination, ID resource, seluruh nominal, serta request schema belum dicakup.
- Belum dijalankan validator OpenAPI terhadap seluruh response HTTP aktual; T-API-01/02/03/04 belum lulus menyeluruh.
- Semua operasi tetap DRAFT dan laporan belum siap menjadi handoff frontend.

SHA-256 `tests/Feature/LaporanApiTest.php`: `a8317569da5c21be381c54f8aaa8b98f74246313d70b0ef87559428a17a8673e`.
