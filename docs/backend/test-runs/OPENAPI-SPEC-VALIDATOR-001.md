# OPENAPI-SPEC-VALIDATOR-001

## Hasil

- Tanggal: 2026-10-05 (Asia/Jakarta)
- Status: PASS
- Dokumen: `docs/api/openapi.yaml`, OpenAPI 3.1.0
- Validator: `openapi-spec-validator` 0.9.0
- Runtime tool: Python 3.12.15
- Lingkungan: Docker Compose one-shot, terisolasi dari backend dan database test
- Output: `docs/api/openapi.yaml: OK`
- Dependency Laravel/Composer: tidak diubah
- Cleanup: container one-shot dihapus otomatis dengan `--rm`; tidak ada port atau volume data database

## Command

```sh
docker compose -f compose.openapi.yaml run --build --rm openapi-validator
```

Base image Docker memakai digest tetap. Versi validator dan semua dependency Python dipatok pada [`requirements.txt`](../../../tools/openapi-validator/requirements.txt). Compose memasang root project sebagai volume read-only. Image dibangun lokal dan tidak dipublikasikan.

## Batas bukti

Run ini membuktikan dokumen lolos validasi spesifikasi OpenAPI 3.1. Ia tidak membandingkan endpoint Laravel dengan schema saat runtime, mengesahkan keputusan bisnis yang masih terbuka, atau membuat operasi siap untuk frontend. T-API-01 tetap parsial dan seluruh operasi API tetap `DRAFT`.
