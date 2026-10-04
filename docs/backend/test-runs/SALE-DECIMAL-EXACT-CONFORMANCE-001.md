# SALE-DECIMAL-EXACT-CONFORMANCE-001

## Lingkup

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Task: BE-302, T-SAL-08, D05/D13.
- Commit yang diuji: `6aadefaaefff715cf837bd7033408b110c75a1fd` (`test: verify exact sale decimal arithmetic`).
- SHA-256 `tests/Feature/PenjualanApiTest.php`: `2f880cce696d29dd87492f3e24ea62e8eeec4356e44bb4106f2e97c6a584e871`.
- Input: harga menu `17.25`, qty `3.00`, tanpa diskon, bayar tunai `60.00`.

## Hasil

Pint lulus. `PenjualanApiTest` lulus 16 test / 2651 assertions. Suite penuh lulus 309 test / 33214 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 / `larissama_test` melalui Docker Compose test-only. Container dan network test dibersihkan.

POST `/api/v1/penjualans` mengembalikan subtotal/total `51.75`, kembalian `8.25`, qty `3.00`, harga snapshot `17.25`, dan subtotal rincian `51.75`. Nilai header dan rincian yang sama tersimpan pada MySQL sebagai decimal eksak. Request dan response terpilih cocok dengan schema OpenAPI.

## Batas bukti

Kasus hanya memakai harga positif, qty bilangan bulat, pembayaran tunai, dan diskon nol. Ini tidak menetapkan harga nol, qty pecahan, aturan diskon, metode noncash, atau seluruh kapasitas `DECIMAL(15,2)`. D05 masih PARTIAL dan seluruh operasi tetap `DRAFT`.
