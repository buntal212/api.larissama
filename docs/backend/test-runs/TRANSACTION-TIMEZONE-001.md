# TRANSACTION-TIMEZONE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-305, BE-405

Test ID: T-REP-03 (PARTIAL)

Commit yang diuji: `89c74de`

Status run: **PASS untuk skenario DST yang diuji**

## Environment

- Docker Compose project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` dari service `test-db`; tidak membuka port host atau memakai volume persisten. `RefreshDatabase` menjalankan fixture dan migration pada DB disposable ini.
- Tidak menggunakan database development atau production. Container database dan network dihentikan serta dihapus setelah test selesai.

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
| Test laporan terarah | PASS, 2 test / 8 assertions; tanpa warning |
| Suite `composer test` | PASS, 23 test / 103 assertions; tanpa warning |
| Pembersihan stack | PASS, container DB dan network disposable dihapus |

## Bukti skenario

PHP timezone data pada `America/New_York` menghitung tanggal lokal 2026-03-08 sebagai jendela UTC `[2026-03-08 05:00:00Z, 2026-03-09 04:00:00Z)`, karena pergantian DST membuat hari tersebut berdurasi 23 jam. Masing-masing endpoint mendapat empat header transaksi pada `2026-03-08 04:59:59Z`, `2026-03-08 05:00:00Z`, `2026-03-09 03:59:59Z`, dan `2026-03-09 04:00:00Z`.

| Test | Harapan | Hasil aktual |
| --- | --- | --- |
| `GET /api/v1/laporan/penjualan` | Record sebelum awal dan tepat pada akhir eksklusif keluar; dua record dalam periode dihitung; total `5000.00`, count `2`, timezone response `America/New_York` | PASS |
| `GET /api/v1/laporan/pembelian` | Batas sama; dua header dihitung sekali; total `5000.00`, count `2`, timezone response `America/New_York` | PASS |

Fixture ditulis sebagai waktu UTC tanpa offset pada kolom `DATETIME`; koneksi aplikasi/test menggunakan UTC. Test menguji perilaku HTTP laporan yang ada, tanpa mengubah schema atau kontrak.

## Batas bukti

- T-REP-03 tetap PARTIAL: kasus DST ini hanya menguji `America/New_York`; kasus `Asia/Jakarta` tercatat pada `TRANSACTION-FEATURE-001`.
- Presisi timestamp terkecil yang didukung kolom MySQL belum diuji. Periode kosong/invalid, berbagai zona tambahan, dan seluruh matriks tenant belum ditutup.
- Ini bukan OpenAPI conformance atau penerimaan G3/G4. Dua operasi laporan tetap berstatus DRAFT dan belum siap untuk integrasi frontend.

SHA-256 `tests/Feature/LaporanApiTest.php`: `2f729354c4c29bccbe8eae1486ccc3a4a0284fe5302c0877bbf9314ae212ba7f`.
