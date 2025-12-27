KusikusiCMS Monorepo

This repository hosts the KusikusiCMS ecosystem:

- packages/kusikusicms/models — Core Eloquent models used by the CMS
- packages/kusikusicms/website — Website/router manager for KusikusiCMS
- packages/kusikusicms/media — Media manager
- packages/kusikusicms/admin — Admin UI for managing content
- apps/starter — A Laravel starter project wired to use all local packages via Composer path repositories

Goals
- Develop all related packages in a single repository.
- Use a standard, Laravel-friendly layout.
- Support publishing each package independently to Packagist (via repo splits).

Repository layout
- packages/kusikusicms/* — Individual packages, each a valid Composer package with PSR-4 autoloading and a Laravel Service Provider.
- apps/starter — A Laravel application configured to consume the packages via Composer path repositories (see instructions below to initialize the app).
- .github/workflows/split.yml — GitHub Actions workflow to split packages to individual repositories for Packagist publishing.

Quick start

A) Starter app (committed)
- A full Laravel 12 starter app is committed at `apps/starter`. It requires the KusikusiCMS packages as normal Composer dependencies (intended for end users via Packagist).
- Note: Until packages are published to Packagist, `composer install` in `apps/starter` will fail to resolve them. Use the Dev App below for local development.

Run the starter app once packages are published:
- cd apps/starter
- composer install
- php artisan serve

B) Dev app (path-repo wired to local packages)
- Generate a local Laravel 12 app that uses the monorepo packages via Composer path repositories:

  bash apps/dev/bootstrap.sh

- Start the dev server:
  cd apps/dev && php artisan serve

Package auto-discovery
- Packages are set up with Laravel package auto-discovery via the `extra.laravel.providers` key. No manual registration should be necessary.

Releasing and publishing packages
- This monorepo uses unified versioning and automated releases via Semantic Release. Pushing conventional commits to `main` will produce GitHub releases and update the root `CHANGELOG.md`.
- Repo splits are handled by `.github/workflows/split.yml` using `symplify/monorepo-split-github-action`, mirroring tags and directories under `packages/kusikusicms/*` to individual repositories (e.g., github.com/kusikusicms/models, etc.). Connect each split repo to Packagist.
- Update the `split.yml` mapping with your actual destination repos before enabling the workflow.

Conventional commits
- Format commit messages using the Conventional Commits spec. Common types:
  - feat: new feature (minor release)
  - fix: bug fix (patch release)
  - perf: performance improvement (patch release)
  - docs, style, refactor, test, chore: non-user facing changes
- Breaking changes: add `!` after the type/scope or include a `BREAKING CHANGE:` footer to trigger a major release. Example: `feat!: drop PHP 8.2`

Release automation
- Workflow: `.github/workflows/release.yml` runs on pushes to `main` and manual dispatch.
- Config: `.releaserc.json` with plugins for commit analysis, notes, changelog, git commit, and GitHub release.
- Output: a tag `vX.Y.Z`, a GitHub Release with notes, and an updated `CHANGELOG.md` committed back to `main`.
- Split: once the tag exists, the split workflow can mirror it to the per-package repos where Packagist will detect the new version.

Testing packages
- Each package includes a minimal skeleton and is set up to support Testbench. You can add `orchestra/testbench` in `require-dev` and write package tests under `tests/`.

Contributing
- Make changes in the package directories.
- Use the starter app for manual end-to-end testing.
- Use conventional commits in PR titles and merge commits. Releases are produced automatically from `main`.

License
- MIT License © 2025 Cuatromedos SC. See `LICENSE` at the repository root.
