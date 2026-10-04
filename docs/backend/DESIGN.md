# Rancangan Backend LarisSama

Status: rancangan backend; fondasi Laravel dan Sanctum sudah dipasang. Migration `warungs` dibuat dan berhasil diterapkan pada clean-install lokal MySQL 8.0.40; migration bisnis lain dan endpoint belum diimplementasikan. Dasar: [delapan tabel](../../database/README.md), [aturan backend](../../AGENTS.md), dan keputusan K01–K09 di [register keputusan](DECISIONS.md). Pilihan bertanda Dxx masih menunggu penetapan. Urutan pekerjaan dan bukti pelaksanaan berada di [tracker](../../IMPLEMENTATION_PROGRESS.md).

## Kondisi awal yang diamati

- composer.json meminta PHP ^8.3 dan Laravel ^13.17; itu constraint proyek, bukan bukti runtime terpasang.
- Kolom User masih bawaan (`name`, `email`, `password`); trait Sanctum sudah ditambahkan. Migration `warungs` berhasil diterapkan pada clean-install lokal; migration bisnis lain belum ada. Migration users/cache/jobs dan `personal_access_tokens` adalah infrastruktur.
- `bootstrap/app.php` mendaftarkan API; `routes/api.php` masih kosong dan belum menyediakan endpoint.
- Test yang tersedia hanya contoh Unit dan Feature. Tidak ada bukti tenant, nominal, transaksi, atau kontrak bisnis sudah lulus.
- Pemeriksaan 2026-10-04: PHP dan Composer tidak tersedia di host, tetapi image Docker opsional menyediakan PHP 8.3.35 dan Composer 2.10.3. Clean-install migrations berjalan di MySQL 8.0.40 lokal; harness test dan upgrade data lama belum diverifikasi (lihat `DB-MIGRATION-001`).

## Modul dan hasil bagi pengguna

| Modul | Tabel | Perilaku yang direncanakan |
| --- | --- | --- |
| Akses | warungs, users | Login, profil, logout, pembatasan user/warung aktif, izin role. |
| Administrasi | warungs, users | Superadmin mengelola warung; kandidat provisioning membuat warung dan owner awal secara atomik (D04). Owner mengelola user warung. |
| Katalog | kategori_menus, menus | Daftar, detail, tambah, ubah kategori/menu dan status aktif. |
| Penjualan | penjualans, penjualan_rincis | Catat menu terdaftar, baca riwayat/detail, pertahankan snapshot nama/harga jual. |
| Pembelian | pembelians, pembelian_rincis | Catat pembelian bahan rinci atau ringkas, baca riwayat/detail. |
| Laporan | query header transaksi | Pendapatan penjualan dan total pembelian dalam periode terpilih, terpisah per warung. |

Tidak ada workflow dapur atau pengaitan pembelian dengan stok/resep. Field legacy `harga_modal` tidak dipakai oleh API penjualan/pembelian. Perhitungan HPP atau laba bukan keluaran yang disepakati.

## Alur request dan struktur kode yang disarankan

```mermaid
flowchart LR
    A[HTTP API] --> B[Auth dan status user/warung]
    B --> C[Tenant context dan policy]
    C --> D[FormRequest]
    D --> E[Action write atau Query read]
    E --> F[Model dan database]
    F --> G[API Resource sesuai OpenAPI]
```

Urutan aktual middleware/binding harus memastikan resource tenant lain tidak dapat diakses sebelum policy dijalankan. Route binding ID saja tidak cukup.

```text
routes/api.php
app/Http/Controllers/Api/V1/{Auth,Admin,KategoriMenu,Menu,Penjualan,Pembelian,Laporan}Controller.php
app/Http/Requests/                      validasi struktur dan field
app/Http/Resources/                     serialisasi sesuai kontrak
app/Http/Middleware/                    auth, status aktif, tenant context
app/Policies/                          izin tindakan dan scope
app/Actions/{Penjualan,Pembelian}/      write dalam transaksi eksplisit
app/Queries/                           daftar, detail, agregasi read-only
app/Support/                           helper decimal/tenant bila diperlukan
app/Models/                            delapan entitas bisnis
tests/{Unit,Feature,Integration,Contract}/
```

Nama class adalah usulan organisasi; tidak perlu membuat semua folder atau menambahkan repository abstraction lebih dulu. Controller hanya menghubungkan HTTP, authorization, action/query, dan resource. Efek wajib tidak disembunyikan dalam observer/listener. Framework/package API diverifikasi saat implementasi terhadap versi yang terpasang.

