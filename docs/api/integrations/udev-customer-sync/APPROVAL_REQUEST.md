# Keputusan dan Persetujuan Bertahap Integrasi Larissama–UDEV

Status: **Tahap 1 customer sync, katalog paket read-only, dan aturan akses grace lokal disetujui; invoice/pembayaran UDEV pending**
Tanggal keputusan Tahap 1: 2026-10-24
Rencana master: [INTEGRATION_PLAN.md](INTEGRATION_PLAN.md)

Dokumen ini mempertahankan keseluruhan roadmap. Persetujuan mencakup pendaftaran customer baru di UDEV dan, melalui arahan terpisah 2026-10-25, pembacaan katalog paket aktif secara read-only. Billing, trial/grace enforcement, dan perubahan akses tetap dicatat sebagai pekerjaan berikutnya, bukan dihapus dari rencana atau dianggap selesai oleh dua slice tersebut.

## Tahap 1 — Keputusan yang sudah disetujui

1. Satu warung Larissama bersama owner pendaftarnya menjadi satu customer UDEV.
2. Kirim setelah pendaftaran berhasil tersimpan, tanpa menunggu approval admin atau pembayaran.
3. Mapping: `name` dari `warungs.nama`; `owner_name` dari `users.nama` owner pendaftar; email dari akun owner; telepon dari data warung.
4. Larissama membuat `external_customer_id` stabil per warung dan memakai ID yang sama saat retry.
5. Kirim `status=active` sementara mengikuti arahan UDEV untuk mengirim lebih dahulu dan melakukan review. Status customer ini bukan bukti pembayaran; ubah mapping bila review UDEV meminta koreksi.
6. Gunakan retry otomatis terbatas untuk gangguan sementara. Retry manual dan log detail hanya untuk superadmin/operator internal berwenang serta diaudit; tenant user tidak melihat detail teknis.
7. Pendaftaran menjelaskan penggunaan data owner untuk akun Larissama dan pengiriman field kontak yang diperlukan ke UDEV. Simpan versi pemberitahuan dan waktu persetujuan. Password tidak dikirim ke UDEV.

## Konfirmasi teknis UDEV untuk Tahap 1

- Endpoint customer Sandbox dan token server-to-server Larissama dengan scope minimum `customers:write`.
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

## Tahap 2 — Billing dan pembayaran (pending)

Arahan user 2026-10-25 menyetujui slice katalog harga aktif read-only untuk owner menggunakan endpoint UDEV. Sandbox UAT tetap perlu dilakukan. Keputusan berikut masih menunggu kontrak dan persetujuan lanjutan:

- UAT Sandbox untuk API `GET /api/v1/integrations/subscription-plans`; satu token UDEV bersama memerlukan scope `customers:write` dan `subscription-plans:read`. Pemilihan paket dan kebijakan produk Larissama tetap pending.
- Pembuatan invoice, checkout URL, pembacaan invoice/payment/subscription, dan idempotency.
- Mata uang, pajak/biaya, metode pembayaran, masa berlaku invoice, dan renewal.
- Sumber kebenaran status invoice, payment, settlement, refund/chargeback, `paid_at`, dan `paid_until`.
- Webhook/event, signature, event ID, replay protection, urutan event, retry, serta API rekonsiliasi.
- Apakah UDEV menyediakan entitlement/trial/grace atau Larissama mengelola kebijakannya sendiri.

## Tahap 3 — Trial, akses, dan pemulihan (aturan akses lokal disetujui; billing UDEV pending)

Aturan akses lokal yang disetujui: warung aktif selama trial tiga tanggal lokal; setelah `tanggal_berakhir`, owner dapat login selama 30 tanggal lokal inklusif dan hanya membuka katalog paket; mulai hari ke-31 login dan token lama ditolak. Manager/kasir tidak dapat login selama grace. Karena invoice/checkout belum tersedia, owner menghubungi admin untuk reaktivasi. Aturan ini tidak menetapkan sumber kebenaran pembayaran atau kontrak billing UDEV.

- Masa grace dimulai pada hari lokal setelah tanggal akhir dan berakhir inklusif pada `tanggal_berakhir + 30 hari`.
- Pemilihan paket, pembuatan invoice, pembayaran, perpanjangan berbayar, dan sumber kebenaran status pembayaran tetap memerlukan kontrak UDEV terpisah.
- Pemulihan setelah grace dan pengaktifan kembali setelah suspend manual memerlukan tindakan admin.
- Dampak pada warung legacy dan penangguhan manual.

## Batas persetujuan

Persetujuan Tahap 1 tidak mencakup invoice, checkout, pembayaran, subscription berbayar, webhook billing, access gate berdasarkan fakta pembayaran UDEV, penghapusan customer UDEV otomatis, atau cutover produksi. Slice katalog read-only dan kebijakan akses lokal selama grace disetujui terpisah pada 2026-10-25 dan tidak berarti billing disetujui. Trial lokal tiga hari, aktivasi superadmin tiga hari, dan pembatasan lokal pasca-expiry tidak menetapkan kontrak billing UDEV. Tahap invoice/payment tetap memerlukan kontrak dan keputusan/approval tersendiri. Kredensial produksi juga memerlukan persetujuan dan secret produksi tersendiri.
