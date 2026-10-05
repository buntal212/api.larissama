# PASSWORD-MINIMUM-CONFORMANCE-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit dasar runtime: `70b7591`; slice ini menambah test dan menyelaraskan dokumen D12/OpenAPI, tanpa mengubah validasi runtime.
- Runtime: Docker Compose disposable, PHP 8.3, Laravel 13, MySQL 8.0.40.
- Feature tests: PASS, 2 test / 148 assertions.
- Pint: PASS, 174 file.
- OpenAPI validator: PASS, `docs/api/openapi.yaml: OK`.
- Cleanup: service database dan network test serta network validator dihapus.

## Command

```sh
docker compose -f compose.test.yaml run --build --rm test-runner php artisan test --display-warnings tests/Feature/PasswordMinimumConformanceTest.php
docker compose -f compose.test.yaml run --rm test-runner vendor/bin/pint --test
docker compose -f compose.openapi.yaml run --build --rm openapi-validator
docker compose -f compose.test.yaml down -v --remove-orphans
docker compose -f compose.openapi.yaml down -v --remove-orphans
```

## Cakupan

- Password tujuh karakter saat provisioning owner, pembuatan user, dan penggantian password mendapat 422 dengan error pada field yang sesuai.
- Provisioning dan pembuatan user tidak menambah row; penggantian yang ditolak mempertahankan password lama.
- Request invalid tidak cocok dengan schema minimum OpenAPI; response 422 cocok dengan Error422.
- Skema OpenAPI menetapkan `minLength: 8` untuk field password yang dipakai saat membuat/mengganti password.

Run ini tidak mengubah perilaku login atau runtime. Semua operasi API tetap DRAFT karena gate conformance yang lebih luas belum selesai.
