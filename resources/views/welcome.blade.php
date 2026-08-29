<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>UNB Wire — Real-Time News Agency Wire Service</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root{--navy-900:#0f1730;--navy-800:#16204a;--crimson:#e5484d;--crimson-dark:#d13438;--paper:#faf9f6;--border:#eceae5;--ink:#1c1f2e;--muted:#7c7f8c}
    </style>
</head>
<body class="bg-[#faf9f6] text-[#1c1f2e] antialiased" style="font-family:'Inter',system-ui,sans-serif">
    <div class="h-1 w-full" style="background:linear-gradient(90deg,#e5484d 0%,#f0a832 45%,#16204a 100%)"></div>
    <header class="max-w-[1240px] mx-auto px-6 lg:px-8 py-5 flex items-center justify-between">
        <a href="/" class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-[10px] bg-[#e5484d] text-white flex items-center justify-center font-bold text-sm shadow-md" style="font-family:'Fraunces',serif">U</span>
            <span>
                <span class="block font-semibold text-[17px] leading-none" style="font-family:'Fraunces',serif">UNB Wire</span>
                <span class="block text-[10px] tracking-[0.12em] uppercase text-[#7c7f8c] font-semibold">News Agency Wire Service</span>
            </span>
        </a>
        <nav class="flex items-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="px-5 py-2.5 rounded-xl bg-[#16204a] text-white text-sm font-semibold hover:bg-[#0f1730] transition">Dashboard →</a>
            @else
                <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-xl bg-white border border-[#eceae5] text-sm font-medium hover:border-[#16204a] transition">Log in</a>
                @if(Route::has('register'))
                <a href="{{ route('register') }}" class="px-5 py-2.5 rounded-xl bg-[#e5484d] text-white text-sm font-semibold hover:bg-[#d13438] transition shadow-[0_2px_8px_rgba(229,72,77,0.3)]">Register</a>
                @endif
            @endauth
        </nav>
    </header>

    <section class="max-w-[1240px] mx-auto px-6 lg:px-8 pt-10 pb-16 grid lg:grid-cols-[1.15fr_0.85fr] gap-10 items-center">
        <div>
            <span class="inline-flex items-center gap-2 text-xs font-semibold tracking-widest uppercase text-[#e5484d] bg-[#fdecec] border border-[#f7d9d9] px-3 py-1.5 rounded-full"><span class="w-2 h-2 rounded-full bg-[#e5484d] animate-pulse"></span> Live Wire Distribution</span>
            <h1 class="mt-5 text-[42px] lg:text-[52px] font-semibold leading-[0.95] tracking-tight" style="font-family:'Fraunces',serif">Real-time editorial<br>distribution,<br><span class="text-[#e5484d]">built for newsrooms.</span></h1>
            <p class="mt-5 text-[17px] leading-relaxed text-[#7c7f8c] max-w-[560px]">UNB Wire powers the United News of Bangladesh newsroom — draft, review, publish, and distribute stories, photos, and alerts to 47+ clients in seconds.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="px-7 py-3 rounded-xl bg-[#e5484d] text-white font-semibold hover:bg-[#d13438] transition">Go to Dashboard →</a>
                @else
                    <a href="{{ route('login') }}" class="px-7 py-3 rounded-xl bg-[#e5484d] text-white font-semibold hover:bg-[#d13438] transition">Staff Log in →</a>
                    <a href="{{ route('register') }}" class="px-7 py-3 rounded-xl bg-white border border-[#eceae5] font-medium hover:border-[#16204a] transition">Create account</a>
                @endauth
            </div>
            <div class="mt-10 flex flex-wrap gap-6 text-sm">
                <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#16a34a]"></span> <strong>98.4%</strong> delivery success</span>
                <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#3b6fe0]"></span> <strong>12</strong> stories today</span>
                <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-[#f0a832]"></span> Dhaka · Asia/Dhaka</span>
            </div>
        </div>
        <div class="bg-white border border-[#eceae5] rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-[#16204a] text-white px-6 py-4 flex items-center justify-between">
                <span class="text-sm font-semibold tracking-wide">Wire Preview</span>
                <span class="text-xs bg-[#e5484d] px-2.5 py-1 rounded-full font-bold">LIVE</span>
            </div>
            <div class="divide-y divide-[#eceae5]">
                <div class="p-5">
                    <div class="text-sm font-semibold" style="font-family:'Source Serif 4',serif">Nandini killing: Man sentenced to death in Lalmonirhat</div>
                    <div class="text-xs text-[#7c7f8c] mt-1">English · Bangladesh · Distributed</div>
                    <span class="inline-block mt-2 text-[11px] font-bold px-2 py-1 rounded bg-[#e9f0fd] text-[#3b6fe0]">Standard</span>
                </div>
                <div class="p-5">
                    <div class="text-sm font-semibold" style="font-family:'Source Serif 4',serif">Doctors warn of rise in seasonal flu symptoms</div>
                    <div class="text-xs text-[#7c7f8c] mt-1">English · Health · Distributed</div>
                    <span class="inline-block mt-2 text-[11px] font-bold px-2 py-1 rounded bg-[#fdeef5] text-[#db2777]">Exclusive</span>
                    <span class="inline-block mt-2 text-[11px] font-bold px-2 py-1 rounded bg-[#f2f9e6] text-[#65a30d]">Photo pack</span>
                </div>
                <div class="p-5">
                    <div class="text-sm font-semibold" style="font-family:'Source Serif 4',serif">Govt prioritises creative economy to raise GDP contribution</div>
                    <div class="text-xs text-[#7c7f8c] mt-1">English · Business · Published</div>
                </div>
            </div>
            <div class="px-6 py-3 bg-[#faf9f6] text-center">
                <a href="{{ route('login') }}" class="text-sm font-semibold text-[#e5484d] hover:underline">Log in to view full wire →</a>
            </div>
        </div>
    </section>

    <section class="max-w-[1240px] mx-auto px-6 lg:px-8 pb-12 grid md:grid-cols-3 gap-5">
        <div class="bg-white border border-[#eceae5] rounded-2xl p-6">
            <div class="w-10 h-10 rounded-xl bg-[#eef3fe] border border-[#dce7fb] flex items-center justify-center text-[#3b6fe0]">◈</div>
            <h3 class="mt-4 font-semibold" style="font-family:'Fraunces',serif">Editorial Pipeline</h3>
            <p class="mt-1 text-sm text-[#7c7f8c] leading-relaxed">Draft → Review → Approve → Publish with locks, version history, and AI-assisted pre-edit.</p>
        </div>
        <div class="bg-white border border-[#eceae5] rounded-2xl p-6">
            <div class="w-10 h-10 rounded-xl bg-[#e5f6ec] border border-[#d4efdf] flex items-center justify-center text-[#16a34a]">⬢</div>
            <h3 class="mt-4 font-semibold" style="font-family:'Fraunces',serif">Instant Distribution</h3>
            <p class="mt-1 text-sm text-[#7c7f8c] leading-relaxed">At-least-once fan-out to FTP, API, and portal with package-based entitlements.</p>
        </div>
        <div class="bg-white border border-[#eceae5] rounded-2xl p-6">
            <div class="w-10 h-10 rounded-xl bg-[#fdf3e0] border border-[#fae3cf] flex items-center justify-center text-[#b7791f]">⬣</div>
            <h3 class="mt-4 font-semibold" style="font-family:'Fraunces',serif">Photo & Media DAM</h3>
            <p class="mt-1 text-sm text-[#7c7f8c] leading-relaxed">UNB + AP photo managers, field intake queue, and CDN delivery.</p>
        </div>
    </section>

    <footer class="border-t border-[#eceae5] py-6 text-center text-xs text-[#b0b2bc]">© {{ date('Y') }} UNB Wire — United News of Bangladesh. All rights reserved.</footer>
</body>
</html>
