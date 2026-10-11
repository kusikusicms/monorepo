# Contributing to KusikusiCMS

Thank you for considering contributing! This document explains how to set up your environment, run tests, and propose changes.

## Repository layout

- `packages/*` — the libraries (`models`, `website`, `media`, `admin`). Namespaces are `KusikusiCMS\<Package>\`, tests live in `KusikusiCMS\<Package>\Tests\`.
- `apps/sandbox` — Laravel app that uses the local packages through symlinked path repositories. Your package edits show up immediately.
- `apps/starter` — end-user template (`kusikusicms/starter`); it installs packages from Packagist and must not use path repositories.

## Development setup

Requirements: PHP 8.4+, Composer, SQLite.

```bash
composer install            # at the repo root
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

## Running tests and lint

```bash
composer test               # all package suites (root phpunit.xml)
composer lint               # format with Pint (use `composer lint -- --test` to only check)
vendor/bin/phpunit --testsuite models   # a single package

cd apps/sandbox && php artisan test     # integration tests against the local packages
```

A single package can also be tested on its own: `cd packages/models && ../../vendor/bin/phpunit`.

Please make sure all suites are green and Pint passes before opening a PR.

## Coding standards

- Follow Laravel conventions, PSR-12 and the Pint configuration in `pint.json`.
- Prefer typed method signatures.
- Keep models slim; use scopes, observers, or services where appropriate.
- Do not introduce breaking changes to public package classes, the `kusikusicms-config` publish tag or the `kusikusicms.*` config keys.

## Commit style

Use [Conventional Commits](https://www.conventionalcommits.org/): `feat:` (minor), `fix:`/`perf:` (patch), `docs:`, `test:`, `refactor:`, `chore:`.

Versions follow Laravel majors (`13.x` ↔ Laravel 13). Reserve `BREAKING CHANGE` / `!` commits for the move to the next Laravel major.

## Releases and package splits

- Pushing to `main` runs `.github/workflows/release.yml`: semantic-release creates a `v13.x.y` tag, GitHub release and `CHANGELOG.md` entry.
- When a release is created, the same workflow splits each `packages/<name>` to `kusikusicms/<name>` with that tag (secret `SPLIT_TOKEN`: a PAT with push access to the split repositories). Each split repository is connected to Packagist.
- `.github/workflows/split.yml` is a manual fallback to re-split an existing tag.
- One-time baseline: before the first release on the Laravel-aligned numbering, create and push an annotated baseline tag on `main` so semantic-release continues from it (the old `v1.0.0` tag is left untouched):

  ```bash
  git tag -a v13.0.0 -m "Baseline for Laravel 13" && git push origin v13.0.0
  ```

## Submitting changes

1. Fork and create a feature branch.
2. Add/update tests for your changes.
3. Run `composer lint`, `composer test` and the sandbox tests.
4. Open a PR with a summary of changes and rationale.

## Reporting issues

Please include Laravel version, steps to reproduce, and any relevant logs or failing tests.
