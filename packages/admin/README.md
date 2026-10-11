# kusikusicms/admin

> **Read-only split.** This repository is automatically split from [`packages/admin`](https://github.com/kusikusicms/monorepo/tree/main/packages/admin) in the [kusikusicms/monorepo](https://github.com/kusikusicms/monorepo). Please open issues and pull requests in the monorepo; pull requests sent here are closed automatically.
> Install it with `composer require kusikusicms/admin`.

Admin UI for managing content in KusikusiCMS.

- Namespace: `KusikusiCMS\Admin`
- Service Provider: `KusikusiCMS\Admin\AdminServiceProvider` (auto-discovered)
- Depends on: `kusikusicms/models`

Install
```
composer require kusikusicms/admin
```

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
