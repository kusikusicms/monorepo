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
            <h1>KusikusiCMS</h1>
        </header>
        <main>
            <h2>Entities</h2>
            <h3>Create</h3>
            <form action="/entity" method="POST">
                @csrf
                <input type="text" name="id" placeholder="ID" required><br>
                <input type="text" name="title" placeholder="Title" required><br>
                <input type="text" name="parent_entity_id" placeholder="Parent" value="home ">
                <button type="submit">Create</button>
            </form>
            <h3>List</h3>
            <ul>
                @foreach ($entities as $entity)
                    <li><a href="/entity/{{ $entity->id }}"><code>{{ $entity->id }}</code></a> {{ $entity->contents->title ?? 'No title' }}</li>
                @endforeach
            </ul>
        </main>
    </body>
</html>
