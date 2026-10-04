# Rancangan Test dan Kriteria Lulus

Status awal dokumen ini adalah seluruh test aplikasi **NOT_RUN**. Sejak itu, run `TRANSACTION-FEATURE-001`, `TRANSACTION-READ-001`, dan `TRANSACTION-TIMEZONE-001` menjalankan sebagian feature test transaksi pada MySQL 8.0.40; suite terbaru lulus 23 test / 103 assertions. Bukti dan batasnya ada di [tracker](../../IMPLEMENTATION_PROGRESS.md) serta artefak run terkait. Milestone dan handoff API belum lulus.

Kebutuhan berasal dari K01–K08 pada [DECISIONS.md](DECISIONS.md), invariant INV01–INV11 pada [DESIGN.md](DESIGN.md), dan [OpenAPI](../api/openapi.yaml). Expected result yang bergantung Dxx adalah kandidat: finalkan keputusan dan sesuaikan test sebelum test tersebut menjadi gate.

## Strategi pelaksanaan

| Lapisan | Lokasi usulan | Yang dibuktikan |
| --- | --- | --- |
| Unit | tests/Unit/ | Decimal, rumus, periode, batas nilai; tidak menggandakan detail implementasi controller. |
| Feature | tests/Feature/ | HTTP, auth, policy, validasi, tenant, response dan perubahan DB. |
| Integration | tests/Integration/ | Migration fresh/upgrade, FK/unique/decimal, atomic rollback, transaksi dan concurrency pada engine produksi. |
| Contract | tests/Contract/ | Request/response runtime cocok dengan OpenAPI, termasuk tipe, nullability, kode status, errors. |
| Alur lintas fitur | tests/Feature/ atau runner HTTP | Provisioning sampai laporan dan logout melalui API yang sebenarnya. |

M0 memilih validator OpenAPI 3.1 yang sesuai lalu mencatat versi/command di tracker. Jangan mengunci package hanya karena dipakai App POS. PHPUnit sudah ada di composer.json; struktur folder baru belum dibuat pada tahap rancangan.

Sebelum test DB, pastikan APP_ENV=testing, koneksi dan nama database adalah target test terisolasi, serta bukan data bersama/produksi. Jangan menjalankan refresh/wipe pada koneksi yang belum diketahui. `phpunit.xml` memakai SQLite memory sebagai default, tetapi gate FK/concurrency/migration memerlukan engine target D01. `compose.test.yaml` menyediakan project tersendiri dengan MySQL 8.0.40, DB `larissama_test`, tanpa port host dan tanpa volume data persisten; jangan mengganti host/database dengan konfigurasi development. Fake clock, dua tenant, dan koneksi terpisah membuat skenario dapat diulang.

## Fixture sintetis dan expected result

Dataset angka berikut terpisah dari data produksi. Contoh ini belum berasal dari database berjalan.

- Warung A dan B aktif, masing-masing punya owner, manager, kasir, kategori, dan menu. Tambahkan user nonaktif, warung nonaktif, warung belum mulai, dan warung kedaluwarsa untuk test akses.
- Warung A: Nasi harga `15000.00`, Teh `5000.00`. Warung B punya kode/nama sama tetapi harga berbeda untuk mendeteksi lookup lintas tenant.
- S1, A, 2026-10-04: Nasi 2 + Teh 1, diskon header `2000.00`, bayar cash `50000.00`. Subtotal `35000.00`, total `33000.00`, kembalian `17000.00` menurut kandidat D05.
- S2, A, tanggal sama: transaksi batal total `10000.00` untuk fixture laporan (jangan mengasumsikan endpoint cancel sudah ada). S3, B: penjualan selesai `99000.00`. S4, A: penjualan September `25000.00`.
- P1, A, 2026-10-04: satu rincian `Belanja di pasar`, nominal `150000.00`, qty/satuan/harga NULL.
- P2, A, tanggal sama: Beras 5 × `15000.00` dan Cabai 0.50 × `40000.00`; subtotal `75000.00` dan `20000.00`; total `95000.00`.
- P3, B: pembelian `900000.00`. P4, A, September: pembelian `40000.00`.
- Expected laporan Oktober A: jumlah penjualan selesai 1, pendapatan `33000.00`; jumlah pembelian 2, total `245000.00`. Tiga rincian pembelian tidak boleh mengubah count header 2 menjadi 3 atau menggandakan nominal.

