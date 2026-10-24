# Keputusan dan Persetujuan Bertahap Integrasi Larissama–UDEV

Status: **Tahap 1 UDEV disetujui; instruksi backend trial 3 hari diterima; billing/grace pending**
Tanggal keputusan Tahap 1: 2026-10-24
Rencana master: [INTEGRATION_PLAN.md](INTEGRATION_PLAN.md)

Dokumen ini mempertahankan keseluruhan roadmap. Persetujuan saat ini hanya mencakup pendaftaran customer baru di UDEV. Billing, trial/grace enforcement, dan perubahan akses tetap dicatat sebagai tahap berikutnya, bukan dihapus dari rencana dan bukan bagian implementasi Tahap 1.

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

Keputusan berikut belum diminta untuk implementasi Tahap 1. Kontrak terbaru UDEV sudah mendokumentasikan endpoint baca katalog paket aktif, tetapi integrasinya tetap menunggu persetujuan Tahap 2 dan konfirmasi Sandbox:

- API `GET /api/v1/integrations/subscription-plans` dengan scope `subscription-plans:read`; pemilihan paket dan kebijakan produk Larissama.
- Pembuatan invoice, checkout URL, pembacaan invoice/payment/subscription, dan idempotency.
- Mata uang, pajak/biaya, metode pembayaran, masa berlaku invoice, dan renewal.
- Sumber kebenaran status invoice, payment, settlement, refund/chargeback, `paid_at`, dan `paid_until`.
- Webhook/event, signature, event ID, replay protection, urutan event, retry, serta API rekonsiliasi.
- Apakah UDEV menyediakan entitlement/trial/grace atau Larissama mengelola kebijakannya sendiri.

## Tahap 3 — Trial, akses, dan pemulihan (trial backend diarahkan; billing/grace pending)

Arah produk yang sudah disampaikan: warung langsung aktif selama trial tiga hari; setelah masa aktif berakhir dan belum membayar direncanakan ada masa tenggang 30 hari; setelah itu login diblokir dan owner menghubungi admin. Instruksi lanjutan 2026-10-24 mengizinkan backend menerapkan trial tiga tanggal lokal inklusif serta aksi superadmin memberi aktivasi tiga hari. Grace, billing, dan pembatasan setelah grace tetap belum dikerjakan. Detail berikut masih perlu dibekukan sebelum implementasi lanjutan:

- Akses trial lokal sudah diarahkan penuh selama tiga tanggal lokal inklusif, mulai tanggal pendaftaran.
- Kapan grace 30 hari dimulai dan bagaimana aturan untuk langganan berbayar yang diperpanjang.
- Hak akses selama grace, jalur pembayaran setelah akun terkunci, dan aturan pemulihan admin.
- Dampak pada warung legacy dan penangguhan manual.

## Batas persetujuan

Persetujuan integrasi Tahap 1 tidak mencakup integrasi katalog paket, pemilihan paket, pembuatan invoice, pembayaran, subscription berbayar, webhook billing, access gate karena status pembayaran, penghapusan customer UDEV otomatis, atau cutover produksi. Kontrak baca katalog yang tersedia tidak berarti integrasi atau persetujuan Tahap 2. Trial lokal tiga hari dan aksi aktivasi superadmin adalah instruksi backend terpisah, tidak menetapkan kontrak billing UDEV. Setiap tahap billing tetap memerlukan keputusan/approval tahapnya sendiri. Kredensial produksi juga memerlukan persetujuan dan secret produksi tersendiri.
