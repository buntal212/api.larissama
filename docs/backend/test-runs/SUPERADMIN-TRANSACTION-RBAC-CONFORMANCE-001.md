# SUPERADMIN-TRANSACTION-RBAC-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `931dff1b13bb707af9d788aade055348e0b48909`
- SHA-256 `PenjualanApiTest.php`: `34bceb502264d9774e419f689cca74c29f877d3b8055d851185051a3dad72efb`
- SHA-256 `PembelianApiTest.php`: `c33a299fe43b0d4d6438af53b9b4d670f3a2282b924615444c6fa9c6c037f8`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `PenjualanApiTest` + `PembelianApiTest`, 19 test / 3049 assertions — PASS
- Suite penuh: 295 test / 28238 assertions — PASS
- Cleanup: container MySQL dan network Compose disposable dihapus

Dua request memakai payload yang valid menurut request schema OpenAPI dan token superadmin dengan `warung_id = NULL`. `POST /api/v1/penjualans` memakai menu milik tenant; `POST /api/v1/pembelians` memakai bentuk rincian nominal yang diterima. Keduanya mendapat HTTP 403 `FORBIDDEN`, response cocok dengan `Error403`, dan tabel header/rincian masing-masing tetap kosong.

Inspeksi menunjukkan `PenjualanStoreRequest` dan `PembelianStoreRequest` memanggil policy dari `authorize()` sebelum validasi payload maupun action tulis. Policy yang ada sudah menolak superadmin, sesuai D04. Karena itu slice ini hanya menambahkan coverage; runtime tidak diubah. Test tidak menetapkan hak owner atau matriks role penuh. Semua operasi tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Run memeriksa hanya penolakan superadmin untuk create sale/purchase serta no-write akibat penolakan. Tidak menyimpulkan hak baca superadmin, akses owner, seluruh kombinasi role, atau kesiapan operasi untuk integrasi live.