Fixture batas waktu/rounding harus dibuat pada dataset terpisah agar tidak mengubah expected agregasi di atas. Angka pecahan dibandingkan secara eksak, bukan tolerance float.

## Matriks test

Kolom lulus menjelaskan observable result, bukan sekadar `assertStatus(200)`. Semua variasi tenant/role/operasi yang relevan harus diparameterisasi; satu endpoint yang lolos tidak membuktikan endpoint lain aman.

| ID | Skenario dan lapisan | Kriteria lulus | Prasyarat |
| --- | --- | --- | --- |
| T-ENV-01 | Runtime, dependencies, harness dan DB test terisolasi | Versi memenuhi composer constraints; harness jalan; identitas DB test terbukti; tidak menyentuh DB bersama. | D01, BE-001 |
| T-DB-01 | Migration baru pada engine target | Delapan tabel bisnis terbentuk sesuai rancangan; PK/FK gabungan/unique/index/nullability/decimal benar. Sesi MySQL melaporkan `@@session.time_zone = '+00:00'`, dan timestamp round-trip konsisten UTC. Detail membawa `warung_id`; `penjualan_rincis.menu_id` wajib; `jenis_item` tidak ada; idempotency key unik per tenant/user/endpoint (constraint terpisah pada tabel header). Jalankan subset tabel per milestone; full delapan pada M4/M5. | D01,D07,D08,D09,D16 |
| T-DB-02 | Upgrade users awal dan data lama | Data/ID/password hash yang sah tetap utuh; migration tidak menebak warung; hasil pemetaan sesuai sumber; tanpa edit migration bersama. | D01,D12 |
| T-DB-03 | Constraint dan relasi tenant | Kode/nomor boleh sama pada warung berbeda bila unique composite; duplikat dalam warung ditolak; orphan ditolak; FK gabungan D16 dan scope/validasi aplikasi sama-sama menolak relasi silang tenant. | D01,D12,D16 |
| T-AUTH-01 | Login benar/salah, me | Kredensial sah memberi identity/scope sesuai user; salah 401 tanpa informasi sensitif; /me mengembalikan identitas aktual. | D02,D03,D12 |
| T-AUTH-02 | Nonaktif setelah token/sesi terbit | Login dan request bisnis berikutnya ditolak ketika user/warung dinonaktifkan; tidak ada perubahan data. | D02,D03 |
| T-AUTH-03 | Masa aktif, timezone, dan NULL | Konversi instant UTC ke `warungs.timezone` sebelum membandingkan tanggal lokal inklusif; uji dua timezone berbeda pada instant UTC yang sama. NULL/invalid timezone menolak tenant. NULL pada tanggal_mulai tidak membatasi awal; NULL pada tanggal_berakhir tidak membatasi akhir; uji kedua NULL sekaligus serta masing-masing satu NULL. User dan warung tetap harus aktif. | D03,D08 |
| T-AUTH-04 | Logout dan sesi kadaluwarsa | Token baru berlaku sebelum genap 30 hari; token ditolak saat mencapai expiry 30 hari. Logout sukses 204 tanpa body dan token yang dicabut tidak dapat dipakai kembali. | D02 |
| T-AUTH-05 | Password, log, rate limit | Password tersimpan hash dan tidak keluar response/log; request login melebihi batas mendapat 429; fixture bukan kredensial nyata. | D02,D12 |
| T-TEN-01 | Daftar/laporan A dengan data B | Tidak ada row, count, atau nominal B pada seluruh endpoint tenant, termasuk filter, search, pagination, dan summary. | D04 |
| T-TEN-02 | Detail/update ID milik B | User A mendapat 404 sesuai D13; data B tidak berubah; lookup rincian selalu melalui header tenant. | D04,D13 |
| T-TEN-03 | Injeksi warung/user/menu/kategori | Field pemilih tenant/user tidak didukung ditolak 422; referensi kategori/menu B ditolak; tetap tidak ada write parsial. | D04,D13 |
| T-RBAC-01 | Seluruh role × tindakan | Aksi yang diizinkan berhasil; semua kombinasi terlarang 403; superadmin tidak otomatis memakai jalur transaksi tenant; hak kasir atas riwayat mengikuti matriks final. | D04 |
| T-ADM-01 | Provision warung+owner, gagal owner | Keduanya tersimpan satu transaksi atau keduanya rollback; kode/username unik; owner terkait warung baru. | D04,D12 |
| T-ADM-02 | Pengelolaan user dan eskalasi role | Owner hanya mengelola user yang diizinkan di warungnya; create/patch superadmin atau warung_id ditolak; perubahan profil tidak memindahkan tenant. | D04,D12 |
| T-CAT-01 | Kategori/menu create/read/update/filter | Data dan response sesuai input sah; kode unique per warung; kategori harus satu warung; tenant lain tidak terbaca/terubah; pagination/search/sort/aktif konsisten; kasir selalu hanya melihat menu/kategori aktif. | D04,D05,D06,D13 |
| T-CAT-02 | Nonaktifkan kategori/menu | Manager dapat mengubah status; kasir tidak menerima kategori/menu nonaktif (termasuk detail dan filter `aktif=false`); FK tetap menolak hard delete; aturan transaksi terhadap katalog nonaktif mengikuti D06. | D04,D06 |
| T-SAL-01 | Simpan S1 melalui API | Tepat 1 header dan 2 detail; subtotal 35000.00, total 33000.00, bayar 50000.00, kembalian 17000.00; response sama dengan nilai DB. | D05 |
| T-SAL-02 | Otoritas dan scope menu pada rincian | Setiap baris wajib menunjuk menu aktif pada warung sama; nama/harga dari client tidak dipercaya dan snapshot datang dari master. Menu salah tenant, nonaktif, ID tak ada, rincian kosong, atau field `jenis_item`/item bebas ditolak; tidak ada write parsial. | D05,D06,D07 |
| T-SAL-03 | Exception pada detail kedua | Header, detail pertama, dan efek nomor/retry rollback bersama; tidak ada orphan/penjualan setengah jadi. | D09 |
| T-SAL-04 | Nama/harga menu berubah setelah S1 | Read detail/riwayat S1 tetap nama/harga/total awal; transaksi baru mengikuti kebijakan harga final. | D05 |
| T-SAL-05 | Nilai negatif/nol/overflow/rounding/diskon/bayar | Uji decimal string eksak dan round half-up per rincian, termasuk 0.01 × 0.50 = 0.01 bila fractional qty dikonfirmasi. Validasi batas schema DECIMAL(15,2), diskon berlebih, dan cash kurang. Perilaku harga nol/fractional qty/QRIS-transfer mengikuti D05 final; asumsi implementasi sekarang bukan PASS sebelum disetujui. Semua input invalid 422 tanpa write. | D05 |
| T-SAL-06 | Cancel/koreksi penjualan | Bila masuk scope final: status/izin/alasan/audit sesuai keputusan, snapshot tetap; laporan mengeluarkan batal; pengulangan tidak menggandakan efek. Jika ditunda, keputusan defer dicatat, bukan PASS. | D06 |
| T-BUY-01 | Input P1 hanya nama_item+subtotal | 201, 1 header+1 detail, total 150000.00, qty/satuan/harga_satuan NULL; tidak menuntut master bahan. | K05,D05 |
| T-BUY-02 | Input P2 rinci | 201, 1 header+2 detail; subtotal `75000.00` dan `20000.00`, total `95000.00`; backend menghitung dengan decimal eksak dan round half-up per rincian. | D05,D10 |
| T-BUY-03 | Rincian kosong/nama kosong/mismatch/pasangan sebagian | Array kosong/nama kosong ditolak 422; implementasi kandidat juga menolak qty/harga_satuan yang tidak berpasangan dan subtotal mismatch. Bentuk P1 tetap diterima tanpa qty/unit/price; detail lain mengikuti keputusan final D10. | D10 |
| T-BUY-04 | Kegagalan detail terakhir | Tidak ada header/detail/total/efek retry parsial setelah rollback. | D09 |
| T-BUY-05 | Independensi pembelian | Setelah P1/P2, jumlah/nilai penjualan, menu, dan snapshot tidak berubah; tidak ada syarat maupun efek stok/resep. | K03 |
| T-BUY-06 | Koreksi pembelian dan laporan | Jika fitur disetujui: metadata/fakta original terjaga dan agregasi mengikuti D11. Jika ditunda, tidak membuat field status fiktif atau endpoint sendiri. | D11 |
| T-RET-01 | Retry intent yang sama setelah respons hilang | Key sama untuk tenant/user/endpoint dan payload identik menghasilkan HTTP 201 dengan id/header/detail yang sama; tepat satu header. Uji sale dan purchase. | D09 |
| T-RET-02 | Intent sama dengan payload berbeda | Key sama dengan payload body berbeda menghasilkan HTTP 409 `IDEMPOTENCY_KEY_REUSED`; tidak ada transaksi kedua/perubahan diam-diam. | D09 |
| T-RET-03 | Dua koneksi bersamaan | Request duplikat benar-benar overlap dan menghasilkan satu transaksi; key scope tenant/user/endpoint tidak bertabrakan antar user/warung/endpoint; nomor berbeda tetap unik. Gunakan barrier pada MySQL. | D01,D09 |
| T-RET-04 | Crash sebelum/sesudah commit dan restart worker | Key/hash/transaksi tetap konsisten setelah restart karena tersimpan pada header; sebelum commit tidak ada header, sesudah commit retry me-replay. Key tidak kedaluwarsa selama header dipertahankan. | D09 |
| T-REP-01 | Pendapatan Oktober A | Count 1 dan 33000.00; tidak memasukkan batal, B, September, bayar 50000.00, atau pembelian. | D06,D08 |
| T-REP-02 | Total pembelian Oktober A dan kapasitas agregasi | Count 2 dan 245000.00; 3 detail tidak menggandakan total/count. Halaman list yang berbeda tidak mengubah summary. Dataset batas terpisah: dua header masing-masing 6000000000000.00 menghasilkan AggregateMoney 12000000000000.00 secara eksak, tanpa batas kapasitas satu header. | D05,D08,D11 |
| T-REP-03 | Batas hari dan timezone per warung | Asia/Jakarta 2026-10-04: UTC mulai 2026-10-03T17:00:00Z masuk; sebelum itu keluar; tepat 2026-10-04T17:00:00Z keluar. Kasus `America/New_York` pada 2026-03-08 menguji hari DST 23 jam dengan jendela `[2026-03-08T05:00:00Z, 2026-03-09T04:00:00Z)`: sebelum awal dan tepat akhir keluar, tepat awal dan detik terakhir masuk. Uji presisi akhir yang didukung DB masih terpisah. | D08 |
| T-REP-04 | Periode kosong dan filter invalid | Periode sah kosong memberi count=0/nominal 0.00; tanggal cacat, pasangan hilang, atau awal>akhir mendapat 422, bukan total nol palsu. | D08,D13 |
| T-API-01 | Lint spec/ref/contoh | OpenAPI 3.1 valid di validator M0; semua ref resolve, operationId unik, contoh request/response cocok schema; tidak mengklaim DRAFT sebagai live. | D13 |
| T-API-02 | Conformance terhadap HTTP aktual | Setiap operasi yang diserahkan diuji request/response sukses dan gagal; ID/nominal string, nullability, required, pagination, status HTTP dan tanpa field rahasia sesuai spec. | Implementasi slice |
| T-API-03 | Pagination/search/sort/aktif | Batas 1–100, sort allowlist, tie-breaker stabil, empty page dan metadata benar; pencarian tetap scoped; input sort berbahaya tidak menjadi SQL bebas. | D13 |
| T-API-04 | Errors dan 204 | Error shape stabil dengan dotted field paths dan request_id; tanpa SQL/stack/secret; logout 204 tidak berisi JSON; 401/403/404 tidak tertukar. | D02,D13 |
| T-OPS-01 | Upgrade, backup/restore, rollback aplikasi | Pada environment uji, data fixture terjaga sesudah upgrade/recovery; instruksi runbook dapat diulang; tidak bergantung migrate:fresh produksi. | D01, runbook |
| T-OPS-02 | Config/CORS/log/secret/health | Origins/credential policy sesuai D02, env rahasia tidak di-commit/dilog, health aman; environment integrasi terdokumentasi dan dapat diakses penerima. | D02, deployment |
| T-E2E-01 | Alur API dari provisioning sampai laporan/logout | Warung/user/katalog/sale/P1/P2 terbentuk lewat API sah; angka fixture dan isolasi tenant benar; logout memutus akses. | M1–M4 |
| T-E2E-02 | Handoff frontend | Penerima mencatat versi spec/commit/base URL, mencoba contoh sukses/error dan kedua bentuk pembelian; hasil tercatat. Jika environment/penerima belum tersedia, tetap BLOCKED/PENDING. | Endpoint READY kandidat |