## Pembagian peran yang disetujui (D04)

| Tanggung jawab inti | superadmin | owner | manager | kasir |
| --- | --- | --- | --- | --- |
| Mengelola warung dan membuat owner awal melalui jalur admin | Ya | Tidak | Tidak | Tidak |
| Mengelola user pada warung sendiri | Tidak melalui jalur tenant | Ya | Tidak | Tidak |
| Mengelola katalog, pembelian, dan laporan | Tidak melalui jalur tenant | Belum ditetapkan | Ya | Tidak |
| Menangani penjualan | Tidak melalui jalur tenant | Belum ditetapkan | Belum ditetapkan | Ya |
| Bertindak sebagai user tenant tanpa autentikasi tenant | Tidak | — | — | — |

User menyetujui tanggung jawab inti ini pada 2026-10-04. Izin baca versus ubah di tiap modul, akses owner ke selain user, dan cakupan riwayat penjualan kasir masih menunggu rincian. Sampai diputuskan, policy mengikuti default deny. Superadmin memakai jalur administrasi terpisah dan tidak memperoleh akses transaksi tenant. Request tidak boleh menaikkan role; pengelolaan user tenant tidak boleh membuat superadmin. Perubahan email/username tetap tunduk pada D12.

## Integritas data dan migration

1. Inventaris tabel/users yang sudah ada dan riwayat migration di environment tujuan. Jangan menjalankan composer setup tanpa meninjau script migrate-nya.
2. Buat warungs, lalu sesuaikan users menggunakan migration maju jika migration awal sudah dibagikan. Pemetaan user lama ke warung perlu sumber data berwenang.
3. Buat kategori_menus sebelum menus; buat penjualans sebelum penjualan_rincis; buat pembelians sebelum pembelian_rincis.
4. Pertahankan unique global warungs.kode/users.username serta unique `(warung_id, kode)` menu dan `(warung_id, no_transaksi)` masing-masing header.
5. Gunakan FK/index relasi; kandidat index laporan `(warung_id, tanggal, id)`, dan filter status penjualan sesuai hasil query plan. Pilihan fisik menunggu D01.
6. D01 menentukan strategi FK tenant gabungan. Walaupun ID unik global, backend tetap mengecek kategori/menu/user dari warung yang sama. Detail mengikuti tenant melalui header induk.
7. Rincian tidak memiliki endpoint CRUD bebas. Tindakan bisnis mengelola header dan rincian dalam satu transaksi. D06/D11 menentukan koreksi dan penghapusan.

## Invariant dan rancangan tindakan

| ID | Invariant | Penegakan |
| --- | --- | --- |
| INV01 | Warung biasa berasal dari user terautentikasi. | Abaikan sebagai otoritas dan tolak field tenant yang tidak didukung pada request; filter setiap query/route lookup. |
| INV02 | Semua referensi milik warung yang sama. | Validasi tenant dan FK sesuai engine; detail dibaca melalui header terscope. |
| INV03 | User aktif dan warung aktif dalam masa berlaku. | Periksa login serta setiap request; `tanggal_mulai` NULL tidak membatasi awal, `tanggal_berakhir` NULL tidak membatasi akhir, dan tanggal terisi inklusif; token kedaluwarsa atau status nonaktif tidak boleh diterima. |
| INV04 | Header memiliki >= 1 detail, tanpa penyimpanan sebagian. | Validasi array dan DB transaction; kegagalan detail me-rollback header, total, nomor, serta efek retry. |
| INV05 | Nominal eksak dan dihitung backend. | Decimal, validasi batas/rounding D05; total dari detail, bukan total client. |
| INV06 | Setiap penjualan memilih menu terdaftar pada warung yang sama; riwayat menyimpan nama/harga jual saat transaksi. | `menu_id` wajib pada detail, menu di-resolve di scope warung dan snapshot disimpan dalam action; perubahan master tidak menulis ulang rincian. |
| INV07 | Pembelian ringkas sah. | `nama_item` + `subtotal` menjadi satu detail; qty/satuan/harga_satuan nullable. |
| INV08 | Pembelian tidak memengaruhi penjualan/menu/stok. | Action hanya menulis pembelian dan infrastruktur yang disetujui. |
| INV09 | Retry/concurrency tidak menggandakan transaksi. | D09 harus diputuskan dan diuji dengan dua request/koneksi; disable retry UI saja tidak memenuhi syarat. |
| INV10 | Laporan tidak bocor tenant atau menggandakan header. | Aggregate header terfilter; jangan SUM(header.total) setelah join one-to-many rincian. |
| INV11 | Kontrak mencerminkan implementasi. | Response divalidasi terhadap schema, parameter/peran diuji; handoff READY membutuhkan bukti. |

