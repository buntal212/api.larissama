# Persetujuan Scope Sinkronisasi Pendaftaran Customer ke UDEV

Status: **keputusan pengguna disetujui untuk scope awal; review teknis UDEV menunggu**
Tanggal keputusan: 2026-10-24
Rencana: [INTEGRATION_PLAN.md](INTEGRATION_PLAN.md)

## Scope yang disetujui

Persetujuan ini hanya untuk pendaftaran customer baru di UDEV dari backend Larissama. Billing/invoice, pembayaran, subscription, webhook billing, trial/grace entitlement, dan pembatasan akses karena pembayaran tetap menjadi pembahasan dan persetujuan tahap berikutnya.

Keputusan yang dicatat dari arahan pengguna:

1. Satu warung Larissama bersama owner pendaftarnya menjadi satu customer UDEV.
2. Kirim setelah registrasi berhasil tersimpan, tanpa menunggu approval admin atau pembayaran.
3. Field mapping: `name` dari `warungs.nama`; `owner_name` dari `users.nama` owner pendaftar; email dari akun owner; telepon dari data warung.
4. Larissama membuat `external_customer_id` stabil per warung dan menggunakannya pada retry.
5. Kirim status customer `active` untuk saat ini, mengikuti arahan UDEV agar data dikirim terlebih dahulu. Minta review UDEV; status tersebut bukan pernyataan bahwa customer telah membayar.
6. Kegagalan sementara dicoba ulang otomatis secara terbatas. Retry manual dan log detail dibatasi ke superadmin/operator internal berwenang serta diaudit; tenant user tidak melihat detail teknis.
7. Form pendaftaran menjelaskan penggunaan data owner untuk membuat akun Larissama dan mengirim field kontak yang diperlukan ke UDEV. Simpan versi pemberitahuan dan waktu persetujuan. Password tidak dikirim ke UDEV.

Warung baru langsung aktif selama trial tiga hari di Larissama. Itu status akses lokal dan terpisah dari arti status customer UDEV; mapping `active` di UDEV dikirim sementara untuk review.

## Hal yang harus dikonfirmasi UDEV

Sebelum UAT, UDEV perlu memastikan:

- Endpoint Sandbox customer aktif untuk pendaftaran sebelum ada pembayaran.
- Token server-to-server Larissama memiliki scope minimum `customers:write`.
- `status=active` berarti record customer aktif, bukan bukti pembayaran atau subscription.
- `name` dapat memuat nama warung dan `owner_name` nama pemilik sesuai mapping di atas.
- Bentuk response sukses/error, customer conflict, idempotency, rate limit, dan `Retry-After`.
- Data pendaftaran yang dikirim dapat direview dan dikoreksi jika UDEV memiliki kebutuhan field/status lain.

Jika hasil review UDEV mengubah arti status atau mapping payload, hentikan konfigurasi produksi dan revisi kontrak/rencana sebelum cutover.

## Yang disiapkan Larissama

- ID aplikasi dan ID eksternal stabil per warung.
- Data warung serta owner utama yang sumbernya eksplisit.
- Sandbox Base URL dan token yang disimpan hanya di secret manager/environment backend.
- Queue worker, retry terbatas, metadata sync, pemulihan manual, dan log aman yang terbatas.
- Pemberitahuan/persetujuan penggunaan data dan penyimpanan versi/waktu persetujuan.
- UAT Bearer eksternal dari backend; panel admin internal UDEV saja tidak membuktikan token eksternal berfungsi.

## Batas

Persetujuan ini tidak mencakup akses/pengiriman token melalui chat atau browser, invoice/payment/subscription, penghapusan customer UDEV otomatis, atau cutover produksi. Kredensial produksi dan keputusan billing memerlukan persetujuan tersendiri.
