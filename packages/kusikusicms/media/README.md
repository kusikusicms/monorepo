# kusikusicms/media

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
