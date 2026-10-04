# Tracker Implementasi Backend LarisSama

Baseline: 2026-10-04. Repository masih starter. Rancangan delapan tabel, desain backend, draft OpenAPI, dan rencana test sudah ditulis; itu tidak berarti backend telah diimplementasikan. Source roadmap: [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md). Detail gate/test: [TEST_PLAN.md](docs/backend/TEST_PLAN.md).

## Ringkasan pelaksanaan

| Milestone | Task implementasi | DONE | Gate |
| --- | --- | --- | --- |
| M0 Persiapan dan kontrak | 4 | 0 | Belum terpenuhi |
| M1 Akses dan administrasi | 5 | 0 | Belum terpenuhi |
| M2 Katalog | 4 | 0 | Belum terpenuhi |
| M3 Penjualan dan pendapatan | 6 | 0 | Belum terpenuhi |
| M4 Pembelian dan total periode | 6 | 0 | Belum terpenuhi |
| M5 Integrasi dan rilis | 4 | 0 | Belum terpenuhi |
| Total | 29 | 0 | 0% implementasi |

Status awal: **0/28 operasi siap frontend**, **0/45 skenario aplikasi telah dijalankan**. Semua test aplikasi adalah NOT_RUN; tidak ada hasil PASS Laravel yang dicatat. Dua contoh test bawaan bukan bukti fitur bisnis. User telah memilih keluarga database MySQL/MariaDB, Sanctum bearer token, dan penjualan hanya dari menu terdaftar; pilihan itu diperbarui pada register keputusan.

Persentase = jumlah task DONE / jumlah task implementasi aktif × 100. Semua task berbobot sama untuk tracking pekerjaan, bukan estimasi usaha. Dokumen perencanaan tidak masuk pembilang. Bila scope berubah, catat penambahan/pengurangan task dan sumber keputusan; jangan menghapus task gagal agar persentase naik.

## Cara memperbarui tracker

- Status task: `NOT_STARTED`, `IN_PROGRESS`, `BLOCKED`, `IN_REVIEW`, `DONE`. BLOCKED harus mencatat hambatan, keputusan/dependency, dan langkah pembuka. Dependency belum selesai bukan hasil test gagal.
- Sebelum coding, baca rules/keputusan/task, cek Git, lalu catat task aktif dan ringkasan pra-implementasi. Status IN_PROGRESS tidak berarti kontrak READY.
- Setelah mengedit satu file, review diff, commit file tersebut, baru lanjut file berikutnya. Catat semua hash satu slice; commit per file adalah checkpoint, bukan pengganti gate.
- Sesudah implementasi, catat test yang dijalankan dengan hasil sebenarnya. Kaitkan task, test IDs, run_id, commit yang diuji, dan bukti di log run.
- Ubah DONE hanya setelah deliverable, keputusan, test wajib, dokumentasi, serta handoff task tersebut terpenuhi. BLOCKED/NOT_RUN/SKIPPED tidak dihitung PASS.
- Saat konteks berpindah agent, penerus membaca task terakhir, keputusan pemblokir, diff/status Git, dan bukti. Jangan mengulang kerja selesai atau menganggap seluruh fase selesai dari satu commit.
- Sesuai instruksi pengguna, commit perubahan yang relevan sudah diotorisasi. Push tidak otomatis termasuk.

## Backlog dengan dependency dan acceptance

Semua bukti `—` berarti belum ada, bukan hilang dari laporan. Kolom test mengacu ID pada TEST_PLAN; skenario lintas tenant/role/kontrak diulang pada tiap fitur yang relevan.

