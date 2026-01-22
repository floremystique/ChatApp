<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'JooJo') }}</title>

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])

        @inertiaHead
        <script>
            window.Laravel = window.Laravel || {};
            window.Laravel.userId = {{ auth()->check() ? (int)auth()->id() : 'null' }};
        </script>
    </head>
    <body class="antialiased bg-gray-50">
        @inertia
    </body>
</html>
