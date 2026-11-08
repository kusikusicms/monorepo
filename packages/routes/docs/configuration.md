# Configuration

The config file is `config/kusikusicms/routes.php`, merged into `kusikusicms.routes`.

```PHP
return [
    'models' => ['*'],
    'except_models' => [],
    'slug_field' => 'slug',
    'domains' => [
        'my-site.com' => [
            'root_entity_id' => 'home',
            'lang' => 'en',
        ],
    ],
    'catch_all' => [
        'enabled' => true,
        'middleware' => ['web'],
    ],
    'controller' => App\Http\Controllers\WebsiteController::class,
    'formats' => ['html', 'rss', 'amp', 'json', 'xml'],
    'default_format' => 'html',
];
```

## Keys

| Key | Default | Description |
|---|---|---|
| `models` | `['*']` | Entity models (the `model` column, e.g. `Home`, `BlogPost`) that get routes. `*` means all. |
| `except_models` | `[]` | Models that never get routes, even with `*`. Their slugs are still used in their children's paths. |
| `slug_field` | `slug` | The `EntityContent` field used to build paths. |
| `domains` | One entry from `KUSIKUSICMS_DOMAIN` or the `APP_URL` host | The site, keyed by host. Version 13.x reads the first entry only: paths are globally unique, so there is one site per database. |
| `domains.*.root_entity_id` | `home` | Only this entity and its descendants get routes. `null` means every entity; then entities without parent are roots. |
| `domains.*.lang` | `KUSIKUSICMS_LANG` or `en` | The primary language of the site. |
| `catch_all.enabled` | `true` | Registers the catch-all fallback route. Read at boot time. |
| `catch_all.middleware` | `['web']` | Middleware of the catch-all route. |
| `controller` | `App\Http\Controllers\WebsiteController` | The host controller. It must extend `KusikusiCMS\Routes\Http\Controllers\EntityRouteController`. |
| `formats` | `html`, `rss`, `amp`, `json`, `xml` | Allowed URL extensions. Other extensions respond 404. |
| `default_format` | `html` | The format of URLs without extension. |

## Environment variables

| Variable | Used by |
|---|---|
| `APP_URL` | Default domain (its host) |
| `KUSIKUSICMS_DOMAIN` | Domain, overrides the `APP_URL` host |
| `KUSIKUSICMS_LANG` | Primary language |
