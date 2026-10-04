# TRANSACTION-REPORT-AGGREGATE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-405

Test ID: T-REP-02

Commit yang diuji: `a9b4637`

Status run: **PASS untuk count/sum header, kapasitas agregat, dan paging independence**

## Environment

- Docker Compose project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` disposable; tidak memakai database development atau production.
- Container DB dan Compose network dihapus setelah test selesai.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner sh -lc 'vendor/bin/pint --dirty --format agent'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/PembelianApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer test'
docker compose -f compose.test.yaml down --remove-orphans
```

| Check | Hasil |
| --- | --- |
| Pint | PASS |
| `PembelianApiTest.php` | PASS, 9 test / 57 assertions; tanpa warning |
| Suite `composer test` | PASS, 27 test / 213 assertions; tanpa warning |
| Pembersihan stack | PASS, container DB dan network disposable dihapus |

## Bukti agregasi

- Dataset report standar berisi 2 header dan 3 detail; report menghasilkan count `2` dan total header `245000.00`, sehingga baris detail tidak menggandakan sum/count.
- Dataset kapasitas membuat dua header masing-masing `6000000000000.00`. Masing-masing berada di bawah batas satu header `DECIMAL(15,2)`, tetapi sum `12000000000000.00` melampaui kapasitas satu header.
- Sebelum pembacaan list, report mengembalikan count `2` dan total string eksak `12000000000000.00`.
- Pembacaan `/api/v1/pembelians?page=1&per_page=1` dan halaman 2 masing-masing mengembalikan satu ID berbeda dengan `meta.total=2`. Report sesudahnya identik dengan response awal.

Hasil membuktikan query laporan membaca serta menjumlah header tenant secara independen dari pagination daftar, tanpa kehilangan presisi nominal lintas header.

## Batas bukti

- T-REP-02 lulus untuk skenario detail count, batas kapasitas header, dan independensi pagination yang direncanakan.
- Ini tidak menutup D11 mengenai koreksi/pembatalan pembelian atau conformance seluruh operasi pembelian. Seluruh operasi tetap DRAFT dan G4 belum lulus.

SHA-256 `tests/Feature/PembelianApiTest.php`: `028ac4859ba14d95a90ed40945d3d660fd2db5fcd07c9bee0bc16998ed5e8a49`.
