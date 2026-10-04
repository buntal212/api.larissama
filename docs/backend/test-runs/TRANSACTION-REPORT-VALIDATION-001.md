# TRANSACTION-REPORT-VALIDATION-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-305, BE-405

Test ID: T-REP-04

Commit yang diuji: `1ae3439`

Status run: **PASS untuk semua kasus T-REP-04 yang tercantum**

## Environment

- Docker Compose project `larissama-backend-test`, file `compose.test.yaml`.
- PHP 8.3.35, Composer 2.10.3, Laravel Framework 13.34.0, MySQL 8.0.40.
- Database `larissama_test` dari service `test-db`; test runner tidak mengekspos port host atau mengonfigurasi volume persisten. `RefreshDatabase` mengisolasi migration dan fixtures.
- Tidak menggunakan database development atau production. Container DB dan Compose network dihapus setelah test selesai.

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
| Test report terarah | PASS, 4 test / 22 assertions; tanpa warning |
| Suite `composer test` | PASS, 25 test / 117 assertions; tanpa warning |
| Pembersihan stack | PASS, container DB dan network disposable dihapus |

## Bukti skenario

| Kasus | Harapan | Hasil aktual |
| --- | --- | --- |
| `GET /api/v1/laporan/penjualan` periode sah kosong | HTTP 200, `jumlah_transaksi: 0`, `total_pendapatan: "0.00"`, timezone `Asia/Jakarta` | PASS |
| `GET /api/v1/laporan/pembelian` periode sah kosong | HTTP 200, `jumlah_transaksi: 0`, `total_pembelian: "0.00"`, timezone `Asia/Jakarta` | PASS |
| Kedua endpoint, tanggal bukan `Y-m-d` | HTTP 422 | PASS, 2 request |
| Kedua endpoint, `date_to` tidak dikirim | HTTP 422 | PASS, 2 request |
| Kedua endpoint, `date_from > date_to` | HTTP 422 | PASS, 2 request |

Input error mengikuti validasi `LaporanPeriodeRequest`; tidak ada write data pada endpoint GET. Periode kosong dibuat tanpa header penjualan maupun pembelian.

## Batas bukti

- Error di atas hanya diverifikasi melalui status HTTP 422. Bentuk `code/message/errors/request_id` yang disetujui sebagai baseline D13 belum diuji untuk seluruh status/error runtime.
- Ini bukan OpenAPI conformance atau penerimaan G3/G4. `laporan.penjualan` dan `laporan.pembelian` tetap berstatus DRAFT.
- T-REP-03 masih menunggu uji presisi timestamp terkecil yang didukung kolom MySQL; kasus Jakarta dan New York DST dibuktikan pada run terpisah.

SHA-256 `tests/Feature/LaporanApiTest.php`: `66379b764d291fc06de3dec9f87e55966f6d443f1a254797e6aa90dba929f270`.
