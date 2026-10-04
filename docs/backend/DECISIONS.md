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
| K08 | File-file yang saling terkait dikomit bersama sebagai satu kelompok perubahan. | Izin commit sudah diberikan; review diff lengkap, stage path spesifik, cek whitespace, catat hash. Push memerlukan instruksi tersendiri. |
| K09 | User memilih MySQL 8.0.40 untuk database produksi dan Sanctum bearer token dengan masa berlaku 30 hari. | Transisi schema/data dan konfigurasi deployment yang masih terbuka dicatat sebelum gate terkait. |

Rancangan delapan tabel dan skema migration yang sudah diterapkan ada di [database/README.md](../../database/README.md). Perubahan provisional `warungs.timezone` untuk mendukung D08 sudah memiliki migration; keputusan bisnis lain tidak dianggap mengubah schema sebelum dicatat dan dimigrasikan.

## Keputusan terbuka dan usulan

| ID | Keputusan | Usulan untuk ditinjau / informasi yang dibutuhkan | Blokir |
| --- | --- | --- | --- |
| D01 | Versi database produksi dan transisi schema awal | User memilih MySQL 8.0.40 sebagai target. Inventaris migration/isi tabel users tetap harus dilakukan sebelum perubahan users; validasi integrasi memakai versi ini, bukan SQLite default. | M0 setup DB, transisi users |
| D02 | Detail konfigurasi auth Sanctum bearer yang dipilih user | User menetapkan masa berlaku bearer token 30 hari, login ulang setelah kedaluwarsa, dan batas login 5 percobaan per menit untuk setiap kombinasi username dan IP. Logout mencabut token yang dipakai. Tetapkan CORS frontend dan HTTPS sebelum deployment. | M1 login dan route terproteksi |
| D03 | Arti tanggal masa aktif warung yang NULL — DIPUTUSKAN | `tanggal_mulai = NULL` tidak membatasi tanggal mulai; `tanggal_berakhir = NULL` tidak membatasi tanggal akhir. Nilai terisi tetap berlaku inklusif. Periksa status aktif user dan warung secara terpisah. | Selesai untuk M1 middleware/login |
| D04 | Matriks role inti dan operasi superadmin — DISETUJUI | User menyetujui pembagian inti: superadmin mengelola warung dan owner awal melalui jalur admin; owner mengelola user warungnya; manager menangani katalog, pembelian, dan laporan; kasir menangani penjualan. Superadmin tidak otomatis bertindak pada tenant. Implementasi katalog memberi manager akses baca/ubah dan kasir akses baca aktif saja; izin owner di luar pengelolaan user serta cakupan riwayat kasir tetap belum diputuskan. | M1 policies dan endpoint berizin; detail policy tersisa |
| D05 | Nominal, qty, diskon, pembayaran, pembulatan | User menyetujui nominal sebagai decimal eksak dua angka pecahan dan pembulatan half-up per rincian. Aturan qty/diskon, harga nol, QRIS/transfer, batas angka, dan mata uang tampilan masih perlu ditetapkan. | M3 calculator dan M4 nominal |
| D06 | Arsip master dan riwayat transaksi | Kandidat master menggunakan aktif=false, FK RESTRICT, tanpa hard-delete riwayat. Pembatalan penjualan perlu izin, state transition, metadata audit/alasan, dan semantik laporan sebelum endpoint cancel dipublikasikan. | M2 arsip, M3 cancellation |
| D07 | Scope rincian penjualan, pembelian, stok, dan dapur | **Diputuskan user 2026-10-04:** setiap rincian penjualan wajib berasal dari menu; tidak ada item bebas, pesanan dapur, hubungan pembelian/menu/resep, atau pengelolaan stok. Pembelian dan pendapatan penjualan dilaporkan terpisah. `harga_modal` bukan dasar perhitungan HPP/laba. Hapus cabang `luar_menu` dan `jenis_item` dari rancangan sebelum migration bisnis. | Selesai; tidak memblokir implementasi katalog/penjualan dalam scope yang disepakati |
| D08 | Timezone, timestamp, periode, dan pendapatan | User menetapkan server menyimpan UTC, tampilan memakai waktu lokal masing-masing warung, dan periode mengikuti waktu warung. Implementasi sementara menambah `warungs.timezone` nullable berisi identifier IANA tanpa default; provisioning wajib mengisinya dan akses ditolak sebelum terisi. Ini kebijakan aman sementara untuk menghindari timezone/backfill berdasarkan tebakan, bukan keputusan eksplisit user tentang warung tanpa zona. Pendapatan = SUM(total) penjualan selesai, bukan SUM(bayar); batal dikecualikan. Pembelian menjumlahkan header yang sah menurut D11. Finalkan perlakuan zona kosong, backdate, dan future date. | Pemeriksaan masa aktif, tanggal transaksi, laporan M1/M3/M4 |
| D09 | Nomor transaksi dan retry/concurrency | User menyetujui `Idempotency-Key`: payload identik mengembalikan hasil transaksi pertama; key sama dengan payload berbeda menghasilkan HTTP 409. Implementasi memakai penyimpanan durable bersama commit transaksi; scope key, format nomor, dan bukti concurrency/replay dicatat pada implementasi. | Create sale/purchase production-ready |
| D10 | Input sebagian pada rincian pembelian | K05 tetap wajib diterima. Kandidat: qty dan harga_satuan diisi berpasangan; bila keduanya ada backend menghitung subtotal dan menolak subtotal kiriman yang tidak cocok. Bila keduanya kosong, nominal subtotal wajib. Satuan opsional. Rincian nominal dan hitungan boleh bercampur. Nilai minimal/rounding mengikuti D05. | Validasi pembelian selain bentuk minimal K05 |
| D11 | Koreksi/pembatalan pembelian | Skema saat ini tidak punya status pembelian. Tentukan apakah perlu revisi/cancel dan metadata audit; tambah rancangan schema lebih dulu jika diperlukan. Jangan mengarang filter status atau menghapus histori. Kontrak awal hanya create/list/detail/report. | Operasi koreksi dan semantik laporan final |
| D12 | Identitas user dan migrasi datanya | User memilih email nullable dan unique global jika terisi. Username tetap unique global pada rancangan. Tetapkan normalisasi username/email dan sensitivitas huruf; data lama `name` tidak otomatis dipetakan tanpa inventaris. Password selalu hash dan tidak keluar API. | M1 users migration dan validasi |
| D13 | Konvensi HTTP dan kompatibilitas | Kandidat OpenAPI: /api/v1, ID string, decimal string, response data/meta, error code/message/errors, pagination page/per_page, page size maksimum 100, sort dari allowlist. Tinjau sebelum menandai kontrak READY. | M0 baseline kontrak |
| D14 | Upload dan perubahan gambar menu | Field `gambar` boleh null sesuai skema; tentukan apakah gambar sekadar reference atau perlu upload/delete API, storage dan validasi. | Endpoint upload/ubah gambar saja |
| D15 | Vendor dan versi database deployment | User memilih MySQL 8.0.40 sebagai target produksi. Validasi collation, FK, decimal, locking, dan migration integrasi terhadap versi ini sebelum klaim kompatibilitas. | Test integrasi final dan klaim kompatibilitas |
| D16 | FK gabungan untuk mencegah relasi lintas warung pada tingkat database | User menyetujui FK gabungan berkolom `warung_id` pada seluruh relasi tenant-owned. Migration ini menambah unique key induk `(warung_id, id)` dan mengganti relasi menu-kategori dengan FK gabungan; migration transaksi mengikuti pola yang sama pada header, pencatat, serta rincian. | T-DB-03, G2–G4 |

