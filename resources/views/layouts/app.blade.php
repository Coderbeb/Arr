<!DOCTYPE html>
<html lang="en" x-data="{ 
    darkMode: localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches),
    init() {
        this.$watch('darkMode', val => {
            localStorage.setItem('theme', val ? 'dark' : 'light');
            if (val) document.documentElement.classList.add('dark');
            else document.documentElement.classList.remove('dark');
        });
        if (this.darkMode) document.documentElement.classList.add('dark');
    }
}" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Arr Wallet')</title>
    
    <!-- Alpine.js -->
    <script defer crossorigin="anonymous" src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- WebSockets -->
    <script crossorigin="anonymous" src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.min.js"></script>
    <script>
        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: '{{ env("REVERB_APP_KEY") }}',
            wsHost: '{{ env("REVERB_HOST") }}',
            wsPort: {{ env("REVERB_PORT", 8080) }},
            wssPort: {{ env("REVERB_PORT", 8080) }},
            forceTLS: ({{ env("REVERB_SCHEME", "http") === "https" ? 'true' : 'false' }}),
            enabledTransports: ['ws', 'wss'],
        });
    </script>
    
    <!-- Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/globals.css') }}">
    
    @yield('styles')
</head>
<body class="bg-gray-50 dark:bg-deep-900 text-gray-900 dark:text-gray-100 min-h-screen overflow-x-hidden selection:bg-gold-400 selection:text-black pb-20 md:pb-0 transition-colors duration-300">
    
    @if(isset($global_announcement) && !empty($global_announcement))
        <div class="bg-amber-500 text-black text-center p-2 font-semibold text-xs relative z-50">
            📢 {{ $global_announcement }}
        </div>
    @endif
    
    <!-- Ambient Background -->
    <div class="bg-orb-1 opacity-50 hidden md:block"></div>
    <div class="bg-orb-2 opacity-50 hidden md:block"></div>

    <div class="flex min-h-screen relative">
        
        <!-- Desktop Sidebar (Hidden on mobile) -->
        <aside class="hidden md:flex flex-col fixed top-0 left-0 h-screen w-[260px] bg-white/90 dark:bg-deep-900/90 backdrop-blur-xl border-r border-gray-200 dark:border-white/10 p-5 z-50">
            <div class="flex items-center mb-10">
                <span class="text-2xl font-bold bg-gradient-to-br from-gold-400 to-gold-600 bg-clip-text text-transparent">
                    🪙 Arr Wallet
                </span>
            </div>

            <nav class="flex flex-col gap-2 flex-1">
                @if(Auth::check() && Auth::user()->role !== 'super_admin')
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('dashboard') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">📊</span> Dashboard
                    </a>
                    <a href="{{ route('trade') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('trade') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">⚡</span> Trade Room
                    </a>
                    <a href="{{ route('wallet') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('wallet') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">💼</span> Wallet
                    </a>
                @endif
                @if(Auth::check() && Auth::user()->role === 'assistance')
                    <a href="{{ route('assistance') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('assistance') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">🛡️</span> Support Queue
                    </a>
                @endif
                @if(Auth::check() && Auth::user()->role === 'super_admin')
                    <a href="{{ route('admin') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('admin') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">⚙️</span> Admin Panel
                    </a>
                @endif
            </nav>

            <div class="mt-auto flex flex-col gap-3">
                <button @click="darkMode = !darkMode" class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-medium bg-gray-100 dark:bg-white/5 hover:bg-gray-200 dark:hover:bg-white/10 transition-colors">
                    <span x-show="!darkMode">🌙 Dark Mode</span>
                    <span x-show="darkMode">☀️ Light Mode</span>
                </button>
                @auth
                    <form action="/api/auth/logout" method="POST" @submit.prevent="fetch('/api/auth/logout', {method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}}).then(() => window.location.href='/login')">
                        <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                            <span class="text-xl">🚪</span> Sign Out
                        </button>
                    </form>
                @endauth
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0 md:ml-[260px]">
            
            <!-- Mobile Topbar (Highly Compact) -->
            <header class="md:hidden sticky top-0 z-30 bg-white/95 dark:bg-deep-900/95 backdrop-blur-md border-b border-gray-200 dark:border-white/10 px-4 py-3 flex items-center justify-between">
                <div class="text-lg font-bold bg-gradient-to-br from-gold-400 to-gold-600 bg-clip-text text-transparent flex items-center gap-1">
                    <span>🪙</span> Arr Wallet
                </div>
                
                <div class="flex items-center gap-3">
                    <button @click="darkMode = !darkMode" class="p-1.5 text-gray-500 dark:text-gray-400">
                        <span x-show="!darkMode">🌙</span>
                        <span x-show="darkMode">☀️</span>
                    </button>
                    @auth
                        <form action="/api/auth/logout" method="POST" @submit.prevent="fetch('/api/auth/logout', {method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}}).then(() => window.location.href='/login')">
                            <button type="submit" class="p-1.5 text-red-500 dark:text-red-400">
                                🚪
                            </button>
                        </form>
                    @endauth
                </div>
            </header>

            <!-- Desktop Topbar -->
            <header class="hidden md:flex sticky top-0 z-30 bg-white/80 dark:bg-deep-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-white/10 px-8 py-4 items-center justify-end">
                @auth
                    <div class="flex items-center gap-3 bg-gray-100 dark:bg-white/5 px-4 py-2 rounded-full border border-gray-200 dark:border-white/10">
                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                        <span class="font-bold text-gray-900 dark:text-white">₹{{ number_format(Auth::user()->wallet_balance, 2) }}</span>
                    </div>
                @endauth
            </header>

            <main class="flex-1 w-full max-w-5xl mx-auto p-4 md:p-8">
                @yield('content')
            </main>
        </div>
        
        <!-- Mobile Bottom Navigation Bar -->
        @auth
            @if(Auth::user()->role !== 'super_admin')
            <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white dark:bg-deep-900 border-t border-gray-200 dark:border-white/10 pb-safe z-50 flex justify-around items-center px-1 py-2">
                <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 p-2 flex-1 {{ request()->routeIs('dashboard') ? 'text-gold-500 dark:text-gold-400' : 'text-gray-500 dark:text-gray-400' }}">
                    <span class="text-xl">📊</span>
                    <span class="text-[10px] font-bold tracking-wide">Home</span>
                </a>
                <a href="{{ route('trade') }}" class="flex flex-col items-center gap-1 p-2 flex-1 {{ request()->routeIs('trade') ? 'text-gold-500 dark:text-gold-400' : 'text-gray-500 dark:text-gray-400' }}">
                    <span class="text-xl">⚡</span>
                    <span class="text-[10px] font-bold tracking-wide">Trade</span>
                </a>
                <a href="{{ route('wallet') }}" class="flex flex-col items-center gap-1 p-2 flex-1 {{ request()->routeIs('wallet') ? 'text-gold-500 dark:text-gold-400' : 'text-gray-500 dark:text-gray-400' }}">
                    <span class="text-xl">💼</span>
                    <span class="text-[10px] font-bold tracking-wide">Wallet</span>
                </a>
                @if(Auth::user()->role === 'assistance')
                <a href="{{ route('assistance') }}" class="flex flex-col items-center gap-1 p-2 flex-1 {{ request()->routeIs('assistance') ? 'text-gold-500 dark:text-gold-400' : 'text-gray-500 dark:text-gray-400' }}">
                    <span class="text-xl">🛡️</span>
                    <span class="text-[10px] font-bold tracking-wide">Support</span>
                </a>
                @endif
            </nav>
            @endif
        @endauth
    </div>

    @yield('scripts')
</body>
</html>
