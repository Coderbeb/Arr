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
    <div x-show="activeTab === 'analytics'" class="animate-fade-in space-y-6">
        <template x-if="analytics">
            <div class="space-y-8">
                <!-- Overview Header -->
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4 mb-2">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Platform Health</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Real-time statistics and financial metrics</p>
                    </div>
                    <div class="text-xs font-semibold px-3 py-1.5 rounded-full bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400 flex items-center gap-2">
                        <span class="relative flex h-2 w-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                        </span>
                        System Online
                    </div>
                </div>

                <!-- Financial Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
                    <!-- Commission Card -->
                    <div class="relative group rounded-3xl overflow-hidden p-0.5 transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl hover:shadow-gold-500/20">
                        <div class="absolute inset-0 bg-gradient-to-br from-gold-400 to-amber-600 opacity-20 group-hover:opacity-100 transition-opacity duration-500 rounded-3xl blur-xl"></div>
                        <div class="relative h-full bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-gray-100 dark:border-white/10 p-6 rounded-[22px] flex flex-col justify-between z-10">
                            <div class="flex justify-between items-start mb-6">
                                <div class="w-12 h-12 rounded-full bg-gold-50 dark:bg-gold-500/10 flex items-center justify-center text-2xl shadow-inner">💰</div>
                                <span class="text-xs font-bold text-gold-600 dark:text-gold-400 uppercase tracking-wider bg-gold-100 dark:bg-gold-500/20 px-2 py-1 rounded">Revenue</span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Commission</div>
                                <div class="text-3xl md:text-4xl font-black text-gray-900 dark:text-white tracking-tight">₹<span x-text="parseFloat(analytics.financials.total_commission).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Escrow Card -->
                    <div class="relative group rounded-3xl overflow-hidden p-0.5 transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl hover:shadow-blue-500/20">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-400 to-indigo-600 opacity-20 group-hover:opacity-100 transition-opacity duration-500 rounded-3xl blur-xl"></div>
                        <div class="relative h-full bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-gray-100 dark:border-white/10 p-6 rounded-[22px] flex flex-col justify-between z-10">
                            <div class="flex justify-between items-start mb-6">
                                <div class="w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-500/10 flex items-center justify-center text-2xl shadow-inner">🔒</div>
                                <span class="text-xs font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider bg-blue-100 dark:bg-blue-500/20 px-2 py-1 rounded">Liquidity</span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Escrow Locked</div>
                                <div class="text-3xl md:text-4xl font-black text-gray-900 dark:text-white tracking-tight">₹<span x-text="parseFloat(analytics.financials.total_liquidity).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Wallets Card -->
                    <div class="relative group rounded-3xl overflow-hidden p-0.5 transition-all duration-300 hover:scale-[1.02] hover:shadow-2xl hover:shadow-emerald-500/20">
                        <div class="absolute inset-0 bg-gradient-to-br from-emerald-400 to-teal-600 opacity-20 group-hover:opacity-100 transition-opacity duration-500 rounded-3xl blur-xl"></div>
                        <div class="relative h-full bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-gray-100 dark:border-white/10 p-6 rounded-[22px] flex flex-col justify-between z-10">
                            <div class="flex justify-between items-start mb-6">
                                <div class="w-12 h-12 rounded-full bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-2xl shadow-inner">💼</div>
                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider bg-emerald-100 dark:bg-emerald-500/20 px-2 py-1 rounded">User Funds</span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Total Wallet Balance</div>
                                <div class="text-3xl md:text-4xl font-black text-gray-900 dark:text-white tracking-tight">₹<span x-text="parseFloat(analytics.financials.total_wallet_balance).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Secondary Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                    <!-- Users Activity -->
                    <div class="bg-white/60 dark:bg-deep-800/60 backdrop-blur-md rounded-3xl border border-gray-100 dark:border-white/10 p-6 flex flex-col justify-between hover:bg-white dark:hover:bg-deep-800 transition-colors">
                        <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100 dark:border-white/5">
                            <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-3xl">👥</div>
                            <div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Users</div>
                                <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400" x-text="analytics.users.total"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center divide-x divide-gray-100 dark:divide-white/5">
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase mb-1">Active</div>
                                <div class="font-bold text-green-500 text-lg" x-text="analytics.users.active"></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase mb-1">Suspended</div>
                                <div class="font-bold text-amber-500 text-lg" x-text="analytics.users.suspended"></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase mb-1">Banned</div>
                                <div class="font-bold text-red-500 text-lg" x-text="analytics.users.banned"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Trades Activity -->
                    <div class="bg-white/60 dark:bg-deep-800/60 backdrop-blur-md rounded-3xl border border-gray-100 dark:border-white/10 p-6 flex flex-col justify-between hover:bg-white dark:hover:bg-deep-800 transition-colors">
                        <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100 dark:border-white/5">
                            <div class="w-14 h-14 rounded-2xl bg-purple-50 dark:bg-purple-500/10 flex items-center justify-center text-3xl">⚖️</div>
                            <div>
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Completed Trades</div>
                                <div class="text-3xl font-black text-purple-600 dark:text-purple-400" x-text="analytics.trades.completed"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-center divide-x divide-gray-100 dark:divide-white/5">
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase mb-1">Active Trades</div>
                                <div class="font-bold text-blue-500 text-lg" x-text="analytics.trades.active"></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 uppercase mb-1">Disputed</div>
                                <div class="font-bold text-red-500 text-lg flex justify-center items-center gap-1">
                                    <span x-text="analytics.trades.disputed"></span>
                                    <span x-show="analytics.trades.disputed > 0" class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </template>
    </div>

    <!-- Platform Settings Tab -->
    <div x-show="activeTab === 'settings'" class="animate-fade-in space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white/60 dark:bg-deep-800/60 backdrop-blur-md p-6 sm:rounded-3xl -mx-4 sm:mx-0 border-y sm:border border-gray-100 dark:border-white/10 shadow-sm sm:shadow-none">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Global Configuration</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage core platform mechanics and limits</p>
            </div>
            <button type="button" @click="$dispatch('submit', { target: $refs.settingsForm })" class="btn-primary w-full sm:w-auto shadow-lg shadow-gold-500/20 px-8 py-3 rounded-xl flex items-center justify-center gap-2 transition-all hover:scale-105 active:scale-95" :disabled="loading">
                <span x-show="!loading" class="flex items-center gap-2">💾 Save Changes</span>
                <span x-show="loading" class="flex items-center gap-2">
                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Saving...
                </span>
            </button>
        </div>

        <form x-ref="settingsForm" @submit.prevent="saveSettings" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- General Settings -->
            <div class="lg:col-span-3 bg-white dark:bg-deep-800 border-y sm:border border-gray-100 dark:border-white/10 p-6 sm:rounded-3xl shadow-sm -mx-4 sm:mx-0">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">🌍 General Platform</h3>
                
                <div class="mb-6 relative">
                    <label class="block text-sm font-bold text-gold-600 dark:text-gold-400 mb-2 uppercase tracking-wide">Global Announcement Banner</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-2xl">📢</div>
                        <input type="text" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-2xl pl-12 pr-4 py-4 text-gray-900 dark:text-white focus:ring-2 focus:ring-gold-500/50 outline-none transition-all" placeholder="Leave empty to disable banner" x-model="settings.global_announcement">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Registration Status</label>
                        <select class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-gold-500/50 outline-none transition-all appearance-none" x-model="settings.registration_open">
                            <option value="1">🟢 Open (Accepting Users)</option>
                            <option value="0">🔴 Closed (Maintenance)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Platform Commission (%)</label>
                        <div class="relative">
                            <input type="number" step="0.01" min="0" max="50" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-gold-500/50 outline-none transition-all pr-10" x-model="settings.commission_percent" required>
                            <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-gray-400 font-bold">%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Limits -->
            <div class="bg-white dark:bg-deep-800 border border-gray-100 dark:border-white/10 p-6 rounded-3xl shadow-sm lg:col-span-1">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">💰 Financial Limits</h3>
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Max Daily Earning</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 font-bold">₹</div>
                            <input type="number" step="0.01" min="0" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl pl-8 pr-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-gold-500/50 outline-none transition-all" x-model="settings.max_daily_earning" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Max Weekly Earning</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 font-bold">₹</div>
                            <input type="number" step="0.01" min="0" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl pl-8 pr-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-gold-500/50 outline-none transition-all" x-model="settings.max_weekly_earning" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trade Timers -->
            <div class="bg-white dark:bg-deep-800 border border-gray-100 dark:border-white/10 p-6 rounded-3xl shadow-sm lg:col-span-2">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">⏱️ Trade Timers</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 text-center sm:text-left">Accept Trade</label>
                        <div class="relative flex flex-col items-center sm:items-start">
                            <div class="text-4xl mb-2">🤝</div>
                            <div class="w-full relative">
                                <input type="number" min="1" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-gold-500/50 outline-none transition-all pr-12 text-center sm:text-left" x-model="settings.trade_accept_minutes" required>
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-gray-400 text-xs font-bold uppercase">Min</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 text-center sm:text-left">Payment Window</label>
                        <div class="relative flex flex-col items-center sm:items-start">
                            <div class="text-4xl mb-2">💸</div>
                            <div class="w-full relative">
                                <input type="number" min="1" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500/50 outline-none transition-all pr-12 text-center sm:text-left" x-model="settings.payment_timer_minutes" required>
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-gray-400 text-xs font-bold uppercase">Min</div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2 text-center sm:text-left">Dispute Proof</label>
                        <div class="relative flex flex-col items-center sm:items-start">
                            <div class="text-4xl mb-2">⚖️</div>
                            <div class="w-full relative">
                                <input type="number" min="1" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500/50 outline-none transition-all pr-12 text-center sm:text-left" x-model="settings.dispute_proof_minutes" required>
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-gray-400 text-xs font-bold uppercase">Min</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="hidden"></button>
        </form>
    </div>

    <!-- Users Management Tab -->
    <div x-show="activeTab === 'users'" class="animate-fade-in space-y-6">
        <!-- Header & Actions -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white/60 dark:bg-deep-800/60 backdrop-blur-md p-6 sm:rounded-3xl -mx-4 sm:mx-0 border-y sm:border border-gray-100 dark:border-white/10 shadow-sm sm:shadow-none">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">User Directory</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage accounts, roles, and wallets</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <button class="btn-primary w-full sm:w-auto shadow-lg shadow-gold-500/20 flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 border-none hover:from-purple-500 hover:to-indigo-500" @click="showSuperAccountModal = true">
                    <span class="text-xl">🌟</span> Create Super Account
                </button>
                <button class="btn-primary w-full sm:w-auto shadow-lg shadow-gold-500/20 flex items-center justify-center gap-2 px-6 py-3 rounded-xl" @click="showStaffModal = true">
                    <span class="text-xl">➕</span> Create Staff
                </button>
            </div>
        </div>
        
        <!-- User Cards Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 -mx-4 sm:mx-0">
            <template x-for="u in users" :key="u.id">
                <div class="group bg-white dark:bg-deep-800 border-y sm:border border-gray-100 dark:border-white/10 sm:rounded-3xl p-6 transition-all duration-300 hover:shadow-xl hover:shadow-gray-200/50 dark:hover:shadow-black/50 hover:border-gold-500/30 flex flex-col justify-between shadow-sm sm:shadow-none">
                    <!-- Card Header: Profile Info & Role Badge -->
                    <div class="flex justify-between items-start mb-6 pb-6 border-b border-gray-50 dark:border-white/5">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-white/5 dark:to-white/10 flex items-center justify-center text-xl font-bold text-gray-500 dark:text-gray-400 shrink-0" x-text="u.full_name.charAt(0)"></div>
                            <div>
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white truncate max-w-[150px] sm:max-w-[200px]" x-text="u.full_name" :title="u.full_name"></h3>
                                <p class="text-sm font-mono text-gray-500 dark:text-gray-400" x-text="u.mobile_number"></p>
                            </div>
                        </div>
                        <span class="px-3 py-1 text-[10px] font-black uppercase tracking-wider rounded-lg border"
                              :class="{
                                  'bg-gold-50 text-gold-600 border-gold-200 dark:bg-gold-500/10 dark:text-gold-400 dark:border-gold-500/20': u.role === 'super_admin',
                                  'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20': u.role === 'assistance',
                                  'bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20': u.role === 'user'
                              }"
                              x-text="u.role.replace('_', ' ')">
                        </span>
                    </div>

                    <!-- Card Body: Stats -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 dark:bg-white/5 p-4 rounded-2xl">
                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Wallet Balance</div>
                            <div class="font-black text-xl text-gold-500 truncate" :title="'₹' + parseFloat(u.wallet_balance).toFixed(2)" x-text="'₹' + parseFloat(u.wallet_balance).toFixed(2)"></div>
                        </div>
                        <div class="bg-gray-50 dark:bg-white/5 p-4 rounded-2xl">
                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">Joined Date</div>
                            <div class="font-bold text-sm text-gray-700 dark:text-gray-300" x-text="new Date(u.created_at).toLocaleDateString()"></div>
                        </div>
                    </div>

                    <!-- Card Footer: Actions -->
                    <div class="flex items-center gap-3">
                        <select class="flex-1 bg-white dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-gold-500/50 appearance-none outline-none transition-colors" 
                            x-model="u.status" 
                            @change="updateUserStatus(u.id, u.status)"
                            :disabled="u.role === 'super_admin' && u.id === '{{ Auth::id() }}'">
                            <option value="active">🟢 Active</option>
                            <option value="suspended">🟡 Suspended</option>
                            <option value="banned">🔴 Banned</option>
                        </select>

                        <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10 transition-colors" title="Manage Wallet" @click="openWalletModal(u)">
                            💼
                        </button>
                        
                        <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-red-50 text-red-500 hover:bg-red-100 dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500/20 transition-colors" x-show="u.role !== 'super_admin'" title="Delete User" @click="deleteUser(u.id)">
                            🗑️
                        </button>
                    </div>
                </div>
            </template>

            <!-- Empty State -->
            <div x-show="users.length === 0 && !loading" class="col-span-full py-16 text-center bg-white/40 dark:bg-deep-800/40 rounded-3xl border border-dashed border-gray-200 dark:border-white/20">
                <div class="text-4xl mb-3">👻</div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">No Users Found</h3>
                <p class="text-gray-500 dark:text-gray-400">The platform is currently quiet.</p>
            </div>
        </div>
    </div>

    <!-- Audit Logs Tab -->
    <div x-show="activeTab === 'logs'" class="animate-fade-in space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white/60 dark:bg-deep-800/60 backdrop-blur-md p-6 sm:rounded-3xl -mx-4 sm:mx-0 border-y sm:border border-gray-100 dark:border-white/10 shadow-sm sm:shadow-none">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">System Audit Logs</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Immutable chronological record of admin actions</p>
            </div>
            <div class="flex items-center gap-2 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 px-4 py-2 rounded-xl font-bold text-sm border border-indigo-200 dark:border-indigo-500/20">
                <span class="text-lg">📜</span>
                <span x-text="auditLogs.length + ' Events Recorded'"></span>
            </div>
        </div>

        <div class="bg-white dark:bg-deep-800 sm:rounded-3xl shadow-sm sm:shadow-none border-y sm:border border-gray-100 dark:border-white/10 p-6 md:p-8 -mx-4 sm:mx-0">
            <template x-if="loading">
                <div class="flex justify-center py-12">
                    <svg class="animate-spin h-10 w-10 text-gold-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                </div>
            </template>
            
            <template x-if="!loading && auditLogs.length === 0">
                <div class="py-12 flex flex-col items-center justify-center text-center">
                    <div class="text-5xl mb-4 opacity-50 grayscale">📭</div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">No Logs Found</h3>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">No admin actions have been recorded yet.</p>
                </div>
            </template>

            <template x-if="!loading && auditLogs.length > 0">
                <div class="relative pl-4 md:pl-0">
                    <!-- Vertical Timeline Line -->
                    <div class="absolute left-4 md:left-[120px] top-4 bottom-4 w-px bg-gray-200 dark:bg-white/10"></div>
                    
                    <div class="space-y-8">
                        <template x-for="log in auditLogs" :key="log.id">
                            <div class="relative flex flex-col md:flex-row items-start md:items-center gap-4 md:gap-8 group">
                                <!-- Timestamp (Left on Desktop, Top on Mobile) -->
                                <div class="w-full md:w-[120px] shrink-0 text-left md:text-right pt-1 md:pt-0 pl-12 md:pl-0 md:pr-8">
                                    <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider" x-text="new Date(log.created_at).toLocaleDateString()"></div>
                                    <div class="text-sm font-black text-gray-900 dark:text-white" x-text="new Date(log.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})"></div>
                                </div>
                                
                                <!-- Timeline Node -->
                                <div class="absolute left-0 md:left-[120px] top-1 md:top-1/2 md:-translate-y-1/2 w-8 h-8 -translate-x-1/2 rounded-full border-4 border-white dark:border-deep-800 bg-gray-100 dark:bg-deep-900 flex items-center justify-center shadow-sm z-10 transition-transform group-hover:scale-110"
                                    :class="{
                                        'bg-blue-500 border-blue-100 dark:border-blue-900/30 text-white': log.action.includes('update') || log.action.includes('edit'),
                                        'bg-green-500 border-green-100 dark:border-green-900/30 text-white': log.action.includes('create') || log.action.includes('add') || log.action === 'wallet_credit',
                                        'bg-red-500 border-red-100 dark:border-red-900/30 text-white': log.action.includes('delete') || log.action.includes('remove') || log.action === 'wallet_debit' || log.action.includes('suspend') || log.action.includes('ban'),
                                        'bg-purple-500 border-purple-100 dark:border-purple-900/30 text-white': log.action.includes('resolve') || log.action.includes('dispute'),
                                        'bg-gray-500 border-gray-100 dark:border-gray-900/30 text-white': !log.action.includes('update') && !log.action.includes('edit') && !log.action.includes('create') && !log.action.includes('add') && !log.action.includes('delete') && !log.action.includes('remove') && !log.action.includes('resolve') && !log.action.includes('dispute') && !log.action.includes('wallet') && !log.action.includes('suspend') && !log.action.includes('ban')
                                    }">
                                    <!-- Simple Dot or Icon based on action, using small text -->
                                    <span class="text-[10px]" x-show="log.action.includes('create') || log.action.includes('add')">➕</span>
                                    <span class="text-[10px]" x-show="log.action.includes('update') || log.action.includes('edit')">✏️</span>
                                    <span class="text-[10px]" x-show="log.action.includes('delete') || log.action.includes('remove') || log.action.includes('suspend') || log.action.includes('ban')">🚫</span>
                                    <span class="text-[10px]" x-show="log.action === 'wallet_credit'">💰</span>
                                    <span class="text-[10px]" x-show="log.action === 'wallet_debit'">💸</span>
                                    <span class="text-[10px]" x-show="log.action.includes('resolve') || log.action.includes('dispute')">⚖️</span>
                                </div>
                                
                                <!-- Content Card -->
                                <div class="flex-1 bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5 p-5 rounded-2xl transition-all group-hover:shadow-md group-hover:border-gray-200 dark:group-hover:border-white/10 ml-8 md:ml-0">
                                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider"
                                                :class="{
                                                    'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400': log.action.includes('update') || log.action.includes('edit'),
                                                    'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400': log.action.includes('create') || log.action.includes('add') || log.action === 'wallet_credit',
                                                    'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400': log.action.includes('delete') || log.action.includes('remove') || log.action === 'wallet_debit' || log.action.includes('suspend') || log.action.includes('ban'),
                                                    'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-400': log.action.includes('resolve') || log.action.includes('dispute'),
                                                    'bg-gray-200 text-gray-700 dark:bg-white/10 dark:text-gray-300': !log.action.includes('update') && !log.action.includes('edit') && !log.action.includes('create') && !log.action.includes('add') && !log.action.includes('delete') && !log.action.includes('remove') && !log.action.includes('resolve') && !log.action.includes('dispute') && !log.action.includes('wallet') && !log.action.includes('suspend') && !log.action.includes('ban')
                                                }"
                                                x-text="log.action.replace(/_/g, ' ')"></span>
                                        </div>
                                        <div class="text-xs font-mono text-gray-500 dark:text-gray-400 bg-white dark:bg-black/20 px-2 py-1 rounded flex items-center gap-1 border border-gray-100 dark:border-white/5" title="Admin ID">
                                            <span>👤</span> <span x-text="log.admin_id.substring(0, 10) + '...'"></span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <span class="font-medium text-gray-500 dark:text-gray-400">Target:</span>
                                        <span class="font-bold text-gold-600 dark:text-gold-400 uppercase tracking-wide text-xs" x-text="log.target_type"></span>
                                        <span class="text-gray-300 dark:text-gray-600">•</span>
                                        <span class="font-mono text-xs" x-text="log.target_id || 'Global'"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
    
    <!-- Support Queue Tab -->
    <div x-show="activeTab === 'assistance'" class="animate-fade-in space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white/60 dark:bg-deep-800/60 backdrop-blur-md p-6 sm:rounded-3xl -mx-4 sm:mx-0 border-y sm:border border-gray-100 dark:border-white/10 shadow-sm sm:shadow-none">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Support Queue</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Review dispute proofs and AI confidence scores</p>
            </div>
            <div class="flex items-center gap-2 bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 px-4 py-2 rounded-xl font-bold text-sm">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                <span x-text="disputes.length + ' Active Disputes'"></span>
            </div>
        </div>

        <template x-if="loading">
            <div class="flex justify-center py-12">
                <svg class="animate-spin h-10 w-10 text-gold-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </div>
        </template>

        <template x-if="!loading && disputes.length === 0">
            <div class="py-16 flex flex-col items-center justify-center text-center bg-white/40 dark:bg-deep-800/40 rounded-3xl border border-dashed border-gray-200 dark:border-white/20">
                <div class="text-6xl mb-4 animate-bounce">✨</div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">No active disputes in queue</h3>
                <p class="text-gray-500 dark:text-gray-400 mt-2">All clean and handled!</p>
            </div>
        </template>

        <template x-if="!loading && disputes.length > 0">
            <div class="grid grid-cols-1 gap-8 -mx-4 sm:mx-0">
                <template x-for="d in disputes" :key="d.id">
                    <div class="bg-white dark:bg-deep-800 border-y sm:border border-gray-100 dark:border-white/10 sm:rounded-3xl shadow-md sm:shadow-sm overflow-hidden flex flex-col">
                        
                        <!-- Header -->
                        <div class="flex justify-between items-center p-6 border-b border-gray-50 dark:border-white/5 bg-gray-50/50 dark:bg-white/5">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 rounded-full flex items-center justify-center text-xl font-bold">⚠️</div>
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900 dark:text-white">
                                        Dispute #<span x-text="d.id.slice(0, 8)" class="font-mono"></span>
                                    </h4>
                                    <p class="text-sm font-medium text-gold-600 dark:text-gold-400">Trade Value: ₹<span x-text="d.trade ? parseFloat(d.trade.amount).toFixed(2) : '0.00'"></span></p>
                                </div>
                            </div>
                            <span class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-wider bg-red-50 text-red-600 border border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20" x-text="d.status"></span>
                        </div>

                        <!-- Proofs Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-gray-100 dark:divide-white/5">
                            
                            <!-- Buyer Proof -->
                            <div class="p-6 md:p-8 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-6">
                                        <h5 class="text-lg font-black text-green-600 dark:text-green-400 uppercase tracking-wider flex items-center gap-2">🛒 Buyer Evidence</h5>
                                        <div class="flex items-center gap-2 bg-green-50 dark:bg-green-500/10 px-3 py-1.5 rounded-lg border border-green-200 dark:border-green-500/20" title="AI Confidence Score">
                                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">AI Score</span>
                                            <span class="font-black" :class="d.buyer_ai_score > 70 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'" x-text="d.buyer_ai_score ? d.buyer_ai_score + '%' : 'Pending'"></span>
                                        </div>
                                    </div>
                                    <div class="space-y-3 mb-8">
                                        <a :href="d.buyer_screen_recording_url" target="_blank" x-show="d.buyer_screen_recording_url" class="group flex items-center justify-between p-4 rounded-2xl bg-gray-50 hover:bg-green-50 dark:bg-white/5 dark:hover:bg-green-500/10 border border-transparent hover:border-green-200 dark:hover:border-green-500/30 transition-all">
                                            <span class="flex items-center gap-3 font-semibold text-gray-700 dark:text-gray-300 group-hover:text-green-600 dark:group-hover:text-green-400"><span class="text-2xl">📹</span> Screen Recording</span>
                                            <span class="text-gray-400 group-hover:text-green-500">↗</span>
                                        </a>
                                        <div x-show="!d.buyer_screen_recording_url" class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-white/5 opacity-60 grayscale"><span class="text-2xl">📹</span> <span class="text-gray-500 text-sm font-medium">No Video Uploaded</span></div>
                                        
                                        <a :href="d.buyer_bank_statement_url" target="_blank" x-show="d.buyer_bank_statement_url" class="group flex items-center justify-between p-4 rounded-2xl bg-gray-50 hover:bg-green-50 dark:bg-white/5 dark:hover:bg-green-500/10 border border-transparent hover:border-green-200 dark:hover:border-green-500/30 transition-all">
                                            <span class="flex items-center gap-3 font-semibold text-gray-700 dark:text-gray-300 group-hover:text-green-600 dark:group-hover:text-green-400"><span class="text-2xl">📄</span> Bank Statement</span>
                                            <span class="text-gray-400 group-hover:text-green-500">↗</span>
                                        </a>
                                        <div x-show="!d.buyer_bank_statement_url" class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-white/5 opacity-60 grayscale"><span class="text-2xl">📄</span> <span class="text-gray-500 text-sm font-medium">No Statement Uploaded</span></div>

                                        <a :href="d.buyer_screenshot_url" target="_blank" x-show="d.buyer_screenshot_url" class="group flex items-center justify-between p-4 rounded-2xl bg-gray-50 hover:bg-green-50 dark:bg-white/5 dark:hover:bg-green-500/10 border border-transparent hover:border-green-200 dark:hover:border-green-500/30 transition-all">
                                            <span class="flex items-center gap-3 font-semibold text-gray-700 dark:text-gray-300 group-hover:text-green-600 dark:group-hover:text-green-400"><span class="text-2xl">🖼️</span> Transaction Screenshot</span>
                                            <span class="text-gray-400 group-hover:text-green-500">↗</span>
                                        </a>
                                        <div x-show="!d.buyer_screenshot_url" class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-white/5 opacity-60 grayscale"><span class="text-2xl">🖼️</span> <span class="text-gray-500 text-sm font-medium">No Screenshot Uploaded</span></div>
                                    </div>

                                    <!-- Buyer's Original Payment Screenshot (from trade) -->
                                    <template x-if="d.buyer_upi_screenshot_url || (d.trade && d.trade.buyer_payment_screenshot_url)">
                                        <div class="mt-4 p-4 bg-green-50/50 dark:bg-green-500/5 rounded-2xl border border-green-100 dark:border-green-500/10">
                                            <p class="text-xs font-bold text-green-700 dark:text-green-400 uppercase tracking-wider mb-2">📱 Original Payment Proof</p>
                                            <a :href="d.buyer_upi_screenshot_url || d.trade.buyer_payment_screenshot_url" target="_blank" class="block rounded-xl overflow-hidden border border-green-200 dark:border-green-500/20 hover:opacity-90 transition-opacity">
                                                <img :src="d.buyer_upi_screenshot_url || d.trade.buyer_payment_screenshot_url" class="w-full max-h-48 object-contain bg-white dark:bg-black/20" alt="Original Payment Screenshot">
                                            </a>
                                            <template x-if="d.buyer_utr_number">
                                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">UTR: <span class="font-mono font-bold text-green-600 dark:text-green-400" x-text="d.buyer_utr_number"></span></p>
                                            </template>
                                        </div>
                                    </template>
                                    
                                    <!-- Buyer & Seller Info -->
                                    <template x-if="d.trade">
                                        <div class="mt-4 p-3 bg-gray-50 dark:bg-white/5 rounded-xl text-xs space-y-1">
                                            <div class="flex justify-between"><span class="text-gray-500">Buyer:</span> <span class="font-bold text-gray-900 dark:text-white" x-text="d.trade.buyer ? d.trade.buyer.full_name : 'Unknown'"></span></div>
                                            <div class="flex justify-between"><span class="text-gray-500">Seller:</span> <span class="font-bold text-gray-900 dark:text-white" x-text="d.trade.seller ? d.trade.seller.full_name : 'Unknown'"></span></div>
                                        </div>
                                    </template>
                                    
                                    <!-- AI Forensics Breakdown -->
                                    <template x-if="d.buyer_ai_breakdown">
                                        <div class="mt-4 p-4 bg-gray-100 dark:bg-black/30 rounded-xl border border-gray-200 dark:border-white/5">
                                            <strong class="block text-xs text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">AI Forensics</strong>
                                            <div class="space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
                                                <div class="flex justify-between"><span>Video:</span><strong x-text="(d.buyer_ai_breakdown.video_score || 0) + '%'"></strong></div>
                                                <div class="flex justify-between"><span>PDF:</span><strong x-text="(d.buyer_ai_breakdown.pdf_score || 0) + '%'"></strong></div>
                                                <div class="flex justify-between"><span>Image:</span><strong x-text="(d.buyer_ai_breakdown.image_score || 0) + '%'"></strong></div>
                                            </div>
                                            <template x-if="d.buyer_proof_analysis">
                                                <div class="mt-2 space-y-1 text-xs">
                                                    <template x-if="d.buyer_proof_analysis.video && d.buyer_proof_analysis.video.breakdown && d.buyer_proof_analysis.video.breakdown.fraud_flag">
                                                        <div class="text-red-500 font-medium" x-text="'⚠️ Video: ' + d.buyer_proof_analysis.video.breakdown.fraud_flag"></div>
                                                    </template>
                                                    <template x-if="d.buyer_proof_analysis.pdf && d.buyer_proof_analysis.pdf.breakdown && d.buyer_proof_analysis.pdf.breakdown.fraud_flag">
                                                        <div class="text-red-500 font-medium" x-text="'⚠️ PDF: ' + d.buyer_proof_analysis.pdf.breakdown.fraud_flag"></div>
                                                    </template>
                                                    <template x-if="d.buyer_proof_analysis.image && d.buyer_proof_analysis.image.breakdown && d.buyer_proof_analysis.image.breakdown.fraud_flag">
                                                        <div class="text-red-500 font-medium" x-text="'⚠️ Image: ' + d.buyer_proof_analysis.image.breakdown.fraud_flag"></div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                <button class="w-full py-4 rounded-2xl font-black text-lg bg-green-50 text-green-600 hover:bg-green-500 hover:text-white dark:bg-green-500/10 dark:text-green-400 dark:hover:bg-green-500 dark:hover:text-white border border-green-200 dark:border-green-500/30 transition-all shadow-lg shadow-green-500/0 hover:shadow-green-500/20" x-show="d.assigned_to && (d.assigned_to.id === '{{ Auth::id() }}' || '{{ Auth::user()->role }}' === 'super_admin')" @click="resolveDispute(d.id, 'buyer')">
                                    🏆 Resolve: Buyer Wins
                                </button>
                            </div>

                            <!-- Seller Proof -->
                            <div class="p-6 md:p-8 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-6">
                                        <h5 class="text-lg font-black text-red-600 dark:text-red-400 uppercase tracking-wider flex items-center gap-2">🏪 Seller Evidence</h5>
                                        <div class="flex items-center gap-2 bg-red-50 dark:bg-red-500/10 px-3 py-1.5 rounded-lg border border-red-200 dark:border-red-500/20" title="AI Confidence Score">
                                            <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase">AI Score</span>
                                            <span class="font-black" :class="d.seller_ai_score > 70 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'" x-text="d.seller_ai_score ? d.seller_ai_score + '%' : 'Pending'"></span>
                                        </div>
                                    </div>
                                    <div class="space-y-3 mb-8">
                                        <a :href="d.seller_screen_recording_url" target="_blank" x-show="d.seller_screen_recording_url" class="group flex items-center justify-between p-4 rounded-2xl bg-gray-50 hover:bg-red-50 dark:bg-white/5 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200 dark:hover:border-red-500/30 transition-all">
                                            <span class="flex items-center gap-3 font-semibold text-gray-700 dark:text-gray-300 group-hover:text-red-600 dark:group-hover:text-red-400"><span class="text-2xl">📹</span> Screen Recording</span>
                                            <span class="text-gray-400 group-hover:text-red-500">↗</span>
                                        </a>
                                        <div x-show="!d.seller_screen_recording_url" class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-white/5 opacity-60 grayscale"><span class="text-2xl">📹</span> <span class="text-gray-500 text-sm font-medium">No Video Uploaded</span></div>
                                        
                                        <a :href="d.seller_bank_statement_url" target="_blank" x-show="d.seller_bank_statement_url" class="group flex items-center justify-between p-4 rounded-2xl bg-gray-50 hover:bg-red-50 dark:bg-white/5 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200 dark:hover:border-red-500/30 transition-all">
                                            <span class="flex items-center gap-3 font-semibold text-gray-700 dark:text-gray-300 group-hover:text-red-600 dark:group-hover:text-red-400"><span class="text-2xl">📄</span> Bank Statement</span>
                                            <span class="text-gray-400 group-hover:text-red-500">↗</span>
                                        </a>
                                        <div x-show="!d.seller_bank_statement_url" class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-white/5 opacity-60 grayscale"><span class="text-2xl">📄</span> <span class="text-gray-500 text-sm font-medium">No Statement Uploaded</span></div>

                                        <a :href="d.seller_txn_screenshot_url" target="_blank" x-show="d.seller_txn_screenshot_url" class="group flex items-center justify-between p-4 rounded-2xl bg-gray-50 hover:bg-red-50 dark:bg-white/5 dark:hover:bg-red-500/10 border border-transparent hover:border-red-200 dark:hover:border-red-500/30 transition-all">
                                            <span class="flex items-center gap-3 font-semibold text-gray-700 dark:text-gray-300 group-hover:text-red-600 dark:group-hover:text-red-400"><span class="text-2xl">🖼️</span> Transaction Screenshot</span>
                                            <span class="text-gray-400 group-hover:text-red-500">↗</span>
                                        </a>
                                        <div x-show="!d.seller_txn_screenshot_url" class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-white/5 opacity-60 grayscale"><span class="text-2xl">🖼️</span> <span class="text-gray-500 text-sm font-medium">No Screenshot Uploaded</span></div>
                                    </div>
                                    
                                    <!-- AI Forensics Breakdown -->
                                    <template x-if="d.seller_ai_breakdown">
                                        <div class="mt-4 p-4 bg-gray-100 dark:bg-black/30 rounded-xl border border-gray-200 dark:border-white/5">
                                            <strong class="block text-xs text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">AI Forensics</strong>
                                            <div class="space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
                                                <div class="flex justify-between"><span>Video:</span><strong x-text="(d.seller_ai_breakdown.video_score || 0) + '%'"></strong></div>
                                                <div class="flex justify-between"><span>PDF:</span><strong x-text="(d.seller_ai_breakdown.pdf_score || 0) + '%'"></strong></div>
                                                <div class="flex justify-between"><span>Image:</span><strong x-text="(d.seller_ai_breakdown.image_score || 0) + '%'"></strong></div>
                                            </div>
                                            <template x-if="d.seller_proof_analysis">
                                                <div class="mt-2 space-y-1 text-xs">
                                                    <template x-if="d.seller_proof_analysis.video && d.seller_proof_analysis.video.breakdown && d.seller_proof_analysis.video.breakdown.fraud_flag">
                                                        <div class="text-red-500 font-medium" x-text="'⚠️ Video: ' + d.seller_proof_analysis.video.breakdown.fraud_flag"></div>
                                                    </template>
                                                    <template x-if="d.seller_proof_analysis.pdf && d.seller_proof_analysis.pdf.breakdown && d.seller_proof_analysis.pdf.breakdown.fraud_flag">
                                                        <div class="text-red-500 font-medium" x-text="'⚠️ PDF: ' + d.seller_proof_analysis.pdf.breakdown.fraud_flag"></div>
                                                    </template>
                                                    <template x-if="d.seller_proof_analysis.image && d.seller_proof_analysis.image.breakdown && d.seller_proof_analysis.image.breakdown.fraud_flag">
                                                        <div class="text-red-500 font-medium" x-text="'⚠️ Image: ' + d.seller_proof_analysis.image.breakdown.fraud_flag"></div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                <button class="w-full py-4 rounded-2xl font-black text-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500 dark:hover:text-white border border-red-200 dark:border-red-500/30 transition-all shadow-lg shadow-red-500/0 hover:shadow-red-500/20" x-show="d.assigned_to && (d.assigned_to.id === '{{ Auth::id() }}' || '{{ Auth::user()->role }}' === 'super_admin')" @click="resolveDispute(d.id, 'seller')">
                                    🏆 Resolve: Seller Wins
                                </button>
                            </div>

                        </div>
                        
                        <!-- Assignment & Claim Footer -->
                        <div class="bg-gray-50/80 dark:bg-black/20 p-4 border-t border-gray-100 dark:border-white/5 flex items-center justify-center">
                            <template x-if="!d.assigned_to">
                                <button class="w-full max-w-md py-3 rounded-xl font-bold text-lg bg-indigo-500 text-white hover:bg-indigo-600 transition-colors shadow-lg shadow-indigo-500/30" @click="claimDispute(d.id)">
                                    ✋ Claim Dispute
                                </button>
                            </template>
                            
                            <template x-if="d.assigned_to">
                                <div class="flex items-center gap-3 text-sm font-medium">
                                    <span class="text-gray-500 dark:text-gray-400">Assigned To:</span>
                                    <div class="flex items-center gap-2 bg-white dark:bg-deep-900 border border-gray-200 dark:border-white/10 px-4 py-2 rounded-xl">
                                        <div class="w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs font-bold" x-text="d.assigned_to.full_name.charAt(0)"></div>
                                        <span class="text-gray-900 dark:text-white font-bold" x-text="d.assigned_to.id === '{{ Auth::id() }}' ? 'You' : d.assigned_to.full_name"></span>
                                    </div>
                                </div>
                            </template>
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

    <!-- Create Super Account Modal -->
    <div x-show="showSuperAccountModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[100]" @click="showSuperAccountModal = false"></div>
    <div x-show="showSuperAccountModal" class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 z-[101] w-[90%] max-w-md bg-white dark:bg-deep-800 rounded-2xl p-6 shadow-2xl border border-gray-200 dark:border-white/10">
        <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100 dark:border-white/10">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Create Super Account</h3>
            <button class="text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="showSuperAccountModal = false">✕</button>
        </div>
        <form @submit.prevent="createSuperAccount">
            <div class="space-y-4">
                <div>
                    <label class="input-label">Full Name</label>
                    <input type="text" class="input-field" x-model="superAccountForm.full_name" required>
                </div>
                <div>
                    <label class="input-label">Mobile Number</label>
                    <input type="text" class="input-field" x-model="superAccountForm.mobile_number" required>
                </div>
                <div>
                    <label class="input-label">Password</label>
                    <input type="password" class="input-field" x-model="superAccountForm.password" required minlength="6">
                </div>
            </div>
            <button type="submit" class="btn-primary w-full mt-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 border-none" :disabled="loading">
                <span x-show="!loading">Create Super Account</span>
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
