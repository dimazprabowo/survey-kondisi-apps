@props(['title' => 'Dashboard'])
@php
    view()->share('pageTitle', $title);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data
      :class="{ 'dark': $store.darkMode.dark }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title }} - {{ app_name() }}</title>

        <!-- Anti-FOUC: Apply dark mode instantly -->
        <script>
            (function() {
                var d = localStorage.getItem('darkMode');
                if (d === 'true' || (d === null && matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <!-- Favicon -->
        <link rel="icon" type="image/webp" href="{{ asset('images/bki-main.webp') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/bki-main.webp') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- Alpine stores, dark mode sync, teleport cleanup loaded via Vite (alpine-stores.js) --}}
        <script>
            // Logout form handler — intercepts logout form submit for CSRF-safe fetch
            document.addEventListener('DOMContentLoaded', function() {
                document.addEventListener('submit', function(e) {
                    const form = e.target;
                    if (form.action && form.action.includes('/logout')) {
                        e.preventDefault();
                        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                        const csrfInput = form.querySelector('input[name="_token"]');
                        if (csrfInput) csrfInput.value = csrfToken;
                        fetch(form.action, {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'text/html' },
                            body: new FormData(form),
                            credentials: 'same-origin'
                        }).then(() => window.location.href = '/').catch(() => window.location.href = '/');
                    }
                });
            });
        </script>
    </head>
    <body class="font-sans antialiased overflow-x-hidden">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900"
             x-data
             @keydown.escape.window="Alpine.store('sidebar').close()">

            {{-- Sidebar: always visible on mobile, desktop only in sidebar mode --}}
            <div x-show="Alpine.store('layout').isSidebar() || window.innerWidth < 1024"
                 x-on:resize.window="$el.style.display = (Alpine.store('layout').isSidebar() || window.innerWidth < 1024) ? '' : 'none'"
                 x-cloak>
                <livewire:layout.sidebar />
            </div>

            {{-- Main Content --}}
            <div :class="{
                     'lg:pl-64': Alpine.store('layout').isSidebar() && !Alpine.store('sidebar').collapsed,
                     'lg:pl-20': Alpine.store('layout').isSidebar() && Alpine.store('sidebar').collapsed,
                     'pl-0': Alpine.store('layout').isNavbar(),
                 }"
                 class="transition-all duration-300">

                <livewire:layout.navigation />

                @if (isset($header))
                    <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
                        <div class="py-4 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <main class="py-6">
                    <div class="px-4 sm:px-6 lg:px-8 relative">
                        {{-- Content Loading Overlay — shown during wire:navigate page transitions --}}
                        <div x-data="{ navigating: false }"
                             x-on:livewire:navigating.window="navigating = true"
                             x-on:livewire:navigated.window="navigating = false"
                             x-show="navigating"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 z-30 flex items-center justify-center bg-gray-100/80 dark:bg-gray-900/80 backdrop-blur-[2px] rounded-lg"
                             style="display: none; min-height: 60vh;">
                            <div class="text-center">
                                <svg class="animate-spin h-10 w-10 text-blue-600 dark:text-blue-400 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="mt-3 text-sm font-medium text-gray-600 dark:text-gray-400">Memuat halaman...</p>
                            </div>
                        </div>

                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        <x-toast />

        @if(session('sso_success'))
            <div x-data x-init='$nextTick(() => $store.notification.add("success", @json(session("sso_success"))))' class="hidden"></div>
        @endif

        <script>
            document.addEventListener('livewire:navigated', () => {
                if (window.location.hash) {
                    requestAnimationFrame(() => {
                        const el = document.querySelector(window.location.hash);
                        if (el) el.scrollIntoView({ behavior: 'smooth' });
                    });
                }
            });
        </script>
    </body>
</html>
