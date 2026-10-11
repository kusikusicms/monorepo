<!--
The PR title becomes the squashed commit message on `main` and drives the next release.
Use Conventional Commits with a scope: `type(scope): description`
Types: feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert
Scopes: models, website, media, admin, sandbox, starter, monorepo, ci, deps, deps-dev, release
Example: feat(models): add rootOf scope
-->

## What does this change?

## Why?

## How was it tested?

- [ ] `composer test`
- [ ] `composer lint -- --test`
- [ ] `composer analyse`
- [ ] Sandbox tests (`cd apps/sandbox && php artisan test`), if the change affects runtime behavior

## Checklist

- [ ] The PR title follows Conventional Commits with a scope
- [ ] No breaking changes to public package APIs (or they are documented)
- [ ] Documentation / `README.md` updated when behavior changes
