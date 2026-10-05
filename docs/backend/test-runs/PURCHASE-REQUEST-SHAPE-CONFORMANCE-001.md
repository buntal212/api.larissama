# PURCHASE-REQUEST-SHAPE-CONFORMANCE-001

- Tanggal: 2026-10-05
- Task: BE-003, BE-402; T-API-02; D10/D13
- Commit kode/test yang diuji: `68c28c8533e02413db40ab55300fde2cacb62cd5`
- Runtime: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`)
- Compose: `compose.test.yaml`, project `larissama-backend-test`; runner disposable

## Cakupan

Test `PembelianApiTest` mengirim request manager ke `POST /api/v1/pembelians`, mencocokkan body/response dengan schema OpenAPI, memeriksa pesan validasi, serta memastikan kegagalan tidak menulis header maupun rincian.

- Qty tanpa harga satuan, harga satuan tanpa qty, dan baris nominal tanpa subtotal: ketiganya tidak cocok dengan `oneOf` OpenAPI dan runtime mengembalikan 422 `VALIDATION_ERROR` pada field yang tepat.
- Qty dan harga satuan lengkap dengan subtotal yang berbeda: body cocok dengan bentuk OpenAPI, tetapi backend mengembalikan 422 pada `rincian.0.subtotal`; tidak ada transaksi yang tersimpan.
- Qty/harga satuan berpasangan tanpa subtotal client: request cocok dengan schema dan mendapat 201. Runtime menyimpan subtotal `75000.00` serta `20000.00` dan total header `95000.00`.

## Hasil

- Tes terarah sebelum format: 5 test / 645 assertions, PASS.
- `vendor/bin/pint --dirty --format agent`: PASS; Pint mengubah posisi kurung pada satu method test.
- Seluruh `PembelianApiTest` sesudah format: 20 test / 2853 assertions, PASS.
- Suite penuh `php artisan test --compact`: 320 test / 36720 assertions, PASS dalam 36.17 detik.
- Commit kode/test yang lulus: `68c28c8533e02413db40ab55300fde2cacb62cd5`.

## Batas bukti

Run ini menambah conformance bentuk request serta hasil runtime untuk kandidat D10; tidak menetapkan bahwa bentuk hitungan D10 diterima sebagai keputusan produk. Hanya bentuk minimal K05 yang sudah diputuskan user. Batas nominal, kuantitas, rounding, dan validasi uang lain D05/D10 tetap terbuka. Checker `TestCase` mendukung subset schema OpenAPI yang dipakai proyek; bukan validator OpenAPI 3.1 umum. Tidak ada perubahan action, validator runtime, migration, database, atau dependency. Seluruh operasi tetap `DRAFT` dan belum siap handoff frontend.
