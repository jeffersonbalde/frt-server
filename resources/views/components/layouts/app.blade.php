@props(['title' => 'Dashboard'])

@php
    $groups = [
        'Overview' => [
            ['route' => 'dashboard', 'label' => 'Dashboard', 'active' => 'dashboard', 'icon' => 'home'],
        ],
        'Reports' => [
            ['route' => 'reports.daily', 'label' => 'Daily Report', 'active' => 'reports.daily*', 'icon' => 'chart'],
            ['route' => 'reports.monthly', 'label' => 'Monthly Report', 'active' => 'reports.monthly*', 'icon' => 'calendar'],
            ['route' => 'closings.index', 'label' => 'End of Day', 'active' => 'closings.*', 'icon' => 'moon'],
            ['route' => 'voids.index', 'label' => 'Voided Transactions', 'active' => 'voids.*', 'icon' => 'void'],
        ],
        'Records' => [
            ['route' => 'customers.index', 'label' => 'Customers', 'active' => 'customers.*', 'icon' => 'users'],
            ['route' => 'personnel.index', 'label' => 'Personnel', 'active' => 'personnel.*', 'icon' => 'team'],
        ],
        'Administration' => [
            ['route' => 'users.index', 'label' => 'Accounts', 'active' => 'users.*', 'icon' => 'key'],
            ['route' => 'devices.index', 'label' => 'Devices', 'active' => 'devices.*', 'icon' => 'phone'],
            ['route' => 'settings.edit', 'label' => 'Settings', 'active' => 'settings.*', 'icon' => 'settings'],
        ],
    ];
    $user = auth()->user();
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Fabriga Rice Trading</title>
    {{-- Page changes are fetched in the background (Turbo); don't flash an old cached copy first. --}}
    <meta name="turbo-cache-control" content="no-preview">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    {{-- Dark backdrop behind the menu on small screens. --}}
    <div data-sidebar-backdrop class="fixed inset-0 z-30 hidden bg-slate-900/30 lg:hidden"></div>

    <aside data-sidebar
           class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 ease-out lg:translate-x-0 print:hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 border-b border-slate-200 px-5 py-4">
            <img src="{{ asset('images/logo-tile.png') }}" alt="" class="size-10">
            <span class="leading-tight">
                <span class="block text-[15px] font-extrabold text-brand-800">Fabriga Rice Trading</span>
                <span class="block text-[9.5px] font-semibold tracking-[0.08em] whitespace-nowrap text-brand-600 uppercase">Sales Monitoring System</span>
            </span>
        </a>

        <nav class="flex-1 space-y-5 overflow-y-auto px-3 py-5" aria-label="Main">
            @foreach ($groups as $group => $items)
                <div>
                    <p class="mb-1.5 px-3 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">{{ $group }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($items as $item)
                            @php($isActive = request()->routeIs($item['active']))
                            <li>
                                <a href="{{ route($item['route']) }}" @class(['nav-link', 'nav-link-active' => $isActive]) @if ($isActive) aria-current="page" @endif>
                                    <x-icon :name="$item['icon']" class="size-5 shrink-0" />
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>

        <div class="border-t border-slate-200 p-3">
            <div class="flex items-center gap-3 rounded-md px-2 py-2">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-800">{{ $initials }}</span>
                <span class="min-w-0 flex-1 leading-tight">
                    <span class="block truncate text-sm font-semibold text-slate-900">{{ $user->name }}</span>
                    <span class="block text-xs text-slate-500">Owner</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md p-2 text-slate-500 transition-colors hover:bg-red-50 hover:text-red-600" title="Log out" aria-label="Log out">
                        <x-icon name="logout" class="size-5" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur print:static print:border-0">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                <button type="button" data-sidebar-open class="-ml-1 rounded-md p-2 text-slate-600 hover:bg-slate-100 lg:hidden print:hidden" aria-label="Open menu">
                    <x-icon name="menu" class="size-6" />
                </button>
                <div class="min-w-0 flex-1">
                    <h1 class="text-base leading-snug font-bold break-words text-slate-900 sm:text-xl">{{ $title }}</h1>
                    <p class="text-xs text-slate-500">{{ now()->format('l, F j, Y') }}</p>
                </div>
                @isset($actions)
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto print:hidden">{{ $actions }}</div>
                @endisset
            </div>
        </header>

        <main class="mx-auto max-w-7xl animate-content-in px-4 py-6 sm:px-6">
            @if (session('status'))
                <div class="mb-6 flex items-center gap-2 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 print:hidden" role="status">
                    <x-icon name="shield" class="size-5 shrink-0" />
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="mx-auto max-w-7xl px-4 pb-6 text-xs text-slate-400 sm:px-6 print:hidden">
            © {{ now()->year }} Fabriga Rice Trading · Mobile rice milling, Mahayag, Zamboanga del Sur
        </footer>
    </div>
</body>
</html>