| ID | Dependensi | Deliverable dan acceptance | Test / keputusan | Status | Bukti |
| --- | --- | --- | --- | --- | --- |
| BE-001 | — | Runtime Docker opsional, dependency Composer, dan cara menjalankan backend sudah disiapkan. T-ENV-01 masih perlu harness serta DB test terisolasi yang terverifikasi. | T-ENV-01; D01,D02 | IN_PROGRESS | B01,ENV-001 |
| BE-002 | — | Tutup keputusan M0/M1 beserta sumbernya; inventaris schema/migration/data users; tidak menebak mapping warung. Pilihan keluarga DB/auth dan scope menu-only telah dicatat. | D01,D02,D03,D04,D12,D13,D15; D08 untuk masa aktif | IN_PROGRESS | Pilihan user 2026-10-04; detail tersisa di DECISIONS.md |
| BE-003 | BE-002 | Finalkan konvensi API dan auth, pilih validator OpenAPI 3.1, tutup gap draft, sediakan pedoman integrasi; kontrak tidak dianggap live hanya karena final draft. | T-API-01; D02,D13 | NOT_STARTED | — |
| BE-004 | BE-001,BE-002,BE-003 | Harness/unit/feature/integration/contract, DB test aman, fixture dua tenant, command runner/CI terdokumentasi. | T-ENV-01; D01 | NOT_STARTED | — |
| BE-101 | BE-002,BE-004 | Migration warungs dan adaptasi users aman; model/constraints sesuai schema; upgrade menjaga data lama. | T-DB-01/02/03; D01,D03,D12 | NOT_STARTED | — |
| BE-102 | BE-101,BE-003 | Login/me/logout dan pemeriksaan user/warung aktif pada setiap request; revokasi mengikuti auth final. | T-AUTH-01/02/03/04/05; D02,D03,D08 | NOT_STARTED | — |
| BE-103 | BE-101,BE-102 | Tenant context, route lookup ter-scope, policy semua role; superadmin jalur terpisah. | T-TEN-01/02/03,T-RBAC-01; D04 | NOT_STARTED | — |
| BE-104 | BE-103 | Administrasi warung, owner awal, user tenant, profil warung; provisioning atomik dan tanpa eskalasi. | T-ADM-01/02,T-TEN-01/02/03; D04,D12 | NOT_STARTED | — |
| BE-105 | BE-102,BE-103,BE-104 | Gate G1 dan operasi akses/admin siap frontend; bukti auth/role/kontrak/environment lengkap. | T-API-02/04; G1 | NOT_STARTED | — |
| BE-201 | BE-105 | Migration kategori/menu, unique per warung, FK dan model siap. | T-DB-01/03; D01,D06 | NOT_STARTED | — |
| BE-202 | BE-201 | API kategori list/detail/create/update/aktif mengikuti kontrak dan tenant. | T-CAT-01,T-TEN-01/02/03,T-RBAC-01; D06 | NOT_STARTED | — |
| BE-203 | BE-201,BE-202 | API menu list/detail/create/update/aktif, harga jual decimal, validasi kategori satu warung. Biaya/gambar lama tidak diekspos oleh API MVP. | T-CAT-01/02; D04,D05,D06; D14 hanya jika media masuk scope | NOT_STARTED | — |
| BE-204 | BE-202,BE-203 | Gate G2, kontrak katalog READY, contoh filter/pagination/errors diserahkan. | T-API-02/03/04; G2 | NOT_STARTED | — |
| BE-301 | BE-204 | Schema/model header-rincian penjualan dan constraints siap; `menu_id` wajib sesuai D07; keputusan nominal/status serta desain nomor/retry durable ditetapkan sebelum action dibuat. | T-DB-01/03; D05,D06,D08,D09 | NOT_STARTED | — |
| BE-302 | BE-301 | Action create sale menghitung nominal, snapshot, bayar/kembali serta menjalankan nomor/retry yang disepakati; rollback semua efek ketika detail gagal. | T-SAL-01/02/03/04/05,T-RET-01/02; D05,D09 | NOT_STARTED | — |
| BE-303 | BE-302 | List/detail penjualan memakai scope role, filter periode/status dan snapshot tersimpan. | T-TEN-01/02,T-API-02/03,T-SAL-04; D04 | NOT_STARTED | — |
| BE-304 | BE-302 | Buktikan nomor/retry action melalui concurrency/crash; implementasikan cancellation bila masuk scope atau catat defer eksplisit sesuai keputusan. | T-RET-01/02/03/04,T-SAL-06; D06,D09 | NOT_STARTED | — |
| BE-305 | BE-303 | Query/API laporan pendapatan periode; nilai dari total sale sah, bukan bayar atau join detail. | T-REP-01/03/04,T-TEN-01; D08 | NOT_STARTED | — |
| BE-306 | BE-302,BE-303,BE-304,BE-305 | Gate G3, create/read/report sale dan ketentuan retry final diserahkan ke frontend. | T-API-02/04; G3 | NOT_STARTED | — |
| BE-401 | BE-105 | Schema/model pembelian header-rinci, unique nomor per warung, nullable qty/unit/harga serta desain retry/koreksi ditetapkan. Tidak bergantung schema penjualan. | T-DB-01/03; D01,D09,D10,D11 | NOT_STARTED | — |
| BE-402 | BE-401 | Create pembelian menerima bentuk ringkas dan rinci, menghitung total, menjalankan nomor/retry yang disepakati, atomic rollback, tanpa efek pada menu/penjualan. | T-BUY-01/02/03/04/05,T-RET-01/02; D05,D09,D10 | NOT_STARTED | — |
| BE-403 | BE-402 | List/detail pembelian ter-scope dengan pagination/periode; tidak mengarang status pembelian. | T-TEN-01/02/03,T-RBAC-01,T-API-02/03 | NOT_STARTED | — |
| BE-404 | BE-402 | Buktikan nomor/retry pembelian melalui concurrency/crash; implementasikan koreksi/cancel sesuai schema/keputusan atau catat defer eksplisit. | T-RET-01/02/03/04,T-BUY-06; D09,D11 | NOT_STARTED | — |
| BE-405 | BE-403 | Laporan total pembelian periode, count header, nol hanya bila periode kosong yang sukses. | T-REP-02/03/04,T-TEN-01; D08,D11 | NOT_STARTED | — |
| BE-406 | BE-402,BE-403,BE-404,BE-405 | Gate G4; contoh ringkas/rinci/validasi/detail/laporan dan kontrak pembelian READY. | T-API-02/04; G4 | NOT_STARTED | — |
| BE-501 | BE-306,BE-406 | Regression lintas modul, tenant, engine target dan API conformance; angka fixture lintas fitur benar. | T-E2E-01,T-API-01/02/03/04; G1–G4 | NOT_STARTED | — |
| BE-502 | BE-001,BE-501 | Runbook setup/deploy/config/migrate/backup/restore/recovery/log/health, environment integration aman dan terdokumentasi. | T-OPS-01/02 | NOT_STARTED | — |
| BE-503 | BE-501,BE-502 | Handoff ke AI/pengembang frontend: versi spec, environment, contoh sukses/error, isu tersisa dan hasil integrasi tercatat. | T-E2E-02 | NOT_STARTED | — |
| BE-504 | BE-503 | Gate G5; seluruh bukti lengkap, keterbatasan/defer disepakati, status endpoint dan release commit konsisten. | G5 | NOT_STARTED | — |

