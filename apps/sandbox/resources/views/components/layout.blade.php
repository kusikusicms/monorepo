<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>KusikusiCMS Sandbox</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <style>
        body {
            font-family: 'instrument-sans', sans-serif;
        }
        h4 {
            margin: 1rem 0 0 0;
        }
        label {
            display: block;
            margin: 0.5rem 0;
        }
        label span {
            display: inline-block;
            width: 80px;
            text-align: right;
        }
        form button {
            margin-left: 84px;
        }
    </style>

</head>
<body class="">
<header class="">
    <a href="/">Root</a>
    <h1>{{ $title }}</h1>
</header>
<main>
    {{ $slot }}
</main>
</body>
</html>
