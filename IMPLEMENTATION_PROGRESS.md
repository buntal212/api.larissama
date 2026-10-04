# Tracker Implementasi Backend LarisSama

Status per 2026-10-04. Migration `warungs`, tenant `users`, timezone, kategori, dan menu telah diperiksa pada MySQL 8.0.40 lokal; implementasi awal M1 dan katalog M2 tersedia. Penjualan, pembelian, serta laporan belum tersedia. Rancangan delapan tabel, kode awal, OpenAPI draft, dan test plan tidak berarti backend siap produksi/frontend. Source roadmap: [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md). Detail gate/test: [TEST_PLAN.md](docs/backend/TEST_PLAN.md).

## Ringkasan pelaksanaan

| Milestone | Task implementasi | DONE | Gate |
| --- | --- | --- | --- |
| M0 Persiapan dan kontrak | 4 | 0 | Belum terpenuhi |
| M1 Akses dan administrasi | 5 | 0 | Belum terpenuhi |
| M2 Katalog | 4 | 0 | Belum terpenuhi |
| M3 Penjualan dan pendapatan | 6 | 0 | Belum terpenuhi |
| M4 Pembelian dan total periode | 6 | 0 | Belum terpenuhi |
| M5 Integrasi dan rilis | 4 | 0 | Belum terpenuhi |
| Total | 29 | 0 | 0% task selesai |

Status saat ini: **0/28 operasi siap frontend**, **0/45 skenario aplikasi telah dijalankan**. Semua test aplikasi adalah NOT_RUN; belum ada hasil PASS test Laravel. Pemeriksaan statis dan migration development dicatat terpisah dari test. User telah memilih MySQL 8.0.40, email nullable unik global saat diisi, Sanctum bearer token dengan expiry 30 hari, NULL masa aktif warung tanpa batas, tanggung jawab inti role, dan penjualan hanya dari menu terdaftar; pilihan itu diperbarui pada register keputusan.

Persentase = jumlah task DONE / jumlah task implementasi aktif × 100. Semua task berbobot sama untuk tracking pekerjaan, bukan estimasi usaha. Dokumen perencanaan tidak masuk pembilang. Bila scope berubah, catat penambahan/pengurangan task dan sumber keputusan; jangan menghapus task gagal agar persentase naik.

## Cara memperbarui tracker

- Status task: `NOT_STARTED`, `IN_PROGRESS`, `BLOCKED`, `IN_REVIEW`, `DONE`. BLOCKED harus mencatat hambatan, keputusan/dependency, dan langkah pembuka. Dependency belum selesai bukan hasil test gagal.
- Sebelum coding, baca rules/keputusan/task, cek Git, lalu catat task aktif dan ringkasan pra-implementasi. Status IN_PROGRESS tidak berarti kontrak READY.
- Kelompokkan file yang saling terkait dalam satu commit. Review diff lengkap, stage hanya path tugas, jalankan `git diff --cached --check`, lalu catat hash commit; checkpoint bukan pengganti gate.
- Sesudah implementasi, catat test yang dijalankan dengan hasil sebenarnya. Kaitkan task, test IDs, run_id, commit yang diuji, dan bukti di log run.
- Ubah DONE hanya setelah deliverable, keputusan, test wajib, dokumentasi, serta handoff task tersebut terpenuhi. BLOCKED/NOT_RUN/SKIPPED tidak dihitung PASS.
- Saat konteks berpindah agent, penerus membaca task terakhir, keputusan pemblokir, diff/status Git, dan bukti. Jangan mengulang kerja selesai atau menganggap seluruh fase selesai dari satu commit.
- Sesuai instruksi pengguna, commit perubahan yang relevan sudah diotorisasi. Push tidak otomatis termasuk.

## Backlog dengan dependency dan acceptance

Semua bukti `—` berarti belum ada, bukan hilang dari laporan. Kolom test mengacu ID pada TEST_PLAN; skenario lintas tenant/role/kontrak diulang pada tiap fitur yang relevan.

