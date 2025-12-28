# Contributing to KusikusiCMS Models

Thank you for considering contributing! This document explains how to set up your environment, run tests, and propose changes.

## Development setup

This repository is a monorepo with a Laravel starter app and package code.

- Package path: `packages/kusikusicms/models`
- Starter app: `apps/starter`

### Requirements
- PHP compatible with Laravel 12
- Composer
- SQLite available for in-memory testing

### Install dependencies
From the repo root:
```
composer install
```

## Running tests

We maintain two complementary suites:

1) Package tests (isolated, via Orchestra Testbench)
```
cd packages/kusikusicms/models
../../../vendor/bin/phpunit -c phpunit.xml
```

2) Host app integration tests (apps/starter)
```
cd apps/starter
php artisan test
```

Please ensure both suites are green before submitting a PR.

## Coding standards
- Follow Laravel conventions and the existing code style in this package.
- Prefer typed method signatures where possible.
- Keep models slim; use scopes, observers, or services where appropriate.

## Commit style
- Clear, descriptive messages. Conventional Commits are welcome but not required.

## Submitting changes
1. Fork and create a feature branch.
2. Add/update tests for your changes.
3. Run both test suites (package + apps/starter).
4. Open a PR with a summary of changes and rationale.

## Notes on architecture
- Short IDs are generated via an injectable `IdGenerator` bound in the service provider; tests may override it.
- `$dispatchesEvents` is used so host apps can listen to model lifecycle events.
- Package tests use Orchestra Testbench; a small integration suite in `apps/starter` validates real app usage.

## Reporting issues
Please include Laravel version, steps to reproduce, and any relevant logs or failing tests.