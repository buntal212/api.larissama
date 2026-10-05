# D06 sale cancellation idempotency conformance

- Date: 2026-10-06
- Scope: `POST /api/v1/penjualans/{id}/pembatalan`; D09 seven-day idempotency behavior for cancellation.
- A valid owner/manager cancellation returns 201, marks the sale `batal`, and stores one correction audit event.
- Retrying the same payload with the same actor/key returns the same audit event ID and does not add another event.
- Reusing the key with a different reason returns schema-conformant HTTP 409 `IDEMPOTENCY_KEY_REUSED`; sale status and audit count remain unchanged.
- Focused test: `PenjualanCorrectionApiTest`, 9 tests / 4,058 assertions, PASS.
- Full suite: 529 tests / 81,562 assertions, PASS in 49.92 seconds on disposable MySQL 8.0.40.
- Quality gates: Pint 187 files PASS; OpenAPI 3.1 validator PASS.
- Cleanup: disposable test and OpenAPI Compose services/networks were removed; no matching containers remained.
- No runtime, schema, or OpenAPI changes. Cancellation remains DRAFT pending remaining conformance and environment handoff.
