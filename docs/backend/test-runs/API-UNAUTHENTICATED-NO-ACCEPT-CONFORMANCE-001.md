# API unauthenticated response without Accept header

- Date: 2026-10-06
- Scope: API auth error rendering, protected-operation 401 conformance, and local Docker development runtime.
- Initial live smoke exposed HTTP 500 for unauthenticated `/api/v1/auth/me` without `Accept: application/json`: Laravel attempted the missing web route `login` before the API exception renderer ran.
- Change: configure guest redirect target to `null` for `/api/*`; non-API guest redirect retains Laravel's `login` target.
- Focused `ProtectedOperation401ConformanceTest`: 33 tests / 951 assertions, PASS. This includes the no-Accept regression and schema-conformant anonymous 401 coverage for all protected operations.
- Full suite: 530 tests / 81,585 assertions, PASS in 46.74 seconds on MySQL 8.0.40.
- Quality gates: Pint 187 files PASS; OpenAPI 3.1 validator PASS.
- Local Docker runtime: existing app container on `127.0.0.1:8010` and MySQL 8.0.40 on `127.0.0.1:33309` were already running. Counts for users, personal access tokens, sales, and purchases were all zero before applying the three pending migrations. All 17 migrations now report `Ran`; `GET /up` is healthy; 33 API routes are registered; unauthenticated `GET /api/v1/auth/me` without Accept now returns 401 `UNAUTHENTICATED` with the D13 envelope. No user or transaction data was created.
- Disposable test/OpenAPI services and networks were removed. The existing local development app/db pair remains available.
- API operation contracts remain DRAFT; this fixes one runtime conformance gap but does not certify full live integration readiness.
