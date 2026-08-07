<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel — Arr Wallet')</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS (Vite) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/globals.css') }}">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
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
                    commission_percent: 0.00,
                    max_daily_earning: 0,
                    max_weekly_earning: 0,
                    trade_accept_minutes: 15,
                    payment_timer_minutes: 30,
                    dispute_proof_minutes: 120,
                    global_announcement: ''
                },
                users: [],
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
                
                showSuperAccountModal: false,
                superAccountForm: { full_name: '', mobile_number: '', password: '' },

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

                async init() {
                    await this.loadAdminData();
                    
                    // Watch for tab changes to load specific data if needed
                    this.$watch('activeTab', async (value) => {
                        if (value === 'assistance') {
                            await this.loadQueue();
                        } else {
                            await this.loadAdminData();
                        }
                    });

                    // Start Real-Time Background Polling
                    this.syncTimer = setInterval(async () => {
                        this.isSyncing = true;
                        if (this.activeTab === 'assistance') {
                            await this.loadQueue(true);
                        } else if (this.activeTab !== 'settings') {
                            // Don't poll settings to avoid overwriting user input
                            await this.loadAdminData(true);
                        }
                        setTimeout(() => this.isSyncing = false, 500); // Small delay to show sync indicator
                    }, 5000);
                },

                async loadAdminData(silent = false) {
                    if (!silent) {
                        this.loading = true;
                        this.errorMsg = '';
                        this.message = '';
                    }
                    try {
                        const [setRes, userRes, logRes, analyticsRes] = await Promise.all([
                            fetch('/api/admin/settings'),
                            fetch('/api/admin/users'),
                            fetch('/api/admin/audit-logs'),
                            fetch('/api/admin/analytics')
                        ]);
                        
                        const settingsData = await setRes.json();
                        if(settingsData && settingsData.id) {
                            settingsData.registration_open = settingsData.registration_open ? 1 : 0;
                            settingsData.global_announcement = settingsData.global_announcement || '';
                            // Only update settings if we're not silently polling, to avoid overwriting form inputs
                            if (!silent) {
                                this.settings = settingsData;
                            }
                        }
                        
                        const userData = await userRes.json();
                        this.users = userData.data || userData || [];

                        this.auditLogs = await logRes.json();
                        this.analytics = await analyticsRes.json();
                    } catch (e) {
                        if (!silent) this.errorMsg = 'Failed to load admin data.';
                    } finally {
                        if (!silent) this.loading = false;
                    }
                },

                async loadQueue(silent = false) {
                    if (!silent) this.loading = true;
                    try {
                        const res = await fetch('/api/assistance/queue');
                        this.disputes = await res.json();
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
                                this.settings = { ...data.settings, registration_open: data.settings.registration_open ? 1 : 0, global_announcement: data.settings.global_announcement || '' };
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
                            const logRes = await fetch('/api/admin/audit-logs');
                            this.auditLogs = await logRes.json();
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
                }
            }
        }
    </script>
</body>
</html>