Roadmap default M0 → M1 → M2 → M3 → M4 → M5. M4 hanya bergantung fondasi akses M1 secara domain; tidak ada hubungan FK atau perhitungan ke penjualan. D09 wajib ditetapkan pada BE-301/BE-401 sebelum action write dibuat. BE-302/BE-402 mengimplementasikan strategi tersebut; BE-304/BE-404 membuktikan concurrency/crash sebelum milestone diterima.

## Status handoff kontrak

`READY_FOR_FRONTEND` membutuhkan implementasi dan bukti test sesuai gate. Prefix `/api/v1` dan bearer masih kandidat. Base URL environment dan auth final belum tersedia.

| Area | Jumlah operasi | Versi | Status kontrak | Implementasi | Commit diuji / run / environment |
| --- | --- | --- | --- | --- | --- |
| Auth | 3 | 0.1.0-draft | DRAFT | NOT_STARTED | — |
| Admin warung | 4 | 0.1.0-draft | DRAFT | NOT_STARTED | — |
| Profil warung dan user | 5 | 0.1.0-draft | DRAFT | NOT_STARTED | — |
| Katalog | 8 | 0.1.0-draft | DRAFT | NOT_STARTED | — |
| Penjualan | 3 | 0.1.0-draft | DRAFT | NOT_STARTED | — |
| Pembelian | 3 | 0.1.0-draft | DRAFT | NOT_STARTED | — |
| Laporan | 2 | 0.1.0-draft | DRAFT | NOT_STARTED | — |

Jika hanya sebagian operasi satu area siap, pecah baris menurut operationId. Jangan menaikkan seluruh area menjadi READY karena satu endpoint lulus.

## Hambatan awal

| ID | Fakta | Dampak / langkah pembuka |
| --- | --- | --- |
| B01 | PHP dan Composer tidak ada di host; Docker Desktop Windows dapat diakses dari WSL. | Image PHP 8.3.35/Composer 2.10.3 berhasil dibangun dan dependency terkunci terpasang. Dockerfile/Compose opsional tersedia; T-ENV-01 masih menunggu harness dan DB test terisolasi. |
| B02 | Versi tepat MySQL/MariaDB produksi dan detail expiry Sanctum, D03/D04/D05/D06/D08/D09/D10/D11/D12/D13/D14 belum ditetapkan. | Pilihan keluarga DB, package Sanctum v4.3.3, dan scope menu-only sudah dicatat; catat detail yang wajib untuk setiap gate. Tetap lanjut pada pekerjaan yang tidak bergantung. |
| B03 | Schema bisnis, route API, dan test aplikasi belum ada. | Jalankan backlog per dependency; jangan menganggap dokumen ini implementasi. |
| B04 | Environment/handoff frontend belum tersedia. | Lengkapi BE-502/BE-503 sesudah endpoint dibangun dan diuji. |

## Log run dan bukti

