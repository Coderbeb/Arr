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

    <!-- Dynamic Commission Branding Banner (Merged) -->
    @if(isset($settings) && ($settings->buy_commission_percent > 0 || $settings->sell_commission_percent > 0))
    <div class="bg-white dark:bg-deep-800 rounded-3xl p-5 md:p-6 mb-6 md:mb-8 shadow-sm dark:shadow-none relative overflow-hidden group border border-gray-200 dark:border-white/10">
        <div class="absolute -right-20 -top-20 w-48 h-48 bg-gold-400/10 dark:bg-gold-500/10 rounded-full blur-3xl group-hover:bg-gold-400/20 transition-all"></div>
        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-5 md:gap-6">
            <div class="w-full md:w-auto text-center md:text-left flex-1">
                <p class="text-[10px] md:text-xs font-bold text-gold-500 dark:text-gold-400 uppercase tracking-widest mb-1">Trade & Earn</p>
                <h3 class="text-xl md:text-2xl font-extrabold text-gray-900 dark:text-white mb-1">Guaranteed Commission</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 leading-tight">Rewards are credited instantly to your wallet upon successful trades.</p>
            </div>
            <div class="flex w-full md:w-auto gap-3 justify-center">
                @if($settings->buy_commission_percent > 0)
                <div class="flex-1 md:flex-none min-w-[100px] bg-gray-50 dark:bg-black/30 rounded-2xl p-3 border border-gray-100 dark:border-white/5 flex flex-col items-center justify-center text-center transform transition hover:scale-105">
                    <span class="text-2xl mb-1">💸</span>
                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-0.5">Buy</span>
                    <span class="text-lg md:text-xl font-black" style="color: #10b981;">+{{ (float)$settings->buy_commission_percent }}%</span>
                </div>
                @endif
                @if($settings->sell_commission_percent > 0)
                <div class="flex-1 md:flex-none min-w-[100px] bg-gray-50 dark:bg-black/30 rounded-2xl p-3 border border-gray-100 dark:border-white/5 flex flex-col items-center justify-center text-center transform transition hover:scale-105">
                    <span class="text-2xl mb-1">🚀</span>
                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-0.5">Sell</span>
                    <span class="text-lg md:text-xl font-black" style="color: #3b82f6;">+{{ (float)$settings->sell_commission_percent }}%</span>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

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
                    <div class="text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-1 flex items-center gap-1">
                        Wallet Balance
                        <button @click="showBalance = !showBalance" class="opacity-70 hover:opacity-100 transition-opacity">
                            <span x-show="showBalance">👁️</span>
                            <span x-show="!showBalance">🙈</span>
                        </button>
                    </div>
                    <div class="text-3xl font-bold">
                        ₹<span x-show="showBalance" x-text="stats.wallet_balance.toFixed(2)">{{ number_format(Auth::user()->wallet_balance, 2) }}</span>
                        <span x-show="!showBalance">****</span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-1 flex items-center justify-end gap-1"><span class="text-amber-400">🔒</span> Escrow</div>
                    <div class="text-lg font-bold">
                        ₹<span x-show="showBalance" x-text="stats.escrow_balance.toFixed(2)">{{ number_format(Auth::user()->escrow_balance, 2) }}</span>
                        <span x-show="!showBalance">****</span>
                    </div>
                </div>
            </div>
            <div class="pt-3 border-t border-white/10 flex justify-between items-center text-xs">
                <span class="text-gray-300">Total Trades: <span x-text="stats.total_trades">{{ Auth::user()->total_trades }}</span></span>
                <a href="{{ route('buy') }}" class="text-gold-400 font-bold flex items-center gap-1 hover:gap-2 transition-all">
                    Trade Now 
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </a>
            </div>
        </div>

        <!-- Desktop Balance Cards Grid -->
        <div class="hidden md:grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="glass-card relative overflow-hidden group">
                <div class="absolute inset-0 bg-gradient-to-br from-gold-400/20 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative p-6">
                    <div class="flex justify-between items-start mb-3">
                        <div class="text-4xl">👛</div>
                        <button @click="showBalance = !showBalance" class="opacity-50 hover:opacity-100 transition-opacity text-xl">
                            <span x-show="showBalance">👁️</span>
                            <span x-show="!showBalance">🙈</span>
                        </button>
                    </div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">
                        ₹<span x-show="showBalance" x-text="stats.wallet_balance.toFixed(2)">{{ number_format(Auth::user()->wallet_balance, 2) }}</span>
                        <span x-show="!showBalance">****</span>
                    </div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">Available Wallet Balance</div>
                </div>
            </div>
            <div class="glass-card relative overflow-hidden group text-center">
                <div class="absolute inset-0 bg-gradient-to-br from-amber-400/10 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative p-6">
                    <div class="text-4xl mb-3">🔒</div>
                    <div class="text-3xl font-bold text-amber-500 tracking-tight">
                        ₹<span x-show="showBalance" x-text="stats.escrow_balance.toFixed(2)">{{ number_format(Auth::user()->escrow_balance, 2) }}</span>
                        <span x-show="!showBalance">****</span>
                    </div>
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
            showBalance: localStorage.getItem('hideBalance') !== 'true',
            stats: {
                wallet_balance: {{ Auth::user()->wallet_balance }},
                escrow_balance: {{ Auth::user()->escrow_balance }},
                total_trades: {{ Auth::user()->total_trades }}
            },
            
            async init() {
                this.$watch('showBalance', val => {
                    localStorage.setItem('hideBalance', !val);
                });
                
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