| ID | Dependensi | Deliverable dan acceptance | Test / keputusan | Status | Bukti |
| --- | --- | --- | --- | --- | --- |
| BE-001 | — | Runtime Docker opsional, dependency Composer, dan cara menjalankan backend sudah disiapkan. T-ENV-01 masih perlu harness serta DB test terisolasi yang terverifikasi. | T-ENV-01; D01,D02 | IN_PROGRESS | B01,ENV-001 |
| BE-002 | — | Tutup keputusan M0/M1 beserta sumbernya; inventaris schema/migration/data users; tidak menebak mapping warung. Pilihan MySQL 8.0.40, email nullable unik, expiry Sanctum 30 hari, semantik NULL tanggal, tanggung jawab inti role, dan scope menu-only telah dicatat. | D01,D02,D03,D04,D12,D13,D15; D08 untuk masa aktif | IN_PROGRESS | Pilihan user 2026-10-04; detail tersisa di DECISIONS.md |
| BE-003 | BE-002 | Finalkan konvensi API dan auth, pilih validator OpenAPI 3.1, tutup gap draft, sediakan pedoman integrasi; kontrak tidak dianggap live hanya karena final draft. | T-API-01; D02,D13 | NOT_STARTED | — |
| BE-004 | BE-001,BE-002,BE-003 | Harness/unit/feature/integration/contract, DB test aman, fixture dua tenant, command runner/CI terdokumentasi. | T-ENV-01; D01 | NOT_STARTED | — |
| BE-101 | BE-002,BE-004 | Migration warungs dan adaptasi users aman; model/constraints sesuai schema; upgrade menjaga data lama. Migration timezone juga diterapkan pada DB lokal MySQL 8.0.40 dan kolom nullable terverifikasi. Upgrade database lama berisi user belum dapat dijalankan karena pemetaan identitas/tenant belum tersedia; preflight migration menolak keadaan itu sebelum DDL. | T-DB-01/02/03; D01,D03,D12 | IN_PROGRESS | `ce2d529`, `9fadf12`, `0f457df`, `4528150`, `8654281`, `edba741`, `b1653cf`, `bdb4b73`, `2cafc46`; DB-MIGRATION-001/002/003, DB-LINT-003/004 |
| BE-102 | BE-101,BE-003 | Login/me/logout, batas login 5 percobaan/menit per username+IP, pemeriksaan user/warung aktif dan token 30 hari. Implementasi awal tersedia; wajib menuntaskan T-AUTH dan conformance sebelum DRAFT diubah. Aplikasi dan koneksi MySQL memakai UTC, sedangkan tanggal masa aktif dihitung menurut timezone warung. Kolom timezone nullable tanpa default dan larangan login saat kosong adalah kebijakan sementara, menunggu konfirmasi perlakuan zona kosong. | T-AUTH-01/02/03/04/05; D02,D03,D08,D12,D13 | IN_PROGRESS | `0938f13`, `f8baae9`, `2cafc46`; AUTH-RATE-LIMIT-001/AUTH-API-001; T-AUTH-01–05 belum dijalankan |
| BE-103 | BE-101,BE-102 | Tenant context, route lookup ter-scope, policy semua role; superadmin jalur terpisah. Fondasi policy admin dan lookup tenant sudah diimplementasikan; matriks detail role masih harus ditutup dan diuji. | T-TEN-01/02/03,T-RBAC-01; D04 | IN_PROGRESS | `0f7e39c`, ADMIN-API-001; test tenant/role belum dijalankan |
| BE-104 | BE-103 | Administrasi warung, owner awal, user tenant, profil warung; provisioning atomik dan tanpa eskalasi. Implementasi awal tersedia; test atomisitas/tenant/validasi belum dijalankan. | T-ADM-01/02,T-TEN-01/02/03; D04,D12 | IN_PROGRESS | `0f7e39c`, ADMIN-API-001; contract dan feature test belum dijalankan |
| BE-105 | BE-102,BE-103,BE-104 | Gate G1 dan operasi akses/admin siap frontend; bukti auth/role/kontrak/environment lengkap. | T-API-02/04; G1 | NOT_STARTED | — |
| BE-201 | BE-105 | Migration kategori/menu, unique per warung, FK dan model tersedia; clean-install lokal MySQL 8.0.40 sudah diperiksa. | T-DB-01/03; D01,D06,D16 | IN_PROGRESS | `eb5ea04`, DB-MIGRATION-004; uji suite DB belum dijalankan, strategi FK gabungan D16 terbuka |
| BE-202 | BE-201 | API kategori list/detail/create/update/status mengikuti kontrak dan tenant. | T-CAT-01,T-TEN-01/02/03,T-RBAC-01; D04,D06,D13,D16 | IN_PROGRESS | `eb5ea04`, CATALOG-API-001; 4 route, tanpa HTTP/feature/contract test |
| BE-203 | BE-201,BE-202 | API menu list/detail/create/update/status, harga jual decimal, validasi kategori satu warung. Biaya/gambar legacy tidak diekspos oleh API MVP. | T-CAT-01/02; D04,D05,D06,D16; D14 hanya bila media masuk scope | IN_PROGRESS | `eb5ea04`, CATALOG-API-001; 4 route, tanpa HTTP/feature/contract test |
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
| Auth | 3 | 0.1.0-draft | DRAFT | IN_PROGRESS | `2cafc46`, AUTH-API-001; route/PHP/config diperiksa di Docker PHP 8.3.35 + MySQL 8.0.40 lokal. T-AUTH dan contract test belum dijalankan; base URL belum tersedia |
| Admin warung | 4 | 0.1.0-draft | DRAFT | IN_PROGRESS | `0f7e39c`, ADMIN-API-001; route/PHP lint lulus. Tanpa HTTP/contract test; DRAFT, belum siap frontend |
| Profil warung dan user | 5 | 0.1.0-draft | DRAFT | IN_PROGRESS | `0f7e39c`, ADMIN-API-001; route/PHP lint lulus. Tanpa HTTP/contract test; DRAFT, belum siap frontend |
| Katalog | 8 | 0.1.0-draft | DRAFT | IN_PROGRESS | `eb5ea04`, CATALOG-API-001/DB-MIGRATION-004; Pint, PHP lint, 8 route, YAML parse dan dua migration lokal lulus. T-CAT/T-TEN/T-RBAC/T-API belum dijalankan; D05/D06/D13/D16 tersisa |
| Penjualan | 3 | 0.1.0-draft | DRAFT | NOT_STARTED | — |
| Pembelian | 3 | 0.1.0-draft | DRAFT | NOT_STARTED | — |
| Laporan | 2 | 0.1.0-draft | DRAFT | NOT_STARTED | — |

