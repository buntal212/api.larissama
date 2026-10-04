# ROLE-TRANSACTION-CREATE-RBAC-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit test: `d8c2613b3f34ae1fdac028946600edaec4fed6e9`
- SHA-256 `PenjualanApiTest.php`: `08dd13c1ee3918c21126e1c5704f2f358301a28835b4f1e98f0b30f8c85a9691`
- SHA-256 `PembelianApiTest.php`: `326d051743557fbfaed7ab8f134cc36c8587ea51085248c46b2500f64de90a78`
- Lingkungan: Docker Compose test-only, PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, database disposable `larissama_test`
- Pint: PASS
- Focused: `PenjualanApiTest` + `PembelianApiTest`, 19 test / 3159 assertions — PASS
- Suite penuh: 295 test / 28348 assertions — PASS
- Cleanup: container MySQL dan network Compose disposable dihapus

Kasus manager membuat penjualan kini mengirim body yang cocok dengan request schema OpenAPI dan merujuk menu di warung manager. Kasus kasir membuat pembelian memakai body minimum valid `nama_item` + `subtotal`. Keduanya mendapat HTTP 403 `FORBIDDEN`, response cocok dengan `Error403`, dan tabel header maupun rincian tidak bertambah.

Kedua FormRequest memanggil policy pada `authorize()` sebelum validasi/action dan policy yang ada sesuai D04. Run ini memperkuat conformance test tanpa perubahan runtime. Tidak ada kesimpulan mengenai akses owner, histori kasir, atau role/aksi lainnya; semua operasi tetap `DRAFT`.

## Command

```sh
docker compose -f compose.test.yaml run --rm --no-deps test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings
docker compose -f compose.test.yaml down --remove-orphans
```

## Batas bukti

Hanya kombinasi manager→create sale dan kasir→create purchase yang diperiksa di sini. Bukti tidak mengesahkan akses baca/koreksi, owner, atau matriks role penuh.
