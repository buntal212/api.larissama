# MALFORMED-JSON-400-CONFORMANCE-001

Status: **PASS**
Task: T-API-02/04, BE-003
Rencana dicatat: `93a32b9`
Commit kode/test: `dd9e0c6dbae5ea5283f354e4ec1e71fc88fdb95f`
Lingkungan: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40 (`larissama_test`), Docker Compose project `larissama-backend-test`.

## Cakupan

Mengirim sintaks JSON rusak dengan `Content-Type: application/json` ke seluruh 11 operasi yang mendefinisikan `requestBody`: login; POST/PATCH warung admin; POST/PATCH user; POST/PATCH kategori menu; POST/PATCH menu; POST penjualan; dan POST pembelian. Operasi tenant memakai bearer owner; operasi administrasi warung memakai bearer superadmin. Resource PATCH tersedia agar kegagalan parser tidak tertutup oleh 404. Satu request login tambahan memakai body whitespace-only untuk memastikan whitespace bukan body kosong yang dilewati.

Setiap response harus HTTP 400, `code=BAD_REQUEST`, pesan `JSON request tidak dapat dibaca.`, `errors` kosong, `request_id` UUID, dan cocok dengan schema response OpenAPI bagi method/path aktual. Jumlah row pada warung, user, katalog, transaksi, dan rincian tetap; target warung, user, kategori, dan menu juga sama sebelum/sesudah.

## Bukti

Probe awal pada HEAD `93a32b9` gagal sesuai dugaan: `POST /auth/login` dengan `{"malformed":` menghasilkan HTTP 422 `VALIDATION_ERROR` dengan field username/password yang hilang, karena Laravel memperlakukan JSON rusak sebagai body kosong.

Ditambahkan middleware `EnsureValidJsonApiBody`. Middleware hanya memeriksa body JSON non-kosong; sintaks invalid diubah ke HTTP 400 melalui envelope API umum. Login memvalidasi sintaks sebelum throttle login. Rute terlindungi memvalidasi setelah autentikasi dan pemeriksaan account aktif. Body kosong tetap mencapai FormRequest; JSON valid tetap menggunakan validasi endpoint biasa. Tidak ada perubahan schema database atau dependency.

## Hasil akhir

- Focused: `ApiMalformedJsonConformanceTest` — 1 test / 306 assertions, PASS.
- Pint: 143 file, PASS.
- Suite penuh: 327 test / 51.176 assertions dalam 35,24 detik, PASS.
- Test dijalankan lewat Compose disposable pada MySQL 8.0.40. Setelah run, `docker compose ... down --remove-orphans` selesai dan `ps -a` tidak menampilkan container untuk project test.
- `git diff --check`: PASS.

Percobaan pertama sebelum implementasi memang gagal pada 422; hasil akhir lulus setelah middleware dipasang. Cakupan hanya sintaks body JSON rusak pada 11 operasi, bukan setiap kemungkinan kesalahan HTTP 400 atau setiap payload schema. Semua operasi tetap DRAFT karena gate lain dan keputusan terbuka belum selesai.
