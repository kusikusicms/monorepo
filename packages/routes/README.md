# KusikusiCMS Routes (Laravel 13)

> **Read-only split.** This repository is automatically split from [`packages/routes`](https://github.com/kusikusicms/monorepo/tree/main/packages/routes) in the [kusikusicms/monorepo](https://github.com/kusikusicms/monorepo). Please open issues and pull requests in the monorepo; pull requests sent here are closed automatically.
> Install it with `composer require kusikusicms/routes`.

Run-time routes for KusikusiCMS entities: paths built from entity slugs, redirects when slugs change, and a catch-all route that resolves incoming URLs to an entity, model, view, format and language.

Version: 13.x

This package requires [`kusikusicms/models`](https://github.com/kusikusicms/models).

## Installation

```
composer require kusikusicms/routes
```

The service provider is auto-discovered. Publish the configuration with:

```
php artisan vendor:publish --tag=kusikusicms-routes-config
```

## License

MIT. See [LICENSE](LICENSE).
