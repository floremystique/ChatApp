<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
      html, body { height: 100%; }
      body { overflow: hidden; }
    </style>

</head>

<body class="font-sans antialiased bg-gray-50 no-overscroll">
    {{ $slot }}
</body>
</html>
