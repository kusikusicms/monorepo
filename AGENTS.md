# AGENTS.md — Project‑specific guidelines for coding agents

Updated: 2026-01-03 20:09 (local)

This document describes how autonomous coding agents should operate in this repository. It provides context, constraints, commands, file layout, and quality gates so agents can make safe, effective changes with minimal human intervention.

---

## 1) Project overview

- Name: KusiKusi CMS (monorepo)
- Language/stack: PHP (Laravel ecosystem), Composer packages, Blade templates, Node tooling for frontend assets.
- Monorepo layout:
  - `apps/` — Application(s) built with/around the packages.
    - `apps/starter/` — Development/demo Laravel app (controllers, routes, views).
  - `packages/` — Reusable packages for the CMS.
    - `packages/kusikusicms/models/` — Core models and domain entities (e.g., `Entity`), events, collections, etc.
  - Root files: `README.md`, `CHANGELOG.md`, `composer.json`, `package.json`, etc.

Primary domain concepts visible in recent work:
- `Entity` model and related domain events (`EntityCreating`, `EntitySaving`, `EntityDeleted`, etc.).
- `EntityCollection` support utilities.
- MVC via Laravel: Controllers under `apps/starter/app/Http/Controllers/`, views under `apps/starter/resources/views/`.

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
- Keep business logic inside packages; keep app-specific glue in `apps/starter/` unless instructed otherwise.
- Do not commit secrets or environment-specific credentials; `.env` values must be documented, not committed.
- Keep database migrations idempotent and backward compatible when possible.
- Respect domain events: if changing `Entity` lifecycle, confirm correct event dispatch order and payload.

---

## 4) Common commands

PHP/Composer:
- Install deps: `composer install`
- Code style (if configured): `composer cs` or `composer lint` (check `composer.json` scripts)
- Tests (if present): `vendor/bin/phpunit`

Laravel app (in `apps/starter/`):
- Migrate DB: `php artisan migrate`
- Serve app: `php artisan serve`

Node (when working on assets):
- Install deps: `npm install`
- Build: `npm run build`
- Dev: `npm run dev`

Note: Paths may require `cd apps/starter` before artisan commands.

---

## 5) Repository conventions

- PHP version: follow `composer.json` `php` constraint.
- Namespaces: match folder structure (PSR-4). For packages under `packages/kusikusicms/*`, ensure `composer.json` `autoload` maps are honored.
- Events: Classes under `packages/kusikusicms/models/src/Events/` represent domain lifecycle hooks. Prefer dispatching them via Laravel’s model/events system rather than manual calls.
- Collections: Domain-specific collections (e.g., `EntityCollection`) should extend Laravel collections appropriately and be type-safe where possible.
- Controllers/Views: App-layer endpoints live under `apps/starter/app/Http/Controllers` with Blade views under `apps/starter/resources/views`.

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

- New domain classes (entities, value objects, services): place under relevant package, e.g., `packages/kusikusicms/models/src/` with proper namespace.
- New events: `packages/kusikusicms/models/src/Events/`.
- App controllers: `apps/starter/app/Http/Controllers/`.
- Blade views: `apps/starter/resources/views/`.
- Config published to app: under `apps/starter/config/` if required.
- Database migrations (for the app): `apps/starter/database/migrations/`.

---

## 9) Backward compatibility checklist (packages)

Before changing classes under `packages/`:
- Public API unchanged? If changed, document migration and update `CHANGELOG.md`.
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

- Commit messages: Conventional style preferred (feat:, fix:, refactor:, docs:, test:, chore:).
- Update `CHANGELOG.md` for user-facing changes in packages.
- Keep `README.md` instructions accurate if setup steps change.

---

## 13) Agent guardrails and etiquette

- If requirements are ambiguous or risky (e.g., potential breaking changes), ask for clarification.
- Prefer minimal diff; avoid mass auto-formatting unrelated code.
- Preserve existing license headers and file docblocks.
- When touching critical files (e.g., `packages/kusikusicms/models/src/Entity.php`), double-check event flows and collection types.

---

## 14) Quick reference: frequently touched files

- `packages/kusikusicms/models/src/Entity.php` — Core entity model; changes may affect multiple events and collections.
- `packages/kusikusicms/models/src/Support/EntityCollection.php` — Collection semantics for entities.
- `packages/kusikusicms/models/src/Events/*.php` — Lifecycle events.
- `apps/dev/app/Http/Controllers/EntityController.php` — App-level endpoints for entities.
- `apps/dev/resources/views/entity-show.blade.php` — Rendering layer for entity details.

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