# Rencana Database LarisSama

## Status dokumen

Dokumen ini adalah rancangan logis yang dipindahkan dari `database.md` di folder Downloads. Delapan tabel bisnisnya belum dibuatkan migration. Setelah migration diterapkan, migration Laravel menjadi sumber kebenaran untuk struktur fisik database; perbarui dokumen ini bila keputusan skema berubah.

## Batas otoritas

- Dokumen ini menjelaskan rancangan logis dan keputusan yang masih terbuka.
- Migration Laravel adalah sumber kebenaran untuk struktur fisik yang benar-benar berjalan.
- Kode backend adalah sumber kebenaran untuk validasi, otorisasi, dan transisi bisnis.
- Kontrak API menentukan bentuk data yang dikonsumsi frontend; frontend tidak menjadi sumber kebenaran bisnis.
- Jika sumber-sumber itu berbeda, telusuri keputusan yang mendasarinya dan perbarui artefak yang terkait. Jangan menyelesaikan konflik dengan menebak atau hanya mengubah dokumen turunan.

Rancangan ini mencakup delapan tabel bisnis: `warungs`, `users`, `kategori_menus`, `menus`, `penjualans`, `penjualan_rincis`, `pembelians`, dan `pembelian_rincis`. Tabel infrastruktur framework, termasuk `sessions`, `cache`, `jobs`, `password_reset_tokens`, dan Sanctum `personal_access_tokens`, berada di luar hitungan tersebut. Aplikasi tidak memakai tabel `mejas` atau `menu_varians`.

## Aturan inti

- Satu aplikasi mengelola banyak warung.
- Satu warung dapat memiliki banyak user; setiap user biasa terhubung ke tepat satu warung.
- `users.warung_id` boleh `NULL` hanya untuk `superadmin`.
- Warung memiliki status aktif dan rentang masa aktif.
- Kategori menu dan menu milik satu warung.
- Satu penjualan memiliki satu kasir dan banyak rincian.
- Rincian penjualan menyimpan snapshot nama dan harga supaya perubahan data menu tidak mengubah riwayat transaksi.
- Setiap rincian penjualan wajib merujuk ke menu pada warung yang sama; nama dan harga jual disimpan sebagai snapshot.
- Satu pembelian dicatat untuk satu warung dan satu user pencatat, lalu memiliki satu atau lebih rincian.
- Rincian pembelian boleh berupa item bahan satu per satu atau satu baris ringkasan, misalnya `Belanja di pasar` dengan nominal total.
- Pembelian dan penjualan berdiri sendiri. Pembelian tidak mengubah stok dan tidak terhubung ke menu, resep, atau rincian penjualan.
- Total pembelian pada header sama dengan penjumlahan `subtotal` seluruh rincian dan dihitung backend.

## Relasi

```mermaid
erDiagram
    WARUNGS ||--o{ USERS : memiliki
    WARUNGS ||--o{ KATEGORI_MENUS : memiliki
    WARUNGS ||--o{ MENUS : memiliki
    KATEGORI_MENUS ||--o{ MENUS : mengelompokkan
    WARUNGS ||--o{ PENJUALANS : mencatat
    USERS ||--o{ PENJUALANS : melayani
    PENJUALANS ||--|{ PENJUALAN_RINCIS : berisi
    MENUS o|--o{ PENJUALAN_RINCIS : sumber_menu
    WARUNGS ||--o{ PEMBELIANS : mencatat
    USERS ||--o{ PEMBELIANS : menginput
    PEMBELIANS ||--|{ PEMBELIAN_RINCIS : berisi
```

## Tabel dan kolom

Tipe berikut menjelaskan maksud desain. Migration harus memakai tipe Laravel yang sesuai dengan database target, menjaga presisi angka, serta membuat foreign key dan index yang diperlukan.

### `warungs`

| Kolom | Tipe/rule |
| --- | --- |
| `id` | BIGINT primary key |
| `kode` | VARCHAR(30), unique |
| `nama` | VARCHAR(150) |
| `alamat` | TEXT, nullable |
| `telepon` | VARCHAR(30), nullable |
| `logo` | VARCHAR(255), nullable |
| `tanggal_mulai` | DATE, nullable pada rancangan |
| `tanggal_berakhir` | DATE, nullable pada rancangan |
| `aktif` | BOOLEAN, default true |
| `created_at`, `updated_at` | timestamp |

### `users`

