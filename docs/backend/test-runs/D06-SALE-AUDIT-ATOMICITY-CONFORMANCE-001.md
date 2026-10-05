# D06 sale audit write atomicity conformance

- Task/test: BE-304, BE-306, G3, D06/D13
- Test commit: `b699236`
- Database: MySQL 8.0.40 disposable Compose service
- Runtime or OpenAPI changes: none.

## Coverage

MySQL `BEFORE INSERT` triggers fail writes to `penjualan_koreksis` and `penjualan_returs`. The test sends a correction, cancellation, and return through the API. Each operation returns HTTP 500 with schema-conformant generic `INTERNAL_ERROR`, a request ID, and no leaked SQL message. The transaction rolls back the changed sale status/catatan, and no correction or return event remains.

## Results

- Focused `TransactionAtomicityTest`: 3 tests / 405 assertions, PASS.
- Full suite: 526 tests / 80,027 assertions in 53.66 seconds, PASS.
- Pint: 187 files, PASS.
- Compose cleanup: `down --remove-orphans`; `ps -a` empty.

## Boundary

This verifies database write failure rollback for the three sale mutation operations. It does not complete all documented HTTP status, request value, role, or environment cases. All API operation contracts remain `DRAFT`.
