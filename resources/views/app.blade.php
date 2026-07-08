<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'STJ Dashboard') }}</title>
        <link rel="icon" type="image/png" href="https://stj-assets.sfo3.cdn.digitaloceanspaces.com/logos/stjdashboard/favicons%20st%20jacks_favicon%20dashboard.png">
        <link rel="shortcut icon" type="image/png" href="https://stj-assets.sfo3.cdn.digitaloceanspaces.com/logos/stjdashboard/favicons%20st%20jacks_favicon%20dashboard.png">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
