# Aturan Database dan Migration

Aturan ini berlaku untuk file di folder `database/` dan mengikuti aturan backend di [`../AGENTS.md`](../AGENTS.md).

- Baca [`README.md`](README.md) sebelum membuat atau mengubah tabel. Catat keputusan skema baru di sana agar rancangan dan implementasi tetap sejalan.
- Jaga batas otoritas: README menjelaskan rancangan logis, migration Laravel menentukan struktur fisik yang berjalan, kode backend menjalankan aturan bisnis, dan kontrak API menentukan data yang dikirim ke frontend. Jika ada selisih, telusuri keputusan sumbernya lalu perbarui dokumen yang perlu; jangan membuat sumber kebenaran kedua.
- Perlakukan keputusan yang belum ada sebagai belum diketahui. Jangan mengarang default tanggal, aturan hapus, backfill, atau constraint bisnis hanya agar migration dapat dibuat; dokumentasikan keputusan yang dibutuhkan sebelum meneruskan.
- Buat skema fisik melalui Laravel migrations dan Schema Builder. Jangan mengubah database secara manual sebagai pengganti migration.
- Pertahankan urutan dependensi foreign key: tabel induk sebelum tabel yang mereferensikannya. Rancangan nama tabel memakai snake_case plural dan kolom mengikuti nama yang tercantum di README.
- Migration yang sudah diterapkan pada environment bersama/produksi tidak boleh diedit atau dihapus. Perbaiki dengan migration maju yang baru; gunakan rollback hanya untuk pekerjaan lokal yang belum dibagikan dan datanya aman dipulihkan.
- Jangan menghapus atau mengganti nama kolom/tabel berisi data tanpa langkah migrasi data dan rencana pemulihan yang jelas. Migration `down()` hanya membatalkan perubahan yang dibuat migration itu sendiri.
- Jangan menonaktifkan pemeriksaan foreign key atau membuat backfill berdasarkan tebakan. Backfill hanya boleh memakai fakta yang bisa ditelusuri ke sumber data yang berwenang.
- Pertahankan batas tenant pada query, validasi aplikasi, dan foreign key. Untuk relasi tenant-owned, gunakan FK gabungan yang memuat `warung_id` bila engine target mendukungnya dan target skema sudah diputuskan. Jika tidak, validasi eksplisit harus menolak hubungan lintas warung. Jangan mengandalkan ID global atau filter UI sebagai pengganti tenant scope.
- Tambahkan foreign key dan index pada kolom relasi/tenant yang dipakai untuk filter, serta unique constraint sesuai rancangan: `warungs.kode`, `users.username`, `(warung_id, menus.kode)`, dan `(warung_id, penjualans.no_transaksi)`. Jika aturan `ON DELETE` belum ditetapkan, pilih `RESTRICT` sebagai default aman lalu catat keputusan; jangan memilih cascade yang dapat menghapus riwayat transaksi.
- Gunakan tipe decimal untuk harga, diskon, subtotal, pembayaran, kembalian, dan kuantitas. Jangan gunakan FLOAT/DOUBLE untuk hitungan uang atau kuantitas; pertahankan angka desimal secara eksak sampai serialisasi API. Presisi tetap mengikuti rancangan LarisSama kecuali ada keputusan eksplisit untuk mengubahnya.
- Simpan snapshot item transaksi. Perubahan master menu tidak boleh menulis ulang nama/harga pada rincian penjualan yang sudah terjadi.
- Buat migration berdasarkan dependensi tabel. Jika siklus foreign key membuat urutan CREATE tidak mungkin, gunakan migration ALTER lanjutan yang didokumentasikan setelah tabel target tersedia.
- Hindari SQL khusus vendor database sampai target produksi ditetapkan. Jangan menyalin asumsi engine, isolation level, tipe ID, atau ukuran decimal dari proyek lain tanpa keputusan untuk LarisSama.
- Jangan menjalankan `migrate:fresh`, `db:wipe`, atau seeder destruktif pada database yang bukan database lokal sekali pakai. Tinjau nama migration, urutan, rollback, dan data yang terdampak sebelum menjalankan perubahan.
- Untuk implementasi migration, rencanakan verifikasi yang mencakup instalasi baru, constraint tenant, rollback/transisi data yang relevan, dan engine produksi. Hasil SQLite tidak membuktikan perilaku MySQL bila engine produksi berbeda; laporkan cakupan yang belum diverifikasi.
- Perbarui README rancangan ketika tipe, relasi, batasan unik, atau perilaku tenant berubah. Migration menggambarkan skema berjalan; README menjelaskan keputusan desain dan perilakunya.
