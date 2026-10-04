# CURRENT-WARUNG-API-ACCEPTANCE-001

## Lingkup

- Tanggal: 2026-10-05 (Asia/Jakarta).
- Task: BE-104; T-ADM-04; D02/D04/D13.
- Endpoint: `GET /api/v1/warung`.
- Sumber kontrak: `docs/api/openapi.yaml`, `WarungResponse`, `WarungPolicy::viewCurrent`.
- Commit kode: `607252ce02f1cbc626a277f15309c274eb509c1d` (`fix: authorize current warung profile correctly`).
- File test: `tests/Feature/CurrentWarungApiTest.php`.
- SHA-256 file test: `319e2f6ad31314d183a618846f2b595b0261dcb29f2e3689639c7dd599a59f01`.
- Database: MySQL 8.0.40 `larissama_test`, Compose disposable, fixture `RefreshDatabase`.

## Kasus

1. Manager dan kasir masing-masing membaca profil berdasarkan warung user dari bearer token. Query `warung_id` yang menunjuk tenant lain tidak mengubah target. Seluruh field resource, ID string, tanggal lokal, dan timestamp UTC dibandingkan dengan `WarungResponse`.
2. Superadmin mendapat 403 pada endpoint current-tenant.
3. Request tanpa token mendapat 401.
4. Ketiga hasil HTTP dicocokkan dengan schema response OpenAPI dan error 401/403 diperiksa memakai envelope D13.

## Probe dan perbaikan

- Probe awal tanpa trait `RefreshDatabase` gagal menyiapkan tabel pada tiga kasus database (3 gagal / 1 lulus, 27 assertions). Setup test diperbaiki; satu percobaan sesudahnya juga menemukan import trait belum ada dan dikoreksi.
- Probe berikutnya mencapai API: manager/kasir 403, superadmin 403 dan anonim 401 (2 gagal / 2 lulus, 56 assertions). `CurrentWarungController` mengirim objek `User` ke `Gate`, sehingga Laravel 13 memilih `UserPolicy`; ability `viewCurrent` ada pada `WarungPolicy`.
- Fix mengirim `Warung::class` sebagai subject Gate agar policy yang telah ada dipilih. Kondisi policy/role tidak berubah.

## Hasil akhir

- Pint: lulus.
- Focused `docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings tests/Feature/CurrentWarungApiTest.php`: **4 passed / 238 assertions**.
- Suite penuh `docker compose -f compose.test.yaml run --rm test-runner php artisan test --display-warnings`: **233 passed / 26059 assertions**.
- Environment: PHP 8.3.35, Laravel 13.34.0, MySQL 8.0.40.
- `docker compose -f compose.test.yaml down --remove-orphans`: lulus; container/network test dihapus.
- Tidak ada perubahan migration, schema database, atau keputusan izin role.

## Batas bukti

Acceptance ini mencakup sukses manager/kasir dan penolakan superadmin/anonim. Akses owner ke profil sendiri masih belum menjadi keputusan D04; karena itu operation tetap DRAFT dan tidak siap handoff frontend. Conformance payload/status lainnya serta G1 masih terbuka.
