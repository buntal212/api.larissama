# D06 sale history readback conformance

- Task/test: BE-303, BE-306, G3, D06/D13
- Test commit: `0091b0b`
- Database: MySQL 8.0.40 disposable Compose service
- Runtime or OpenAPI changes: none.

## Coverage

The test creates a sale, adds a correction and a partial return, then reads the sale detail endpoint. The HTTP 200 response matches the `PenjualanResponse` schema and includes the persisted correction ID, type, reason, after-snapshot, return ID, amount, reason, and updated sale status in `riwayat_koreksi` and `riwayat_retur`.

## Results

- Focused `PenjualanCorrectionApiTest`: 9 tests / 3,609 assertions, PASS.
- Full suite: 529 tests / 81,113 assertions in 50.47 seconds, PASS.
- Pint: 187 files, PASS.
- Compose cleanup: `down --remove-orphans`; `ps -a` empty.

## Boundary

This verifies the readback path for one manager and one sale history sequence. It does not close all role, status, request, or environment cases. All API operation contracts remain `DRAFT`.
