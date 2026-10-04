# Alur Kerja Pengembangan LarisSama

## Tujuan

Alur ini mengadaptasi cara kerja App POS: tetapkan sumber kebenaran, analisis invariant sebelum coding, bangun per milestone secara vertikal, lalu tutup tiap slice dengan acceptance. LarisSama masih berupa starter Laravel/Quasar dengan rancangan database awal; proses ini mengikuti kebutuhan LarisSama dan tidak menganggap arsitekturnya sudah dikunci.

## Batas otoritas

- Keputusan bisnis mengikuti instruksi terbaru yang sudah disetujui pengguna. Jika keputusan penting belum ada, tandai sebagai pertanyaan terbuka; jangan mengarang perilaku.
- [`database/README.md`](database/README.md) adalah rancangan logis database.
- Migration Laravel menunjukkan struktur fisik yang diterapkan.
- Backend menentukan otorisasi, tenant scope, validasi, dan transisi bisnis.
- Kontrak API menentukan bentuk request/response yang dipakai frontend.
- Jika rancangan, migration, kontrak, dan kode tidak cocok, laporkan drift dan sumber konflik sebelum mengubah perilaku.

## Alur untuk setiap pekerjaan

### 1. Pahami permintaan dan batas perubahan

- Baca `AGENTS.md` pada repo backend/frontend yang tersentuh serta aturan database bila skema berubah.
- Periksa struktur dan status Git repo terkait agar perubahan lokal yang sudah ada tetap utuh.
- Baca hanya rancangan, route, model, migration, API, dan komponen yang terkait dengan tugas.
- Tentukan apakah pekerjaan berupa schema/migration, command, query, API, UI, atau gabungannya.

### 2. Buat ringkasan pra-implementasi

Untuk pekerjaan non-trivial, tulis ringkasan singkat berisi:

```text
tujuan dan milestone
sumber aturan/keputusan
data yang dibaca atau diubah
state transition dan invariant
otorisasi dan warung scope
batas transaksi dan retry/concurrency
perubahan API dan frontend
acceptance serta verifikasi yang relevan
file yang diperkirakan berubah
```

Kalau semua keputusan tersedia, lanjutkan tanpa meminta konfirmasi ulang. Minta keputusan hanya jika ada ambiguitas yang mengubah data, keamanan, uang, atau perilaku pengguna.

### 3. Siapkan desain dan dependency

- Pastikan setiap fakta punya satu sumber kebenaran; jangan menambah kolom atau tabel duplikat hanya untuk memudahkan tampilan.
- Tentukan relasi dan urutan migration sebelum menulis migration.
- Tentukan state yang boleh berpindah dan aksi backend yang memindahkannya; hindari perubahan status bebas dari request.
- Untuk operasi yang dapat dicoba ulang, tetapkan perilaku idempotensi sebelum endpoint dibuat.
- Buat rencana slice yang kecil. Jangan membuat semua migration dahulu lalu menunda seluruh perilaku dan UI sampai akhir.

### 4. Implementasikan satu vertical slice

Urutan umum untuk satu fitur:

```text
keputusan/skema yang disetujui
→ migration aman
→ model dan validasi data
→ application action untuk write atau query untuk read
→ otorisasi, tenant scope, transaksi, dan error contract
→ API contract
→ frontend API integration, state, dan UI
→ dokumentasi dan acceptance
```

- Letakkan efek yang harus berhasil/gagal bersama dalam satu transaksi dan tampilkan alurnya secara eksplisit pada application action.
- Jangan menyembunyikan perubahan bisnis penting di observer/listener.
- Backend memeriksa role dan `warung_id` dari identitas terautentikasi. Tampilan UI bukan pengganti otorisasi.
- Nominal uang/kuantitas diproses sebagai decimal; jangan gunakan floating point untuk hasil bisnis.
- Perubahan atau pembatalan transaksi harus mempertahankan fakta historis sesuai keputusan domain.
- Mulai implementasi UI setelah kontrak backend untuk slice itu cukup stabil. Jangan menumpuk semua pekerjaan UI di akhir.

### 5. Verifikasi dan acceptance

Sebelum menyebut slice selesai, cocokkan hasil dengan acceptance yang ditetapkan. Periksa migration, batas tenant, transaksi, kondisi error, payload API, dan perilaku UI yang relevan. Tetapkan tes/check yang diperlukan saat merencanakan pekerjaan; jalankan sesuai lingkup verifikasi yang diminta dan laporkan dengan jujur mana yang belum dijalankan.

Satu slice diterima bila:

- kebutuhan pengguna dan sumber keputusannya dapat ditelusuri;
- skema, backend, API, dan UI sepakat pada perilaku yang sama;
- invariant, tenant scope, dan otorisasi tidak hanya bergantung pada frontend;
- migration aman terhadap data yang ada;
- dokumentasi diperbarui bila kontrak atau keputusan berubah;
- pemeriksaan yang dijalankan dan yang belum dijalankan dilaporkan.

