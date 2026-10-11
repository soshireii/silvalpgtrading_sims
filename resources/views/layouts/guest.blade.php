<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Silva LPG Trading</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans text-gray-900 antialiased">
    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-10 bg-maroon-700">

        <div class="flex flex-col items-center mb-8">
            <a href="/" class="flex flex-col items-center gap-3">
                <img src="{{ Vite::asset('resources/images/logo.png') }}" alt="Silva LPG Trading" class="h-40 w-auto">
                <span class="text-white font-semibold text-lg tracking-wide">Silva LPG Trading</span>
            </a>
        </div>

        <div class="w-full sm:max-w-md px-6 py-8 bg-white shadow-xl overflow-hidden rounded-2xl">
            {{ $slot }}
        </div>

        <p class="text-maroon-200 text-xs mt-8">&copy; {{ now()->year }} Silva LPG Trading. All rights reserved.</p>
    </div>
</body>

</html>