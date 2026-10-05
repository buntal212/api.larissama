# D06 sale correction, cancellation, and return RBAC conformance

- Date: 2026-10-06
- Scope: BE-306 / G3; role and tenant authorization for `PATCH /api/v1/penjualans/{id}`, `POST /api/v1/penjualans/{id}/pembatalan`, and `POST /api/v1/penjualans/{id}/retur`.
- Runtime behavior: the correction endpoint's existing deny checks and success coverage were retained. Cancellation and return were additionally exercised with schema-valid bodies and idempotency keys.
- Results: cashier and platform superadmin without a warung received schema-conformant 403 responses. A manager from another warung received schema-conformant 404. The target sale stayed `selesai`; no correction or return audit rows were written. Same-warung owner/manager success paths remain covered across existing feature tests.
- Focused test: `PenjualanCorrectionApiTest`, 9 tests / 3,741 assertions, PASS.
- Full suite: 529 tests / 81,245 assertions, PASS in 48.23 seconds on disposable MySQL 8.0.40.
- Quality gates: Pint 187 files PASS; OpenAPI 3.1 validator PASS.
- Cleanup: disposable test and OpenAPI Compose services/networks were removed; `docker ps -a` returned no matching containers.
- No runtime, schema, or OpenAPI changes. All operation contracts remain DRAFT; remaining request/status conformance and live environment handoff are still open.
