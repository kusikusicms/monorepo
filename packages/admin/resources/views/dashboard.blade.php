<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>KusikusiCMS Admin</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, sans-serif; margin: 2rem; }
        .card { border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1.25rem; max-width: 40rem; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <div class="card">
        <h1>KusikusiCMS Admin</h1>
        <p class="muted">Starter dashboard view loaded from the admin package.</p>
        <ul>
            <li>Package: {{ isset($package) ? $package : 'kusikusicms/admin' }}</li>
        </ul>
    </div>
</body>
</html>
