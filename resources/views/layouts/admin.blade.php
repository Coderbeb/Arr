<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel — Arr Wallet')</title>
    
    <!-- Fonts loaded via globals.css — preconnect only -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Tailwind CSS (Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/globals.css') }}">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Turbo Drive for instant SPA-like page transitions -->
    <script type="module">
        import * as Turbo from 'https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.12/dist/turbo.es2017-esm.min.js';
        Turbo.start();
    </script>

    <!-- Alpine + Turbo bridge -->
    <script>
        window.ArrRegister = function(name, factory) {
            if (window.Alpine) Alpine.data(name, factory);
            document.addEventListener('alpine:init', () => Alpine.data(name, factory));
        };
    </script>

    <!-- API Response Cache -->
    <script>
        window.ArrCache = {
            _prefix: 'arr_cache_',
            async fetch(url, ttlMs = 3000, opts = {}) {
                const key = this._prefix + url;
                const method = (opts.method || 'GET').toUpperCase();
                if (method === 'GET') {
                    try {
                        const cached = sessionStorage.getItem(key);
                        if (cached) {
                            const { data, ts } = JSON.parse(cached);
                            if (Date.now() - ts < ttlMs) return data;
                        }
                    } catch (e) {}
                }
                const res = await window.fetch(url, opts);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const data = await res.json();
                if (method === 'GET') {
                    try { sessionStorage.setItem(key, JSON.stringify({ data, ts: Date.now() })); }
                    catch (e) { this.clearAll(); }
                }
                return data;
            },
            invalidate(url) { try { sessionStorage.removeItem(this._prefix + url); } catch (e) {} },
            clearAll() { try { Object.keys(sessionStorage).forEach(k => { if (k.startsWith(this._prefix)) sessionStorage.removeItem(k); }); } catch (e) {} }
        };
    </script>
    
    <!-- Smart Polling Utility -->
    <script>
        window.ArrPolling = {
            _timers: {},
            _visible: true,
            init() {
                if (this._initialized) return;
                this._initialized = true;
                document.addEventListener('visibilitychange', () => {
                    this._visible = !document.hidden;
                    Object.keys(this._timers).forEach(key => {
                        const t = this._timers[key];
                        if (this._visible && !t.active) {
                            t.fn();
                            t.id = setInterval(t.fn, t.interval);
                            t.active = true;
                        } else if (!this._visible && t.active) {
                            clearInterval(t.id);
                            t.active = false;
                        }
                    });
                });
            },
            start(name, fn, intervalMs, immediate = true) {
                if (this._timers[name]) this.stop(name);
                if (immediate) fn();
                const id = setInterval(fn, intervalMs);
                this._timers[name] = { id, fn, interval: intervalMs, active: true };
            },
            stop(name) {
                const t = this._timers[name];
                if (t) { clearInterval(t.id); delete this._timers[name]; }
            },
            stopAll() { Object.keys(this._timers).forEach(k => this.stop(k)); }
        };
        ArrPolling.init();

        // Turbo lifecycle — stop pollers before navigation
        document.addEventListener('turbo:before-visit', () => {
            if (window.ArrPolling) window.ArrPolling.stopAll();
        });
        document.addEventListener('turbo:before-cache', () => {
            if (window.ArrPolling) window.ArrPolling.stopAll();
        });
    </script>
    
    <script>
        // Init theme
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-deep-900 text-gray-900 dark:text-gray-100 font-sans antialiased selection:bg-gold-500/30 h-[100dvh] overflow-hidden flex flex-col" x-data="adminApp()">
    
    @if(isset($global_announcement) && !empty($global_announcement))
        <div class="bg-amber-500 text-black text-center py-2 px-4 font-semibold text-sm relative z-50 animate-fade-in">
            📢 {{ $global_announcement }}
        </div>
    @endif
    
    <div class="flex-1 flex overflow-hidden">
        
        <!-- Sidebar -->
        <aside class="hidden lg:flex flex-col static inset-y-0 left-0 w-72 bg-white dark:bg-deep-800 border-r border-gray-200 dark:border-white/5 z-50">
            
            <div class="p-6 border-b border-gray-100 dark:border-white/5 flex items-center justify-between">
                <div>
                    <div class="text-2xl font-bold font-outfit text-gold-500 flex items-center gap-2">
                        <span>🪙</span> Arr Admin
                    </div>
                    <div class="text-xs text-gray-500 font-medium uppercase tracking-wider mt-1">Super User Console</div>
                </div>
            </div>
            
            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all"
                        :class="activeTab === 'analytics' ? 'bg-gold-400/10 text-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white'"
                        @click="activeTab = 'analytics'; sidebarOpen = false;">
                    <span class="text-xl">📈</span> Platform Analytics
                </button>
                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all"
                        :class="activeTab === 'users' ? 'bg-gold-400/10 text-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white'"
                        @click="activeTab = 'users'; sidebarOpen = false;">
                    <span class="text-xl">👥</span> User Management
                </button>
                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all"
                        :class="activeTab === 'settings' ? 'bg-gold-400/10 text-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white'"
                        @click="activeTab = 'settings'; sidebarOpen = false;">
                    <span class="text-xl">⚙️</span> Global Configuration
                </button>
                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all"
                        :class="activeTab === 'assistance' ? 'bg-gold-400/10 text-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white'"
                        @click="activeTab = 'assistance'; sidebarOpen = false;">
                    <span class="text-xl">🛡️</span> Support Queue
                </button>
                <button class="w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium transition-all"
                        :class="activeTab === 'logs' ? 'bg-gold-400/10 text-gold-500' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-white/5 hover:text-gray-900 dark:hover:text-white'"
                        @click="activeTab = 'logs'; sidebarOpen = false;">
                    <span class="text-xl">📜</span> System Audit Logs
                </button>
            </nav>
            
            <div class="p-4 border-t border-gray-100 dark:border-white/5 flex gap-2">
                <button @click="showProfileModal = true" class="flex-1 flex items-center justify-center p-3 rounded-xl bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors" title="My Profile">
                    <span>👤</span>
                </button>
                <button @click="toggleTheme()" class="flex-1 flex items-center justify-center p-3 rounded-xl bg-gray-50 dark:bg-white/5 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors" title="Toggle Theme">
                    <span x-show="!isDark">🌙</span>
                    <span x-show="isDark">☀️</span>
                </button>
                <form action="/api/auth/logout" method="POST" class="flex-[3]" @submit.prevent="fetch('/api/auth/logout', {method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}}).then(() => window.location.href='/login')">
                    <button type="submit" class="w-full flex items-center justify-center gap-2 p-3 rounded-xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 font-medium transition-colors">
                        🚪 Sign Out
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-screen overflow-hidden">
            <!-- Mobile Header (Compact) -->
            <header class="lg:hidden flex items-center justify-between p-3 bg-white/95 dark:bg-deep-900/95 backdrop-blur-md border-b border-gray-200 dark:border-white/10 z-30">
                <div class="font-outfit font-bold text-lg text-gold-500 flex items-center gap-1">
                    🪙 Arr Admin
                    <div x-show="isSyncing" class="ml-2 w-2 h-2 rounded-full bg-green-500 animate-ping" title="Syncing data..."></div>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="showProfileModal = true" class="p-1.5 text-gray-500 dark:text-gray-400">
                        <span>👤</span>
                    </button>
                    <button @click="toggleTheme()" class="p-1.5 text-gray-500 dark:text-gray-400">
                        <span x-show="!isDark">🌙</span>
                        <span x-show="isDark">☀️</span>
                    </button>
                    <form action="/api/auth/logout" method="POST" @submit.prevent="fetch('/api/auth/logout', {method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}}).then(() => window.location.href='/login')">
                        <button type="submit" class="p-1.5 text-red-500 dark:text-red-400">🚪</button>
                    </form>
                </div>
            </header>
            
            <main class="flex-1 overflow-y-auto p-4 pb-40 lg:p-8 lg:pb-8 relative">
                <!-- Fluid Morph Animation Background -->
                <div class="fluid-morph absolute -top-40 -left-40 w-96 h-96 bg-gold-400/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="fluid-morph absolute top-40 right-0 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl pointer-events-none" style="animation-delay: -2s;"></div>
                
                <div class="relative z-10 max-w-7xl mx-auto">
                    @yield('content')
                    
                    <!-- Mobile Navigation Spacer -->
                    <div class="h-48 lg:hidden w-full shrink-0"></div>
                </div>
            </main>
        </div>
    </div>

    <!-- Premium Mobile Bottom Navigation Bar (Floating Pill) -->
    <nav class="lg:hidden fixed bottom-6 left-4 right-4 bg-white/80 dark:bg-deep-900/80 backdrop-blur-xl border border-gray-200/50 dark:border-white/10 rounded-2xl shadow-2xl z-50 flex justify-around items-center p-2">
        <button class="relative flex flex-col items-center gap-1 p-2 flex-1 transition-all duration-300 rounded-xl" 
            :class="activeTab === 'analytics' ? 'bg-gold-500/10 text-gold-500 scale-105' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5'" 
            @click="activeTab = 'analytics'">
            <span class="text-xl" :class="activeTab === 'analytics' ? 'animate-bounce' : ''">📈</span>
            <span class="text-[10px] font-black tracking-wide">Data</span>
        </button>
        <button class="relative flex flex-col items-center gap-1 p-2 flex-1 transition-all duration-300 rounded-xl" 
            :class="activeTab === 'users' ? 'bg-indigo-500/10 text-indigo-500 scale-105' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5'" 
            @click="activeTab = 'users'">
            <span class="text-xl" :class="activeTab === 'users' ? 'animate-bounce' : ''">👥</span>
            <span class="text-[10px] font-black tracking-wide">Users</span>
        </button>
        <button class="relative flex flex-col items-center gap-1 p-2 flex-1 transition-all duration-300 rounded-xl" 
            :class="activeTab === 'settings' ? 'bg-emerald-500/10 text-emerald-500 scale-105' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5'" 
            @click="activeTab = 'settings'">
            <span class="text-xl" :class="activeTab === 'settings' ? 'animate-bounce' : ''">⚙️</span>
            <span class="text-[10px] font-black tracking-wide">Config</span>
        </button>
        <button class="relative flex flex-col items-center gap-1 p-2 flex-1 transition-all duration-300 rounded-xl" 
            :class="activeTab === 'assistance' ? 'bg-amber-500/10 text-amber-500 scale-105' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5'" 
            @click="activeTab = 'assistance'">
            <span class="text-xl" :class="activeTab === 'assistance' ? 'animate-bounce' : ''">🛡️</span>
            <span class="text-[10px] font-black tracking-wide">Support</span>
            <!-- Red dot for active disputes -->
            <span x-show="disputes.length > 0" class="absolute top-1 right-2 w-2 h-2 rounded-full bg-red-500 border border-white dark:border-deep-900"></span>
        </button>
        <button class="relative flex flex-col items-center gap-1 p-2 flex-1 transition-all duration-300 rounded-xl" 
            :class="activeTab === 'logs' ? 'bg-purple-500/10 text-purple-500 scale-105' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5'" 
            @click="activeTab = 'logs'">
            <span class="text-xl" :class="activeTab === 'logs' ? 'animate-bounce' : ''">📜</span>
            <span class="text-[10px] font-black tracking-wide">Logs</span>
        </button>
    </nav>

    <script>
        function adminApp() {
            return {
                isDark: document.documentElement.classList.contains('dark'),
                sidebarOpen: false,
                activeTab: 'analytics',
                settings: {
                    registration_open: 1,
                    buy_commission_percent: 0.00,
                    sell_commission_percent: 0.00,
                    max_daily_earning: 0,
                    max_weekly_earning: 0,
                    trade_accept_minutes: 15,
                    payment_timer_minutes: 30,
                    dispute_proof_minutes: 120,
                    trade_suspended: 0,
                    trade_suspended_message: '',
                    allowed_trade_amounts: '',
                    global_announcement: ''
                },
                users: [],
                usersPagination: { current_page: 1, last_page: 1, total: 0 },
                userSearch: '',
                userRoleFilter: '',
                auditLogs: [],
                analytics: null,
                disputes: [],
                loading: true,
                message: '',
                errorMsg: '',

                showStaffModal: false,
                staffForm: { full_name: '', mobile_number: '', password: '' },

                showWalletModal: false,
                walletForm: { user_id: '', full_name: '', action: 'add', amount: '', note: '' },

                showProfileModal: false,
                profileForm: { mobile_number: '{{ auth()->user()->mobile_number ?? "" }}', password: '' },

                showUserDetailsModal: false,
                selectedUser: null,
                
                showSuperAccountModal: false,
                superAccountForm: { full_name: '', mobile_number: '', password: '' },

                showResetPasswordModal: false,
                resetPasswordForm: { user_id: '', full_name: '', new_password: '' },

                toggleTheme() {
                    this.isDark = !this.isDark;
                    if (this.isDark) {
                        document.documentElement.classList.add('dark');
                        localStorage.theme = 'dark';
                    } else {
                        document.documentElement.classList.remove('dark');
                        localStorage.theme = 'light';
                    }
                },

                syncTimer: null,
                isSyncing: false,

                _csrfToken: '{{ csrf_token() }}',
                _headers() {
                    return {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this._csrfToken,
                        'Accept': 'application/json',
                    };
                },

                async init() {
                    // Clear Turbo snapshot cache so F5 never shows stale HTML
                    if (window.Turbo && window.Turbo.cache) {
                        window.Turbo.cache.clear();
                    }
                    // Clear any stale browser sessionStorage cache
                    if (window.ArrCache) {
                        window.ArrCache.clearAll();
                    }

                    await this.loadAdminData();
                    
                    // Watch for tab changes — immediately fetch fresh data for the active tab
                    this.$watch('activeTab', async (value) => {
                        if (value === 'analytics') {
                            await this.loadAnalytics();
                        } else if (value === 'users') {
                            await this.loadUsers();
                        } else if (value === 'assistance') {
                            await this.loadQueue();
                        } else if (value === 'logs') {
                            await this.loadAuditLogs();
                        } else if (value === 'settings') {
                            await this.loadSettings();
                        }
                    });

                    // Background poller — keeps data fresh every 10s (pauses when tab hidden)
                    const self = this;
                    ArrPolling.start('admin-sync', async () => {
                        self.isSyncing = true;
                        try {
                            if (self.activeTab === 'analytics') {
                                await self.loadAnalytics(true);
                            } else if (self.activeTab === 'assistance') {
                                await self.loadQueue(true);
                            }
                        } catch (e) {}
                        setTimeout(() => self.isSyncing = false, 500);
                    }, 10000, false);
                },

                // --- Direct fetch helpers (no browser cache) ---
                async _fetchJson(url) {
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.json();
                },

                async loadAdminData() {
                    this.loading = true;
                    this.errorMsg = '';
                    this.message = '';
                    try {
                        const [settingsData, userData, logData, analyticsData] = await Promise.all([
                            this._fetchJson('/api/admin/settings'),
                            this._fetchJson('/api/admin/users'),
                            this._fetchJson('/api/admin/audit-logs'),
                            this._fetchJson('/api/admin/analytics'),
                        ]);
                        
                        if (settingsData && settingsData.id) {
                            settingsData.registration_open = settingsData.registration_open ? 1 : 0;
                            settingsData.trade_suspended = settingsData.trade_suspended ? 1 : 0;
                            settingsData.global_announcement = settingsData.global_announcement || '';
                            this.settings = settingsData;
                        }
                        
                        this.users = userData.data || userData || [];
                        this.auditLogs = logData;
                        this.analytics = analyticsData;
                    } catch (e) {
                        this.errorMsg = 'Failed to load admin data.';
                    } finally {
                        this.loading = false;
                    }
                },

                async loadAnalytics(silent = false) {
                    if (!silent) this.loading = true;
                    try {
                        this.analytics = await this._fetchJson('/api/admin/analytics');
                    } catch (e) {
                        if (!silent) this.errorMsg = 'Failed to load analytics.';
                    } finally {
                        if (!silent) this.loading = false;
                    }
                },

                async loadUsers(page = 1, silent = false) {
                    if (!silent) this.loading = true;
                    try {
                        let url = `/api/admin/users?page=${page}`;
                        if (this.userSearch) url += `&search=${encodeURIComponent(this.userSearch)}`;
                        if (this.userRoleFilter) url += `&role=${encodeURIComponent(this.userRoleFilter)}`;
                        
                        const data = await this._fetchJson(url);
                        this.users = data.data || [];
                        this.usersPagination = {
                            current_page: data.current_page || 1,
                            last_page: data.last_page || 1,
                            total: data.total || 0
                        };
                    } catch (e) {
                        if (!silent) this.errorMsg = 'Failed to load users.';
                    } finally {
                        if (!silent) this.loading = false;
                    }
                },

                async loadAuditLogs(silent = false) {
                    if (!silent) this.loading = true;
                    try {
                        this.auditLogs = await this._fetchJson('/api/admin/audit-logs');
                    } catch (e) {
                        if (!silent) this.errorMsg = 'Failed to load audit logs.';
                    } finally {
                        if (!silent) this.loading = false;
                    }
                },

                async loadSettings(silent = false) {
                    if (!silent) this.loading = true;
                    try {
                        const data = await this._fetchJson('/api/admin/settings');
                        if (data && data.id) {
                            data.registration_open = data.registration_open ? 1 : 0;
                            data.trade_suspended = data.trade_suspended ? 1 : 0;
                            data.global_announcement = data.global_announcement || '';
                            this.settings = data;
                        }
                    } catch (e) {
                        if (!silent) this.errorMsg = 'Failed to load settings.';
                    } finally {
                        if (!silent) this.loading = false;
                    }
                },

                async loadQueue(silent = false) {
                    if (!silent) this.loading = true;
                    try {
                        this.disputes = await this._fetchJson('/api/assistance/queue');
                    } catch (e) {
                        if (!silent) this.errorMsg = 'Failed to load support queue.';
                    } finally {
                        if (!silent) this.loading = false;
                    }
                },

                async saveSettings() {
                    this.message = '';
                    this.errorMsg = '';
                    this.loading = true;
                    try {
                        const payload = { ...this.settings };
                        payload.registration_open = payload.registration_open == 1;
                        payload.trade_suspended = payload.trade_suspended == 1;

                        const res = await fetch('/api/admin/settings', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(payload)
                        });
                        const data = await res.json();
                        
                        if(!res.ok) {
                            let msg = data.message || data.error || 'Failed to update settings';
                            if (data.errors) msg = Object.values(data.errors).flat().join(', ');
                            this.errorMsg = msg;
                        } else {
                            this.message = 'Platform settings updated successfully!';
                            if (data.settings) {
                                this.settings = { ...data.settings, registration_open: data.settings.registration_open ? 1 : 0, trade_suspended: data.settings.trade_suspended ? 1 : 0, global_announcement: data.settings.global_announcement || '' };
                            }
                        }
                    } catch (e) {
                        this.errorMsg = 'Network error while saving settings.';
                    } finally {
                        this.loading = false;
                        setTimeout(() => this.message = this.errorMsg = '', 4000);
                    }
                },

                async updateUserStatus(userId, newStatus) {
                    this.message = '';
                    this.errorMsg = '';
                    try {
                        const res = await fetch(`/api/admin/users/${userId}/status`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ status: newStatus })
                        });
                        const data = await res.json();
                        if(!res.ok) {
                            let msg = data.error || data.message || 'Failed to update user status';
                            if (data.errors) msg = Object.values(data.errors).flat().join(', ');
                            this.errorMsg = msg;
                            await this.loadAdminData();
                        } else {
                            this.message = 'User status updated successfully!';
                            await this.loadAuditLogs(true);
                        }
                    } catch (e) {
                        this.errorMsg = 'Network error while updating user.';
                    } finally {
                        setTimeout(() => this.message = this.errorMsg = '', 4000);
                    }
                },

                async createStaff() {
                    this.message = '';
                    this.errorMsg = '';
                    this.loading = true;
                    try {
                        const res = await fetch('/api/admin/staff/create', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(this.staffForm)
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            let msg = data.message || 'Failed to create staff';
                            if (data.errors) msg = Object.values(data.errors).flat().join(', ');
                            this.errorMsg = msg;
                        } else {
                            this.message = 'Staff created successfully!';
                            this.showStaffModal = false;
                            this.staffForm = { full_name: '', mobile_number: '', password: '' };
                            await this.loadAdminData();
                        }
                    } catch (e) {
                        this.errorMsg = 'Network error.';
                    } finally {
                        this.loading = false;
                        setTimeout(() => this.message = this.errorMsg = '', 4000);
                    }
                },

                async createSuperAccount() {
                    this.message = '';
                    this.errorMsg = '';
                    this.loading = true;
                    try {
                        const res = await fetch('/api/admin/super-account', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(this.superAccountForm)
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            let msg = data.error || data.message || 'Failed to create Super Account';
                            if (data.errors) msg = Object.values(data.errors).flat().join(', ');
                            this.errorMsg = msg;
                        } else {
                            this.message = 'Super Account created successfully!';
                            this.showSuperAccountModal = false;
                            this.superAccountForm = { full_name: '', mobile_number: '', password: '' };
                            await this.loadAdminData();
                        }
                    } catch (e) {
                        this.errorMsg = 'Network error.';
                    } finally {
                        this.loading = false;
                        setTimeout(() => this.message = this.errorMsg = '', 4000);
                    }
                },

                openWalletModal(u) {
                    this.walletForm = { user_id: u.id, full_name: u.full_name, action: 'add', amount: '', note: '' };
                    this.showWalletModal = true;
                },

                async adjustWallet() {
                    this.message = '';
                    this.errorMsg = '';
                    this.loading = true;
                    try {
                        const res = await fetch(`/api/admin/users/${this.walletForm.user_id}/wallet-adjust`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ action: this.walletForm.action, amount: this.walletForm.amount, note: this.walletForm.note })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            let msg = data.error || data.message || 'Failed to adjust wallet';
                            if (data.errors) msg = Object.values(data.errors).flat().join(', ');
                            this.errorMsg = msg;
                        } else {
                            this.message = 'Wallet adjusted successfully!';
                            this.showWalletModal = false;
                            await this.loadAdminData();
                        }
                    } catch (e) {
                        this.errorMsg = 'Network error.';
                    } finally {
                        this.loading = false;
                        setTimeout(() => this.message = this.errorMsg = '', 4000);
                    }
                },

                async claimDispute(disputeId) {
                    try {
                        const res = await fetch(`/api/assistance/claim/${disputeId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.errorMsg = data.message || 'Failed to claim dispute';
                        } else {
                            this.message = 'Dispute claimed successfully!';
                            await this.loadQueue();
                        }
                    } catch (e) {
                        this.errorMsg = 'Network error while claiming dispute.';
                    } finally {
                        setTimeout(() => this.message = this.errorMsg = '', 4000);
                    }
                },

                async resolveDispute(disputeId, winner) {
                    if (!confirm(`Are you sure you want to resolve in favor of ${winner.toUpperCase()}?`)) return;
                    try {
                        const res = await fetch(`/api/assistance/resolve/${disputeId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ winner: winner, notes: 'Resolved by super admin' })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.errorMsg = data.message || 'Failed to resolve dispute';
                        } else {
                            this.message = data.message;
                            await this.loadQueue();
                        }
                    } catch (e) {
                        this.errorMsg = 'Failed to resolve dispute.';
                    } finally {
                        setTimeout(() => this.message = this.errorMsg = '', 4000);
                    }
                },

                async deleteUser(userId) {
                    if (!confirm('Are you sure you want to completely delete this user? This action cannot be undone and will delete all their trades, orders, and wallet history.')) return;
                    this.loading = true;
                    this.errorMsg = '';
                    this.message = '';
                    try {
                        const res = await fetch(`/api/admin/users/${userId}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            }
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            let msg = data.error || data.message || 'Failed to delete user';
                            if (data.errors) msg = Object.values(data.errors).flat().join(', ');
                            this.errorMsg = msg;
                        } else {
                            this.message = 'User deleted successfully!';
                            await this.loadAdminData();
                        }
                    } catch (e) {
                        this.errorMsg = 'Network error.';
                    } finally {
                        this.loading = false;
                        setTimeout(() => this.message = this.errorMsg = '', 4000);
                    }
                },

                formatDate(dateStr) {
                    if (!dateStr) return 'N/A';
                    const d = new Date(dateStr);
                    return d.toLocaleString();
                },
                
                async saveProfile() {
                    this.message = '';
                    this.errorMsg = '';
                    this.loading = true;
                    try {
                        const res = await fetch('/api/admin/profile', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(this.profileForm)
                        });
                        const data = await res.json();
                        if(!res.ok) throw new Error(data.error || data.message || 'Failed to update profile');
                        this.message = 'Profile updated successfully!';
                        setTimeout(() => this.showProfileModal = false, 1500);
                        this.profileForm.password = '';
                    } catch(err) {
                        this.errorMsg = err.message;
                    } finally {
                        this.loading = false;
                    }
                },

                openResetPasswordModal(user) {
                    this.resetPasswordForm = { user_id: user.id, full_name: user.full_name, new_password: '' };
                    this.showResetPasswordModal = true;
                },

                async resetStaffPassword() {
                    this.message = '';
                    this.errorMsg = '';
                    this.loading = true;
                    try {
                        const res = await fetch(`/api/admin/users/${this.resetPasswordForm.user_id}/reset-password`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ new_password: this.resetPasswordForm.new_password })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            let msg = data.error || data.message || 'Failed to reset password';
                            if (data.errors) msg = Object.values(data.errors).flat().join(', ');
                            this.errorMsg = msg;
                        } else {
                            this.message = data.message || 'Password reset successfully!';
                            this.showResetPasswordModal = false;
                            this.resetPasswordForm = { user_id: '', full_name: '', new_password: '' };
                            await this.loadAuditLogs(true);
                        }
                    } catch (e) {
                        this.errorMsg = 'Network error.';
                    } finally {
                        this.loading = false;
                        setTimeout(() => this.message = this.errorMsg = '', 4000);
                    }
                }
            }
        }
    </script>
        <!-- Admin Profile Modal -->
        <div x-show="showProfileModal" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center" @keydown.escape.window="showProfileModal = false">
            <div x-show="showProfileModal" class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" @click="showProfileModal = false" x-transition.opacity></div>
            <div x-show="showProfileModal" class="relative bg-white dark:bg-deep-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl border border-gray-100 dark:border-white/10 m-4 transition-all" x-transition.scale.origin.bottom>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">My Profile</h3>
                    <button @click="showProfileModal = false" class="text-gray-400 hover:text-gray-900 dark:hover:text-white text-2xl leading-none">&times;</button>
                </div>
                
                <form @submit.prevent="saveProfile" class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Mobile Number</label>
                        <input type="text" x-model="profileForm.mobile_number" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-gold-500/50 outline-none" required>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">New Password (Optional)</label>
                        <input type="password" x-model="profileForm.password" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-gold-500/50 outline-none" placeholder="Leave blank to keep current">
                    </div>
                    
                    <button type="submit" class="w-full btn-primary px-4 py-3 rounded-xl font-bold text-lg mt-4 shadow-lg shadow-gold-500/20" :disabled="loading">
                        <span x-show="!loading">Save Profile</span>
                        <span x-show="loading" class="animate-pulse">Saving...</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div x-show="showResetPasswordModal" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center" @keydown.escape.window="showResetPasswordModal = false">
            <div x-show="showResetPasswordModal" class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" @click="showResetPasswordModal = false" x-transition.opacity></div>
            <div x-show="showResetPasswordModal" class="relative bg-white dark:bg-deep-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl border border-gray-100 dark:border-white/10 m-4 transition-all" x-transition.scale.origin.bottom>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">🔑 Reset Password</h3>
                    <button @click="showResetPasswordModal = false" class="text-gray-400 hover:text-gray-900 dark:hover:text-white text-2xl leading-none">&times;</button>
                </div>
                
                <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 p-3 rounded-xl mb-5 text-amber-700 dark:text-amber-400 text-sm font-medium flex items-center gap-2">
                    <span class="text-lg">⚠️</span>
                    <span>Resetting password for <strong x-text="resetPasswordForm.full_name"></strong></span>
                </div>

                <form @submit.prevent="resetStaffPassword" class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">New Password</label>
                        <input type="password" x-model="resetPasswordForm.new_password" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500/50 outline-none" placeholder="Enter new password (min 6 chars)" required minlength="6">
                    </div>
                    
                    <button type="submit" class="w-full text-white px-4 py-3 rounded-xl font-bold text-lg mt-4 shadow-lg transition-all active:scale-95 disabled:opacity-70" style="background: linear-gradient(to right, #ef4444, #dc2626); box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.2);" :disabled="loading">
                        <span x-show="!loading">Reset Password</span>
                        <span x-show="loading" class="animate-pulse">Resetting...</span>
                    </button>
                </form>
            </div>
        </div>

    </div>
</body>
</html>
