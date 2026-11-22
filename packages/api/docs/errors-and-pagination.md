# Errors and pagination

## Error envelope

Every error under the API prefix is JSON, with or without an `Accept` header:

```json
{"message": "The given data was invalid.", "code": "validation_failed", "errors": {"model": ["The model field is required."]}}
```

| Status | `code` |
|---|---|
| 401 | `unauthenticated` |
| 403 | `forbidden` |
| 404 | `not_found` |
| 409 | `conflict` (duplicate relation, route path conflict, deleting a primary route) |
| 419 | `csrf_mismatch` |
| 422 | `validation_failed` (`errors` keyed by field), `invalid_credentials`, `stateful_required` |
| 429 | `too_many_requests` |
| 500 | `server_error` (the message is hidden unless `app.debug`) |

Requests outside the prefix keep Laravel's default rendering.

Packages throw `KusikusiCMS\Api\Support\ApiException($message, $code, $status, $errors)` or map their own exceptions:

```php
ApiErrorRenderer::map(RoutePathConflictException::class, 409, 'conflict');
```

## Pagination

Lists are length-aware: `?page=2&per_page=50`. `per_page` defaults to `pagination.per_page` (25) and is capped at `pagination.max_per_page` (100). Responses use Laravel's shape:

```json
{"data": […], "links": {"first": "…", "last": "…", "prev": null, "next": "…"},
 "meta": {"current_page": 1, "last_page": 4, "per_page": 25, "total": 92, …}}
```

The query string is kept in the pagination links.
