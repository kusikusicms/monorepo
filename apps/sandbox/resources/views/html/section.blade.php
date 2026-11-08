{{--
    Rendered by App\Http\Controllers\WebsiteController::home() for entities with the "home" view
    requested as HTML ("{format}.{view}" → "html.home").
    Available: $entity (contents in $lang), $children (live children), $paths (child id → path) and $lang.
    Missing content fields are empty strings.
--}}
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $entity->contents->title ?: config('app.name') }}</title>
</head>
<body>
    <main>
        <h1>{{ $entity->contents->title ?: config('app.name') }}</h1>

        @if ($entity->contents->body)
            <p>{{ $entity->contents->body }}</p>
        @endif

        @if ($children->isNotEmpty())
            <nav>
                <ul>
                    @foreach ($children as $child)
                        <li>
                            <a href="{{ $child->route }}">{{ $child->contents->title ?: $child->id }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </main>
</body>
</html>
