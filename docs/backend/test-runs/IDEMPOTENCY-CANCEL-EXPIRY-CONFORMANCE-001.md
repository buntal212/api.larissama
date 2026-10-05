# IDEMPOTENCY-CANCEL-EXPIRY-CONFORMANCE-001

- Tanggal: 2026-10-05
- Task/test: BE-404, T-RET-05, D09/D11
- Base commit: `0486e85`
- `tests/Feature/IdempotencyExpiryTest.php` SHA-256: `653a509b59c9b3636f122b5714a061b0e2ecaa1ccbf69418b3462e38cc27f3fe`
- Runtime: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40, Compose test-only

## Hasil

**PASS:** cancellation event di-replay sampai tepat sebelum tujuh hari. Pada timestamp expiry, retry terhadap pembelian yang sudah batal diproses sebagai request baru lalu mendapat 409 `PEMBELIAN_SUDAH_DIBATALKAN`; tidak ada pembatalan/audit kedua. Sesudahnya, key yang sama berhasil dipakai untuk membatalkan pembelian aktif lain di scope yang sama. Metadata key/hash event lama dilepas dan dua event audit serta kedua fakta pembatalan tetap ada.

Jika business-state validation menolak request expired, perubahan metadata expiry dalam transaksi tersebut ikut rollback. Record lama masih memiliki metadata secara fisik, tetapi setiap pencarian tetap mengenalinya sebagai expired dan tidak me-replay respons lama. Pemakaian ulang key yang berhasil melepas metadata lama ketika mencatat event baru.

- Focused `IdempotencyExpiryTest`: **4 test / 247 assertions**, PASS, 1.90 detik.
- Laravel Pint (`--dirty`): PASS.
- Stack MySQL Compose disposable dihapus setelah run.

## Command

```sh
docker compose -f compose.test.yaml run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/IdempotencyExpiryTest.php
docker compose -f compose.test.yaml down -v
docker compose -f compose.test.yaml ps -a
```

## Batas

Ini menutup boundary expiry khusus event pembatalan, termasuk status final yang mencegah aksi ganda. Tidak ada background cleanup; expired metadata yang belum berhasil dipakai ulang dapat tetap tersimpan. Semua operasi API tetap `DRAFT`.
