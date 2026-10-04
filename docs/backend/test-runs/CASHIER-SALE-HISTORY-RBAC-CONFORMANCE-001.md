# CASHIER-SALE-HISTORY-RBAC-CONFORMANCE-001

## Lingkup

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Task: BE-303, T-RBAC-03, D04/D13.
- Commit yang diuji: `dcbd467de67f9a1947d7202e47f069d19bac7b14` (`test: scope cashier sale history to owner`).
- SHA-256 `tests/Feature/PenjualanApiTest.php`: `4ec4699c958dd383d2651c9d741f657ed28e0de5124d227f2d8cabe991eacef2`.
- Lingkup data: dua kasir berbeda pada warung yang sama, masing-masing mempunyai header dan rincian penjualan.

## Hasil

Pint lulus untuk 136 file. `PenjualanApiTest` lulus 17 test / 3036 assertions; suite penuh lulus 310 test / 33599 assertions pada PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 / `larissama_test` melalui Docker Compose test-only.

Kasir mendapat HTTP 200 pada daftar dengan hanya transaksi miliknya (`meta.total=1`), HTTP 200 untuk detail miliknya, dan HTTP 404 untuk detail kasir lain dalam warung sama. Response/query yang diuji cocok dengan schema OpenAPI. Transaksi milik kasir lain tetap tersimpan di database. Implementasi policy/query sudah sesuai; perubahan runtime, schema, dan dependency tidak diperlukan. Compose dibersihkan.

## Batas bukti

Kasus membuktikan cakupan riwayat sale kasir untuk list/detail di tenant yang sama. Ia tidak menetapkan izin owner atau semua kombinasi role dan operasi; manager read access serta matriks D04 yang lebih luas masih memerlukan keputusan/conformance. Semua operasi tetap `DRAFT`.
