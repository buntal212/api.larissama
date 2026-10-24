# Rencana Sinkronisasi Pendaftaran Customer Larissama ke UDEV

Status: **KEPUTUSAN SCOPE AWAL DISETUJUI PENGGUNA; MENUNGGU REVIEW TEKNIS UDEV**
Tanggal: 2026-10-24

## Tujuan dan batas tahap ini

Saat owner mendaftarkan warung di Larissama, backend Larissama mengirim satu data customer yang mewakili warung dan owner tersebut ke UDEV. Pengiriman berlangsung setelah pendaftaran berhasil disimpan, tanpa menunggu approval admin atau pembayaran.

Tahap ini hanya mencakup pencatatan customer UDEV. Pembuatan paket/invoice, checkout, pembayaran, subscription, webhook billing, serta perubahan akses karena pembayaran belum termasuk. UDEV sedang merencanakan bagian tersebut dan perlu memiliki kontrak serta approval tersendiri nanti.

Dokumen sumber `larissama-customer-sync.md` dan `customer-integration.yaml` dipertahankan sebagai referensi kontrak customer. Kontrak dan endpoint tetap perlu dikonfirmasi pada Sandbox UDEV sebelum UAT.

## Keputusan scope yang telah disetujui

| Keputusan | Hasil |
| --- | --- |
| Identitas customer | Satu `Warung` bersama owner pendaftarnya menjadi satu customer UDEV. |
| Waktu kirim | Setelah pendaftaran berhasil commit; tidak menunggu approval atau pembayaran. |
| Pemetaan nama | `name` ← `warungs.nama`; `owner_name` ← `users.nama` milik owner pendaftar. |
| Kontak | Email dari akun owner pendaftar; telepon dari data warung. Owner pendaftar menjadi kontak utama. |
| ID eksternal | Larissama membuat ID khusus yang stabil per warung; bukan `warungs.kode` yang dapat berubah. |
| Status customer UDEV | Kirim `active` untuk pendaftaran awal. Ini sementara mengikuti arahan UDEV untuk mengirim data lebih dahulu dan akan direview bersama UDEV; jangan tafsirkan sebagai bukti pembayaran. |
| Retry dan log | Retry otomatis terbatas untuk kegagalan sementara; retry manual dan log detail hanya untuk superadmin/operator internal yang diberi wewenang. Tenant user tidak melihat detail teknis. |
| Penggunaan data | Form pendaftaran memberi pemberitahuan dan meminta persetujuan atas penyimpanan data owner untuk akun Larissama serta pengiriman field kontak yang diperlukan ke UDEV. Catat versi pemberitahuan dan waktu persetujuan. |

Pada alur produk Larissama, warung baru langsung aktif untuk trial tiga hari. Itu status akses lokal; pengiriman `status=active` ke UDEV adalah mapping sementara untuk customer baru dan tetap menunggu review UDEV. Customer status tidak menjadi fakta pembayaran.

Password hanya digunakan untuk autentikasi Larissama, disimpan dengan hash oleh Larissama, dan tidak dikirim ke UDEV.

## Sumber data dan pemetaan payload

| Field UDEV | Sumber Larissama |
| --- | --- |
| `external_customer_id` | ID integrasi stabil yang dibuat dan disimpan Larissama untuk warung. |
| `name` | `warungs.nama`. |
| `owner_name` | `users.nama` untuk owner yang mendaftarkan warung. |
| `email` | `users.email` owner pendaftar bila tersedia. |
| `phone` | `warungs.telepon` bila tersedia. |
| `status` | `active` untuk customer yang baru didaftarkan, sementara menunggu review UDEV. |

`application_id` berasal dari token aplikasi UDEV dan tidak dikirim di body. Data transaksi penjualan, password, dan data billing tidak ikut dikirim.

## Alur operasional

