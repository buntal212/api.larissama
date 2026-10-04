# Rencana Kerja Backend LarisSama

## Status dan lingkup

Ini rencana implementasi berdasarkan [rancangan database](database/README.md), bukan persetujuan untuk langsung membangun semua fitur. Lingkup yang dikerjakan adalah backend Laravel dan dokumentasi API contract. Kode frontend tidak termasuk lingkup.

Pengembang frontend akan memakai kontrak API backend sebagai acuan integrasi. Untuk itu, setiap milestone harus menghasilkan atau memperbarui spesifikasi OpenAPI sebelum endpoint diserahkan untuk integrasi.

Ikuti [alur kerja pengembangan](DEVELOPMENT_WORKFLOW.md), aturan backend di [`AGENTS.md`](AGENTS.md), dan aturan migration di [`database/AGENTS.md`](database/AGENTS.md).

## Prinsip pelaksanaan

- Kerjakan fitur dalam vertical slice yang bisa diterima satu per satu; jangan membuat semua tabel lebih dulu lalu menunda endpoint dan integrasi.
- Keputusan domain yang belum ditetapkan tetap terbuka sampai diputuskan. Tidak ada asumsi diam-diam untuk uang, role, tenant, status, atau masa aktif.
- Backend adalah otoritas untuk otorisasi, tenant isolation, validasi, dan perhitungan.
- Pembelian dan penjualan adalah pencatatan terpisah; pembelian tidak mengelola stok dan tidak terkait ke menu/resep.
- Migration yang sudah dipakai bersama tidak diedit; koreksi dilakukan lewat migration baru.
- Setiap perubahan kontrak API dicatat bersama implementasi backend yang mengubahnya.

## Kontrak API untuk frontend

### Artefak yang akan dibuat

```text
docs/api/openapi.yaml   OpenAPI 3.1, kontrak machine-readable
docs/api/README.md      konvensi API dan cara membaca kontrak
```

Spesifikasi ditulis di backend dan menjadi acuan untuk integrasi frontend. Dokumen database menjelaskan penyimpanan; dokumen OpenAPI menjelaskan request dan response. Frontend tidak boleh menyimpulkan payload langsung dari nama kolom.

Setiap operasi OpenAPI harus menjelaskan:

- method, path, `operationId`, ringkasan, serta versi;
- autentikasi dan role/scope yang diperlukan;
- path/query parameters, filter, sort, pagination, dan batasnya;
- request schema, field wajib/nullable, validasi, dan contoh request;
- response sukses, status HTTP, response schema, dan contoh response;
- error schema beserta kondisi yang memicunya;
- format ID, tanggal/waktu/zona waktu, uang/kuantitas decimal, serta aturan pembulatan;
- aturan idempotency atau version precondition jika operasi membutuhkannya.

Sebelum milestone M0 selesai, tetapkan API prefix/versioning, skema auth, response/error convention, pagination, format decimal/tanggal, dan kebijakan perubahan kompatibel. OpenAPI tidak dianggap final selama keputusan itu masih terbuka. Setelah kontrak stabil, implementasi Laravel harus sesuai dengan spec; jangan meminta frontend menebak atau menyesuaikan terhadap implementasi yang tidak terdokumentasi.

## Milestone

| Milestone | Backend scope | API contract / handoff | Gate |
| --- | --- | --- | --- |
| M0 — Kesiapan dan keputusan | Verifikasi runtime, cara menjalankan kedua repo, migration Laravel yang ada, target DB, dan strategi auth. Tutup keputusan prasyarat tenant, tanggal, nominal, API errors, serta kompatibilitas `users` bawaan. | Tetapkan konvensi API; siapkan OpenAPI 3.1 dan pedoman kontrak. Belum menetapkan endpoint bisnis yang belum memiliki aturan. | Toolchain/DB/auth dan strategi migration diketahui; tidak ada asumsi prasyarat untuk slice M1. |
| M1 — Warung, user, dan akses | Buat `warungs`; adaptasi `users` dengan `warung_id`, `nama`, `username`, `role`, dan `aktif`. Implementasikan relasi, login, current-user, pemeriksaan status user/warung, tanggal aktif, dan tenant scope. Tangani superadmin sebagai jalur otorisasi tersendiri. | Tulis operasi auth, profil/current-user, serta operasi pengelolaan warung/user yang sudah disepakati. Sertakan role/scope, contoh payload, validasi, dan error akses. | User tenant tidak dapat melintasi warung; akses aktif/kedaluwarsa ditegakkan backend; dokumentasi cocok dengan endpoint. |
| M2 — Kategori dan menu | Buat `kategori_menus` dan `menus`; terapkan unique code per warung, relasi kategori-menu satu warung, validasi, query ter-tenant-scope, serta aturan aktif/arsip/hapus yang disetujui. | Tulis operasi daftar/detail/buat/ubah/status sesuai keputusan fitur, parameter filter/pagination, payload, validasi, error, dan contoh. Frontend menerima spec sebelum integrasi. | Request tenant tidak bisa membaca atau mengubah data warung lain; menu tidak dapat memakai kategori lintas warung. |
| M3 — Penjualan | Buat `penjualans` dan `penjualan_rincis`; simpan header dan detail atomik, hitung nominal di backend, simpan snapshot nama/harga, dan dukung item luar-menu. Tetapkan nomor transaksi, pembatalan, diskon, pembayaran, dan retry sebelum coding. | Tulis kontrak create/list/detail/cancel sesuai perilaku yang disepakati, struktur item menu/luar-menu, bentuk nominal decimal, error, serta retry/idempotency. | Retry tidak menggandakan transaksi; nominal dan snapshot benar; detail penjualan dan header tidak terpisah; tenant scope terjaga. |
| M4 — Pembelian | Buat `pembelians` dan `pembelian_rincis` secara atomik. Dukung rincian bahan satu per satu maupun satu rincian ringkas seperti `Belanja di pasar`; setiap header wajib memiliki minimal satu rincian. Backend menghitung total dari subtotal rincian dan membatasi data pada warung. | Dokumentasikan create/list/detail dan laporan total pembelian per periode. Sertakan contoh kedua bentuk input, filter tanggal, response nominal, validasi, error, dan tenant scope. | Kedua bentuk pencatatan diterima; header dan rincian konsisten; laporan hanya menjumlahkan pembelian warung tersebut; pembelian tidak mengubah data penjualan atau stok. |
| M5 — Pengerasan dan rilis | Tinjau migrasi fresh/upgrade, otorisasi, integritas tenant, error handling, konfigurasi, backup/recovery, dan perilaku lintas milestone. | Pastikan OpenAPI lengkap dan cocok dengan implementasi; catat perubahan yang berdampak ke frontend dan siapkan contoh integrasi yang relevan. | Tidak ada endpoint undocumented untuk alur yang diserahkan; cakupan verifikasi dan batasan rilis dilaporkan. |

