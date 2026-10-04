# Register Keputusan Backend

Status awal: 2026-10-04. Dokumen ini membedakan kebutuhan yang sudah disepakati dari pilihan yang masih perlu ditetapkan sebelum implementasi. `PROPOSED` bukan persetujuan pengguna. Agent boleh menyelesaikan pekerjaan lain yang tidak bergantung pada keputusan tersebut.

## Kebutuhan yang sudah disepakati

| ID | Keputusan | Dampak |
| --- | --- | --- |
| K01 | Aplikasi melayani banyak warung; user tenant memakai warung dari identitas terautentikasi. | Seluruh query, relasi, transaksi, dan laporan wajib terisolasi per warung. |
| K02 | Penjualan mencatat menu berdasarkan harga jual. | Tidak ada proses pesanan dapur, resep, pemakaian bahan, atau pengurangan stok. |
| K03 | Pembelian bahan berdiri sendiri dari penjualan. | Tidak ada relasi pembelian ke menu/penjualan; total pembelian dilaporkan sendiri. |
| K04 | Penjualan dan pembelian masing-masing memiliki header dan minimal satu rincian. | Simpan header serta rincian secara atomik. |
| K05 | Pembelian boleh lengkap atau cukup keterangan seperti `Belanja di pasar` dan nominal. | Input ringkas menjadi satu rincian; tidak boleh ditolak karena qty/satuan/harga satuan kosong. |
| K06 | Pengguna memperoleh pendapatan penjualan dan total pembelian pada periode tertentu. | Keduanya agregasi terpisah; selisihnya tidak otomatis menjadi laba/HPP. |
| K07 | Pekerjaan tim ini adalah backend beserta dokumentasi dan API contract. | AI frontend memakai kontrak yang telah dinyatakan siap, bukan menebak tabel atau route. |
| K08 | Setelah mengedit satu file, commit file itu sebelum mengedit file berikutnya. | Izin commit sudah diberikan; stage path spesifik, review diff, cek whitespace, catat hash. Push memerlukan instruksi tersendiri. |
| K09 | User memilih MySQL/MariaDB untuk database produksi dan Sanctum bearer token untuk autentikasi. | Versi database serta lifecycle/config token dicatat sebagai detail yang perlu difinalkan sebelum gate terkait. |

Rancangan delapan tabel ada di [database/README.md](../../database/README.md). Pilihan di bawah belum mengubah skema tersebut.

## Keputusan terbuka dan usulan