Jika hanya sebagian operasi satu area siap, pecah baris menurut operationId. Jangan menaikkan seluruh area menjadi READY karena satu endpoint lulus.

## Hambatan awal

| ID | Fakta | Dampak / langkah pembuka |
| --- | --- | --- |
| B01 | PHP dan Composer tidak ada di host; Docker Desktop Windows dapat diakses dari WSL. | Image PHP 8.3.35/Composer 2.10.3 berhasil dibangun; Compose memakai MySQL 8.0.40, sama dengan target yang dipilih. Dockerfile/Compose opsional tersedia; T-ENV-01 masih menunggu harness dan DB test terisolasi. |
| B02 | Transisi users/mapping, deployment auth, rincian izin D04, dan D05/D06/D08/D09/D10/D11/D12/D13/D14 belum sepenuhnya ditetapkan. | Target MySQL 8.0.40, aturan email nullable unik, expiry Sanctum 30 hari, NULL tanggal tanpa batas, tanggung jawab inti role, package v4.3.3, dan scope menu-only sudah dicatat; detail tersisa tetap per gate. |
| B03 | Implementasi awal auth/admin dan katalog kategori/menu sudah ada; migration katalog telah diterapkan lokal. Schema transaksi, HTTP/app/contract test, dan handoff live belum tersedia. | Lanjutkan per dependency; jangan menganggap katalog awal atau auth/admin DRAFT sebagai backend bisnis siap pakai. |
| B04 | Environment/handoff frontend belum tersedia. | Lengkapi BE-502/BE-503 sesudah endpoint dibangun dan diuji. |

