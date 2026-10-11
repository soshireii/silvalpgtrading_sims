@php
$links = [
['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
['route' => 'admin.orders.index', 'active' => 'admin.orders.*', 'label' => 'Orders', 'icon' => 'clipboard'],
['route' => 'admin.inventory.index', 'active' => 'admin.inventory.*', 'label' => 'Inventory', 'icon' => 'cube'],
['route' => 'purchase-orders.index', 'active' => 'purchase-orders.*', 'label' => 'Supplier Orders', 'icon' => 'truck'],
['route' => 'admin.suppliers.index', 'active' => 'admin.suppliers.*', 'label' => 'Suppliers', 'icon' => 'users'],
['route' => 'admin.expenses.index', 'active' => 'admin.expenses.*', 'label' => 'Expenses', 'icon' => 'wallet'],
['route' => 'admin.reports.index', 'active' => 'admin.reports.*', 'label' => 'Reports', 'icon' => 'chart'],
['route' => 'admin.delivery-staff.index', 'active' => 'admin.delivery-staff.*', 'label' => 'Delivery Staff', 'icon' => 'users'],
];
$icons = [
'home' => 'M3 9.5L12 4l9 5.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1V9.5z',
'clipboard' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 8h6m-6 4h6',
'cube' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
'wallet' => 'M17 9V7a4 4 0 00-8 0v2M5 9h14l-1 11H6L5 9z',
'chart' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
'truck' => 'M3 7h11v8H3V7zm11 3h4l3 3v2h-7v-5zM6 19a2 2 0 100-4 2 2 0 000 4zm11 0a2 2 0 100-4 2 2 0 000 4z',
'users' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm7 0a4 4 0 10-3.87-5',
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