| Run ID | Scope / commit | Hasil | Batas bukti |
| --- | --- | --- | --- |
| DOC-001 | Draft OpenAPI `4d6b965`; Python PyYAML + jsonschema | PASS pemeriksaan lokal: 18 path, 28 operasi unik, 58 schema, 463 ref resolve, 264 contoh request/response tervalidasi termasuk response error yang digunakan ulang. | Command sesi `python3 /tmp/larissama_validate_contract.py`; script sementara, bukan tool proyek/CI. Bukan lint OAS penuh dan bukan T-API-01 lulus. |
| DOC-002 | OpenAPI setelah perbaikan kapasitas laporan `17c6586` | PASS pemeriksaan yang sama: 18 path, 28 operasi, 59 schema, 463 ref resolve, 264 contoh. AggregateMoney menjaga kapasitas jumlah lintas transaksi. | Validasi dokumen/JSON Schema saja; tidak mengeksekusi Laravel, DB, atau validator OAS penuh. |
| DOC-003 | Paket dokumen pada `4b4f7a0` | PASS: 56 tautan lokal, 29 task dengan dependency tanpa siklus, 45 skenario, 28 operasi terdokumentasi, 3 contoh JSON panduan, bentuk pembelian minimal valid dan 6 input invalid ditolak schema; arithmetic fixture decimal dan kesamaan AGENTS/CLAUDE benar; 14 commit dalam paket masing-masing satu file. | Command sesi `python3 /tmp/larissama_validate_docs.py`; cek awal helper gagal menghitung ID E2E karena regex hanya menerima huruf. Regex helper diperbaiki dan pemeriksaan ulang lulus. Ini bukan hasil test aplikasi. |
| ENV-OBS-001 | Pemeriksaan shell 2026-10-04 | php -v dan composer -V: command not found. | Observasi awal; tidak ada test aplikasi yang dijalankan. |
| ENV-001 | Dockerfile/Compose `e8914b9`, `ee6a4df`, `3686947`, `a16b536`, `17863be`; Boost `bb0c553`, `0781735` | PASS: `docker compose config --quiet`; image PHP 8.3.35 dan Composer 2.10.3; `composer install` 110 package; `boost:install` selesai untuk Codex dan Claude; Pint pada User model passed. | Tidak menyelesaikan T-ENV-01: harness dan identitas DB test belum diverifikasi. Tidak ada feature test atau migration bisnis yang dijalankan. MySQL 8.0.40 hanya image development lokal, bukan pilihan versi produksi. |
| AUTH-BOOT-001 | Sanctum `7f6fba7`, `7e1ffcc`, `2eb6036`, `a2fcc30`, `2beb299`, `71cd88f`, `a3abfab`, `fbb6001`, `4cf2df6` | Package v4.3.3, config, migration token, `HasApiTokens`, dan API route registration tersedia. Route inspection memuat route file tanpa menyisakan endpoint bawaan `/api/user`. | Fondasi saja: login/me/logout belum dibuat, migration belum dijalankan, belum ada token yang diterbitkan. Menunggu BE-101 dan keputusan lifecycle D02, D03/D04. |

Run aplikasi berikutnya wajib memakai format lengkap pada TEST_PLAN. Simpan output yang relevan pada artefak bukti (lokasi disepakati saat M0), tanpa secret, dan tautkan di sini. Tiap hasil mencantumkan commit yang diuji, bukan sekadar branch yang bisa bergerak.

## Catatan aktivitas

| Tanggal | Aktivitas | Bukti / dampak |
| --- | --- | --- |
| 2026-10-04 | Rancangan pembelian ditambahkan | `652450a`, delapan tabel; belum migration. |
| 2026-10-04 | Milestone pembelian awal | `6716613`, rencana sebelum rincian backlog ini. |
| 2026-10-04 | Register keputusan, desain, kontrak dan test plan | `e570f65`, `2df2e17`, `4d6b965`, `25f3dfd`, `293b107`. Implementasi tetap 0/29. |
| 2026-10-04 | Backlog/gate dan pedoman agent diselaraskan | `bda7024`, `c17b5be`, `b675557`, `cfe161e`, `0145fd7`, `0697609`. Aturan commit per file persisten dan AGENTS/CLAUDE sama. |
| 2026-10-04 | Review kontrak dan pemeriksaan paket rancangan | `17c6586`, `785a597`, `4b4f7a0`; bukti DOC-002/DOC-003. Tidak ada task implementasi atau operasi live yang dinaikkan statusnya. |
| 2026-10-04 | Pilihan DB/auth dicatat dan scope penjualan dikunci ke menu terdaftar | `4972b96`, `c7d0127`, `4875172`, `c6575f7`, `88d0886`, `724e837`, `e30c52d`. Migration bisnis masih belum dibuat. |

Saat mulai task, tambahkan log berisi ID task, agent/pelaksana, tujuan, fakta/invariant, scope izin, batas transaksi/retry, file, dan acceptance. Saat selesai, tambahkan perubahan, hasil test, hash tiap file, operasi yang diserahkan, serta task berikutnya.
