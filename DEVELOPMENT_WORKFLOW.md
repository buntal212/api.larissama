# Alur Kerja Pengembangan LarisSama

## Tujuan

Alur ini mengadaptasi cara kerja App POS: tetapkan sumber kebenaran, analisis invariant sebelum coding, bangun per milestone secara vertikal, lalu tutup tiap slice dengan acceptance. LarisSama masih berupa starter Laravel/Quasar dengan rancangan database awal; proses ini mengikuti kebutuhan LarisSama dan tidak menganggap arsitekturnya sudah dikunci.

## Lingkup tugas saat ini

Tanggung jawab implementasi adalah backend Laravel: schema/migration, aturan bisnis, otorisasi tenant, endpoint, dan dokumentasi kontrak API. Repo frontend tidak diubah. Untuk setiap slice, backend menyerahkan kontrak API yang stabil agar pengembang frontend dapat mengintegrasikan tanpa menebak struktur database atau payload.

## Batas otoritas

- Keputusan bisnis mengikuti instruksi terbaru yang sudah disetujui pengguna. Jika keputusan penting belum ada, tandai sebagai pertanyaan terbuka; jangan mengarang perilaku.
- [`database/README.md`](database/README.md) adalah rancangan logis database.
- Migration Laravel menunjukkan struktur fisik yang diterapkan.
- Backend menentukan otorisasi, tenant scope, validasi, dan transisi bisnis.
- Kontrak API menentukan bentuk request/response yang dipakai frontend.
- Jika rancangan, migration, kontrak, dan kode tidak cocok, laporkan drift dan sumber konflik sebelum mengubah perilaku.
- Gunakan [register keputusan](docs/backend/DECISIONS.md) untuk kebutuhan yang disepakati dan pilihan terbuka, [desain backend](docs/backend/DESIGN.md) untuk invariant, serta [test plan](docs/backend/TEST_PLAN.md) untuk acceptance.
- [Tracker](IMPLEMENTATION_PROGRESS.md) mencatat status task, test, commit, dan kesiapan handoff; dokumen rancangan bukan bukti implementasi.

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
→ draft/update API contract
→ migration aman
→ model dan validasi data
→ application action untuk write atau query untuk read
→ otorisasi, tenant scope, transaksi, dan error contract
→ endpoint yang sesuai dengan API contract
→ verifikasi backend dan handoff kontrak ke frontend
→ dokumentasi dan acceptance
```

- Letakkan efek yang harus berhasil/gagal bersama dalam satu transaksi dan tampilkan alurnya secara eksplisit pada application action.
- Jangan menyembunyikan perubahan bisnis penting di observer/listener.
- Backend memeriksa role dan `warung_id` dari identitas terautentikasi. Tampilan UI bukan pengganti otorisasi.
- Nominal uang/kuantitas diproses sebagai decimal; jangan gunakan floating point untuk hasil bisnis.
- Perubahan atau pembatalan transaksi harus mempertahankan fakta historis sesuai keputusan domain.
- Kontrak harus cukup stabil dan dipublikasikan sebelum handoff frontend. Implementasi UI dilakukan oleh pengembang frontend; tugas backend memastikan endpoint dan kontraknya cocok.

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

Roadmap mengikuti rancangan delapan tabel. [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) adalah indeks rencana; task dan dependency rinci dipelihara hanya di [IMPLEMENTATION_PROGRESS.md](IMPLEMENTATION_PROGRESS.md), sedangkan kriteria gate ada di [TEST_PLAN.md](docs/backend/TEST_PLAN.md).

| Milestone | Hasil dan gate |
| --- | --- |
| M0 | Runtime, keputusan awal, konvensi API, validator, harness dan DB test terisolasi; gate G0. |
| M1 | Warung, users, auth, policy, tenant, administrasi dan kontrak akses; gate G1. |
| M2 | Kategori/menu, validasi tenant, arsip dan kontrak katalog; gate G2. |
| M3 | Penjualan header-rinci, snapshot, nominal, nomor/retry, riwayat dan laporan pendapatan; gate G3. |
| M4 | Pembelian ringkas/rinci yang independen, nomor/retry, riwayat dan laporan total pembelian; gate G4. |
| M5 | Regression, environment, runbook, integrasi/handoff frontend dan rilis; gate G5. |

Jangan menaikkan status milestone hanya karena dokumen atau migration selesai. Operasi API tetap DRAFT sampai perilaku/izin final, implementasi, dan test yang diwajibkan tersedia. Handoff memakai operationId, versi spec, commit yang diuji, environment, dan bukti sesuai panduan API.

## Disiplin perubahan dan commit

- Backend dan frontend adalah repo Git terpisah. Periksa status serta diff repo yang dikerjakan; scope saat ini backend dan dokumentasinya.
- Pengguna sudah memberikan instruksi berkelanjutan: **setelah mengedit satu file, commit file itu sebelum mengedit file berikutnya**. Tidak perlu meminta izin commit ulang untuk perubahan dalam tugas yang telah diotorisasi.
- Sebelum commit: review diff, stage path file itu saja, pastikan staged names sesuai, dan jalankan git diff --cached --check. Jangan menyertakan perubahan pengguna/tim yang tidak terkait.
- Sesudah commit: catat hash dan periksa status. Jangan melakukan push, amend, atau rewrite commit bersama tanpa instruksi yang mengotorisasinya.
- Satu slice dapat mempunyai beberapa commit file. Catat semuanya di tracker, lalu jalankan verifikasi slice yang lengkap. Commit checkpoint tidak berarti slice telah lulus test atau siap rilis.
- Perubahan kontrak dan test yang diperlukan tetap bagian dari slice, walaupun commit dilakukan satu file sekali. Pertahankan urutan dependency agar penerus memahami checkpoint yang belum lengkap.

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