## Log run dan bukti

| Run ID | Scope / commit | Hasil | Batas bukti |
| --- | --- | --- | --- |
| DOC-001 | Draft OpenAPI `4d6b965`; Python PyYAML + jsonschema | PASS pemeriksaan lokal: 18 path, 28 operasi unik, 58 schema, 463 ref resolve, 264 contoh request/response tervalidasi termasuk response error yang digunakan ulang. | Command sesi `python3 /tmp/larissama_validate_contract.py`; script sementara, bukan tool proyek/CI. Bukan lint OAS penuh dan bukan T-API-01 lulus. |
| DOC-002 | OpenAPI setelah perbaikan kapasitas laporan `17c6586` | PASS pemeriksaan yang sama: 18 path, 28 operasi, 59 schema, 463 ref resolve, 264 contoh. AggregateMoney menjaga kapasitas jumlah lintas transaksi. | Validasi dokumen/JSON Schema saja; tidak mengeksekusi Laravel, DB, atau validator OAS penuh. |
| DOC-003 | Paket dokumen pada `4b4f7a0` | PASS: 56 tautan lokal, 29 task dengan dependency tanpa siklus, 45 skenario, 28 operasi terdokumentasi, 3 contoh JSON panduan, bentuk pembelian minimal valid dan 6 input invalid ditolak schema; arithmetic fixture decimal dan kesamaan AGENTS/CLAUDE benar; 14 commit dalam paket masing-masing satu file. | Command sesi `python3 /tmp/larissama_validate_docs.py`; cek awal helper gagal menghitung ID E2E karena regex hanya menerima huruf. Regex helper diperbaiki dan pemeriksaan ulang lulus. Ini bukan hasil test aplikasi. |
| ENV-OBS-001 | Pemeriksaan shell 2026-10-04 | php -v dan composer -V: command not found. | Observasi awal; tidak ada test aplikasi yang dijalankan. |
| ENV-001 | Dockerfile/Compose `e8914b9`, `ee6a4df`, `3686947`, `a16b536`, `17863be`; Boost `bb0c553`, `0781735` | PASS: `docker compose config --quiet`; image PHP 8.3.35 dan Composer 2.10.3; `composer install` 110 package; `boost:install` selesai untuk Codex dan Claude; Pint pada User model passed. | Tidak menyelesaikan T-ENV-01: harness dan identitas DB test belum diverifikasi. MySQL 8.0.40 dipilih sebagai target dan clean-install migrations diverifikasi di `DB-MIGRATION-001`; test aplikasi serta upgrade data lama belum diverifikasi. |
| AUTH-BOOT-001 | Sanctum `7f6fba7`, `7e1ffcc`, `2eb6036`, `a2fcc30`, `2beb299`, `71cd88f`, `a3abfab`, `fbb6001`, `4cf2df6`, `fba952d` | Package v4.3.3, config expiry 30 hari (43200 menit), migration token, `HasApiTokens`, dan API route registration tersedia. | Fondasi saja: login/me/logout belum dibuat dan belum ada token yang diterbitkan. Migration token sudah diterapkan pada clean-install lokal MySQL 8.0.40 (`DB-MIGRATION-001`). Masa aktif NULL, expiry 30 hari, batas login 5/menit per username+IP, dan tanggung jawab inti role disetujui; zona waktu D08, normalisasi identitas D12, policy rinci, CORS/HTTPS, dan test aplikasi masih tersisa. |
| AUTH-CONFIG-001 | `fba952d`; `docker compose run --rm --no-deps app php artisan config:show sanctum` | PASS pemeriksaan config efektif: `expiration=43200` menit. | Read-only config inspection di container; tidak menjalankan test, route bisnis, atau migration dan tidak menyentuh database. |
| AUTH-RATE-LIMIT-001 | `0938f13`; Laravel `RateLimiter::for('login')` | Implementasi menetapkan 5 request per menit dengan kunci hash dari username lower-case dan IP; PHP lint dan Pint lulus. | Belum ada request login atau test aplikasi; lower-case hanya untuk bucket rate limit dan tidak menetapkan normalisasi identitas D12. |
| DB-SCHEMA-001 | Migration `2026_10_04_063825_create_warungs_table.php`, commit `ce2d529` | Review kode migration terhadap kolom logis: kode unik global, kontak/logo nullable, tanggal nullable, dan aktif default true. | Review kode saja; hasil eksekusi clean-install database dicatat pada `DB-MIGRATION-001`. |
| DB-LINT-001 | `ce2d529`, `9fadf12`; Docker `php -l` pada migration dan `Warung.php` | PASS pemeriksaan sintaks PHP untuk kedua file. | Parser check saja; bukan test aplikasi dan tidak memvalidasi koneksi/schema database. |
| DB-MIGRATION-001 | Migration code `ce2d529`; `docker compose run --rm --no-deps app php artisan migrate --force`; MySQL image 8.0.40 | PASS clean install pada volume project yang dikonfirmasi kosong: migration users, cache, jobs, Sanctum personal tokens, dan `warungs` semuanya selesai. | Hanya database development lokal; tidak ada data produksi. Belum membuktikan upgrade database berisi data atau menyelesaikan T-DB-01 untuk delapan tabel. Tidak menjalankan test aplikasi. |
| DB-LINT-002 | Migration `0f457df`; Docker `php -l database/migrations/2026_10_04_065854_adapt_users_for_larissama_tenants.php` | PASS pemeriksaan sintaks PHP. | Parser check saja; tidak membuktikan perilaku preflight atau schema. |
| DB-LINT-003 | Model `User.php` (`4528150`) dan `Warung.php` (`8654281`); Docker `php -l` | PASS pemeriksaan sintaks kedua model setelah penambahan field fillable/cast dan relasi dua arah user-warung. | Parser check saja; tidak menjalankan query relasi atau test aplikasi. |
| DB-LINT-004 | `WarungFactory.php` (`edba741`), `Warung.php` (`b1653cf`), dan `UserFactory.php` (`bdb4b73`); Docker `php -l` + Pint | PASS pemeriksaan sintaks dan format; factory menyusun user tenant, superadmin tanpa tenant, serta akun/warung nonaktif. | Tidak menjalankan factory terhadap DB atau test aplikasi. |
| DB-MIGRATION-002 | Migration `0f457df`; `docker compose run --rm --no-deps app php artisan migrate --force` dan `php artisan db:table users`; MySQL 8.0.40 | PASS pada DB lokal tanpa user: kolom `nama`, `username`, `email` nullable, `warung_id`, `role`, `aktif` terbentuk; unique email/username dan FK warung terlihat. | Clean install saja; guard database berisi user belum diuji. DB production/data lama tidak disentuh; test aplikasi tidak dijalankan. Schema tetap menyimpan field framework `email_verified_at` dan `remember_token`. |
| DB-MIGRATION-003 | Migration `2026_10_04_073156_add_timezone_to_warungs_table.php`, commit `2cafc46`; migrate dan `php artisan db:table warungs`; MySQL 8.0.40 | PASS: migration berjalan; `timezone` terlihat sebagai `VARCHAR(64) NULL` pada tabel InnoDB. Sesi Laravel melaporkan `@@session.time_zone = '+00:00'`. | DB development lokal saja; tidak ada data produksi. Tidak menguji rollback berisi nilai timezone atau full timestamp round-trip. |
| DB-MIGRATION-004 | Commit `eb5ea04`; dua migration kategori/menu, `php artisan migrate --force`, `php artisan db:table kategori_menus/menus`, MySQL 8.0.40 | PASS untuk schema katalog lokal: migration selesai; kategori punya FK warung dan index tenant/status/order; menu punya FK warung/kategori, unique `(warung_id,kode)`, index katalog, harga DECIMAL(15,2), dan field schema nullable sesuai rancangan. | Database development `larissama_dev` saja; tidak ada produksi. Belum menguji upgrade database lama, relasi silang melalui insert langsung, atau suite T-DB-01/03. FK gabungan D16 belum diputuskan. |
| AUTH-API-001 | Commit `2cafc46`; Pint, PHP lint, `php artisan route:list --path=api/v1`, parse YAML OpenAPI; Docker PHP 8.3.35 | PASS: Pint lulus; PHP lint lulus untuk controller, middleware, model, resource, factory, migration, route, dan config DB; 3 route auth terdaftar; OpenAPI YAML dapat dibaca; sesi MySQL UTC. | Tidak mengirim HTTP request dan bukan test aplikasi/contract. T-AUTH-01–05 serta T-API-02/04 tetap NOT_RUN; seluruh operasi auth tetap DRAFT. |
| ADMIN-API-001 | Commit `0f7e39c`; Pint, PHP lint, `php artisan route:list --path=api/v1`, parse YAML OpenAPI; Docker PHP 8.3.35 | PASS: Pint lulus; PHP lint lulus untuk action, controller, request, policy, response helper, provider, dan route; ke-12 route akses/admin terdaftar; OpenAPI YAML dapat dibaca. | Tidak mengirim HTTP request dan bukan test aplikasi/contract. T-ADM-01/02, T-TEN-01/02/03, T-RBAC-01, T-API-02/04 tetap NOT_RUN; operasi admin/profil/user tetap DRAFT. |
| CATALOG-API-001 | Commit `eb5ea04`; Pint, PHP lint file katalog, `php artisan route:list --path=api/v1`, PyYAML OpenAPI parse; Docker PHP 8.3.35 | PASS pemeriksaan statis: Pint/PHP lint lulus, 20 route API terdaftar (termasuk 8 katalog), OpenAPI memuat 28 operasi dan 8 operasi katalog berstatus IN_PROGRESS/DRAFT. | Bukan HTTP, feature, integration, atau contract test. T-CAT-01/02, T-TEN, T-RBAC, T-API-02/03/04 tetap NOT_RUN; jangan integrasikan frontend sebagai live. |

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
| 2026-10-04 | Pilihan masa token, batas tanggal, dan tanggung jawab inti role dicatat; expiry Sanctum dikonfigurasi | `d19079a`, `de0c4e6`, `fba952d`. `sanctum.expiration` = 43200 menit. Login/me/logout, migration bisnis, dan policy tetap belum dibuat; tidak ada test yang dijalankan. |
| 2026-10-04 | Target MySQL 8.0.40 dan aturan email nullable unique disetujui | Dicatat pada `c323ddf`, `6b3bf2b`; migrasi users tetap menunggu normalisasi identitas serta pemeriksaan data/migration lama. |
| 2026-10-04 | BE-101 bagian pertama: migration dan model `warungs` | `ce2d529`, `9fadf12`. Kolom dan unique index mengikuti README; model mengisi field yang diizinkan dan cast tanggal/status. PHP lint lulus (`DB-LINT-001`); clean-install migration lulus di MySQL 8.0.40 (`DB-MIGRATION-001`). Users tidak diubah dan tidak ada backfill; upgrade data lama serta test aplikasi belum dikerjakan. |
| 2026-10-04 | Pra-implementasi adaptasi `users` | Target MySQL 8.0.40; email nullable unique saat diisi; username unique global. File target migration maju sesudah `warungs`; preflight menolak sebelum DDL bila `users` berisi data. Tidak membuat username/role/warung mapping berdasarkan tebakan. Acceptance: clean local install memenuhi kolom logis; database berisi user gagal aman tanpa perubahan. Data produksi tidak disentuh. |
| 2026-10-04 | Schema users dan model tenant diselaraskan | Migration `0f457df` lulus PHP lint dan berjalan pada MySQL 8.0.40 lokal; struktur/unique/FK diperiksa read-only (`DB-MIGRATION-002`). `User` memetakan `nama`, `username`, `email`, role/status, dan relasi `warung`; `Warung` memiliki relasi `users`. PHP lint lulus (`DB-LINT-003`, commit `4528150`, `8654281`). Tidak ada user seeded; test aplikasi dan upgrade database lama berisi user belum tersedia. |
| 2026-10-04 | Factory diselaraskan dan BE-102 dimulai | Factory Warung/User tersedia (`edba741`, `b1653cf`, `bdb4b73`; PHP lint/Pint lulus `DB-LINT-004`). Pra-implementasi auth: D02 token 30 hari dan batas login 5/menit per username+IP, D03 semantik tanggal inclusive/NULL, OpenAPI auth draft, user tenant dari identitas autentikasi, superadmin tetap tanpa tenant context. Rate limiter diterapkan (`0938f13`); validasi/resource/error shape dan aturan akses tanggal dibuat (`f8baae9`). Tulis token hanya saat login valid; logout cabut token aktif; tidak ada tenant ID dari request atau transaksi bisnis. Login/me/logout dan middleware masih belum tersambung; zona waktu per warung belum punya representasi schema, detail D12/D13 tersisa; operasi tetap DRAFT. |
| 2026-10-04 | Pra-implementasi zona waktu tenant | User memilih penyimpanan UTC, tampilan lokal tiap warung, dan periode berdasarkan waktu warung. Implementasi menambah migration maju `warungs.timezone VARCHAR(64)` nullable tanpa default; API provisioning wajib mengisi identifier IANA dan akses tenant menolak nilai NULL/invalid. Tidak ada tebakan timezone atau backfill untuk data lama. Scope: migration/model/factory/resource/database README/OpenAPI. Acceptance: batas masa aktif memakai tanggal lokal warung, timestamp disimpan UTC, report periode memakai timezone warung. |
| 2026-10-04 | Factory diselaraskan dan BE-102 dimulai | Factory Warung/User tersedia (`edba741`, `b1653cf`, `bdb4b73`; PHP lint/Pint lulus `DB-LINT-004`). Pra-implementasi auth: D02 token 30 hari dan batas login 5/menit per username+IP, D03 semantik tanggal inclusive/NULL, OpenAPI auth draft, user tenant dari identitas autentikasi, superadmin tetap tanpa tenant context. Rate limiter diterapkan (`0938f13`); validasi/resource/error shape dan aturan akses tanggal dibuat (`f8baae9`). Tulis token hanya saat login valid; logout cabut token aktif; tidak ada tenant ID dari request atau transaksi bisnis. Login/me/logout dan middleware masih belum tersambung; perlakuan warung tanpa timezone, normalisasi D12, dan detail D13 tersisa; operasi tetap DRAFT. |
| 2026-10-04 | Implementasi awal auth dan timezone warung | Commit `2cafc46` menghubungkan login/me/logout, middleware pemeriksaan akun, UTC MySQL, dan migration `warungs.timezone`. Pint/PHP lint, route list, YAML parse, migration lokal dan pemeriksaan `@@session.time_zone` lulus (`AUTH-API-001`, `DB-MIGRATION-003`). Tidak ada test aplikasi; kontrak tetap DRAFT. Perlakuan tenant tanpa timezone masih asumsi sementara D08. |
| 2026-10-04 | Administrasi warung dan user tenant | Commit `0f7e39c` menambahkan admin warung/owner awal secara transaksional, profil warung saat ini, serta CRUD user owner dalam scope warung. Pint, PHP lint, 12 route dan YAML parse lulus (`ADMIN-API-001`). Tidak ada HTTP/app/contract test; role detail D04 tetap belum final dan operasi masih DRAFT. |
| 2026-10-04 | Katalog kategori dan menu | Commit `eb5ea04` menggabungkan migration/model/factory, API tenant-scope, role manager/kasir, OpenAPI, skema, serta test criteria dalam satu kelompok. Dua migration lolos pada MySQL 8.0.40 lokal. Pint, PHP lint, 20 route API, dan parse YAML lulus (`CATALOG-API-001`, `DB-MIGRATION-004`). Operasi tetap DRAFT; test aplikasi belum dijalankan. FK tenant gabungan D16 dan keputusan nominal/arsip D05/D06 masih terbuka. |

Saat mulai task, tambahkan log berisi ID task, agent/pelaksana, tujuan, fakta/invariant, scope izin, batas transaksi/retry, file, dan acceptance. Saat selesai, tambahkan perubahan, hasil test, hash tiap file, operasi yang diserahkan, serta task berikutnya.
