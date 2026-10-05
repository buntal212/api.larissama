# D06 sale state conflict conformance

- Task/test: BE-304, BE-306, G3, D06/D13
- Test commit: `fddf581`
- Database: MySQL 8.0.40 disposable Compose service
- Runtime or OpenAPI changes: none.

## Coverage

The acceptance test creates a partially returned sale, then confirms both correction and cancellation receive schema-conformant 409 `PENJUALAN_TIDAK_AKTIF`, with the return and sale status unchanged and no correction event. It also cancels a separate sale and confirms a later return receives schema-conformant 409 `PENJUALAN_DIBATALKAN` without creating a return record.

The first focused run failed because the test expected the more specific `PENJUALAN_SUDAH_DIRETUR` code for a partially returned sale. Runtime state validation returns `PENJUALAN_TIDAK_AKTIF` for any sale whose status is not `selesai`; the expectation was corrected to the implemented and documented state conflict. The rerun passed.

## Results

- Focused `PenjualanCorrectionApiTest`: 6 tests / 2,523 assertions, PASS.
- Full suite: 525 tests / 79,842 assertions in 46.59 seconds, PASS.
- Pint: 187 files, PASS.
- Compose cleanup: `down --remove-orphans`; `ps -a` empty.

## Boundary

This validates conflicting sale states for the covered requests. It does not close the full request/error/role matrix or base URL/environment handoff. All API operations remain `DRAFT`.
