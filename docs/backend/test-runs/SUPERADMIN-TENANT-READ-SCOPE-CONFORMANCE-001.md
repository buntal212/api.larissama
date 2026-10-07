# SUPERADMIN-TENANT-READ-SCOPE-CONFORMANCE-001

Tanggal: 2026-10-07
Task: BE-107 / keputusan D04
Environment: Docker Compose test, MySQL 8.0.40 disposable

## Hasil

PASS — 101 test / 15.315 assertions.

Test mencakup GET daftar dan detail user, kategori, menu, penjualan, pembelian, serta kedua laporan. Superadmin wajib memilih `warung_id`; hasil daftar/laporan hanya berasal dari warung terpilih, detail di luar pilihan mengembalikan 404, dan timezone report mengikuti warung pilihan. Selector yang hilang/tidak valid menghasilkan 422. User tenant tetap memakai warung bearer dan tidak boleh mengirim selector. Policy tetap menolak mutasi superadmin.

Follow-up setelah cakupan diperluas ke selector wajib pada semua GET detail: `SuperadminTenantReadAccessApiTest` dan `LaporanApiTest` PASS 9 test / 1.824 assertions.

Jalankan juga Laravel Pint pada file PHP yang berubah dan validator OpenAPI 3.1 proyek; keduanya PASS. Validator melaporkan `docs/api/openapi.yaml: OK`.

## Batas bukti

Ini focused conformance untuk scope baca baru dan policy terkait. Full regression G3/G4 tidak dijalankan. Endpoint `/warung` tetap menampilkan tenant identitas token; superadmin memakai route platform `/admin/warungs/{id}` untuk melihat profil warung. Operasi tulis tidak menerima `warung_id` sebagai hak bertindak atas tenant.
