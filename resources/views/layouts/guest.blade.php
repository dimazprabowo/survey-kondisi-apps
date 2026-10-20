@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data :class="{ 'dark': $store.darkMode.dark }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ? $title . ' - ' . app_name() : app_name() }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/webp" href="{{ asset('images/bki-main.webp') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/bki-main.webp') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Alpine stores, dark mode sync, teleport cleanup loaded via Vite (alpine-stores.js) --}}

        @if(config('services.recaptcha.enabled'))
        <!-- Google reCAPTCHA v2 -->
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        @endif
    </head>
    <body class="font-sans text-gray-900 antialiased">
        {{ $slot }}
        
        <x-toast />
    </body>
</html>
