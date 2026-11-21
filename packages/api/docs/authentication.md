# Authentication

Two modes share the same login checks (throttle, user provider, `invalid_credentials` errors) and the same `auth:sanctum` middleware, so every protected endpoint accepts either.

| Client | Mode |
|---|---|
| Mobile apps, scripts, third-party or cross-site frontends | Personal access tokens (`Authorization: Bearer`) |
| A first-party SPA on the same top-level domain | Session cookie + CSRF (Sanctum stateful) |

## Personal access tokens

```http
POST /api/v1/auth/login
{"email": "ada@example.com", "password": "secret", "device_name": "iphone"}

201 {"token": "1|abc…", "user": {"id": 1, "name": "Ada", "email": "ada@example.com", …}}
```

Send `Authorization: Bearer 1|abc…` afterwards. `POST /api/v1/auth/logout` revokes that token (204). `GET /api/v1/me` returns `{data: user}`. Tokens expire after `auth.token_expiration` minutes when set.

## SPA cookie / session

1. `GET /sanctum/csrf-cookie` (Sanctum's route) sets the `XSRF-TOKEN` cookie.
2. `POST /api/v1/auth/session` with `{email, password, remember?}` and the `X-XSRF-TOKEN` header (axios and `fetch` wrappers usually copy it from the cookie). The response is `200 {data: user}`; no token is returned. The session id is regenerated.
3. Send cookies (`credentials: 'include'` / `withCredentials: true`) and the `X-XSRF-TOKEN` header on every later request.
4. `POST /api/v1/auth/logout` logs out the session guard, invalidates the session and rotates the CSRF token.

The API group runs `EnsureFrontendRequestsAreStateful` when `auth.spa.enabled` is on. It only starts a session and checks CSRF for requests whose `Origin`/`Referer` is in `sanctum.stateful`; all other requests stay stateless, so token clients are unaffected.

### Setup

```dotenv
SANCTUM_STATEFUL_DOMAINS=admin.example.com,localhost:5173
SESSION_DOMAIN=.example.com
```

`config/cors.php` (publish with `php artisan config:publish cors`):

```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['https://admin.example.com'], // never '*' with credentials
'supports_credentials' => true,
```

`auth.spa.guard` (default `web`) must be one of `sanctum.guard`.

### Troubleshooting

| Symptom | Cause |
|---|---|
| `422 stateful_required` on `/auth/session` | The SPA origin is not in `SANCTUM_STATEFUL_DOMAINS` (include the port in development) |
| `419 csrf_mismatch` | `/sanctum/csrf-cookie` was not called first, or the `X-XSRF-TOKEN` header is missing |
| Login works but the next request returns `401` | `SESSION_DOMAIN` does not cover both hosts, or the client does not send cookies |
| The browser blocks the response | CORS: the origin is not allowed or `supports_credentials` is off |

Set `KUSIKUSICMS_API_SPA=false` to disable cookie auth: `/auth/session` is not registered and no session is started.