| Kolom | Tipe/rule |
| --- | --- |
| `id` | BIGINT primary key |
| `warung_id` | BIGINT foreign key, nullable hanya untuk superadmin |
| `nama` | VARCHAR(150) |
| `username` | VARCHAR(100), unique global sesuai rancangan |
| `email` | VARCHAR(150), nullable; aturan uniqueness perlu diputuskan |
| `password` | VARCHAR(255), simpan hash melalui mekanisme Laravel |
| `role` | VARCHAR(30); contoh: `superadmin`, `owner`, `manager`, `kasir`, `koki` |
| `aktif` | BOOLEAN, default true |
| `created_at`, `updated_at` | timestamp |

Relasi: satu warung memiliki banyak user. Validasi aplikasi harus memastikan user tanpa warung benar-benar ber-role `superadmin` dan user dengan role lain memiliki warung.

### `kategori_menus`

| Kolom | Tipe/rule |
| --- | --- |
| `id` | BIGINT primary key |
| `warung_id` | BIGINT foreign key |
| `nama` | VARCHAR(100) |
| `urutan` | INT, default 0 |
| `aktif` | BOOLEAN, default true |
| `created_at`, `updated_at` | timestamp |

### `menus`

| Kolom | Tipe/rule |
| --- | --- |
| `id` | BIGINT primary key |
| `warung_id` | BIGINT foreign key |
| `kategori_menu_id` | BIGINT foreign key |
| `kode` | VARCHAR(30), unique bersama `warung_id` |
| `nama` | VARCHAR(150) |
| `harga` | DECIMAL(15,2) |
| `harga_modal` | DECIMAL(15,2), nullable |
| `gambar` | VARCHAR(255), nullable |
| `deskripsi` | TEXT, nullable |
| `aktif` | BOOLEAN, default true |
| `created_at`, `updated_at` | timestamp |

Kategori dan menu harus berasal dari warung yang sama.

### `penjualans`

| Kolom | Tipe/rule |
| --- | --- |
| `id` | BIGINT primary key |
| `warung_id` | BIGINT foreign key |
| `user_id` | BIGINT foreign key kasir |
| `no_transaksi` | VARCHAR(50), unique bersama `warung_id` |
| `tanggal` | DATETIME |
| `subtotal` | DECIMAL(15,2) |
| `diskon` | DECIMAL(15,2), default 0 |
| `total` | DECIMAL(15,2) |
| `bayar` | DECIMAL(15,2) |
| `kembalian` | DECIMAL(15,2), default 0 |
| `metode_pembayaran` | VARCHAR(30); contoh: `cash`, `qris`, `transfer` |
| `status` | VARCHAR(20); contoh: `selesai`, `batal` |
| `catatan` | TEXT, nullable |
| `created_at`, `updated_at` | timestamp |

Relasi: satu warung dan satu user dapat terkait dengan banyak penjualan. User pencatat dan penjualan harus berada pada tenant yang sama, kecuali alur superadmin yang diotorisasi secara eksplisit.

### `penjualan_rincis`

| Kolom | Tipe/rule |
| --- | --- |
| `id` | BIGINT primary key |
| `penjualan_id` | BIGINT foreign key |
| `menu_id` | BIGINT foreign key wajib |
| `nama_menu` | VARCHAR(150), snapshot nama item |
| `harga` | DECIMAL(15,2), snapshot harga saat transaksi |
| `qty` | DECIMAL(10,2) |
| `diskon` | DECIMAL(15,2), default 0 |
| `subtotal` | DECIMAL(15,2) |
| `catatan` | TEXT, nullable |
| `created_at`, `updated_at` | timestamp |

Setiap detail memakai `menu_id` dari warung transaksi serta snapshot `nama_menu` dan harga jual saat transaksi. Tidak ada rincian item bebas. Perubahan master menu tidak boleh menulis ulang snapshot transaksi yang sudah terjadi.

### `pembelians`

| Kolom | Tipe/rule |
| --- | --- |
| `id` | BIGINT primary key |
| `warung_id` | BIGINT foreign key |
| `user_id` | BIGINT foreign key user pencatat |
| `no_transaksi` | VARCHAR(50), unique bersama `warung_id` |
| `tanggal` | DATETIME |
| `total` | DECIMAL(15,2), jumlah seluruh subtotal rincian |
| `catatan` | TEXT, nullable |
| `created_at`, `updated_at` | timestamp |

Relasi: satu warung dan satu user dapat terkait dengan banyak pembelian. User pencatat dan header pembelian harus berasal dari warung yang sama, kecuali alur superadmin yang diotorisasi secara eksplisit.

### `pembelian_rincis`

