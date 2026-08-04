<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Arr Wallet — P2P Trading Platform')</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="{{ asset('css/globals.css') }}">
    
    <!-- Alpine.js for lightweight client interactivity -->
    <script defer crossorigin="anonymous" src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Pusher & Laravel Echo for WebSockets -->
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
    
    @yield('styles')
</head>
<body x-data="{ sidebarOpen: false }">
    @if(isset($global_announcement) && !empty($global_announcement))
        <div style="background: var(--warning); color: #000; text-align: center; padding: 0.75rem; font-weight: 600; font-size: 0.95rem; position: relative; z-index: 50;">
            📢 {{ $global_announcement }}
        </div>
    @endif
    
    <!-- Ambient Background Orbs -->
    <div class="bg-orb-1"></div>
    <div class="bg-orb-2"></div>

    <div class="app-layout">
        <!-- Sidebar Navigation Overlay -->
        <div class="sidebar-overlay" :class="{ 'open': sidebarOpen }" @click="sidebarOpen = false"></div>

        <!-- Sidebar Navigation -->
        <aside class="sidebar-nav" :class="{ 'open': sidebarOpen }">
            <div class="sidebar-logo">
                <span class="topbar-title" style="font-size: 1.5rem;">🪙 Arr Wallet</span>
            </div>

            <nav class="sidebar-links">
                @if(Auth::check() && Auth::user()->role !== 'super_admin')
                    <a href="{{ route('dashboard') }}" class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <span>📊 Dashboard</span>
                    </a>
                    <a href="{{ route('trade') }}" class="sidebar-item {{ request()->routeIs('trade') ? 'active' : '' }}">
                        <span>⚡ Trade Room</span>
                    </a>
                    <a href="{{ route('wallet') }}" class="sidebar-item {{ request()->routeIs('wallet') ? 'active' : '' }}">
                        <span>💼 Wallet & Ledger</span>
                    </a>
                @endif
                @if(Auth::check() && Auth::user()->role === 'assistance')
                    <a href="{{ route('assistance') }}" class="sidebar-item {{ request()->routeIs('assistance') ? 'active' : '' }}">
                        <span>🛡️ Support Queue</span>
                    </a>
                @endif
                @if(Auth::check() && Auth::user()->role === 'super_admin')
                    <a href="{{ route('admin') }}" class="sidebar-item {{ request()->routeIs('admin') ? 'active' : '' }}">
                        <span>⚙️ Admin Panel</span>
                    </a>
                @endif
            </nav>

            <div class="sidebar-footer">
                @auth
                    <form action="/api/auth/logout" method="POST" @submit.prevent="fetch('/api/auth/logout', {method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}}).then(() => window.location.href='/login')">
                        <button type="submit" class="sidebar-item logout-btn btn-full">
                            <span>🚪 Sign Out</span>
                        </button>
                    </form>
                @endauth
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Topbar -->
            <header class="topbar">
                <button class="btn btn-ghost btn-sm mobile-only" @click="sidebarOpen = !sidebarOpen">
                    ☰ Menu
                </button>
                <div class="topbar-title">Arr Wallet</div>
                <div>
                    @auth
                        <span class="badge badge-gold">₹{{ number_format(Auth::user()->wallet_balance, 2) }}</span>
                    @else
                        <a href="/login" class="btn btn-ghost btn-sm">Login</a>
                    @endauth
                </div>
            </header>

            <main class="page-inner">
                @yield('content')
            </main>
        </div>
    </div>

    @yield('scripts')
</body>
</html>
