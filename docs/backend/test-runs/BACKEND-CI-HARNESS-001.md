# BACKEND-CI-HARNESS-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Commit implementasi yang diverifikasi: `dfa6051dfe2013d9e3df7e314a83b8d8c80b811e`
- Lingkungan: Docker Desktop di Windows melalui WSL, PHP 8.3 container, MySQL 8.0.40, Compose test-only; database `larissama_test`
- Workflow: `.github/workflows/backend.yml`, memakai `actions/checkout` v5 pada commit `fbc6f3992d24b796d5a048ff273f7fcc4a7b6c09`
- YAML workflow dan dua Compose config berhasil diparse/dirender.
- `composer install --no-interaction --prefer-dist --no-progress`: PASS, lock file tidak berubah.
- `openapi-spec-validator`: PASS, `docs/api/openapi.yaml: OK`.
- Pint: PASS, 145 file.
- Full suite: PASS, 361 test / 53.778 assertions dalam 35,74 detik.
- Cleanup: kedua project menjalankan `down --remove-orphans`; `compose ps -a` sesudahnya kosong.

## Perintah yang dijalankan

```sh
docker compose --project-name larissama-openapi-ci -f compose.openapi.yaml run --build --rm openapi-validator
docker compose --project-name larissama-backend-ci -f compose.test.yaml run --build --rm test-runner sh -lc 'composer install --no-interaction --prefer-dist --no-progress && vendor/bin/pint --test && php artisan test --no-progress'
docker compose --project-name larissama-backend-ci -f compose.test.yaml down --remove-orphans
docker compose --project-name larissama-openapi-ci -f compose.openapi.yaml down --remove-orphans
```

Compose test menggunakan service MySQL tanpa port host dan tanpa volume database persisten. Suite memakai factory dan `RefreshDatabase`; fixture dua warung tersedia untuk pengujian tenant. CI menetapkan UID/GID runner agar Composer menulis dependency ke workspace dengan pemilik yang tepat dan selalu menjalankan cleanup.

## Batas bukti

Perintah CI yang sama lulus lokal pada commit di atas, tetapi workflow belum dijalankan oleh GitHub Actions karena perubahan belum dipush. Karena itu BE-004 tetap `IN_PROGRESS`; run lokal tidak membuktikan kondisi runner GitHub atau konfigurasi branch protection. Tidak ada perubahan runtime API, schema database, atau keputusan bisnis. Semua 28 operasi OpenAPI tetap `DRAFT`.
