<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Backend database and tenant rules

- The backend is the authority for database access, tenant isolation, authorization, and business calculations. The frontend must not connect to the database directly.
- Treat [`database/README.md`](database/README.md) as the approved logical database plan. A proposed field or relationship is not implemented until it exists in a Laravel migration.
- Derive a tenant user's `warung_id` from the authenticated user. Never trust a request body, query string, or route parameter to choose a tenant for ordinary tenant-scoped operations.
- Apply tenant scope to reads, writes, updates, and deletes, and verify related records belong to the same warung. A superadmin with `warung_id = NULL` needs an explicit authorization path before accessing a warung's data.
- Enforce each user's active state at login and on every authenticated API request. For tenant users, also check the warung's active state and subscription dates at both points; define superadmin access separately because its `warung_id` is `NULL`.
- Treat menu names and prices in `penjualan_rincis` as transaction snapshots. Recalculate and validate sale amounts on the backend; do not accept frontend totals as authoritative.
- Follow [`database/AGENTS.md`](database/AGENTS.md) when creating or changing schema and migrations.
- Follow [`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md) for backend milestone order and required API contract handoff to frontend.

## Backend implementation principles

- Follow [`DEVELOPMENT_WORKFLOW.md`](DEVELOPMENT_WORKFLOW.md) for cross-repo feature work, milestone order, and acceptance gates.
- Before non-trivial backend work, identify the action, source of truth, facts being changed, invariants, authorization and tenant scope, transaction boundary, retry/concurrency behavior, API impact, and verification needed. If a business decision is missing, report it instead of inventing a rule or schema.
- Keep HTTP controllers focused on transport and authorization boundaries. Use explicit application actions for writes and queries for reads when behavior needs orchestration; a query must not change business facts. Do not add repository or domain layers without a concrete need.
- Put all database changes that must succeed or fail together in one transaction. Keep required business consequences visible in the application action; do not hide them in model observers or listeners.
- Define retry behavior for sale creation/finalization before exposing a retryable API operation. Retries must not create duplicate sales. Do not introduce an idempotency table, header, or API contract before its behavior is decided.
- Preserve completed sales as history. A cancellation or correction must follow an explicit backend operation and preserve the original transaction facts; do not hard-delete completed sales to correct them.
- Backend authorization and tenant scoping are authoritative. UI visibility is only a usability hint.
- For implementation work, identify applicable checks for the important invariants and report clearly which checks were and were not run.

## Planning, tests, handoff, and commits

- Start backend planning or implementation from [`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md). Use [`docs/backend/DESIGN.md`](docs/backend/DESIGN.md) for module boundaries and invariants, and [`docs/backend/DECISIONS.md`](docs/backend/DECISIONS.md) for approved requirements and unresolved choices. A PROPOSED choice is not a final business decision.
- Track execution in [`IMPLEMENTATION_PROGRESS.md`](IMPLEMENTATION_PROGRESS.md), including task dependencies, status, commit hashes, test run evidence, and API handoff. Documentation completion does not mean backend implementation is complete.
- Follow [`docs/backend/TEST_PLAN.md`](docs/backend/TEST_PLAN.md) for meaningful scenarios and milestone gates. Record PASS, FAIL, BLOCKED, and NOT_RUN honestly; skipped tests do not satisfy required gates. Test database constraints and concurrency against the selected production engine before claiming those guarantees.
- Maintain [`docs/api/openapi.yaml`](docs/api/openapi.yaml) and [`docs/api/README.md`](docs/api/README.md) with the implementation. An operation stays DRAFT until its decisions, endpoint, and required contract/functional tests are complete; only then mark READY_FOR_FRONTEND and record environment/version/evidence in the tracker.
- The user has authorized commits for task-related changes: after editing one file, review its diff, stage only that file, run `git diff --cached --check`, and commit it before editing the next file. Verify commit/status and preserve unrelated work. No repeated commit approval is needed within authorized scope; pushing requires separate authorization.
- Keep this file and `CLAUDE.md` synchronized, committing each file separately under the user's per-file rule. A file commit is a checkpoint; acceptance still requires the complete slice and its tests.
