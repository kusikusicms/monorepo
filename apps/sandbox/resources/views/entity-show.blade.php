<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        <style>
            body {
                font-family: 'instrument-sans', sans-serif;
            }
        </style>

    </head>
    <body class="">
        <header class="">
            <h1>Entity {{ $entity1->id }}</h1>
            <pre>{{ $entity1->toJSON(JSON_PRETTY_PRINT) }}</pre>

            <hr>
            <h2>Children</h2>
            <pre>@foreach($children as $e){{ $e->id }}<br>{{ json_encode($e->contentsByLang ?? []) }}<br><br>@endforeach</pre>

            <hr>
            <h2>Descendants</h2>
            <pre>{{ $descendants->toJSON(JSON_PRETTY_PRINT) }}</pre>

            <h2>Ancestors</h2>
            <pre>{{ $ancestors->toJSON(JSON_PRETTY_PRINT) }}</pre>
        </header>
        <main>
        </main>
    </body>
</html>
