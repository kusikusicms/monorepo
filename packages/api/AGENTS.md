# AGENTS.md — `packages/api`

Guidance for coding agents working on `kusikusicms/api` (`KusikusiCMS\Api`). Read the root `AGENTS.md` first. Human docs live in `README.md` and `docs/*.md`.

## Purpose and boundaries

- A versioned JSON API (`/api/v1`) so the CMS runs headless and the future admin SPA has a backend.
- Owns the plumbing (route group, Sanctum token and SPA session auth, error envelope, pagination, deny-by-default, capabilities, extension hooks) and the **entity endpoints** (models has no HTTP code and cannot depend on api).
- Requires `models` and `acl`. Other packages (`acl`, `routes`, later `media`) contribute their own endpoints and fields; api never references them.
- Out of scope: admin SPA, OpenAPI, password reset, 2FA, registration.

## Class map

| Class | Role |
|---|---|
| `ApiServiceProvider` | merges `kusikusicms.api`, `Capabilities` singleton, `Gate::after` deny-by-default, routes, own capability, `ApiErrorRenderer`, `AboutCommand` (warns when `auth.spa.guard` is not in `sanctum.guard`) |
| `Api` | `routes(Closure)` (prefix, middleware, `kusikusicms.api.` names; prepends `ForceJsonResponse` and, with SPA auth, Sanctum's `EnsureFrontendRequestsAreStateful`), `capability()`, `prefix()`, `spaEnabled()` |
| `Support\ApiErrorRenderer` | `{message, code, errors?}` for requests under the prefix only; `map(exception, status, code)` for package exceptions |
| `Support\ApiException` | throw with a machine `code` and status |
| `Support\Capabilities`, `Capability` | registry for `GET /capabilities` (same name replaces) |
| `Support\Pagination` | `perPage()` clamped to `max_per_page` |
| `Actions\AttemptLogin` | throttle (`login_throttle`, key email+IP, shared by both modes) + credential check through the guard's user provider; 422 `invalid_credentials` |
| `Actions\IssueToken`, `StartSession`, `Logout` | token issue (expiry), session login (422 `stateful_required` when not from `sanctum.stateful`, session regenerated), logout by mode (`TransientToken` = cookie session) |
| `Actions\CreateEntity`, `UpdateEntity`, `SaveContents`, `SyncRelation` | writes in transactions through `save()`/`createContents()`/model deletes (events always fire); entity saved before its contents; `ancestor` relations rejected |
| `Services\EntityQueryBuilder` | maps list params to `EntityBuilder` filters, includes, sort, trashed; applies `EntityQuery` modifiers |
| `Services\EntityQuery` | static query modifiers `fn (EntityBuilder, Request, 'index'\|'show')` |
| `Http\Resources\EntityResource` (+ `EntityResourceCollection`) | entity shape; `extend($fields, $prepare)`; `prepare` runs once per response (page, tree incl. children, or one entity) using a `WeakMap` |
| `Http\Requests\*` | validation + `Gate` authorization (`create` on the parent, `update` + `create` on a new parent when moving) |

## Invariants

- **Authorization goes through `Gate`** (policies or ability handlers); never call Spatie or build permission names here. The acl event guard is not toggled by api.
- **Deny by default** applies only when no handler answered (`$result === null`) and only to `kusikusicms.*` abilities and Entity abilities; never to app abilities.
- **No N+1**: list endpoints run a fixed number of queries; keep the query-count tests green and batch extension work in `prepare`.
- **Never use mass writes** for entities, contents or relations; route syncing and the acl guard rely on model events.
- **Errors**: only requests under the prefix are rendered by api; keep codes stable (clients depend on them).
- **SPA auth**: never open CORS to `*`; the stateful middleware is added only when `auth.spa.enabled`; `/auth/session` only for stateful requests; regenerate on login, invalidate on logout.
- Hooks are static: tests must call `EntityResource::flushExtensions()` and `EntityQuery::flush()`.

## Tests

```bash
vendor/bin/phpunit --testsuite api     # from the monorepo root
```

Matrix: `TestCase` (models + api, no acl: deny-by-default path), `WithAclTestCase` (+ Spatie, acl), plus contribution tests in `packages/acl/tests/Api` and `packages/routes/tests/Api`. Sanctum migrations come from the root `vendor`; the fixture users table from `tests/Fixtures/Migrations`. CSRF is skipped in unit tests, so the 419 envelope is tested by throwing `TokenMismatchException` from a test route. Stateful tests set `sanctum.stateful=['localhost']`, `session.driver=array` and an `Origin` header.

## Quality gate

`composer test`, `composer lint -- --test`, `composer audit`, `composer analyse -- --no-progress` (do not grow `phpstan-baseline.neon`).

## Commits

Conventional Commits with the `api` scope, e.g. `feat(api): add entity search`.
