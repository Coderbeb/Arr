<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel — Arr Wallet')</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="{{ asset('css/globals.css') }}">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        body {
            background-color: #050505; /* Deep dark for admin */
            color: #ffffff;
        }
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: 280px;
            background: rgba(10, 10, 10, 0.95);
            border-right: 1px solid rgba(255, 215, 0, 0.1);
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(10px);
            z-index: 200;
            transition: transform 0.3s ease;
        }
        .admin-sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 215, 0, 0.1);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .admin-sidebar-nav {
            flex: 1;
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            overflow-y: auto;
        }
        .admin-nav-item {
            padding: 0.85rem 1rem;
            border-radius: var(--radius-md);
            color: var(--text-muted);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s;
            cursor: pointer;
            font-weight: 500;
        }
        .admin-nav-item:hover, .admin-nav-item.active {
            background: rgba(255, 215, 0, 0.05);
            color: var(--gold);
        }
        .admin-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }
        .admin-content {
            padding: 2rem 3rem;
            flex: 1;
            overflow-y: auto;
        }
        .mobile-header {
            display: none;
            padding: 1rem 1.5rem;
            background: #0a0a0a;
            border-bottom: 1px solid rgba(255,215,0,0.1);
            align-items: center;
            justify-content: space-between;
        }
        
        @media (max-width: 1024px) {
            .admin-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 85%;
                max-width: 320px;
                height: 100vh;
                transform: translateX(-100%);
                box-shadow: 5px 0 25px rgba(0,0,0,0.8);
            }
            .admin-sidebar.open {
                transform: translateX(0);
            }
            .mobile-header {
                display: flex;
                height: 60px;
                padding: 0 1rem;
            }
            .admin-content {
                padding: 1rem;
            }
            .admin-nav-item {
                padding: 1rem;
                font-size: 1.05rem;
            }
        }
    </style>
</head>
<body x-data="adminApp()">
    
    @if(isset($global_announcement) && !empty($global_announcement))
        <div style="background: var(--warning); color: #000; text-align: center; padding: 0.75rem; font-weight: 600; font-size: 0.95rem; position: relative; z-index: 50;">
            📢 {{ $global_announcement }}
        </div>
    @endif
    
    <div class="admin-layout">
        <!-- Sidebar Overlay (Mobile) -->
        <div class="sidebar-overlay" :class="{ 'open': sidebarOpen }" @click="sidebarOpen = false"></div>

        <!-- Sidebar -->
        <aside class="admin-sidebar" :class="{ 'open': sidebarOpen }">
            <div class="admin-sidebar-header">
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--gold);">🪙 Arr Admin</div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">Super User Console</div>
            </div>
            
            <nav class="admin-sidebar-nav">
                <button class="admin-nav-item btn-ghost" :class="{ 'active': activeTab === 'analytics' }" @click="activeTab = 'analytics'; sidebarOpen = false;">
                    📈 Platform Analytics
                </button>
                <button class="admin-nav-item btn-ghost" :class="{ 'active': activeTab === 'users' }" @click="activeTab = 'users'; sidebarOpen = false;">
                    👥 User Management
                </button>
                <button class="admin-nav-item btn-ghost" :class="{ 'active': activeTab === 'settings' }" @click="activeTab = 'settings'; sidebarOpen = false;">
                    ⚙️ Global Configuration
                </button>
                <button class="admin-nav-item btn-ghost" :class="{ 'active': activeTab === 'assistance' }" @click="activeTab = 'assistance'; sidebarOpen = false;">
                    🛡️ Support Queue
                </button>
                <button class="admin-nav-item btn-ghost" :class="{ 'active': activeTab === 'logs' }" @click="activeTab = 'logs'; sidebarOpen = false;">
                    📜 System Audit Logs
                </button>
            </nav>
            
            <div style="padding: 1.5rem 1rem; border-top: 1px solid rgba(255, 215, 0, 0.1);">
                <form action="/api/auth/logout" method="POST" @submit.prevent="fetch('/api/auth/logout', {method: 'POST', headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}}).then(() => window.location.href='/login')">
                    <button type="submit" class="admin-nav-item btn-ghost" style="width: 100%; color: var(--danger);">
                        🚪 Sign Out
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="admin-main">
            <header class="mobile-header">
                <button class="btn btn-ghost" style="padding: 0.5rem; font-size: 1.5rem;" @click="sidebarOpen = true">☰</button>
                <div style="font-weight: 700; color: var(--gold); font-size: 1.2rem;">🪙 Arr Admin</div>
                <div style="width: 32px;"></div> <!-- Spacer to center title -->
            </header>
            
            <main class="admin-content fade-in">
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        function adminApp() {
            return {
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
                },

                async loadAdminData() {
                    this.loading = true;
                    this.errorMsg = '';
                    this.message = '';
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
                            this.settings = settingsData;
                        }
                        
                        const userData = await userRes.json();
                        this.users = userData.data || userData || [];

                        this.auditLogs = await logRes.json();
                        this.analytics = await analyticsRes.json();
                    } catch (e) {
                        this.errorMsg = 'Failed to load admin data.';
                    } finally {
                        this.loading = false;
                    }
                },

                async loadQueue() {
                    this.loading = true;
                    try {
                        const res = await fetch('/api/assistance/queue');
                        this.disputes = await res.json();
                    } catch (e) {
                        this.errorMsg = 'Failed to load support queue.';
                    } finally {
                        this.loading = false;
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
                        this.message = data.message;
                        await this.loadQueue();
                    } catch (e) {
                        this.errorMsg = 'Failed to resolve dispute.';
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
