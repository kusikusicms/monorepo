# kusikusicms/website

> **Read-only split.** This repository is automatically split from [`packages/website`](https://github.com/kusikusicms/monorepo/tree/main/packages/website) in the [kusikusicms/monorepo](https://github.com/kusikusicms/monorepo). Please open issues and pull requests in the monorepo; pull requests sent here are closed automatically.
> Install it with `composer require kusikusicms/website`.

Website/router manager for KusikusiCMS.

- Namespace: `KusikusiCMS\Website`
- Service Provider: `KusikusiCMS\Website\WebsiteServiceProvider` (auto-discovered)

Install
```
composer require kusikusicms/website
```

Config and routes
- Routes are auto-loaded from the package.
- Health check route: `GET /kusikusicms-health` (prefix configurable via `kusikusicms.website.route_prefix`).

Publish config
```
php artisan vendor:publish --tag=kusikusicms-config
```

Testing
- Recommend using Orchestra Testbench.
- Run tests:
```
composer install
vendor/bin/phpunit
```
