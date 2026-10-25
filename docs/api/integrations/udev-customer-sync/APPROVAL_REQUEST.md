# Keputusan dan Persetujuan Bertahap Integrasi Larissama–UDEV

Status: **Customer sync, katalog paket, dan integrasi invoice/status pembayaran disetujui bertahap; uji transaksi pembayaran Sandbox belum dilakukan**
Tanggal keputusan Tahap 1: 2026-10-24
Rencana master: [INTEGRATION_PLAN.md](INTEGRATION_PLAN.md)

Dokumen ini mempertahankan keseluruhan roadmap. Persetujuan mencakup pendaftaran customer UDEV, pembacaan katalog, dan implementasi invoice/checkout/status billing. Status approval dan verifikasi tiap slice tetap dicatat terpisah; persetujuan ini bukan bukti pembayaran Sandbox atau kesiapan produksi.

## Tahap 1 — Keputusan yang sudah disetujui

1. Satu warung Larissama bersama owner pendaftarnya menjadi satu customer UDEV.
2. Kirim setelah pendaftaran berhasil tersimpan, tanpa menunggu approval admin atau pembayaran.
3. Mapping: `name` dari `warungs.nama`; `owner_name` dari `users.nama` owner pendaftar; email dari akun owner; telepon dari data warung.
4. Larissama membuat `external_customer_id` stabil per warung dan memakai ID yang sama saat retry.
5. Kirim `status=active` sementara mengikuti arahan UDEV untuk mengirim lebih dahulu dan melakukan review. Status customer ini bukan bukti pembayaran; ubah mapping bila review UDEV meminta koreksi.
6. Gunakan retry otomatis terbatas untuk gangguan sementara. Retry manual dan log detail hanya untuk superadmin/operator internal berwenang serta diaudit; tenant user tidak melihat detail teknis.
7. Pendaftaran menjelaskan penggunaan data owner untuk akun Larissama dan pengiriman field kontak yang diperlukan ke UDEV. Simpan versi pemberitahuan dan waktu persetujuan. Password tidak dikirim ke UDEV.

## Konfirmasi teknis UDEV untuk Tahap 1

- Endpoint customer Sandbox dan Global API Token server-to-server. Catatan drift: dokumen customer-sync menyebut token terikat aplikasi, sementara route integrasi pada kode UDEV saat ini mensyaratkan Global API Token; user menyetujui jenis Global untuk token bersama.
- Konfirmasi endpoint menerima pendaftaran sebelum ada pembayaran.
- Review arti `status=active`, serta mapping `name` = nama warung dan `owner_name` = nama owner.
- Konfirmasi schema field, respons sukses/error, idempotency, konflik customer manual, rate limit, dan `Retry-After`.
- Review data uji nama warung, owner, email, dan telepon; sampaikan koreksi field/status bila diperlukan.
- Berikan mekanisme aman untuk pengelolaan token dan rotasi/revoke. Secret tidak dikirim melalui chat atau frontend.

## Yang Larissama siapkan untuk Tahap 1

- ID aplikasi dan `external_customer_id` stabil per warung.
- Data warung dan owner pendaftar dengan relasi kontak yang eksplisit.
- Pemberitahuan/persetujuan penggunaan data beserta versi dan waktu persetujuan.
- Sandbox Base URL dan secret server-side.
- Queue worker, metadata sync, retry terbatas, pemulihan manual, monitoring, dan akses log yang dibatasi.
- UAT Bearer eksternal dari backend; panel admin internal UDEV saja bukan bukti autentikasi eksternal.

## Tahap 2 — Integrasi invoice dan pembayaran

Arahan user 2026-10-26 menyetujui implementasi integrasi billing dari backend Larissama ke API UDEV. Keputusan implementasi:

- Semua request server-to-server memakai Global API Token bersama.
- Base URL sementara `https://apiudev.udumbara.my.id`; jika gagal diakses, user akan mengonfirmasi URL ke UDEV.
- Invoice jatuh tempo satu hari setelah diterbitkan, mengikuti contoh payload UDEV.
- Owner dapat memvalidasi kode referral sebelum memakainya. Penguncian referral dilakukan eksplisit setelah owner menyetujui bahwa kode hanya dapat dipasang sekali pada customer dan tidak bisa diganti. Harga final tetap dihitung UDEV.
- Integrasi memakai alur checkout yang dikontrak UDEV, tetapi belum menjalankan transaksi pembayaran atau UAT pembayaran.
- Larissama memeriksa invoice tiap 30 detik selama checkout masih pending dan memeriksa kembali saat halaman pembayaran dibuka.
- Request checkout yang hasilnya tidak diketahui karena gangguan jaringan diulang dengan idempotency key yang sama. Jika payment attempt telah berakhir/ditolak/kedaluwarsa/dibatalkan, percobaan checkout berikut memakai key baru.
- UDEV adalah sumber kebenaran paket/harga, invoice, pembayaran terverifikasi, settlement, dan subscription. Larissama memperbarui masa aktif lokal dari subscription UDEV setelah settlement selesai dan tidak memperpendek masa lokal yang lebih panjang.
- Belum ada webhook yang dipakai; status diperiksa melalui endpoint GET UDEV yang didokumentasikan.

Uji integrasi endpoint dan transaksi pembayaran adalah gate terpisah. Transaksi pembayaran Sandbox, simulasi sukses/gagal, serta kesiapan produksi tetap pending sampai user meminta dan UDEV memberi environment/akses yang sesuai.

## Tahap 3 — Trial, akses, dan pemulihan (aturan akses lokal disetujui; integrasi billing disetujui)

Aturan akses lokal yang disetujui: warung aktif selama trial tiga tanggal lokal; setelah `tanggal_berakhir`, owner dapat login selama 30 tanggal lokal inklusif dan hanya membuka menu pembayaran; mulai hari ke-31 login dan token lama ditolak. Manager/kasir tidak dapat login selama grace. Pembayaran UDEV yang terverifikasi menyelaraskan tanggal masa aktif lokal dari subscription UDEV. Implementasi billing belum menjalani transaksi pembayaran Sandbox.

- Masa grace dimulai pada hari lokal setelah tanggal akhir dan berakhir inklusif pada `tanggal_berakhir + 30 hari`.
- Pemilihan paket, pembuatan invoice, status pembayaran, settlement, dan subscription memakai kontrak UDEV Tahap 2.
- Pemulihan setelah grace dan pengaktifan kembali setelah suspend manual memerlukan tindakan admin.
- Dampak pada warung legacy dan penangguhan manual.

## Batas persetujuan

Persetujuan integrasi invoice/status tidak berarti transaksi pembayaran sudah diuji, sistem siap cutover produksi, atau kredensial produksi baru telah disediakan. Trial lokal tiga hari dan aktivasi superadmin tiga hari tetap aturan lokal. Akses grace lokal dapat menggunakan menu pembayaran; kebijakan setelah grace dan pemulihan manual tetap mengikuti keputusan lokal/admin.
