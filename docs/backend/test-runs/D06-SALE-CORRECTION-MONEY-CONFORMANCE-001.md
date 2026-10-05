# D06 sale correction money rule conformance

- Task/test: BE-304, BE-306, G3, D05/D13
- Test commit: `9d10125`
- Database: MySQL 8.0.40 disposable Compose service
- Runtime or OpenAPI changes: none.

## Coverage

Sale correction rejects a header discount larger than subtotal, a QRIS payment that does not exactly match the total, and a request that sends `bayar` without the paired `metode_pembayaran`. Each response is HTTP 422 `VALIDATION_ERROR` with the expected field errors. The sale total, discount, payment method, amount, and correction history remain unchanged.

## Results

- Focused `PenjualanCorrectionApiTest`: 8 tests / 3,065 assertions, PASS.
- Full suite: 528 tests / 80,569 assertions in 51.20 seconds, PASS.
- Pint: 187 files, PASS.
- Compose cleanup: `down --remove-orphans`; `ps -a` empty.

## Boundary

This confirms selected D05 rules on the sale correction endpoint. It does not cover every monetary boundary or all request/status/role cases. All API operation contracts remain `DRAFT`.