## Gate milestone

| Gate | Test wajib dan hasil yang diterima |
| --- | --- |
| G0 / M0 | T-ENV-01 dan T-API-01 lulus; D01/D02/D03/D04/D08/D12/D13 yang dibutuhkan M1 ditetapkan; harness dan DB test siap. |
| G1 / M1 | T-DB-01/02/03 untuk warung/users, T-AUTH-01–05, T-TEN-01–03, T-RBAC-01, T-ADM-01–02, T-API-02/04 untuk operasi M1 semuanya lulus. |
| G2 / M2 | T-DB-01/03 untuk katalog, T-CAT-01–02, T-TEN-01–03, T-RBAC-01, T-API-02/03/04 untuk katalog lulus. |
| G3 / M3 | T-SAL-01–05, T-RET-01–04 untuk penjualan, T-REP-01/03/04, test DB/tenant/role/contract slice lulus. T-SAL-06 lulus atau scope defer telah diputuskan eksplisit. |
| G4 / M4 | T-BUY-01–05, T-RET-01–04 untuk pembelian, T-REP-02/03/04, test DB/tenant/role/contract slice lulus. T-BUY-06 lulus atau scope defer eksplisit. |
| G5 / M5 | Semua gate sebelumnya terpenuhi; T-OPS-01–02 dan T-E2E-01–02 lulus; tidak ada kontrak live tanpa bukti conformance; runbook dan handoff lengkap. |