| ID | Keputusan | Usulan untuk ditinjau / informasi yang dibutuhkan | Blokir |
| --- | --- | --- | --- |
| D01 | Versi database produksi dan transisi schema awal | User memilih keluarga MySQL/MariaDB. Tetapkan vendor/version tepat sesuai deployment dan inventaris migration/isi tabel users sebelum menentukan migration maju. Test integrasi harus memakai versi target; SQLite default bukan keputusan produksi. | M0 setup DB, seluruh migration |
| D02 | Detail konfigurasi auth Sanctum bearer yang dipilih user | `laravel/sanctum` v4.3.3 sudah dipasang dan migration `personal_access_tokens` dicatat sebagai infrastruktur. Tetapkan expiry/revokasi token, CORS frontend, HTTPS dan rate limit sebelum route auth digunakan. | M1 login dan route terproteksi |
| D03 | Arti tanggal masa aktif warung yang NULL | Pilih apakah NULL berarti tanpa batas atau belum dikonfigurasi. Batas tanggal terisi tetap inklusif. Uji masing-masing kombinasi NULL; jangan menganggap user aktif jika status belum dapat ditentukan. | M1 middleware/login |
| D04 | Matriks role dan operasi superadmin | Kandidat di DESIGN.md: superadmin mengelola warung dan owner awal; owner mengelola user warung; manager mengelola katalog/pembelian/laporan; kasir mencatat penjualan. Superadmin tidak otomatis memperoleh hak transaksi tenant. Finalkan juga cakupan riwayat kasir dan akses owner/manager. | M1 policies, semua endpoint berizin |
| D05 | Nominal, qty, diskon, pembayaran, pembulatan | Kandidat: string decimal dua angka pecahan; qty > 0; uang >= 0; round half-up per rincian; diskon nominal; total = subtotal - diskon header; cash bayar >= total. Putuskan QRIS/transfer, harga nol, batas angka, mata uang tampilan, dan apakah qty penjualan boleh pecahan. | M3 calculator dan M4 nominal |
| D06 | Arsip master dan riwayat transaksi | Kandidat master menggunakan aktif=false, FK RESTRICT, tanpa hard-delete riwayat. Pembatalan penjualan perlu izin, state transition, metadata audit/alasan, dan semantik laporan sebelum endpoint cancel dipublikasikan. | M2 arsip, M3 cancellation |
| D07 | Scope rincian penjualan, pembelian, stok, dan dapur | **Diputuskan user 2026-10-04:** setiap rincian penjualan wajib berasal dari menu; tidak ada item bebas, pesanan dapur, hubungan pembelian/menu/resep, atau pengelolaan stok. Pembelian dan pendapatan penjualan dilaporkan terpisah. `harga_modal` bukan dasar perhitungan HPP/laba. Hapus cabang `luar_menu` dan `jenis_item` dari rancangan sebelum migration bisnis. | Selesai; tidak memblokir implementasi katalog/penjualan dalam scope yang disepakati |
| D08 | Timezone, timestamp, periode, dan pendapatan | Kandidat: simpan UTC, timezone bisnis Asia/Jakarta, filter tanggal lokal dari awal hari sampai sebelum awal hari sesudah tanggal akhir. Pendapatan = SUM(total) penjualan selesai, bukan SUM(bayar); batal dikecualikan. Pembelian menjumlahkan header yang sah menurut D11. Finalkan aturan backdate/future date. | Tanggal transaksi, laporan M3/M4 |
| D09 | Nomor transaksi dan retry/concurrency | Tentukan pembuatan nomor unik per warung serta strategi retry durable untuk kedua transaksi; jangan menggunakan COUNT+1. Tentukan kunci, scope, payload sama/berbeda, crash/replay, masa simpan, dan mekanisme penyimpanan sebelum menambah tabel/header API. Draft belum menetapkan Idempotency-Key atau mengizinkan retry otomatis. | Create sale/purchase production-ready |
| D10 | Input sebagian pada rincian pembelian | K05 tetap wajib diterima. Kandidat: qty dan harga_satuan diisi berpasangan; bila keduanya ada backend menghitung subtotal dan menolak subtotal kiriman yang tidak cocok. Bila keduanya kosong, nominal subtotal wajib. Satuan opsional. Rincian nominal dan hitungan boleh bercampur. Nilai minimal/rounding mengikuti D05. | Validasi pembelian selain bentuk minimal K05 |
| D11 | Koreksi/pembatalan pembelian | Skema saat ini tidak punya status pembelian. Tentukan apakah perlu revisi/cancel dan metadata audit; tambah rancangan schema lebih dulu jika diperlukan. Jangan mengarang filter status atau menghapus histori. Kontrak awal hanya create/list/detail/report. | Operasi koreksi dan semantik laporan final |
| D12 | Identitas user dan migrasi datanya | Tentukan uniqueness email nullable, normalisasi username/email dan sensitivitas huruf. Username tetap unique global pada rancangan. Data lama `name` tidak otomatis dipetakan tanpa inventaris; password selalu hash dan tidak keluar API. | M1 users migration dan validasi |
| D13 | Konvensi HTTP dan kompatibilitas | Kandidat OpenAPI: /api/v1, ID string, decimal string, response data/meta, error code/message/errors, pagination page/per_page, page size maksimum 100, sort dari allowlist. Tinjau sebelum menandai kontrak READY. | M0 baseline kontrak |
| D14 | Upload dan perubahan gambar menu | Field `gambar` boleh null sesuai skema; tentukan apakah gambar sekadar reference atau perlu upload/delete API, storage dan validasi. | Endpoint upload/ubah gambar saja |
| D15 | Vendor dan versi database deployment | Pilih MySQL atau MariaDB beserta versi minimum/target; validasi collation, FK, decimal, locking, dan migration integrasi terhadap engine yang dipakai. | Test integrasi final dan klaim kompatibilitas |

## Proses penetapan

1. Catat keputusan, sumber persetujuan/otoritas, tanggal, dan dampak pada schema/API/test.
2. Ubah status keputusan menjadi `DECIDED` hanya jika pilihan dan alasannya sudah tersedia. Jika memilih rekomendasi, tulis pilihannya secara eksplisit.
3. Perbarui dokumen terkait satu file per commit. Semua commit dalam satu slice dicatat di tracker.
4. Buka task yang sebelumnya terhalang. Jalankan test yang membuktikan keputusan, termasuk skenario negatif.

| ID | Status saat ini | Pilihan final | Sumber / tanggal |
| --- | --- | --- | --- |
| D01 | PARTIAL | Keluarga MySQL/MariaDB | Pilihan user, 2026-10-04; D15 tersisa |
| D02 | PARTIAL | Sanctum bearer token; `laravel/sanctum` v4.3.3 | Pilihan user dan package terpasang, 2026-10-04; lifecycle/deployment tersisa |
| D03–D06 | OPEN | Belum ditetapkan | Keputusan produk/implementasi terkait |
| D07 | DECIDED | Menu terdaftar saja; pembelian terpisah; tanpa stok/dapur/resep | Klarifikasi eksplisit user, 2026-10-04 |
| D08–D15 | OPEN | Belum ditetapkan | Keputusan produk/implementasi terkait |

## Batas kontrak draft

[OpenAPI](../api/openapi.yaml) adalah kandidat konkret untuk review dan mock terlabel. Seluruh operasi awal berstatus DRAFT dan NOT_STARTED. Bearer Sanctum sudah dipilih, sedangkan role, tanggal, nominal, error, dan detail auth mengikuti D03–D06/D08/D12/D13. Belum boleh diklaim tersedia di server. D09 sengaja belum mempunyai header atau mekanisme retry. AI frontend harus memeriksa status handoff di [tracker](../../IMPLEMENTATION_PROGRESS.md) sebelum integrasi live.