## Urutan kerja per milestone

1. Baca aturan dan artefak yang relevan; cek status repository backend.
2. Tulis ringkasan tindakan, keputusan sumber, fakta yang diubah, invariant, auth/tenant, transaksi/retry, API, acceptance, dan file.
3. Tutup keputusan yang menghalangi milestone. Jika perlu keputusan pengguna, tanyakan hanya hal yang belum bisa disimpulkan.
4. Draft/update OpenAPI untuk perilaku yang disepakati.
5. Implementasikan migration aman, model, application action/query, authorization, validation, dan endpoint.
6. Pastikan endpoint mengikuti kontrak; jalankan verifikasi sesuai lingkup tugas dan laporkan apa yang belum diuji.
7. Serahkan OpenAPI beserta contoh request/response dan error kepada frontend; perbarui kontrak dan changelog saat ada perubahan.
8. Tutup milestone dengan gate sebelum memulai milestone berikutnya.

## Keputusan yang perlu ditutup

Rincian rancangan ada pada bagian “Keputusan yang harus ditetapkan sebelum migration fitur” di `database/README.md`. Untuk rencana ini, keputusan tersebut dikelompokkan sebagai berikut:

### Harus ada sebelum M1

- Target database dan strategi migration untuk tabel `users` bawaan.
- Autentikasi API, cara client mengirim kredensial, API versioning/prefix, dan standar response/error.
- Perilaku tanggal warung yang nullable, timezone untuk masa aktif, keunikan email, serta operasi superadmin yang diizinkan.
- Strategi integritas foreign key/validasi tenant dan aturan penghapusan akun/warung.

### Harus ada sebelum M2

- Apakah kategori/menu dapat dihapus atau hanya dinonaktifkan/diarsipkan.
- Filter dan pagination minimum yang dibutuhkan frontend untuk layar katalog.

### Harus ada sebelum M3

- Rumus subtotal, diskon rincian/header, pembayaran, kembalian, presisi, dan pembulatan.
- Status dan operasi pembatalan transaksi; kebijakan retry/idempotency serta nomor transaksi.
- Makna timezone pada `penjualans.tanggal` dan bentuk tanggal/waktu di API.
- Batas operasi superadmin terkait transaksi.

### Harus ada sebelum M4

- Perilaku koreksi atau pembatalan pembelian setelah dicatat.
- Rumus subtotal pembelian ketika kuantitas dan harga satuan disediakan, serta aturan presisi/pembulatannya.
- Perilaku retry/idempotency dan penomoran transaksi pembelian.
- Rentang tanggal laporan pembelian dan zona waktu yang dipakai untuk menentukan periode.

## Hasil serah-terima backend ke frontend

Untuk setiap slice, serahkan:

1. Link ke operasi terkait dalam `docs/api/openapi.yaml`.
2. Contoh request/response sukses dan contoh error.
3. Kebutuhan auth dan role/scope.
4. Arti field nullable, status, angka decimal, dan tanggal.
5. Catatan perubahan kompatibilitas bila kontrak sebelumnya berubah.

## Bukan bagian dari rencana ini

Rancangan saat ini tidak mendefinisikan stok, meja, varian menu, refund, pajak, atau ledger. Jangan menambahkan tabel/alur domain tersebut tanpa requirement dan keputusan desain tersendiri. Rencana ini juga tidak mengunci engine, metode auth, bentuk route, ataupun semantik yang masih tercatat sebagai keputusan terbuka.
