<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Dashboard' }} - Silva LPG Trading</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body class="font-sans antialiased bg-gray-50 text-gray-900" x-data="{ sidebarOpen: false }">

    <div class="min-h-screen flex">

        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
            class="fixed inset-0 bg-black/40 z-30 lg:hidden"></div>

        <aside
            class="fixed inset-y-0 left-0 z-40 w-64 bg-maroon-700 flex flex-col transform transition-transform duration-200 lg:translate-x-0 lg:static lg:z-auto"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">

            <div class="h-16 flex items-center gap-2 px-5 border-b border-maroon-600/60">
                <div class="w-8 h-8 rounded-md bg-white text-maroon-700 flex items-center justify-center font-bold text-sm">S</div>
                <div class="leading-tight">
                    <p class="text-white font-semibold text-sm">Silva LPG Trading</p>
                    <p class="text-maroon-200 text-xs">{{ ucfirst(auth()->user()->role) }} Panel</p>
                </div>
            </div>

            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                @auth
                @if(auth()->user()->isAdmin())
                @include('partials.nav-admin')
                @elseif(auth()->user()->isCashier())
                @include('partials.nav-cashier')
                @elseif(auth()->user()->isDelivery())
                @include('partials.nav-delivery')
                @endif
                @endauth
            </nav>

            <div class="p-3 border-t border-maroon-600/60">
                <div class="flex items-center gap-3 px-2 py-2">
                    <div class="w-9 h-9 rounded-full bg-maroon-500 text-white flex items-center justify-center text-sm font-semibold">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-white text-sm font-medium truncate">{{ auth()->user()->name }}</p>
                        <p class="text-maroon-200 text-xs truncate">{{ auth()->user()->email }}</p>
                    </div>
                </div>
                <div class="flex gap-2 mt-2">
                    <a href="{{ route('profile.edit') }}" class="flex-1 text-center text-xs text-maroon-100 hover:text-white py-1.5 rounded-md hover:bg-maroon-600/60">Profile</a>
                    <form method="POST" action="{{ route('logout') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full text-xs text-maroon-100 hover:text-white py-1.5 rounded-md hover:bg-maroon-600/60">Log out</button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">

            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-4 lg:px-8 sticky top-0 z-20">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = true" class="lg:hidden text-gray-500 hover:text-maroon-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-lg font-semibold text-gray-900">{{ $header ?? ($title ?? 'Dashboard') }}</h1>
                </div>
                <div class="text-sm text-gray-400">{{ now()->format('l, F j, Y') }}</div>
            </header>

            <div class="px-4 lg:px-8 pt-4">
                @if(session('success'))
                <div class="mb-4 flex items-center gap-2 bg-status-success-bg text-status-success text-sm font-medium px-4 py-3 rounded-lg border border-green-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ session('success') }}
                </div>
                @endif
                @if(session('error'))
                <div class="mb-4 flex items-center gap-2 bg-status-danger-bg text-status-danger text-sm font-medium px-4 py-3 rounded-lg border border-red-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    {{ session('error') }}
                </div>
                @endif
                @if($errors->any())
                <div class="mb-4 bg-status-danger-bg text-status-danger text-sm px-4 py-3 rounded-lg border border-red-200">
                    <p class="font-medium mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            <main class="flex-1 px-4 lg:px-8 pb-10">
                {{ $slot }}
            </main>
        </div>
    </div>

</body>

</html>