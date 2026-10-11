@php
    $links = [
        ['route' => 'cashier.dashboard', 'active' => 'cashier.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
        ['route' => 'cashier.orders.index', 'active' => 'cashier.orders.*', 'label' => 'Orders', 'icon' => 'clipboard'],
        ['route' => 'cashier.inventory.index', 'active' => 'cashier.inventory.*', 'label' => 'Inventory', 'icon' => 'cube'],
        ['route' => 'purchase-orders.index', 'active' => 'purchase-orders.*', 'label' => 'Supplier Orders', 'icon' => 'truck'],
        ['route' => 'cashier.expenses.index', 'active' => 'cashier.expenses.*', 'label' => 'Expenses', 'icon' => 'wallet'],
    ];
    $icons = [
        'home' => 'M3 9.5L12 4l9 5.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1V9.5z',
        'clipboard' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 8h6m-6 4h6',
        'cube' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
        'truck' => 'M3 7h11v8H3V7zm11 3h4l3 3v2h-7v-5zM6 19a2 2 0 100-4 2 2 0 000 4zm11 0a2 2 0 100-4 2 2 0 000 4z',
        'wallet' => 'M17 9V7a4 4 0 00-8 0v2M5 9h14l-1 11H6L5 9z',
    ];
@endphp

@foreach($links as $link)
    <a href="{{ route($link['route']) }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition
              {{ request()->routeIs($link['active']) ? 'bg-maroon-600 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$link['icon']] }}" />
        </svg>
        <span>{{ $link['label'] }}</span>
    </a>
@endforeach
