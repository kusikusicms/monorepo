# AGENTS.md — Project‑specific guidelines for coding agents

Updated: 2026-10-10 (local)

This document describes how autonomous coding agents should operate in this repository. It provides context, constraints, commands, file layout, and quality gates so agents can make safe, effective changes with minimal human intervention.

---

## 1) Project overview

- Name: KusiKusi CMS (monorepo)
- Language/stack: PHP (Laravel ecosystem), Composer packages, Blade templates, Node tooling for frontend assets.
- Monorepo layout:
  - `apps/` — Application(s) built with/around the packages.
    - `apps/sandbox/` — Development Laravel app wired to the local `packages/*` through symlinked path repositories.
    - `apps/starter/` — Clean end-user template (`kusikusicms/starter`), installs the packages from Packagist; do not add path repositories here.
  - `packages/` — Reusable packages for the CMS (`models`, `website`, `media`, `admin`).
    - `packages/models/` — Core models and domain entities (e.g., `Entity`), events, collections, etc.
  - Root files: `README.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `SECURITY.md`, `composer.json`, `package.json`, etc.
  - `monorepo.json` — source of truth for which projects (packages and apps) are split to read-only repositories (`split`) and published to Packagist on release (`publish`), and for the Laravel major (`laravelMajor`, currently 13) the versions are locked to. Do not hardcode project lists in workflows; read this file.
  - `docs/repository-setup.md` — checklist of GitHub/Packagist settings that live outside the repository.

Primary domain concepts visible in recent work:
- `Entity` model and related domain events (`EntityCreating`, `EntitySaving`, `EntityDeleted`, etc.).
- `EntityCollection` support utilities.
- `EntityBuilder` (`packages/models/src/Builders/`): a custom Eloquent builder holding all the Entity query filters (`childrenOf`, `descendantsOf`, `relatedBy`, `withContents`, `orderByContent`, ...). Design decisions:
  - `Entity` registers it with `#[UseEloquentBuilder(EntityBuilder::class)]`; the old `scopeXxx` model methods were removed (deliberate break, kept on 13.x as the version is not yet used in any project).
  - The builder is split into traits in `Builders/Concerns/` (`FiltersHierarchy`, `FiltersRelations`, `FiltersContents`); it stays one class so subclasses just extend `EntityBuilder`.
  - `lastUsedLang` lives on the builder (per query), not on the model. Use `$this->getModel()` instead of `Entity::` inside the builder.
  - Subclasses add filters with normal `scopeXxx` model methods (resolved via `Builder::__call`), but must override built-in filters by extending `EntityBuilder` (a model scope with a builder method's name is ignored). A subclass with its own builder must extend `EntityBuilder`.
  - Keep the `@method static` / `@property` PHPDoc on `Entity` in sync with the builder methods (needed for Larastan).
- MVC via Laravel: Controllers under `apps/sandbox/app/Http/Controllers/`, views under `apps/sandbox/resources/views/`.

---

## 2) Objectives for agents

- Implement requested features/fixes with minimal disruption to public APIs in `packages/`.
- Preserve Laravel conventions and PSR standards.
- Maintain testability and, if possible, backward compatibility in package code.
- Add or update documentation/comments where behavior is non‑obvious.

---

## 3) Hard constraints (do not violate)

- Do not edit files under `vendor/` or generated artifacts.
- Do not introduce breaking changes to public classes/functions in `packages/` unless explicitly approved by the user.
- Follow PSR-12 coding style and Laravel naming/location conventions.
- Keep business logic inside packages; keep app-specific glue in `apps/sandbox/` unless instructed otherwise.
- Do not commit secrets or environment-specific credentials; `.env` values must be documented, not committed. Workflow secrets (`SPLIT_TOKEN`, `RELEASE_APP_ID`, `RELEASE_APP_PRIVATE_KEY`, `PACKAGIST_*`) are configured in GitHub only.
- Keep database migrations idempotent and backward compatible when possible.
- Respect domain events: if changing `Entity` lifecycle, confirm correct event dispatch order and payload.

---

## 4) Common commands

PHP/Composer:
- Install deps (repo root): `composer install`
- Code style: `composer lint` (Pint); CI runs `composer lint -- --test`
- Static analysis: `composer analyse` (Larastan/PHPStan level 5, `phpstan.neon` + `phpstan-baseline.neon`). New code must not add errors; do not grow the baseline.
- Tests for all packages: `composer test`
- Dependency audit: `composer audit` and `npm audit --omit=dev`
- Validate a composer file: `composer validate --strict --no-check-lock --no-check-publish --working-dir=<dir>`

Laravel app (in `apps/sandbox/`):
- Migrate DB: `php artisan migrate`
- Serve app: `php artisan serve`

Node:
- Install deps (root, also installs the husky git hook): `npm install`
- Check a commit message: `echo "feat(models): x" | npx commitlint`
- Check a range: `npx commitlint --from origin/main --to HEAD`
- Release preview (needs the right branch/remote state): `npx semantic-release --dry-run --no-ci`
- Assets in apps (when working on them): `npm run build`, `npm run dev`

Note: Paths may require `cd apps/sandbox` before artisan commands.

---

## 5) Repository conventions

- PHP version: follow `composer.json` `php` constraint.
- Namespaces: match folder structure (PSR-4). For packages under `packages/*`, ensure `composer.json` `autoload` maps are honored.
- Events: Classes under `packages/models/src/Events/` represent domain lifecycle hooks. Prefer dispatching them via Laravel’s model/events system rather than manual calls.
- Collections: Domain-specific collections (e.g., `EntityCollection`) should extend Laravel collections appropriately and be type-safe where possible.
- Controllers/Views: App-layer endpoints live under `apps/sandbox/app/Http/Controllers` with Blade views under `apps/sandbox/resources/views`.

---

## 6) Decision process for changes (agent playbook)

1. Clarify scope
   - Restate the task in your own words.
   - Identify which layer(s) are affected: package domain, app glue, views, migrations.
2. Locate relevant code
   - Search for symbols/terms (e.g., `Entity`, `EntityController`, specific event names).
3. Propose minimal design
   - Choose the narrowest change that satisfies requirements.
   - Prefer extension points (events, service classes) over editing core logic when possible.
4. Implement with tests (when feasible)
   - For package changes, add/update unit tests under the corresponding package tests directory if present.
   - For app changes, consider feature tests in the app if a testing skeleton exists.
5. Validate locally
   - `composer install`, run tests, smoke test the dev app if changes affect runtime behavior.
6. Document
   - Update `README.md` (or package-level docs) if public behavior changes.
   - Update `AGENTS.md` with the new design decisions and implementation details.
   - Add inline PHPDoc for new/complex methods.

---

## 7) Coding standards and patterns

- Style: PSR-12, Laravel conventions (snake_case DB columns, StudlyCase models, etc.).
- Null/edge handling: Avoid fatal errors; prefer early returns and clear exceptions for invalid state.
- Collections: Prefer immutable-like patterns where possible; avoid surprising side effects.
- Events: Dispatch correct events during create/update/delete/restore lifecycles in expected order.
- Error handling: Throw domain-appropriate exceptions in packages; translate to HTTP responses at controller level.
- Dependency injection: Use Laravel’s container for services; avoid new-ing concrete classes in controllers.

---

## 8) File/location guidance

- New domain classes (entities, value objects, services): place under relevant package, e.g., `packages/models/src/` with proper namespace.
- New events: `packages/models/src/Events/`.
- App controllers: `apps/sandbox/app/Http/Controllers/`.
- Blade views: `apps/sandbox/resources/views/`.
- Config published to app: under `apps/sandbox/config/` if required.
- Database migrations (for the app): `apps/sandbox/database/migrations/`.

---

## 9) Backward compatibility checklist (packages)

Before changing classes under `packages/`:
- Public API unchanged? If changed, document the migration in the README and the PR (the changelog is generated at release time).
- Events still dispatched with same payloads and order?
- Default behavior preserved for existing consumers?
- Are there deprecation paths where possible?

---

## 10) Testing guidance

- Prefer unit tests for package logic; feature tests for app integration.
- Use Laravel’s testing helpers if the app tests are available.
- Keep tests deterministic; avoid relying on external services.
- Name tests to reflect behavior (Given_When_Then style acceptable).

---

## 11) Performance and security

- Avoid N+1 queries in controllers and domain services; use eager loading.
- Validate user input at controller/form request level.
- Sanitize output in Blade via escaped output unless explicit HTML intended.
- Do not log secrets; redact sensitive fields.

---

## 12) Git and documentation

- Commit messages: Conventional Commits are **enforced** (commitlint via the husky `commit-msg` hook and in CI). Format `type(scope): description`.
  - Types: `feat`, `fix`, `perf`, `docs`, `style`, `refactor`, `test`, `build`, `ci`, `chore`, `revert`.
  - Scopes (required): `models`, `website`, `media`, `admin`, `sandbox`, `starter`, `monorepo`, `ci`, `deps`, `deps-dev`, `release`. Use the app or package name when the change is limited to it.
- Pull request titles follow the same format (validated in CI). PRs are squash-merged; the PR title becomes the commit on `main`.
- `CHANGELOG.md` is generated by semantic-release when a release runs. **Do not edit it by hand** and do not add changelog entries in PRs; write a clear Conventional Commit title instead, since it feeds the changelog.
- Keep `README.md` instructions accurate if setup steps change. Package and app READMEs keep their "read-only split" notice.

### Releases, splits and publishing

- Releases are manual (`.github/workflows/release.yml`, `workflow_dispatch`, optional `dry_run`). Agents must never trigger or emulate a release or create tags.
- Versions follow Laravel: `13.x.y`. `feat` → minor, `fix`/`perf` → patch, breaking (`!`/`BREAKING CHANGE`) → minor only. `.github/release/guard-major.cjs` aborts a release whose major differs from `laravelMajor` in `monorepo.json`.
- Every push to `main` splits projects with `split: true` (`.github/workflows/split.yml`). Releases tag only projects with `publish: true`.
- Other workflows: `ci.yml` (validate, audit, Pint, Larastan, package tests, sandbox tests, starter smoke test), `commitlint.yml`, `pr-title.yml`, `security.yml` (dependency review, CodeQL for actions, OpenSSF Scorecard). Dependabot is configured in `.github/dependabot.yml`.
- `dependabot-lockfile.yml` auto-regenerates `package-lock.json` on Dependabot's npm pull requests, working around a `dependabot-core` bug that otherwise leaves the lock file out of sync and fails `commitlint`/`CI` with `npm ci`'s EUSAGE error. It runs on `pull_request` (never `pull_request_target`, which CodeQL flags when PR code is checked out) and reads the App credentials from the **Dependabot** secrets store, since `pull_request` runs triggered by `dependabot[bot]` get no Actions secrets. Never interpolate PR-controlled values (`head_ref`, titles, etc.) directly into `run:` scripts; pass them via `env:`.
- Split repositories are read-only: `packages/*/.github/workflows/close-pull-request.yml` and `apps/starter/.github/workflows/close-pull-request.yml` close PRs there. Do not add other workflows under those folders unless they should ship to the split repository.

---

## 13) Agent guardrails and etiquette

- If requirements are ambiguous or risky (e.g., potential breaking changes), ask for clarification.
- Prefer minimal diff; avoid mass auto-formatting unrelated code.
- Preserve existing license headers and file docblocks.
- When touching critical files (e.g., `packages/models/src/Entity.php`), double-check event flows and collection types.

---

## 14) Quick reference: frequently touched files

- `packages/models/src/Entity.php` — Core entity model; changes may affect multiple events and collections.
- `packages/models/src/Support/EntityCollection.php` — Collection semantics for entities.
- `packages/models/src/Events/*.php` — Lifecycle events.
- `apps/sandbox/app/Http/Controllers/EntityController.php` — App-level endpoints for entities.
- `apps/sandbox/resources/views/entity-show.blade.php` — Rendering layer for entity details.

---

## 15) Ready-to-use task templates

- Add a new small feature to `Entity`:
  1) Locate method/area in `Entity.php`.
  2) Add minimal code; ensure related events still make sense.
  3) Update PHPDoc and, if applicable, tests.

- Add controller action + view in the dev app:
  1) Add method in `EntityController.php`.
  2) Create/update Blade view under `resources/views/`.
  3) Wire route (if routes file is present in the app).

- Introduce a new event:
  1) Create event class under `packages/.../Events/`.
  2) Dispatch it at the correct lifecycle point.
  3) Document purpose and payload.

---

## 16) Assumptions and unknowns

- Exact PHP version, test framework setup, and code style scripts depend on `composer.json`. If missing, ask the user before adding tooling.
- If no tests exist, propose a minimal testing plan before adding new test frameworks.

---

End of AGENTS.md