### Penjualan

Action membaca setiap menu dalam scope warung, memeriksa aktif, mengambil harga/nama jual yang sah saat pencatatan, menghitung setiap subtotal, lalu menyimpan header dan semua snapshot detail. Setiap rincian harus mempunyai `menu_id`; transaksi dengan item bebas tidak diterima. Harga kiriman client tidak menjadi otoritas. D05 menentukan respons terhadap perubahan harga bersamaan; snapshot harus konsisten dengan pembacaan dalam transaksi.

Kandidat rumus D05: `subtotal_rinci = round(harga_jual × qty - diskon_rinci, 2)`; `subtotal_header = SUM(subtotal_rinci)`; `total = subtotal_header - diskon_header`; `kembalian = bayar - total` untuk cash. Validasi mencegah total negatif/diskon berlebih; QRIS/transfer perlu aturan eksplisit. `bayar` bukan pendapatan. Kebijakan cancel penjualan masih D06.

Action harus mengaitkan nomor transaksi dan hasil retry dengan commit bisnis yang sama. Tentukan durable storage dan perilaku konflik D09 sebelum membuat endpoint write siap produksi. Kegagalan di detail terakhir tidak meninggalkan header/rincian awal.

### Pembelian

Ringkas: satu detail `nama_item="Belanja di pasar"`, `subtotal="150000.00"`; qty/satuan/harga_satuan NULL. Rinci: beberapa nama bahan dengan qty/harga_satuan, subtotal dihitung backend. Bentuk wire kandidat berada di OpenAPI; D10 menutup kasus input sebagian dan mismatch subtotal. Tidak ada lookup menu atau syarat master bahan.

Action menetapkan warung/user dari identitas terautentikasi, menghitung total semua detail, dan menyimpan semuanya atomik. Input header.total tidak dipercaya. Bentuk ringkas dan rinci menggunakan endpoint serta tabel yang sama. Tidak ada status `selesai`/`batal` pembelian yang boleh dibuat diam-diam; D11 harus menambah schema dan kontrak jika dibutuhkan.

### Laporan periode

Kandidat D08: query `tanggal >= start_of_day(date_from, zone)` dan `tanggal < start_of_next_day(date_to, zone)` setelah dikonversi ke zona penyimpanan. Tanggal akhir inklusif bagi pengguna; jangan memakai 23:59:59 yang bisa melewatkan pecahan detik.

- Pendapatan: jumlah `penjualans.total` dengan status selesai pada periode. Tidak mengambil bayar/kembalian, nama/harga menu terbaru, atau total pembelian.
- Pembelian: jumlah `pembelians.total` pada periode; aturan transaksi yang dikoreksi menunggu D11.
- Count adalah jumlah header; detail tidak menggandakan count maupun total. Summary mencakup semua hasil filter, tidak hanya halaman list.
- Periode kosong menghasilkan count 0 dan total `"0.00"` setelah query berhasil. Error jaringan/izin tidak boleh dikonversi ke nol oleh frontend.

## Keamanan, operasional, dan handoff

Password/token tidak keluar resource atau log. Sanctum bearer token dengan masa berlaku 30 hari sudah dipilih dan package v4.3.3 terpasang; konfigurasi kedaluwarsa dan logout harus menerapkan keputusan ini. Rate limit login, HTTPS/CORS, penyimpanan rahasia, dan detail deployment masih perlu ditetapkan sebelum BE-102/BE-502 selesai. Error JSON tidak menampilkan SQL atau kredensial; request ID membantu penelusuran. Halaman di luar scope menggunakan respons yang konsisten menurut D13.

Runbook rilis yang dibuat pada BE-502 harus berisi prasyarat versi, konfigurasi tanpa rahasia, migrasi upgrade, backup/restore yang diuji pada salinan, rollback aplikasi, endpoint health, log, dan keterbatasan. Hindari migration destruktif pada data bersama.

Satu fitur selesai setelah task, test, kontrak, contoh payload, dan bukti commit terhubung di tracker. Frontend menerima operationId yang READY beserta base URL environment, auth, parameter, contoh sukses/error, dan release commit. Kontrak draft tidak menjadi bukti endpoint sudah tersedia.
