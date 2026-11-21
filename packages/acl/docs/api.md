# JSON API contribution

When [`kusikusicms/api`](../../api/README.md) is installed, acl adds to it (disable with `KUSIKUSICMS_ACL_API=false` / `kusikusicms.acl.api.enabled`):

- **`can` flags** on every entity resource: `{view, update, delete, create_child}`, answered by `EntityPolicy` (`create_child` is `create` with the entity as parent). The UI should never decide permissions itself.
- **Filtered lists**: every entity list is restricted with `whereUserCan('view')`, so users only see what they may view.
- **`GET /api/v1/me/permissions`**: the entity permissions of the signed-in user and of the guest role, parsed:

```json
{"data": {
  "user": [{"name": "entities.update.blog.descendants", "actions": ["update"], "entity_ids": ["blog"], "scopes": ["descendants"]}],
  "guest": [{"name": "entities.view.*.*", "actions": ["view"], "entity_ids": ["*"], "scopes": ["*"]}]
}}
```

- **Route abilities**: when `kusikusicms/routes` is installed, `kusikusicms.routes.create` and `kusikusicms.routes.delete` are allowed when the user may `update` the entity.
- The capability `acl` with the features `can-flags`, `filtered-lists`, `me.permissions`.

## Performance

The flags of a whole page are computed at once: `PermissionResolver::primeAncestors()` loads the ancestors of every entity of the page in one query and `PermissionResolver::batch()` looks a missing guest role up only once, so the number of queries does not depend on the page size.

```php
$resolver = app(PermissionResolver::class);
$resolver->primeAncestors($entities->pluck('id'));
$resolver->batch(fn () => $entities->map(fn ($e) => Gate::allows('update', $e)));
```
