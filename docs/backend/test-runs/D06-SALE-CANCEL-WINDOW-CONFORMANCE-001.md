# D06 sale cancellation window conformance

- Task/test: BE-304, BE-306, G3, D06/D13
- Test commit: `a8c60c6`
- Database: MySQL 8.0.40 disposable Compose service
- Runtime or OpenAPI changes: none.

## Coverage

`PenjualanCorrectionApiTest` now exercises cancellation at the exact 72-hour boundary and one second after it. The boundary request returns 201 and changes the sale to `batal`; the late request returns schema-conformant 409 `BATAS_KOREKSI_TERLEWATI`, leaves status `selesai`, and writes no correction event. Sale correction boundary cases remain covered by the same test.

## Results

- Focused file: 5 tests / 2,449 assertions, PASS.
- Full suite: 524 tests / 79,768 assertions in 51.24 seconds, PASS.
- Pint: 187 files, PASS.
- Compose cleanup: `down --remove-orphans`; `ps -a` empty.

## Boundary

This confirms the cancellation time boundary, not the entire request/status/role matrix. All API operations remain `DRAFT`; full G3 conformance and base URL/environment handoff remain open.