## Urutan milestone LarisSama

Urutan berikut adalah roadmap awal berdasarkan rancangan enam tabel. Ini bukan keputusan bisnis baru; aturan fitur yang belum ditetapkan harus disepakati sebelum slice terkait dimulai.

### M0 — Kesiapan proyek

- Verifikasi runtime/toolchain, cara menjalankan backend dan frontend, serta status migration awal.
- Putuskan database produksi, pendekatan autentikasi/API, representasi nominal decimal, dan aturan timezone yang dibutuhkan slice pertama.
- Tentukan rencana penyesuaian tabel `users` bawaan Laravel tanpa menghilangkan data bila migration sudah dipakai.
- Pastikan frontend dapat mencapai endpoint backend yang aman untuk diagnosis. Jika perlu, gunakan endpoint health/diagnostic tanpa membuat fakta operasional.

**Gate M0:** toolchain dan kontrak komunikasi diketahui; keputusan yang diperlukan untuk login/tenant sudah tersedia; tidak ada konflik migration awal yang belum dipahami.

### M1 — Warung, user, dan akses

- Implementasikan `warungs` dan penyesuaian `users`.
- Bangun login, role, tenant scope, status aktif, dan masa aktif warung.
- Selesaikan arti tanggal nullable, keunikan email, dan jalur superadmin sebelum migration/endpoint yang bergantung padanya.
- Lengkapi alur UI autentikasi dan penanganan akses ditolak.

**Gate M1:** user tenant hanya dapat mengakses data warungnya; akses aktif/kedaluwarsa diperiksa backend; superadmin memiliki jalur yang ditentukan.

### M2 — Kategori dan menu

- Implementasikan `kategori_menus` dan `menus` beserta relasi, keunikan kode per warung, dan perlindungan dari relasi lintas warung.
- Selesaikan CRUD/arsip dan perilaku penghapusan master sebelum menulis endpoint.
- Bangun UI kategori/menu melalui kontrak API yang sama.

**Gate M2:** tenant tidak dapat membaca atau mengubah kategori/menu warung lain; validasi API dan UI konsisten.

### M3 — Penjualan

- Implementasikan header `penjualans` dan rincian `penjualan_rincis` secara atomik.
- Tetapkan lebih dulu rumus subtotal/diskon, aturan pembayaran/kembalian, nomor transaksi, retry/idempotensi, dan pembatalan.
- Simpan snapshot nama/harga; dukung item `luar_menu` dengan `menu_id = NULL` sesuai rancangan.
- Bangun alur kasir dan riwayat transaksi setelah kontrak API stabil.

**Gate M3:** retry tidak menggandakan transaksi; angka dihitung/diterima backend sesuai aturan; riwayat mempertahankan snapshot; transaksi tenant terisolasi.

### M4 — Pengerasan dan rilis

- Tinjau migrasi upgrade, authorization, error handling, backup/recovery, konfigurasi environment, dan alur end-to-end yang sudah dibangun.
- Tutup keputusan tersisa berdasarkan fitur yang benar-benar akan dirilis; jangan menambahkan domain baru tanpa kebutuhan.

**Gate M4:** alur rilis dapat diulang dan keterbatasan/keputusan yang belum selesai tercatat.

## Disiplin perubahan

- Backend dan frontend adalah repo Git terpisah. Periksa status serta diff masing-masing repo; jangan mencampur perubahan yang tidak berkaitan.
- Jaga setiap perubahan tetap reviewable sebagai satu fitur/perbaikan yang koheren. Migration, perilaku backend, kontrak API, dan UI yang membentuk satu slice sebaiknya ditinjau bersama.
- Jangan membuat commit otomatis. Stage/commit hanya setelah diminta pengguna, dan pastikan hanya file untuk slice tersebut yang masuk.

## Kondisi untuk berhenti dan meminta keputusan

Hentikan bagian yang terdampak dan laporkan bila:

- aturan bisnis penting saling bertentangan atau belum punya keputusan;
- sumber kebenaran atau transisi status belum jelas;
- migration memerlukan backfill yang tidak memiliki sumber data berwenang;
- relasi dapat menembus tenant dan perlindungannya belum diputuskan;
- perubahan berisiko menghapus atau menulis ulang fakta transaksi.

Perbedaan gaya penamaan atau preferensi framework bukan alasan untuk mendesain ulang fitur yang sudah disetujui.

## Yang diadaptasi dari App POS

Diadaptasi: analisis pra-code, sumber kebenaran yang jelas, milestone dengan gate, vertical slice backend–API–frontend, invariant dan acceptance per fitur, serta laporan verifikasi yang jujur.

Tetap spesifik LarisSama: engine dan tipe ID mengikuti keputusan LarisSama; tidak menyalin MySQL 8, ULID, ukuran decimal App POS, mode DEMO_LOCAL/IndexedDB, larangan query khusus App POS, atau seluruh domain ERP/ledger/approval mereka.
