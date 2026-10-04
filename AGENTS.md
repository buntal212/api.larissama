<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

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
- The user has authorized commits for task-related changes: group related files into a logical feature or documentation commit. Review the full diff, stage only task-related paths, run `git diff --cached --check`, and verify commit/status. Preserve unrelated work. No repeated commit approval is needed within authorized scope; pushing requires separate authorization.
- Keep `AGENTS.md` and `CLAUDE.md` synchronized and commit them together with related project-rule updates. A commit is a checkpoint; acceptance still requires the complete slice and its tests.
