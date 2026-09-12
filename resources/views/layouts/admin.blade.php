<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'UNB Wire' }} — UNB Wire</title>

    <!-- Fonts: Fraunces (headings), Inter (UI), Source Serif 4 (article body), Noto Sans Bengali (conjunct-safe, NFR §10) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans bg-paper text-ink antialiased" x-data="{ sidebarOpen: false }" data-density="{{ auth()->user()->density ?? 'comfortable' }}">
    {{-- Brand strip across the very top --}}
    <div class="fixed top-0 inset-x-0 h-1 z-50 bg-[linear-gradient(90deg,#e5484d_0%,#f0a832_45%,#16204a_100%)]"></div>

    <div class="grid lg:grid-cols-[240px_1fr] min-h-screen pt-1">
        <div class="hidden lg:block">
            <x-sidebar />
        </div>

        <div x-show="sidebarOpen" x-transition.opacity.duration.200ms x-cloak class="fixed inset-0 z-[70] lg:hidden" @click="sidebarOpen = false">
            <div class="absolute inset-0 bg-navy-900/50 backdrop-blur-sm"></div>
            <div class="absolute left-0 top-0 bottom-0 w-[260px] overflow-y-auto bg-gradient-to-b from-navy-800 to-navy-900 shadow-2xl" @click.stop>
                <x-sidebar />
            </div>
        </div>

        <div class="min-w-0">
            <x-topnav />

            <main class="px-6 lg:px-[38px] pt-[34px] pb-20 max-w-[1240px]">
                {{ $slot }}
            </main>
        </div>
    </div>

    <x-toast />
    @livewireScripts
    @stack('scripts')
</body>
</html>