Test tenant, uang, rollback, retry, dan kontrak yang wajib harus 100% lulus dengan nol failure/error. Skipped, blocked, atau not-run tidak dihitung sebagai pass. Defer hanya berlaku pada fitur opsional yang benar-benar diputuskan ditunda, bukan menghilangkan syarat K01–K07. Persentase coverage baris saja tidak menggantikan invariant.

## Rencana command dan bukti

Periksa konfigurasi Compose sebelum menjalankan test, lalu jalankan suite terfokus hanya pada project test. `RefreshDatabase` menjalankan migration pada DB `larissama_test`; jangan mengarahkan koneksi itu ke service development atau DB lain. `IdempotencyConcurrencyTest` menjalankan dua proses Laravel HTTP Kernel dengan koneksi MySQL terpisah, menahan keduanya pada barrier sebelum request, dan memakai trigger delay sementara pada DB disposable.

```sh
docker compose -f compose.test.yaml config --quiet
docker compose -f compose.test.yaml run --rm test-runner sh -lc 'composer install --no-interaction && php artisan config:clear && php artisan test --display-warnings tests/Feature/PenjualanApiTest.php tests/Feature/PembelianApiTest.php tests/Feature/TransactionAtomicityTest.php tests/Feature/IdempotencyConcurrencyTest.php'
docker compose -f compose.test.yaml down --remove-orphans
```

