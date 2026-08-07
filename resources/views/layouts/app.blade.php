<!DOCTYPE html>
<html lang="en" x-data="{ 
    darkMode: localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches),
    globalBalance: {{ Auth::check() ? Auth::user()->wallet_balance : 0 }},
    init() {
        this.$watch('darkMode', val => {
            localStorage.setItem('theme', val ? 'dark' : 'light');
            if (val) document.documentElement.classList.add('dark');
            else document.documentElement.classList.remove('dark');
        });
        if (this.darkMode) document.documentElement.classList.add('dark');
        window.addEventListener('wallet-updated', e => {
            this.globalBalance = e.detail;
        });
    }
}" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Arr Wallet')</title>
    
    <!-- Alpine.js -->
    <script defer crossorigin="anonymous" src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- instant.page — Prefetch links on hover for near-instant page loads -->
    <script src="https://cdn.jsdelivr.net/npm/instant.page@5.2.0/instantpage.min.js" type="module"></script>

    <!-- API Response Cache for faster data loading -->
    <script>
        window.ArrCache = {
            _prefix: 'arr_cache_',

            /**
             * Fetch with sessionStorage caching.
             * Returns cached data instantly if available and fresh, then optionally refreshes.
             * @param {string} url - API endpoint
             * @param {number} ttlMs - Cache lifetime in milliseconds (default 3000ms)
             * @param {object} opts - fetch options (method, headers, body etc.)
             * @returns {Promise<any>} parsed JSON response
             */
            async fetch(url, ttlMs = 3000, opts = {}) {
                const key = this._prefix + url;
                const method = (opts.method || 'GET').toUpperCase();

                // Only cache GET requests
                if (method === 'GET') {
                    try {
                        const cached = sessionStorage.getItem(key);
                        if (cached) {
                            const { data, ts } = JSON.parse(cached);
                            if (Date.now() - ts < ttlMs) {
                                return data;
                            }
                        }
                    } catch (e) {}
                }

                // Fresh fetch — use window.fetch explicitly to avoid recursion
                const res = await window.fetch(url, opts);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();

                // Cache the response for GET requests
                if (method === 'GET') {
                    try {
                        sessionStorage.setItem(key, JSON.stringify({ data, ts: Date.now() }));
                    } catch (e) {
                        // Storage full — clear old entries
                        this.clearAll();
                    }
                }

                return data;
            },

            /** Invalidate a specific cached URL */
            invalidate(url) {
                try { sessionStorage.removeItem(this._prefix + url); } catch (e) {}
            },

            /** Clear all ArrCache entries */
            clearAll() {
                try {
                    Object.keys(sessionStorage).forEach(k => {
                        if (k.startsWith(this._prefix)) sessionStorage.removeItem(k);
                    });
                } catch (e) {}
            }
        };
    </script>

    <!-- Smart Polling Utility -->
    <script>
        window.ArrPolling = {
            _timers: {},
            _visible: true,

            init() {
                // Only bind once
                if (this._initialized) return;
                this._initialized = true;
                
                document.addEventListener('visibilitychange', () => {
                    this._visible = !document.hidden;
                    Object.keys(this._timers).forEach(key => {
                        const t = this._timers[key];
                        if (this._visible && !t.active) {
                            t.fn(); // Immediate refresh on tab focus
                            t.id = setInterval(t.fn, t.interval);
                            t.active = true;
                        } else if (!this._visible && t.active) {
                            clearInterval(t.id);
                            t.active = false;
                        }
                    });
                });
            },

            /**
             * Register a polling function.
             * @param {string} name - Unique identifier for this poller
             * @param {Function} fn - Async function to call periodically
             * @param {number} intervalMs - Polling interval in milliseconds
             * @param {boolean} immediate - Whether to call fn immediately
             */
            start(name, fn, intervalMs, immediate = true) {
                if (this._timers[name]) this.stop(name);
                if (immediate) fn();
                const id = setInterval(fn, intervalMs);
                this._timers[name] = { id, fn, interval: intervalMs, active: true };
            },

            stop(name) {
                const t = this._timers[name];
                if (t) {
                    clearInterval(t.id);
                    delete this._timers[name];
                }
            },

            stopAll() {
                Object.keys(this._timers).forEach(k => this.stop(k));
            }
        };
        ArrPolling.init();
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
                @if(Auth::check() && Auth::user()->role !== 'super_admin' && Auth::user()->role !== 'super_account')
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('dashboard') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">📊</span> Dashboard
                    </a>
                    <a href="{{ route('buy') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('buy') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">⬇️</span> Buy
                    </a>
                    <a href="{{ route('sell') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('sell') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">⬆️</span> Sell
                    </a>
                    <a href="{{ route('wallet') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('wallet') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">💼</span> Wallet
                    </a>
                    <a href="{{ route('referrals.index') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('referrals.index') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">🎁</span> Referrals
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
                @if(Auth::check() && Auth::user()->role === 'super_account')
                    <a href="{{ route('super_dashboard') }}" class="flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all {{ request()->routeIs('super_dashboard') ? 'bg-gold-400/10 text-gold-500 dark:text-gold-400 border-l-4 border-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white' }}">
                        <span class="text-xl">🌟</span> Super Dashboard
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
                    @auth
                        <a href="{{ route('wallet') }}" class="flex items-center gap-2 bg-gradient-to-r from-gold-400/20 to-gold-600/20 border border-gold-500/30 px-3 py-1.5 rounded-full shadow-sm hover:scale-105 transition-transform">
                            <span class="text-sm">🪙</span>
                            <span class="text-xs font-bold text-gold-700 dark:text-gold-400 tracking-wide" x-text="'₹' + Number(globalBalance).toFixed(2)">₹{{ number_format(Auth::user()->wallet_balance, 2) }}</span>
                        </a>
                    @endauth
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
                    <a href="{{ route('wallet') }}" class="flex items-center gap-3 bg-gradient-to-r from-gold-400/10 to-gold-600/10 px-5 py-2 rounded-full border border-gold-500/30 shadow-[0_0_15px_rgba(234,179,8,0.1)] transition-all hover:scale-105 hover:shadow-[0_0_20px_rgba(234,179,8,0.2)] group cursor-pointer">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-gold-400 to-gold-600 text-white shadow-md group-hover:rotate-12 transition-transform">
                            <span class="text-sm">₹</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[9px] font-bold text-gold-600/80 dark:text-gold-400/80 uppercase tracking-widest">Available Balance</span>
                            <span class="font-extrabold text-gray-900 dark:text-white leading-none text-base" x-text="'₹' + Number(globalBalance).toFixed(2)">₹{{ number_format(Auth::user()->wallet_balance, 2) }}</span>
                        </div>
                    </a>
                @endauth
            </header>

            <main class="flex-1 w-full max-w-5xl mx-auto p-4 md:p-8">
                @yield('content')
                
                <!-- Mobile Navigation Spacer -->
                <div class="h-48 md:hidden w-full shrink-0"></div>
            </main>
        </div>
        
        <!-- Mobile Bottom Navigation Bar -->
        @auth
            @if(Auth::user()->role !== 'super_admin' && Auth::user()->role !== 'super_account')
            <nav class="md:hidden fixed bottom-0 left-0 w-full bg-white dark:bg-deep-900 border-t border-gray-200 dark:border-white/10 pb-safe z-50 flex justify-around items-center px-1 py-2">
                <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 p-2 flex-1 {{ request()->routeIs('dashboard') ? 'text-gold-500 dark:text-gold-400' : 'text-gray-500 dark:text-gray-400' }}">
                    <span class="text-xl">📊</span>
                    <span class="text-[10px] font-bold tracking-wide">Home</span>
                </a>
                <a href="{{ route('buy') }}" class="flex flex-col items-center gap-1 p-2 flex-1 {{ request()->routeIs('buy') ? 'text-gold-500 dark:text-gold-400' : 'text-gray-500 dark:text-gray-400' }}">
                    <span class="text-xl">⬇️</span>
                    <span class="text-[10px] font-bold tracking-wide">Buy</span>
                </a>
                <a href="{{ route('sell') }}" class="flex flex-col items-center gap-1 p-2 flex-1 {{ request()->routeIs('sell') ? 'text-gold-500 dark:text-gold-400' : 'text-gray-500 dark:text-gray-400' }}">
                    <span class="text-xl">⬆️</span>
                    <span class="text-[10px] font-bold tracking-wide">Sell</span>
                </a>
                <a href="{{ route('wallet') }}" class="flex flex-col items-center gap-1 p-2 flex-1 {{ request()->routeIs('wallet') ? 'text-gold-500 dark:text-gold-400' : 'text-gray-500 dark:text-gray-400' }}">
                    <span class="text-xl">💼</span>
                    <span class="text-[10px] font-bold tracking-wide">Wallet</span>
                </a>
                <a href="{{ route('referrals.index') }}" class="flex flex-col items-center gap-1 p-2 rounded-xl transition-all {{ request()->routeIs('referrals.index') ? 'text-gold-500 dark:text-gold-400 font-bold scale-110 shadow-[0_0_15px_rgba(250,204,21,0.5)]' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                <span class="text-xl">🎁</span>
                <span class="text-[10px]">Invite</span>
                </a>
                @if(Auth::user()->role === 'super_account')
                <a href="{{ route('super_dashboard') }}" class="flex flex-col items-center gap-1 p-2 rounded-xl transition-all {{ request()->routeIs('super_dashboard') ? 'text-gold-500 dark:text-gold-400 font-bold scale-110 shadow-[0_0_15px_rgba(250,204,21,0.5)]' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                    <span class="text-xl">🌟</span>
                    <span class="text-[10px]">Super</span>
                </a>
                @endif
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

    @stack('scripts')

    @auth
    <script>
        // Global navbar balance polling — keeps wallet display fresh on every page
        ArrPolling.start('global-balance', async () => {
            try {
                const data = await ArrCache.fetch('/api/wallet/balance', 4000);
                window.dispatchEvent(new CustomEvent('wallet-updated', { detail: data.wallet_balance }));
            } catch (e) {}
        }, 5000, false);
    </script>
    @endauth
</body>
</html>
