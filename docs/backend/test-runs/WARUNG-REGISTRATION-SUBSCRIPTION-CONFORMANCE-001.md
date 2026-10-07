# WARUNG-REGISTRATION-SUBSCRIPTION-CONFORMANCE-001

## Hasil

PASS untuk acceptance utama D17, per 2026-10-07.

- Feature/conformance fokus: 77 test / 21.809 assertions, MySQL 8.0.40 melalui Compose disposable.
- Pint: PASS (`vendor/bin/pint --dirty --format agent`).
- OpenAPI 3.1 validator 0.9.0: PASS (`docs/api/openapi.yaml: OK`).
- Migration `2026_10_07_124412_add_registration_approval_to_warungs_table`: diterapkan pada test DB fresh serta database development lokal.
- Route runtime: `POST /api/v1/auth/register`, persetujuan, dan perpanjangan terdaftar. API health `http://127.0.0.1:8010/up` merespons sukses dari host.

## Perintah focused

```sh
docker compose -f compose.test.yaml --project-name larissama-backend-test run --build --rm test-runner sh -lc 'vendor/bin/pint --dirty --format agent && php artisan test --compact tests/Feature/WarungRegistrationAndSubscriptionApiTest.php tests/Feature/WarungRegistrationApprovalMigrationSafetyTest.php tests/Feature/AdminWarungApiTest.php tests/Feature/AdminWarungManagementApiTest.php tests/Feature/AuthApiTest.php tests/Feature/ApiRouteOpenApiConformanceTest.php tests/Feature/ApiOpenApiExamplesConformanceTest.php tests/Feature/ApiOpenApiDocumentIntegrityTest.php tests/Feature/BusinessSchemaMigrationConformanceTest.php'
docker compose -f compose.openapi.yaml run --build --rm openapi-validator
```

## Cakupan

- Pendaftaran publik membuat owner dan warung pending secara atomik; owner belum bisa login.
- Username global duplikat menghasilkan 422 tanpa membuat warung tambahan.
- Filter admin pending tidak mencampur warung approved yang dinonaktifkan.
- Persetujuan superadmin memulai 30 tanggal lokal inklusif; approval ulang menghasilkan 409.
- Perpanjangan menambah 30 hari setelah akhir aktif atau memulai ulang 30 tanggal setelah kedaluwarsa.
- Status kedaluwarsa dan penolakan login mengikuti tanggal lokal otomatis tanpa scheduler.
- Role tenant tidak dapat menyetujui atau memperpanjang; schema kolom dan migration rollback guard diperiksa.
- Request/response utama, route inventory, contoh OpenAPI, integritas schema, dan field resource dicocokkan dengan OpenAPI.

## Batas verifikasi

Full regression suite G1–G4 tidak dijalankan untuk slice D17. Race dua admin pada satu warung, throttle pendaftaran, variasi timezone/calendar edge-case, perpanjangan warung approved tanpa tanggal akhir, serta seluruh status/error OpenAPI belum tercakup.

Commit implementasi dicatat pada tracker setelah commit kelompok fitur.