Validator OpenAPI tetap terpisah dari test HTTP. Runs `TRANSACTION-FEATURE-001`, `TRANSACTION-READ-001`, dan `TRANSACTION-TIMEZONE-001` sudah menjalankan slice feature test transaksi pada DB MySQL terisolasi; suite `composer test` terbaru lulus 23 test / 103 assertions. Rincian hasil serta cakupan yang masih terbuka tercatat di tracker dan artefak run. Catat setiap run dengan command, environment, commit, hasil aktual, serta gap. Jangan mengklaim concurrency PASS bila barrier tidak benar-benar dilewati dua request.

Setiap run dicatat dengan format berikut pada [tracker](../../IMPLEMENTATION_PROGRESS.md):

```text
run_id:
tanggal_dan_timezone:
task_ids / test_ids:
commit_yang_diuji:
environment / php / framework / engine_versi / identitas_db_test_tanpa_secret:
command:
expected:
actual / pass / fail / skipped:
status: PASS | FAIL | BLOCKED | NOT_RUN | NOT_APPLICABLE
lokasi_output_atau_artifact:
catatan_gap / keputusan / tindak_lanjut:
```

FAIL tidak dihapus oleh rerun; catat perbaikan dan run baru. NOT_APPLICABLE memerlukan keputusan scope yang ditautkan. Update progress sesudah satu slice diverifikasi, bukan mengklaim seluruh milestone selesai hanya karena migration atau dokumentasinya selesai.
