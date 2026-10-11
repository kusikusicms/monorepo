# Contributing to KusikusiCMS

Thank you for considering contributing! This document explains how to set up your environment, run tests, and propose changes. By participating you agree to follow our [Code of Conduct](CODE_OF_CONDUCT.md). Please report security issues privately, as described in [SECURITY.md](SECURITY.md).

All development happens in this monorepo (`kusikusicms/monorepo`). The repositories `kusikusicms/models`, `website`, `media`, `admin` and `starter` are **read-only splits**: pull requests sent there are closed automatically.

## Repository layout

- `packages/*` — the libraries (`models`, `website`, `media`, `admin`). Namespaces are `KusikusiCMS\<Package>\`, tests live in `KusikusiCMS\<Package>\Tests\`.
- `apps/sandbox` — Laravel app that uses the local packages through symlinked path repositories. Your package edits show up immediately.
- `apps/starter` — end-user template (`kusikusicms/starter`); it installs packages from Packagist and must not use path repositories.
- `monorepo.json` — the list of projects (packages and apps) with their `split` and `publish` switches, and the Laravel major the versions are locked to.

## Development setup

Requirements: PHP 8.4+, Composer, SQLite and Node.js 22+ (only for commit linting and releases tooling).

```bash
composer install            # at the repo root
npm install                 # installs the git hooks (husky) and commitlint
```

### Sandbox app

```bash
cd apps/sandbox
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan serve
```

### Using local packages as dependencies

Packages that depend on each other declare a normal constraint, for example `admin` requires `"kusikusicms/models": "^13.0"`. During development the local code is used without changing those constraints:

- The root `composer.json` autoloads every package (`KusikusiCMS\Models\`, …), so `composer test` always runs against the local sources.
- `apps/sandbox` installs the packages through `path` repositories with `*@dev` constraints (symlinked). Every `composer.json` in `packages/*` has `branch-alias` `dev-main` → `13.x-dev`, which satisfies `^13.0`.
- `apps/starter` must not use path repositories. CI tests it against the local packages with a temporary `composer.ci.json` (see `.github/workflows/ci.yml`).
- Add `kusikusicms/models` to another package's `require` only when its code actually uses models classes.

## Running tests, lint and analysis

```bash
composer test               # all package suites (root phpunit.xml)
composer lint               # format with Pint (use `composer lint -- --test` to only check)
composer analyse            # static analysis with Larastan (PHPStan level 5, with a baseline)
composer audit              # known vulnerabilities in dependencies
vendor/bin/phpunit --testsuite models   # a single package

cd apps/sandbox && php artisan test     # integration tests against the local packages
```

A single package can also be tested on its own: `cd packages/models && ../../vendor/bin/phpunit`.

`composer analyse` uses `phpstan-baseline.neon` to ignore known legacy findings. New code must not add errors; fix them instead of regenerating the baseline. If you fixed baselined errors, regenerate it with `composer analyse -- --generate-baseline phpstan-baseline.neon`.

Please make sure all suites are green, Pint passes and `composer analyse` reports no errors before opening a PR.

## Coding standards

- Follow Laravel conventions, PSR-12 and the Pint configuration in `pint.json`.
- Prefer typed method signatures.
- Keep models slim; use scopes, observers, or services where appropriate.
- Do not introduce breaking changes to public package classes, the `kusikusicms-config` publish tag or the `kusikusicms.*` config keys.

## Commit messages

Every commit must follow [Conventional Commits](https://www.conventionalcommits.org/) **with a scope**:

```
<type>(<scope>): <description>
```

- Types: `feat` (minor release), `fix` and `perf` (patch release), `docs`, `style`, `refactor`, `test`, `build`, `ci`, `chore`, `revert`.
- Scopes: `models`, `website`, `media`, `admin`, `sandbox`, `starter`, `monorepo`, `ci`, `deps`, `deps-dev`, `release`.
- Examples: `feat(models): add rootOf scope`, `fix(admin): escape entity titles`, `chore(deps): bump actions/checkout`.

[commitlint](commitlint.config.mjs) enforces this locally through a husky `commit-msg` hook (installed by `npm install`) and in CI for every commit of a pull request. To check a message manually: `echo "feat(models): x" | npx commitlint`.

## Pull requests

1. Fork and create a feature branch.
2. Add/update tests for your changes.
3. Run `composer lint`, `composer analyse`, `composer test` and the sandbox tests.
4. Open a PR using the template. **The PR title must follow the same Conventional Commit format** (type and scope); it is validated automatically.
5. Pull requests are **squash-merged** and the PR title becomes the commit message on `main`, so the title is what drives the next version and the changelog.

Required checks: `CI`, `PR Title`, `commitlint` and `dependency-review`.

## Releases

Releases are manual and never run on push.

1. A maintainer opens **Actions → Release → Run workflow** on `main`. Use `dry_run` first to preview the next version and release notes without publishing anything.
2. [semantic-release](https://semantic-release.gitbook.io) computes the next `v13.x.y` from the squashed PR titles on `main`: `feat` → minor, `fix`/`perf` → patch. A `BREAKING CHANGE` or `!` also only bumps the minor.
3. The version is locked to the Laravel major (`laravelMajor` in `monorepo.json`). If the next version would belong to another major, the release aborts. Moving to Laravel 14 is a deliberate change: update `laravelMajor`, the `^13.0` constraints and the `branch-alias`, and create a new baseline tag.
4. semantic-release commits the `CHANGELOG.md` entry to `main` (message `chore(release): … [skip ci]`), creates the tag and the GitHub release. This commit is pushed with a GitHub App token that may bypass the `main` ruleset.
5. Finally, each project with `"publish": true` is split to its own repository **with the tag**, and Packagist picks up the new version. Projects with `"publish": false` are not tagged, so no stable version appears for them.

One-time baseline: the only existing tag is `v1.0.0`, so before the first release on the Laravel-aligned numbering, create and push an annotated baseline tag on `main` so semantic-release continues from it, then split it manually:

```bash
git tag -a v13.0.0 -m "Baseline for Laravel 13" && git push origin v13.0.0
# then run Actions → Monorepo Split → Run workflow with tag: v13.0.0
```

### Splits and publishing (`monorepo.json`)

```json
{"name": "starter", "path": "apps/starter", "repository": "starter", "split": true, "publish": false}
```

- `split: true` — every push to `main` updates the `main` branch of `kusikusicms/<repository>` (`.github/workflows/split.yml`). The code is visible but no version exists on Packagist.
- `publish: true` — releases also push the version tag to that repository. Requires `split: true`.
- To publish a project (for example the starter), set `"publish": true` and make sure the packages it requires are published first. No workflow edits are needed.
- The `split.yml` workflow can also be run manually with a `tag` to re-split an existing tag.

The repository settings, secrets and Packagist configuration needed for all of this are listed in [docs/repository-setup.md](docs/repository-setup.md).

## Reporting issues

Please include Laravel version, steps to reproduce, and any relevant logs or failing tests. Use the issue templates. For vulnerabilities, follow [SECURITY.md](SECURITY.md) instead of opening a public issue.
