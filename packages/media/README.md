# kusikusicms/media

> **Read-only split.** This repository is automatically split from [`packages/media`](https://github.com/kusikusicms/monorepo/tree/main/packages/media) in the [kusikusicms/monorepo](https://github.com/kusikusicms/monorepo). Please open issues and pull requests in the monorepo; pull requests sent here are closed automatically.
> Install it with `composer require kusikusicms/media`.

Media manager for KusikusiCMS.

- Namespace: `KusikusiCMS\Media`
- Service Provider: `KusikusiCMS\Media\MediaServiceProvider` (auto-discovered)

Install
```
composer require kusikusicms/media
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
