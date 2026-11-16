@props(['title', 'lang'])
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body>
    <main>
        {{ $slot }}
    </main>
    <footer>
        <hr>
        <small><a href="/entities">Admin</a></small>
    </footer>
</body>
</html>
