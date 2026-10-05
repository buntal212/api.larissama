# D06 sale correction date conformance

- Task/test: BE-304, BE-306, D08/D13
- Test commit: `ba3c97c`
- Database: MySQL 8.0.40 disposable Compose service
- Runtime or OpenAPI changes: none.

## Coverage

Sale correction rejects a future RFC3339 transaction timestamp with HTTP 422 `VALIDATION_ERROR` on `tanggal`; the sale and audit history remain unchanged. A valid backdated timestamp with `+07:00` offset is accepted with HTTP 201 and stored/returned as the equivalent UTC instant.

## Results

- Focused `PenjualanCorrectionApiTest`: 7 tests / 2,881 assertions, PASS.
- Full suite: 527 tests / 80,385 assertions in 51.32 seconds, PASS.
- Pint: 187 files, PASS.
- Compose cleanup: `down --remove-orphans`; `ps -a` empty.

## Boundary

This confirms future rejection and backdate/UTC conversion for the sale correction request. It does not close all request/status/role combinations. All API operation contracts remain `DRAFT`.
