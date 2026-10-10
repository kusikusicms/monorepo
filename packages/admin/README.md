# kusikusicms/admin

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
