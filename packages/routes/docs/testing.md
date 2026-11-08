# Testing

## Package tests

The package tests use Orchestra Testbench with SQLite in memory and `RefreshDatabase`. `tests/TestCase.php` loads the `models` and `routes` providers and Laravel's default migrations (entities reference `users`), enables SQLite foreign keys (for the cascade on delete) and sets an app key (for the `web` middleware).

```shell
cd packages/routes
../../vendor/bin/phpunit
```

From the monorepo root, `composer test` runs every package, including the `routes` suite.

| Suite | Covers |
|---|---|
| `Unit/ParsedPathTest` | URL parsing and URI building |
| `Unit/RouteKindTest` | `isRedirect()` and `httpStatus()` |
| `Unit/PathBuilderTest` | Path building rules |
| `Feature/EntityRouteTest` | Table, enum cast, `findByPath()`, FK cascade |
| `Feature/RouteSyncingTest` | Creating, redirecting, promoting, cascading, moving, filters |
| `Feature/RouteConflictsTest` | Conflicts and rollbacks |
| `Feature/RequestHandlingTest` | Catch-all, dispatch, formats, redirects, 404s |
| `Feature/InstallCommandTest` | Prompts, defaults, `--force`, publishing, end-to-end home page |

## Testing your app

The host controller is a normal controller, so use HTTP tests:

```PHP
public function test_the_home_page_is_rendered(): void
{
    Entity::create(['id' => 'home', 'model' => 'Home'])
        ->createContents(['title' => 'Welcome', 'slug' => '']);

    $this->get('/')->assertOk()->assertSee('Welcome');
}
```

To test a controller without the database, build a `ResolvedRoute` and call its method directly.

`Event::fake()` without arguments also fakes the `EntityContent*` events, so routes are not synced. Fake only the events you assert, for example `Event::fake([EntityCreated::class])`.