1. Validasi pendaftaran serta pemberitahuan/persetujuan pemrosesan data owner.
2. Dalam satu transaksi lokal, buat user owner dan warung, buat identitas sync stabil berstatus pending, dan siapkan queue job setelah commit.
3. Worker membaca ulang data owner/warung terbaru lalu mengirim `POST /api/v1/integrations/customers` dari backend dengan Bearer token server-side.
4. Respons `201`/`200` menyimpan ID customer UDEV, waktu sync, request ID, serta status berhasil. Retry memakai `external_customer_id` yang sama agar tidak membuat duplikat.
5. Timeout/`408`/`429`/`5xx` menjadi retry terbatas dengan backoff dan mengikuti `Retry-After` bila diberikan. Registrasi lokal tetap berhasil jika UDEV tidak tersedia.
6. `401`/`403` menjadi gangguan token/scope; `409` konflik customer manual; `422` data ditolak. Tandai untuk perbaikan operator, tanpa menyimpan token, payload mentah, atau response sensitif.
7. Setelah perbaikan, operator internal berwenang dapat meminta retry manual untuk warung tertentu. Setiap retry manual mencatat actor, waktu, dan alasan. Tidak ada retry manual dari tenant user.

Rekomendasi tahap pertama: log detail tersedia hanya bagi superadmin/platform operator yang diberi wewenang melalui jalur internal. Owner cukup melihat status umum bahwa pendaftaran sedang diproses atau perlu bantuan; tidak mendapat token, request body, response body, atau stack trace.

## Yang perlu dikonfirmasi UDEV sebelum UAT

Arahan UDEV adalah mengirim data pendaftaran lebih dahulu untuk direview. Sebelum UAT, konfirmasikan:

1. Endpoint Sandbox yang aktif, token server-to-server Larissama dengan scope `customers:write`, dan prosedur rotasi/revoke.
2. Penerimaan customer saat baru mendaftar, walau belum membayar dan belum memiliki subscription.
3. Makna `status=active`: status record customer, bukan status lunas/entitlement. Jika artinya berbeda, UDEV memberi mapping/status yang benar sebelum integrasi produksi.
4. Pemetaan `name` sebagai nama warung dan `owner_name` sebagai nama owner sesuai kontrak yang tersedia.
5. Response sukses/error, aturan konflik dengan customer manual, idempotency, rate limit, dan format `Retry-After`.
6. Review atas data Sandbox yang dikirim: nama toko, owner, email/telepon, serta kebutuhan perubahan field bila format UDEV berkembang.

## Kebutuhan Larissama

- Konfigurasi Base URL Sandbox dan token UDEV di secret environment/manager server-side; tidak menaruh rahasia di source atau frontend.
- Queue worker berjalan di environment pengiriman dan status job dapat dipantau internal.
- Simpan metadata sync minimal: `warung_id`, `external_customer_id`, ID UDEV, status, jumlah/waktu percobaan, HTTP status, request ID, dan kode error aman.
- Data owner memiliki hubungan eksplisit ke warung; jangan memilih kontak dari urutan query.
- Pemberitahuan pendaftaran menyebut data yang digunakan untuk akun Larissama dan field kontak yang dikirim ke UDEV; simpan versi dan waktu persetujuan.
- Retry manual dan akses log dibatasi serta diaudit.

## Penerimaan tahap customer sync

- Pendaftaran menghasilkan satu warung, satu owner, dan satu customer UDEV dengan field mapping yang disetujui.
- Job baru mengirim setelah transaksi lokal commit; rollback tidak mengirim request.
- Pengiriman ulang memakai ID yang sama dan tidak membuat customer duplikat.
- Gangguan UDEV tidak menghapus/membatalkan pendaftaran lokal; retry dan recovery dapat dipantau internal.
- Hanya operator internal berwenang yang melihat detail kegagalan atau memicu retry manual.
- Password/token tidak terkirim atau tercatat pada log; persetujuan data dapat ditelusuri.
- UDEV mereview makna status `active` dan data awal sebelum konfigurasi produksi.

## Tahap berikutnya

Billing dibahas terpisah bersama UDEV: definisi paket dan harga, invoice/checkout, status pembayaran final, webhook/rekonsiliasi, trial dan masa tenggang, serta pembatasan akses Larissama. Rencana billing belum menjadi bagian dari persetujuan customer sync ini.
