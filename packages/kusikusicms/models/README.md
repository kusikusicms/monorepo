# kusikusicms/models

Core Eloquent models used by KusikusiCMS.

- Namespace: `KusikusiCMS\Models`
- Service Provider: `KusikusiCMS\Models\ModelsServiceProvider` (auto-discovered)

Install
```
composer require kusikusicms/models
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
