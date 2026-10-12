# REPORT-EXTENSIONS-ACCEPTANCE-001

## Hasil

- Tanggal: 2026-10-12 (Asia/Jakarta)
- Lingkungan: Docker Compose test terisolasi, PHP 8.3, MySQL 8.0.40, database disposable `larissama_test`
- Test laporan tambahan: 3 passed / 572 assertions
- Regression laporan, penjualan, pembelian, route: 86 passed / 21.768 assertions
- Test laporan dan integrity kontrak: 26 passed / 2.349 assertions
- Suite backend lengkap: 583 passed / 100.158 assertions dalam 60,58 detik
- Pint `--dirty --format agent`: PASS; hanya formatter `BuildLaporanPeriode.php`
- OpenAPI validator Docker: `docs/api/openapi.yaml: OK`
- Container, network, dan volume database test disposable telah dihapus.

## Cakupan

- `GET /laporan/penjualan` mengembalikan jumlah transaksi lunas, total penjualan, rata-rata total per transaksi, pembagian jumlah/nilai menurut metode pembayaran, total retur, dan pendapatan bersih.
- `GET /laporan/pesanan-belum-lunas` hanya menjumlahkan pesanan yang saat ini masih pending, berdasarkan tanggal order lokal.
- `GET /laporan/penjualan-harian` mengelompokkan pembayaran dan retur menurut tanggal lokal masing-masing; nilai harian dapat negatif.
- `GET /laporan/penjualan-item` mengelompokkan snapshot nama/harga, memisahkan menu katalog dan item bebas, serta tidak memasukkan pesanan belum lunas.
- Response dari endpoint baru dan summary cocok dengan schema OpenAPI; test memeriksa filter waktu lokal dan tenant.

## Batas interpretasi

Penjualan per metode dan rata-rata menggunakan total transaksi sebelum alokasi retur. Rincian item menjumlah subtotal setelah diskon baris tetapi sebelum diskon header. Data retur hanya nominal tingkat transaksi, sehingga retur tidak dialokasikan ke item. Ringkasan item bebas menggabungkan nama dan harga teks yang sama. Data yang ada tidak mendukung laba/HPP, stok, atau biaya operasional.

## Command

```sh
docker compose -f compose.test.yaml run --rm test-runner php artisan test --compact
docker compose -f compose.test.yaml run --rm test-runner vendor/bin/pint --dirty --format agent
docker compose -f compose.openapi.yaml run --build --rm openapi-validator
docker compose -f compose.test.yaml down --volumes
```
