@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="animate-fade-in-up">
    
    <!-- Mobile User Welcome (Hidden on MD) -->
    <div class="md:hidden mb-4">
        <h1 class="text-xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 dark:from-white dark:to-gray-300 bg-clip-text text-transparent">
            Welcome, {{ explode(' ', Auth::user()->full_name)[0] }} 👋
        </h1>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Active Trader</p>
    </div>

    <!-- Desktop User Welcome -->
    <div class="hidden md:flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 dark:from-white dark:to-gray-300 bg-clip-text text-transparent">
                Welcome, {{ Auth::user()->full_name }} 👋
            </h1>
            <p class="text-gray-500 dark:text-gray-400 mt-1">P2P Fiat Trading & Dual-Balance Escrow System</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400 border border-green-200 dark:border-green-500/20">
            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> Active Trader
        </span>
    </div>

    @if(empty(Auth::user()->upi_id))
    <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 p-3 md:p-4 rounded-xl mb-6 md:mb-8 flex items-start sm:items-center gap-2 md:gap-3 text-amber-800 dark:text-amber-400">
        <span class="text-lg md:text-2xl shrink-0">⚠️</span>
        <span class="text-xs md:text-sm"><strong>Action Required:</strong> Set your UPI ID before trading.</span>
    </div>
    @endif

    <div x-data="dashboardStats">
        <!-- Mobile Compact Balance Card (Single Card, multi-value) -->
        <div class="md:hidden bg-gradient-to-br from-gray-900 to-black dark:from-white/10 dark:to-white/5 rounded-2xl p-4 mb-6 shadow-xl text-white relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-32 h-32 bg-gold-500/20 rounded-full blur-2xl"></div>
            <div class="flex justify-between items-end mb-4">
                <div>
                    <div class="text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-1">Wallet Balance</div>
                    <div class="text-3xl font-bold">₹<span x-text="stats.wallet_balance.toFixed(2)">{{ number_format(Auth::user()->wallet_balance, 2) }}</span></div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-1 flex items-center justify-end gap-1"><span class="text-amber-400">🔒</span> Escrow</div>
                    <div class="text-lg font-bold">₹<span x-text="stats.escrow_balance.toFixed(2)">{{ number_format(Auth::user()->escrow_balance, 2) }}</span></div>
                </div>
            </div>
            <div class="pt-3 border-t border-white/10 flex justify-between items-center text-xs">
                <span class="text-gray-300">Total Trades: <span x-text="stats.total_trades">{{ Auth::user()->total_trades }}</span></span>
                <a href="{{ route('buy') }}" class="text-gold-400 font-bold">Trade Now →</a>
            </div>
        </div>

        <!-- Desktop Balance Cards Grid -->
        <div class="hidden md:grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass-card relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-br from-gold-400/20 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative p-6">
                    <div class="text-4xl mb-3">👛</div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">₹<span x-text="stats.wallet_balance.toFixed(2)">{{ number_format(Auth::user()->wallet_balance, 2) }}</span></div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">Available Wallet Balance</div>
                </div>
            </div>
            <div class="glass-card relative overflow-hidden group text-center">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-400/10 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative p-6">
                    <div class="text-4xl mb-3">🔒</div>
                    <div class="text-3xl font-bold text-amber-500 tracking-tight">₹<span x-text="stats.escrow_balance.toFixed(2)">{{ number_format(Auth::user()->escrow_balance, 2) }}</span></div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">Locked Escrow Balance</div>
                </div>
            </div>
            <div class="glass-card relative overflow-hidden group text-center">
                <div class="absolute inset-0 bg-gradient-to-br from-blue-400/10 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative p-6">
                    <div class="text-4xl mb-3">✅</div>
                    <div class="text-3xl font-bold text-blue-500 tracking-tight"><span x-text="stats.total_trades">{{ Auth::user()->total_trades }}</span></div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">Total Completed Trades</div>
                </div>
            </div>
        </div>
    </div>

    <!-- LIVE ORDERS DASHBOARD -->
    @include('components.live-orders')
</div>
@endsection

@push('scripts')
<script>
    ArrRegister('dashboardStats', () => ({
            stats: {
                wallet_balance: {{ Auth::user()->wallet_balance }},
                escrow_balance: {{ Auth::user()->escrow_balance }},
                total_trades: {{ Auth::user()->total_trades }}
            },
            
            async init() {
                this.loadStats();
                
                // Smart polling every 3 seconds (visibility-aware)
                const self = this;
                ArrPolling.start('dashboard-stats', async () => {
                    await self.loadStats();
                }, 3000, false);

                // Listen to local actions (instant feedback for same-page actions)
                window.addEventListener('trade-updated', () => {
                    ArrCache.invalidate('/api/wallet/balance');
                    this.loadStats();
                });
            },

            async loadStats() {
                try {
                    const data = await ArrCache.fetch('/api/wallet/balance', 3000);
                    this.stats.wallet_balance = data.wallet_balance;
                    this.stats.escrow_balance = data.escrow_balance;
                    this.stats.total_trades = data.total_trades;
                    // Also update navbar balance
                    window.dispatchEvent(new CustomEvent('wallet-updated', { detail: data.wallet_balance }));
                } catch (e) {
                    console.error("Failed to load dashboard stats", e);
                }
            }
    }));
</script>
@endpush
