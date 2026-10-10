# KusikusiCMS

KusikusiCMS brings CMS features to Laravel projects the standard Laravel way: Composer packages, service providers with auto-discovery, Eloquent models, migrations and publishable config. Use it to build a website or blog from scratch, or add it to an existing Laravel application.

| Package | Description |
| --- | --- |
| [`kusikusicms/models`](packages/models) | Core Eloquent models (`Entity`, contents, relations) and domain events |
| [`kusikusicms/website`](packages/website) | Website/router manager |
| [`kusikusicms/media`](packages/media) | Media manager |
| [`kusikusicms/admin`](packages/admin) | Admin UI for managing content |

## Installation

### In an existing Laravel 12 application

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

Each package is split to its own read-only repository (`kusikusicms/<package>`) on every release, and published to Packagist from there.

## Versioning

Versions are aligned with Laravel major versions: `12.x` supports Laravel 12, `13.x` will support Laravel 13, and so on. Within a Laravel major, releases follow semver (`feat:` → minor, `fix:` → patch). `BREAKING CHANGE` commits are reserved for moving to the next Laravel major. All packages share the same version number.

## Development

```bash
composer install        # root workspace: autoloads and tests all packages
composer test           # run every package test suite
composer lint           # format code with Pint

cd apps/sandbox
cp .env.example .env && composer install
php artisan key:generate && php artisan migrate
php artisan serve
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full workflow, conventional commits and the release process.

## License

MIT License © 2025 Cuatromedios SC. See [LICENSE](LICENSE).
