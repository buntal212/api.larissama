# TRANSACTION-REPORT-PRECISION-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-305, BE-405

Test ID: T-REP-03

Commit yang diuji: `bc96a7b`

Status run: **PASS untuk batas timezone dan resolusi timestamp dalam test plan**

## Environment

- Docker Compose project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` dari service `test-db`; migration dan test memakai DB disposable, tanpa database development atau production.
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
| Test report terarah | PASS, 5 test / 100 assertions; tanpa warning |
| Suite `composer test` | PASS, 26 test / 195 assertions; tanpa warning |
| Pembersihan stack | PASS, container DB dan network disposable dihapus |

## Bukti presisi dan batas

Feature test membaca `information_schema.columns` pada database MySQL aktif:

| Tabel | Kolom | `DATETIME_PRECISION` |
| --- | --- | --- |
| `penjualans` | `tanggal` | `0` |
| `pembelians` | `tanggal` | `0` |

Dengan presisi kolom satu detik, `23:59:59` adalah waktu transaksi terakhir yang dapat disimpan pada suatu hari. Fixture Jakarta dan New York memasukkan detik terakhir hari lokal (masing-masing batas UTC yang sudah diuji) dan mengecualikan record tepat pada `00:00:00` UTC hari berikutnya. Kasus New York menguji hari DST 23 jam. Hasil report hanya menjumlahkan record pada batas awal inklusif dan akhir eksklusif.

T-REP-03 lulus untuk timezone, batas, dan presisi yang dinyatakan pada matriks test. D08 tetap PARTIAL karena penolakan zona NULL/invalid dalam auth serta kebijakan backdate/future date masih menunggu keputusan/bukti lanjutan. Ini bukan conformance penuh OpenAPI atau penerimaan G3/G4; operasi report masih DRAFT.

SHA-256 `tests/Feature/LaporanApiTest.php`: `b43d9717f4cc64babf836ddaa7e64ceb666d92d7b3859e3535146ce9dcadbed5`.
