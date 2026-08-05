@extends('layouts.admin')

@section('title', 'Admin Control Panel')

@section('content')

    <!-- Top Loading Indicator -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 dark:from-white dark:to-gray-300 bg-clip-text text-transparent m-0">
                <span x-show="activeTab === 'analytics'">📈 Platform Analytics</span>
                <span x-show="activeTab === 'users'">👥 User Management</span>
                <span x-show="activeTab === 'settings'">⚙️ Global Configuration</span>
                <span x-show="activeTab === 'assistance'">🛡️ Support Queue</span>
                <span x-show="activeTab === 'logs'">📜 System Audit Logs</span>
            </h1>
        </div>
        <div x-show="loading" class="shrink-0 flex items-center justify-center p-2 bg-white dark:bg-white/5 rounded-full shadow-sm">
            <svg class="animate-spin h-6 w-6 text-gold-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        </div>
    </div>

    <!-- Feedback Messages -->
    <template x-if="message">
        <div class="glass-card !bg-green-50 dark:!bg-green-500/10 !border-green-200 dark:!border-green-500/20 p-4 mb-6 text-green-700 dark:text-green-400 font-medium" x-text="message"></div>
    </template>
    <template x-if="errorMsg">
        <div class="glass-card !bg-red-50 dark:!bg-red-500/10 !border-red-200 dark:!border-red-500/20 p-4 mb-6 text-red-700 dark:text-red-400 font-medium" x-text="errorMsg"></div>
    </template>

    <!-- Analytics Tab -->
    <div x-show="activeTab === 'analytics'" class="animate-fade-in">
        <template x-if="analytics">
            <div>
                <!-- Mobile Compact Analytics (Hidden on MD) -->
                <div class="md:hidden bg-gradient-to-br from-gray-900 to-black dark:from-white/10 dark:to-white/5 rounded-2xl p-4 mb-6 shadow-xl text-white relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 w-32 h-32 bg-gold-500/20 rounded-full blur-2xl"></div>
                    <div class="mb-4">
                        <div class="text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-1">Total Platform Comm.</div>
                        <div class="text-3xl font-bold text-gold-400">₹<span x-text="parseFloat(analytics.financials.total_commission).toFixed(2)"></span></div>
                    </div>
                    <div class="flex justify-between items-end mb-4">
                        <div>
                            <div class="text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-1"><span class="text-amber-400">🔒</span> Escrow</div>
                            <div class="text-lg font-bold">₹<span x-text="parseFloat(analytics.financials.total_liquidity).toFixed(2)"></span></div>
                        </div>
                        <div class="text-right">
                            <div class="text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-1"><span class="text-blue-400">💼</span> Wallets</div>
                            <div class="text-lg font-bold">₹<span x-text="parseFloat(analytics.financials.total_wallet_balance).toFixed(2)"></span></div>
                        </div>
                    </div>
                    <div class="pt-3 border-t border-white/10 flex justify-between items-center text-xs">
                        <span class="text-gray-300">Active Users: <strong class="text-green-400" x-text="analytics.users.active"></strong></span>
                        <span class="text-gray-300">Trades: <strong class="text-emerald-400" x-text="analytics.trades.completed"></strong></span>
                    </div>
                </div>

                <!-- Desktop Analytics Grid -->
                <div class="hidden md:grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Financials -->
                <div class="glass-card p-6 !border-gold-400/30 dark:!bg-gold-500/5 hover:scale-[1.02] transition-transform">
                    <div class="text-4xl font-bold text-gold-500 mb-2">₹<span x-text="parseFloat(analytics.financials.total_commission).toFixed(2)"></span></div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Commission Generated</div>
                </div>
                <div class="glass-card p-6 !border-amber-400/30 dark:!bg-amber-500/5 hover:scale-[1.02] transition-transform">
                    <div class="text-4xl font-bold text-amber-500 mb-2">₹<span x-text="parseFloat(analytics.financials.total_liquidity).toFixed(2)"></span></div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Escrow Liquidity</div>
                </div>
                <div class="glass-card p-6 !border-blue-400/30 dark:!bg-blue-500/5 hover:scale-[1.02] transition-transform">
                    <div class="text-4xl font-bold text-blue-500 mb-2">₹<span x-text="parseFloat(analytics.financials.total_wallet_balance).toFixed(2)"></span></div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Users Wallet Balance</div>
                </div>
                
                <!-- Users -->
                <div class="glass-card p-6 !border-indigo-400/30 dark:!bg-indigo-500/5">
                    <div class="text-4xl font-bold text-indigo-500 mb-2" x-text="analytics.users.total"></div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Total Registered Users</div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Active: <strong class="text-green-500" x-text="analytics.users.active"></strong></span>
                        <span class="text-gray-600 dark:text-gray-400">Suspended: <strong class="text-amber-500" x-text="analytics.users.suspended"></strong></span>
                        <span class="text-gray-600 dark:text-gray-400">Banned: <strong class="text-red-500" x-text="analytics.users.banned"></strong></span>
                    </div>
                </div>

                <!-- Trades -->
                <div class="glass-card p-6 !border-emerald-400/30 dark:!bg-emerald-500/5">
                    <div class="text-4xl font-bold text-emerald-500 mb-2" x-text="analytics.trades.completed"></div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">Completed Trades</div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Active: <strong class="text-blue-500" x-text="analytics.trades.active"></strong></span>
                        <span class="text-gray-600 dark:text-gray-400">Disputed: <strong class="text-red-500" x-text="analytics.trades.disputed"></strong></span>
                    </div>
                </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Platform Settings Tab -->
    <div x-show="activeTab === 'settings'" class="animate-fade-in">
        <form @submit.prevent="saveSettings" class="glass-card p-4 sm:p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="md:col-span-2 mb-4">
                    <label class="input-label !text-gold-500 font-bold">Global Announcement Banner (Leave empty to disable)</label>
                    <input type="text" class="input-field !bg-gray-100 dark:!bg-white/5 !text-lg !py-3" placeholder="e.g. Platform maintenance tonight at 12 AM" x-model="settings.global_announcement">
                </div>

                <div>
                    <label class="input-label">Registration Status</label>
                    <select class="input-field" x-model="settings.registration_open">
                        <option value="1">Open (Enabled)</option>
                        <option value="0">Closed (Disabled)</option>
                    </select>
                </div>

                <div>
                    <label class="input-label">Platform Commission (%)</label>
                    <input type="number" step="0.01" min="0" max="50" class="input-field" x-model="settings.commission_percent" required>
                </div>

                <div>
                    <label class="input-label">Max Daily Earning (₹)</label>
                    <input type="number" step="0.01" min="0" class="input-field" x-model="settings.max_daily_earning" required>
                </div>

                <div>
                    <label class="input-label">Max Weekly Earning (₹)</label>
                    <input type="number" step="0.01" min="0" class="input-field" x-model="settings.max_weekly_earning" required>
                </div>

                <div>
                    <label class="input-label">Trade Accept Time (Mins)</label>
                    <input type="number" min="1" class="input-field" x-model="settings.trade_accept_minutes" required>
                </div>

                <div>
                    <label class="input-label">Payment Timer (Mins)</label>
                    <input type="number" min="1" class="input-field" x-model="settings.payment_timer_minutes" required>
                </div>

                <div>
                    <label class="input-label">Dispute Proof Time (Mins)</label>
                    <input type="number" min="1" class="input-field" x-model="settings.dispute_proof_minutes" required>
                </div>
                
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100 dark:border-white/10 flex justify-end">
                <button type="submit" class="btn-primary w-full sm:w-auto px-12 py-3 text-lg" :disabled="loading">
                    <span x-show="!loading">Save Settings</span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Saving...
                    </span>
                </button>
            </div>
        </form>
    </div>

    <!-- Users Management Tab -->
    <div x-show="activeTab === 'users'" class="animate-fade-in">
        <div class="glass-card p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
                <p class="text-gray-500 dark:text-gray-400 font-medium">Manage all registered accounts</p>
                <button class="btn-primary shrink-0" @click="showStaffModal = true">➕ Create Support Staff</button>
            </div>
            
            <div class="overflow-x-hidden sm:-mx-6 sm:px-6">
                <table class="w-full text-left">
                    <thead class="hidden sm:table-header-group">
                        <tr class="border-b-2 border-gold-400/20 text-gold-600 dark:text-gold-400 text-xs font-bold uppercase tracking-wider">
                            <th class="py-4 px-3">Name / Mobile</th>
                            <th class="py-4 px-3">Role</th>
                            <th class="py-4 px-3">Wallet Bal</th>
                            <th class="py-4 px-3">Joined</th>
                            <th class="py-4 px-3">Status Action</th>
                            <th class="py-4 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5 block sm:table-row-group">
                        <template x-for="u in users" :key="u.id">
                            <tr class="block sm:table-row hover:bg-gray-50 dark:hover:bg-white/5 transition-colors group mb-4 sm:mb-0 border border-gray-200 dark:border-white/10 sm:border-none rounded-xl p-4 sm:p-0">
                                <td class="flex sm:table-cell justify-between items-center sm:py-4 sm:px-3 py-2 border-b sm:border-none border-gray-100 dark:border-white/5">
                                    <span class="sm:hidden text-xs font-bold text-gray-500 uppercase">User</span>
                                    <div class="text-right sm:text-left">
                                        <div class="font-bold text-gray-900 dark:text-white" x-text="u.full_name"></div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400" x-text="u.mobile_number"></div>
                                    </div>
                                </td>
                                <td class="flex sm:table-cell justify-between items-center sm:py-4 sm:px-3 py-2 border-b sm:border-none border-gray-100 dark:border-white/5">
                                    <span class="sm:hidden text-xs font-bold text-gray-500 uppercase">Role</span>
                                    <div class="text-right sm:text-left">
                                        <span class="inline-flex px-2 py-1 rounded text-xs font-bold tracking-wider" 
                                              :class="u.role === 'super_admin' ? 'bg-gold-100 text-gold-700 dark:bg-gold-500/20 dark:text-gold-400' : (u.role === 'assistance' ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400')"
                                              x-text="u.role.replace('_', ' ').toUpperCase()"></span>
                                    </div>
                                </td>
                                <td class="flex sm:table-cell justify-between items-center sm:py-4 sm:px-3 py-2 border-b sm:border-none border-gray-100 dark:border-white/5">
                                    <span class="sm:hidden text-xs font-bold text-gray-500 uppercase">Wallet</span>
                                    <div class="font-bold text-gold-500 text-lg text-right sm:text-left" x-text="'₹' + parseFloat(u.wallet_balance).toFixed(2)"></div>
                                </td>
                                <td class="flex sm:table-cell justify-between items-center sm:py-4 sm:px-3 py-2 border-b sm:border-none border-gray-100 dark:border-white/5">
                                    <span class="sm:hidden text-xs font-bold text-gray-500 uppercase">Joined</span>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 text-right sm:text-left" x-text="formatDate(u.created_at)"></div>
                                </td>
                                <td class="flex sm:table-cell justify-between items-center sm:py-4 sm:px-3 py-2 border-b sm:border-none border-gray-100 dark:border-white/5">
                                    <span class="sm:hidden text-xs font-bold text-gray-500 uppercase">Status</span>
                                    <select class="input-field !py-1.5 !px-3 !text-sm w-auto inline-block" 
                                        x-model="u.status" 
                                        @change="updateUserStatus(u.id, u.status)"
                                        :disabled="u.role === 'super_admin' && u.id === '{{ Auth::id() }}'">
                                        <option value="active">Active</option>
                                        <option value="suspended">Suspended</option>
                                        <option value="banned">Banned</option>
                                    </select>
                                </td>
                                <td class="flex sm:table-cell justify-between items-center sm:py-4 sm:px-3 py-3 mt-2 sm:mt-0 text-right">
                                    <span class="sm:hidden text-xs font-bold text-gray-500 uppercase">Actions</span>
                                    <div class="flex gap-2 justify-end sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                        <button class="btn-secondary !py-1.5 !px-3 !text-sm" @click="openWalletModal(u)">Wallet</button>
                                        <button class="btn-danger !py-1.5 !px-3 !text-sm" x-show="u.role !== 'super_admin'" @click="deleteUser(u.id)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="users.length === 0 && !loading" class="block sm:table-row">
                            <td colspan="6" class="py-12 text-center text-gray-500 dark:text-gray-400 block sm:table-cell">No users found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Audit Logs Tab -->
    <div x-show="activeTab === 'logs'" class="animate-fade-in">
        <div class="glass-card p-4 sm:p-6">
            <p class="text-gray-500 dark:text-gray-400 font-medium mb-6">Immutable record of all admin actions</p>
            <div class="overflow-x-hidden sm:-mx-6 sm:px-6">
                <table class="w-full text-left">
                    <thead class="hidden sm:table-header-group">
                        <tr class="border-b border-gray-200 dark:border-white/10 text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-wider">
                            <th class="py-4 px-3">Timestamp</th>
                            <th class="py-4 px-3">Admin ID</th>
                            <th class="py-4 px-3">Action</th>
                            <th class="py-4 px-3">Target (Type/ID)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5 block sm:table-row-group">
                        <template x-for="log in auditLogs" :key="log.id">
                            <tr class="block sm:table-row hover:bg-gray-50 dark:hover:bg-white/5 transition-colors mb-4 sm:mb-0 border border-gray-200 dark:border-white/10 sm:border-none rounded-xl p-4 sm:p-0">
                                <td class="flex sm:table-cell justify-between items-center sm:py-4 sm:px-3 py-2 border-b sm:border-none border-gray-100 dark:border-white/5">
                                    <span class="sm:hidden text-xs font-bold text-gray-500 uppercase">Time</span>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 text-right sm:text-left" x-text="formatDate(log.created_at)"></div>
                                </td>
                                <td class="flex sm:table-cell justify-between items-center sm:py-4 sm:px-3 py-2 border-b sm:border-none border-gray-100 dark:border-white/5">
                                    <span class="sm:hidden text-xs font-bold text-gray-500 uppercase">Admin ID</span>
                                    <div class="font-mono text-sm text-gray-600 dark:text-gray-300 text-right sm:text-left" x-text="log.admin_id.substring(0, 13) + '...'"></div>
                                </td>
                                <td class="flex sm:table-cell justify-between items-center sm:py-4 sm:px-3 py-2 border-b sm:border-none border-gray-100 dark:border-white/5">
                                    <span class="sm:hidden text-xs font-bold text-gray-500 uppercase">Action</span>
                                    <div class="text-right sm:text-left">
                                        <span class="inline-flex px-2 py-1 rounded text-xs font-bold tracking-wider bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400" x-text="log.action.replace(/_/g, ' ').toUpperCase()"></span>
                                    </div>
                                </td>
                                <td class="flex sm:table-cell justify-between items-center sm:py-4 sm:px-3 py-2 mt-2 sm:mt-0">
                                    <span class="sm:hidden text-xs font-bold text-gray-500 uppercase">Target</span>
                                    <div class="text-right sm:text-left">
                                        <div class="font-bold text-xs uppercase text-gold-500 mb-0.5" x-text="log.target_type"></div>
                                        <div class="font-mono text-xs text-gray-500 dark:text-gray-400" x-text="log.target_id || 'N/A'"></div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="auditLogs.length === 0 && !loading" class="block sm:table-row">
                            <td colspan="4" class="py-12 text-center text-gray-500 dark:text-gray-400 block sm:table-cell">No audit logs available.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Support Queue Tab -->
    <div x-show="activeTab === 'assistance'" class="animate-fade-in">
        <div class="mb-8">
            <p class="text-gray-500 dark:text-gray-400 font-medium">Review buyer & seller video proofs and AI confidence scores</p>
        </div>

        <template x-if="loading">
            <div class="flex justify-center py-12">
                <svg class="animate-spin h-10 w-10 text-gold-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </div>
        </template>

        <template x-if="!loading && disputes.length === 0">
            <div class="glass-card border-dashed border-2 py-16 flex flex-col items-center justify-center text-center">
                <div class="text-5xl mb-4">✨</div>
                <div class="text-xl font-bold text-gray-700 dark:text-gray-300">No active disputes in queue</div>
                <p class="text-gray-500 dark:text-gray-400 mt-2">All clean and handled!</p>
            </div>
        </template>

        <template x-if="!loading && disputes.length > 0">
            <div class="space-y-6">
                <template x-for="d in disputes" :key="d.id">
                    <div class="glass-card p-4 sm:p-8">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 pb-4 border-b border-gray-100 dark:border-white/5 gap-4">
                            <h4 class="text-xl font-bold text-gray-900 dark:text-white">
                                Dispute #<span x-text="d.id.slice(0, 8)"></span> 
                                <span class="text-gold-500 ml-2">(Trade ₹<span x-text="d.trade ? parseFloat(d.trade.amount).toFixed(2) : ''"></span>)</span>
                            </h4>
                            <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400" x-text="d.status"></span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <!-- Buyer Proof -->
                            <div class="bg-gray-50 dark:bg-white/5 p-6 rounded-xl border border-gray-200 dark:border-white/5">
                                <label class="block text-green-600 dark:text-green-400 font-bold text-lg mb-4">Buyer Proof</label>
                                <div class="space-y-3">
                                    <template x-if="d.buyer_screen_recording_url">
                                        <a :href="d.buyer_screen_recording_url" target="_blank" class="flex items-center justify-center w-full py-2 px-4 rounded-lg border border-green-500 text-green-600 dark:text-green-400 hover:bg-green-500/10 font-medium transition-colors">📹 View Buyer Video</a>
                                    </template>
                                    <template x-if="!d.buyer_screen_recording_url">
                                        <div class="text-center py-2 px-4 rounded-lg bg-gray-100 dark:bg-black/20 text-gray-500 text-sm">No video uploaded</div>
                                    </template>
                                    
                                    <template x-if="d.buyer_bank_statement_url">
                                        <a :href="d.buyer_bank_statement_url" target="_blank" class="flex items-center justify-center w-full py-2 px-4 rounded-lg border border-green-500 text-green-600 dark:text-green-400 hover:bg-green-500/10 font-medium transition-colors">📄 View Buyer PDF</a>
                                    </template>
                                    <template x-if="!d.buyer_bank_statement_url">
                                        <div class="text-center py-2 px-4 rounded-lg bg-gray-100 dark:bg-black/20 text-gray-500 text-sm">No PDF uploaded</div>
                                    </template>

                                    <template x-if="d.buyer_screenshot_url">
                                        <a :href="d.buyer_screenshot_url" target="_blank" class="flex items-center justify-center w-full py-2 px-4 rounded-lg border border-green-500 text-green-600 dark:text-green-400 hover:bg-green-500/10 font-medium transition-colors">🖼️ View Buyer Screenshot</a>
                                    </template>
                                    <template x-if="!d.buyer_screenshot_url">
                                        <div class="text-center py-2 px-4 rounded-lg bg-gray-100 dark:bg-black/20 text-gray-500 text-sm">No screenshot uploaded</div>
                                    </template>
                                </div>
                                <div class="text-center mt-6 p-4 rounded-lg bg-white dark:bg-black/20 border border-gray-100 dark:border-white/5">
                                    <span class="text-gray-500 font-medium mr-2">AI Confidence Score:</span>
                                    <strong class="text-xl" :class="d.buyer_ai_score > 70 ? 'text-green-500' : 'text-gold-500'" x-text="d.buyer_ai_score ? d.buyer_ai_score + '%' : 'Pending'"></strong>
                                </div>
                            </div>

                            <!-- Seller Proof -->
                            <div class="bg-gray-50 dark:bg-white/5 p-6 rounded-xl border border-gray-200 dark:border-white/5">
                                <label class="block text-red-600 dark:text-red-400 font-bold text-lg mb-4">Seller Proof</label>
                                <div class="space-y-3">
                                    <template x-if="d.seller_screen_recording_url">
                                        <a :href="d.seller_screen_recording_url" target="_blank" class="flex items-center justify-center w-full py-2 px-4 rounded-lg border border-red-500 text-red-600 dark:text-red-400 hover:bg-red-500/10 font-medium transition-colors">📹 View Seller Video</a>
                                    </template>
                                    <template x-if="!d.seller_screen_recording_url">
                                        <div class="text-center py-2 px-4 rounded-lg bg-gray-100 dark:bg-black/20 text-gray-500 text-sm">No video uploaded</div>
                                    </template>
                                    
                                    <template x-if="d.seller_bank_statement_url">
                                        <a :href="d.seller_bank_statement_url" target="_blank" class="flex items-center justify-center w-full py-2 px-4 rounded-lg border border-red-500 text-red-600 dark:text-red-400 hover:bg-red-500/10 font-medium transition-colors">📄 View Seller PDF</a>
                                    </template>
                                    <template x-if="!d.seller_bank_statement_url">
                                        <div class="text-center py-2 px-4 rounded-lg bg-gray-100 dark:bg-black/20 text-gray-500 text-sm">No PDF uploaded</div>
                                    </template>

                                    <template x-if="d.seller_txn_screenshot_url">
                                        <a :href="d.seller_txn_screenshot_url" target="_blank" class="flex items-center justify-center w-full py-2 px-4 rounded-lg border border-red-500 text-red-600 dark:text-red-400 hover:bg-red-500/10 font-medium transition-colors">🖼️ View Seller Screenshot</a>
                                    </template>
                                    <template x-if="!d.seller_txn_screenshot_url">
                                        <div class="text-center py-2 px-4 rounded-lg bg-gray-100 dark:bg-black/20 text-gray-500 text-sm">No screenshot uploaded</div>
                                    </template>
                                </div>
                                <div class="text-center mt-6 p-4 rounded-lg bg-white dark:bg-black/20 border border-gray-100 dark:border-white/5">
                                    <span class="text-gray-500 font-medium mr-2">AI Confidence Score:</span>
                                    <strong class="text-xl" :class="d.seller_ai_score > 70 ? 'text-green-500' : 'text-gold-500'" x-text="d.seller_ai_score ? d.seller_ai_score + '%' : 'Pending'"></strong>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <button class="btn-success flex-1 py-4 text-lg" @click="resolveDispute(d.id, 'buyer')">
                                🏆 Resolve: Buyer Wins
                            </button>
                            <button class="btn-danger flex-1 py-4 text-lg" @click="resolveDispute(d.id, 'seller')">
                                🏆 Resolve: Seller Wins
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- Create Staff Modal -->
    <div x-show="showStaffModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100]" @click="showStaffModal = false"></div>
    <div x-show="showStaffModal" class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-[101] w-[90%] max-w-md bg-white dark:bg-deep-800 rounded-2xl p-6 shadow-2xl border border-gray-200 dark:border-white/10">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100 dark:border-white/10">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Create Support Staff</h3>
            <button class="text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="showStaffModal = false">✕</button>
        </div>
        <form @submit.prevent="createStaff">
            <div class="space-y-4">
                <div>
                    <label class="input-label">Full Name</label>
                    <input type="text" class="input-field" x-model="staffForm.full_name" required>
                </div>
                <div>
                    <label class="input-label">Mobile Number</label>
                    <input type="text" class="input-field" x-model="staffForm.mobile_number" required>
                </div>
                <div>
                    <label class="input-label">Password</label>
                    <input type="password" class="input-field" x-model="staffForm.password" required minlength="6">
                </div>
            </div>
            <button type="submit" class="btn-primary w-full mt-6 py-3" :disabled="loading">
                <span x-show="!loading">Create Staff Account</span>
                <span x-show="loading">Creating...</span>
            </button>
        </form>
    </div>

    <!-- Manage Wallet Modal -->
    <div x-show="showWalletModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100]" @click="showWalletModal = false"></div>
    <div x-show="showWalletModal" class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-[101] w-[90%] max-w-md bg-white dark:bg-deep-800 rounded-2xl p-6 shadow-2xl border border-gray-200 dark:border-white/10">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100 dark:border-white/10">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Manage Wallet</h3>
            <button class="text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="showWalletModal = false">✕</button>
        </div>
        
        <div class="mb-6 bg-gray-50 dark:bg-white/5 p-4 rounded-xl border border-gray-200 dark:border-white/5">
            <span class="text-gray-500 text-sm">User:</span> <strong class="text-gold-500 ml-1 text-lg" x-text="walletForm.full_name"></strong>
        </div>
        
        <form @submit.prevent="adjustWallet">
            <div class="space-y-4">
                <div>
                    <label class="input-label">Action</label>
                    <select class="input-field" x-model="walletForm.action">
                        <option value="add">Add Funds (Credit)</option>
                        <option value="deduct">Deduct Funds (Debit)</option>
                    </select>
                </div>
                <div>
                    <label class="input-label">Amount (₹)</label>
                    <input type="number" step="0.01" min="0.01" class="input-field" x-model="walletForm.amount" required>
                </div>
                <div>
                    <label class="input-label">Admin Note (Reason)</label>
                    <input type="text" class="input-field" x-model="walletForm.note" placeholder="e.g. Refund for failed trade">
                </div>
            </div>
            <button type="submit" class="w-full mt-6 py-3" :class="walletForm.action === 'add' ? 'btn-primary' : 'btn-danger'" :disabled="loading">
                <span x-show="!loading">Confirm Adjustment</span>
                <span x-show="loading">Processing...</span>
            </button>
        </form>
    </div>

@endsection
