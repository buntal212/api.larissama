# TRANSACTION-QUERY-CONFORMANCE-001

Tanggal: 2026-10-04 (Asia/Jakarta)

Task: BE-303/403/305/405, subset T-API-02/03 dan D13

Commit yang diuji: `954530019aad19e42b347d1e86da3f6a6708c2fd`

Environment: PHP 8.3.35, Composer 2.10.3, Laravel 13.34.0, MySQL 8.0.40, database `larissama_test` dalam Compose test-only disposable. Stack database/network sudah dibersihkan.

## Perintah dan hasil

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php tests/Feature/LaporanApiTest.php'
docker compose -f compose.test.yaml run --rm test-runner composer test
docker compose -f compose.test.yaml down --remove-orphans
```

| Pemeriksaan | Hasil |
| --- | --- |
| Pint | PASS |
| Tiga feature test transaksi/laporan | PASS, 22 test / 3439 assertions; tanpa warning |
| Suite `composer test` | PASS, 81 test / 9917 assertions; tanpa warning |
| Cleanup Compose test | PASS, container database dan network disposable dihapus |

## Bukti query yang dicocokkan

`assertOperationQueryMatchesOpenApi()` membaca parameter query operasi dari `docs/api/openapi.yaml`, menyelesaikan `$ref`, memeriksa nama parameter dan required, mengubah scalar ter-serialize sesuai tipe OpenAPI, lalu menjalankan schema checker yang tersedia.

- `GET /penjualans`: query sukses `page=1`, `per_page=1`, `sort=-tanggal`, `status=selesai` cocok dengan schema operation.
- `GET /pembelians`: query sukses dua halaman (`page=1` dan `page=2`), `per_page=1`, `sort=-tanggal` cocok dengan schema operation.
- `GET /laporan/penjualan` dan `GET /laporan/pembelian`: query `date_from=2026-03-08` dan `date_to=2026-03-08` untuk report New York DST cocok dengan schema Date dan memenuhi required parameters.

Map query yang diperiksa dipakai langsung untuk membentuk URL yang dikirim oleh feature test. Tidak ditemukan mismatch pada nilai terpilih.

## Batas bukti

Checker memvalidasi map fixture sebelum request, bukan mengambil request object Laravel aktual. Ia mendukung scalar string/integer/number/boolean yang digunakan oleh parameter operasi ini; ini bukan validator OpenAPI umum. Run tidak menguji semua nilai filter, `per_page=100`, parameter invalid/unknown sebagai conformance request, seluruh status atau operasi lain, maupun semua kombinasi query. Uji Laravel yang sudah ada untuk validasi input invalid tetap terpisah dari checker schema. Tidak ada perubahan controller, DB, tenant scope, authorization, business rule, retry, atau transaksi. Seluruh 28 operasi tetap `DRAFT`; T-API-02/03 dan gate G3/G4 tetap terbuka.

Source hashes yang diuji:

- `tests/TestCase.php`: `49b2d6a4657baf6d814e011e00041a57ef813a2c8bb115c318a420667dd379e9`
- `tests/Feature/PenjualanApiTest.php`: `19824430693326f8db5f81b60939c9e19c8ec45ff6334fcc64a9061a0a97fe12`
- `tests/Feature/PembelianApiTest.php`: `bc46343b90dd53360415504c385fa145ab069b78d175af0ea8afdc12776a3e21`
- `tests/Feature/LaporanApiTest.php`: `6395ed35f23ef6e30baa1e0278f29ad36b478505ea34fea6fc6ca8ac3656ae12`