| Kolom | Tipe/rule |
| --- | --- |
| `id` | BIGINT primary key |
| `pembelian_id` | BIGINT foreign key |
| `nama_item` | VARCHAR(150), nama bahan atau keterangan, misalnya `Belanja di pasar` |
| `qty` | DECIMAL(10,2), nullable |
| `satuan` | VARCHAR(30), nullable |
| `harga_satuan` | DECIMAL(15,2), nullable |
| `subtotal` | DECIMAL(15,2), nominal rincian yang wajib diisi |
| `created_at`, `updated_at` | timestamp |

Setiap header pembelian harus memiliki minimal satu rincian. Untuk pencatatan lengkap, buat satu baris per bahan dan backend menghitung subtotal dari kuantitas serta harga satuan yang diberikan. Untuk pencatatan ringkas, buat satu baris dengan `nama_item` berisi keterangan umum, misalnya `Belanja di pasar`; `qty`, `satuan`, dan `harga_satuan` boleh `NULL`, sedangkan `subtotal` berisi nominal total. Backend menghitung `pembelians.total` dari seluruh subtotal dalam transaksi database yang sama.

Rincian pembelian adalah catatan bebas, bukan master bahan atau catatan stok. `pembelian_rincis` hanya terhubung ke header pembelian dan tidak memiliki relasi ke menu, resep, stok, maupun penjualan. Laporan periode menjumlahkan total pembelian berdasarkan `warung_id` dan tanggal header.

## Batas akses warung

User tenant dapat login dan memakai API hanya jika seluruh kondisi ini terpenuhi:

```text
user.aktif = TRUE
warung.aktif = TRUE
(tanggal_mulai IS NULL OR tanggal_mulai <= tanggal hari ini)
(tanggal_berakhir IS NULL OR tanggal_berakhir >= tanggal hari ini)
```

Pemeriksaan dilakukan saat login dan pada setiap request API terautentikasi agar token lama tidak melewati masa aktif. Batas tanggal terisi bersifat inklusif. User telah menetapkan bahwa `NULL` pada `tanggal_mulai` berarti tidak ada batas mulai dan `NULL` pada `tanggal_berakhir` berarti tidak ada batas akhir (2026-10-04). Status aktif user dan warung tetap wajib.

## Keputusan yang harus ditetapkan sebelum migration fitur

1. Vendor dan versi minimum database produksi serta collation yang dipakai. Keluarga MySQL/MariaDB sudah dipilih; validasi integrasi final menunggu target vendor/versi yang pasti.
2. Apakah email nullable tetap unique global. Migration Laravel bawaan saat ini mewajibkan email dan membuatnya unique, sedangkan rancangan meminta email nullable.
3. Aturan hapus/perubahan untuk warung, user, kategori, menu, penjualan, pembelian, dan rincian. Snapshot rincian perlu tetap utuh; transaksi tidak boleh hilang hanya karena master dihapus. Jika belum ada keputusan, gunakan `RESTRICT` sebagai default aman.
4. Cara database dan aplikasi mencegah `kategori_menu_id`, `menu_id`, kasir, penjualan, pembelian, dan user pencatat menghubungkan data dari warung berbeda, termasuk apakah engine target akan memakai foreign key gabungan dengan `warung_id`.
5. Batas nilai dan pembulatan uang, serta rumus subtotal/diskon header dan rincian.
6. Apakah daftar nilai role, metode pembayaran, dan status dijaga sebagai konstanta/enum aplikasi atau constraint database. Pembagian tanggung jawab inti superadmin/owner/manager/kasir disetujui; detail izin per operasi mengikuti D04.
7. Perilaku idempotensi untuk request pembuatan/finalisasi penjualan yang dapat dicoba ulang, agar retry tidak menggandakan transaksi.
8. Arti zona waktu pada `penjualans.tanggal` dan `pembelians.tanggal`: apakah itu instant tersimpan dalam UTC atau waktu lokal warung, serta bagaimana zona waktu bisnis ditetapkan untuk filter laporan periode.
9. Aturan koreksi atau pembatalan pembelian setelah dicatat, termasuk dampaknya pada laporan dan apakah perlu status khusus.

## Kondisi proyek saat dokumen dibuat

Backend masih memakai starter Laravel dan belum memiliki migration untuk delapan tabel bisnis. Migration framework saat ini menyediakan `users` dengan `name`, `email` non-null unique, `email_verified_at`, `password`, `remember_token`, tabel session/password reset, cache/jobs, serta migration Sanctum untuk `personal_access_tokens`. Ini belum sama dengan rancangan bisnis di atas. Periksa apakah migration pernah dijalankan atau database sudah berisi data sebelum menentukan cara transisi; jangan mengubah migration yang telah dipakai bersama.