## Proses penetapan

1. Catat keputusan, sumber persetujuan/otoritas, tanggal, dan dampak pada schema/API/test.
2. Ubah status keputusan menjadi `DECIDED` hanya jika pilihan dan alasannya sudah tersedia. Jika memilih rekomendasi, tulis pilihannya secara eksplisit.
3. Perbarui file-file terkait dan commit sebagai kelompok perubahan yang koheren. Semua commit dalam satu slice dicatat di tracker.
4. Buka task yang sebelumnya terhalang. Jalankan test yang membuktikan keputusan, termasuk skenario negatif.

| ID | Status saat ini | Pilihan final | Sumber / tanggal |
| --- | --- | --- | --- |
| D01 | PARTIAL | MySQL 8.0.40; transisi users masih menunggu inventaris | Pilihan user, 2026-10-04 |
| D02 | PARTIAL | Sanctum bearer token 30 hari; login dibatasi 5 percobaan per menit per username dan IP; logout mencabut token aktif | Pilihan user, 2026-10-04; CORS/HTTPS deployment tersisa |
| D03 | DECIDED | NULL berarti tanpa batas; tanggal terisi inklusif | Jawaban user, 2026-10-04 |
| D04 | PARTIAL | Pembagian tugas inti role dan batas superadmin disetujui | Persetujuan user, 2026-10-04; detail policy baca/ubah dan riwayat tersisa |
| D05 | PARTIAL | Decimal eksak dua angka pecahan pada wire/penyimpanan; pembulatan half-up per rincian | Persetujuan user, 2026-10-04; aturan qty, diskon, pembayaran, dan batas nilai tersisa |
| D06 | OPEN | Belum ditetapkan | Keputusan produk/implementasi terkait |
| D07 | DECIDED | Menu terdaftar saja; pembelian terpisah; tanpa stok/dapur/resep | Klarifikasi eksplisit user, 2026-10-04 |
| D08 | PARTIAL | Simpan timestamp UTC; tampilkan waktu lokal warung dan hitung periode menurut zona warung. Implementasi sementara menambah `warungs.timezone` IANA nullable tanpa default dan menolak akses tenant bila NULL/invalid | Jawaban user, 2026-10-04; perlakuan warung tanpa timezone dan aturan backdate/future date tetap menunggu |
| D09 | PARTIAL | `Idempotency-Key`; payload identik replay hasil pertama, payload berbeda pada key sama menghasilkan HTTP 409 | Persetujuan user, 2026-10-04; format nomor dan bukti concurrency/replay dicatat saat implementasi |
| D10–D11 | OPEN | Belum ditetapkan | Keputusan produk/implementasi terkait |
| D12 | PARTIAL | Email nullable, unique global jika diisi | Jawaban user, 2026-10-04; normalisasi dan pemetaan data lama tersisa |
| D13–D14 | OPEN | Belum ditetapkan | Keputusan produk/implementasi terkait |
| D15 | DECIDED | MySQL 8.0.40 sebagai target produksi | Jawaban user, 2026-10-04 |
| D16 | DECIDED | Terapkan FK gabungan dengan `warung_id` untuk relasi tenant-owned | Persetujuan user, 2026-10-04 |

## Batas kontrak draft

[OpenAPI](../api/openapi.yaml) adalah kandidat konkret untuk review dan mock terlabel. Seluruh operasi awal berstatus DRAFT dan NOT_STARTED. Bearer Sanctum, expiry 30 hari, batas tanggal NULL, pembagian role inti, baseline decimal, replay idempoten, dan FK gabungan telah dipilih; detail policy, nominal yang tersisa, error, dan deployment mengikuti D02/D04–D06/D08/D12/D13. Belum boleh diklaim tersedia di server. AI frontend harus memeriksa status handoff di [tracker](../../IMPLEMENTATION_PROGRESS.md) sebelum integrasi live.
