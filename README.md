# KusikusiCMS

KusikusiCMS brings CMS features to Laravel projects the standard Laravel way: Composer packages, service providers with auto-discovery, Eloquent models, migrations and publishable config. Use it to build a website or blog from scratch, or add it to an existing Laravel application.

| Package | Description |
| --- | --- |
| [`kusikusicms/models`](packages/models) | Core Eloquent models (`Entity`, contents, relations) and domain events |
| [`kusikusicms/website`](packages/website) | Website/router manager |
| [`kusikusicms/media`](packages/media) | Media manager |
| [`kusikusicms/admin`](packages/admin) | Admin UI for managing content |

## Installation

### In an existing Laravel 13 application

```bash
composer require kusikusicms/models kusikusicms/website kusikusicms/media kusikusicms/admin
php artisan migrate
```

Service providers are registered through Laravel package auto-discovery. Publish configuration when you need to customize it:

```bash
# every package
php artisan vendor:publish --tag=kusikusicms-config

# a single package
php artisan vendor:publish --tag=kusikusicms-models-config
php artisan vendor:publish --tag=kusikusicms-models-migrations
```

Package routes (`admin`, `website`, `media`) can be configured or disabled in `config/kusikusicms/<package>.php`:

```php
'routes' => [
    'enabled' => true,
    'prefix' => 'admin',
    'middleware' => ['web'],
],
```

### New project from the starter template

```bash
composer create-project kusikusicms/starter my-site
cd my-site
php artisan serve
```

## Repository layout

```
packages/   models, website, media, admin  (each one a Composer library)
apps/
  sandbox/  Laravel app wired to the local packages (symlinked path repositories)
  starter/  Template published as kusikusicms/starter (packages from Packagist)
```

Every merge to `main` splits each project listed with `"split": true` in [`monorepo.json`](monorepo.json) (packages and apps) to its own read-only repository (`kusikusicms/<name>`). Releases are triggered manually; only projects with `"publish": true` are tagged there and picked up by Packagist, so you decide when a package or app (for example `kusikusicms/starter`) is ready to be installed with Composer. Issues and pull requests go to this monorepo, not to the split repositories.

## Versioning

Versions are aligned with Laravel major versions: `12.x` supported Laravel 12, `13.x` supports Laravel 13, `14.x` will support Laravel 14, and so on. Within a Laravel major, releases follow semver (`feat:` → minor, `fix:`/`perf:` → patch). The major version is locked to `laravelMajor` in `monorepo.json`: breaking changes (`!` or `BREAKING CHANGE`) only bump the minor, and the release aborts if the next version would leave the Laravel major. All published projects share the same version number. Releases are created manually from **Actions → Release → Run workflow** (see [CONTRIBUTING.md](CONTRIBUTING.md#releases)).

## Development

```bash
composer install        # root workspace: autoloads and tests all packages
composer test           # run every package test suite
composer lint           # format code with Pint
composer analyse        # static analysis with Larastan (PHPStan)

cd apps/sandbox
cp .env.example .env && composer install
php artisan key:generate && php artisan migrate
php artisan serve
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full workflow, conventional commits and the release process, and [docs/repository-setup.md](docs/repository-setup.md) for the GitHub and Packagist setup checklist. Security issues: see [SECURITY.md](SECURITY.md).

## License

MIT License © 2025 Cuatromedios SC. See [LICENSE](LICENSE).
