@extends('layouts.app')

@section('title', 'Wallet & Ledger')

@section('content')
<div class="animate-fade-in-up" x-data="{
    balance: 0,
    escrow: 0,
    transactions: [],
    loading: true,
    initialLoad: true,

    async init() {
        await this.loadWalletData();
        this.initialLoad = false;

        // Smart polling every 5 seconds (visibility-aware)
        const self = this;
        ArrPolling.start('wallet-data', async () => {
            await self.loadWalletData();
        }, 5000, false);
    },

    async loadWalletData() {
        this.loading = true;
        try {
            const balData = await ArrCache.fetch('/api/wallet/balance', 3000);
            this.balance = balData.wallet_balance;
            this.escrow = balData.escrow_balance;
            // Keep navbar in sync
            window.dispatchEvent(new CustomEvent('wallet-updated', { detail: balData.wallet_balance }));

            const txData = await ArrCache.fetch('/api/wallet/transactions', 5000);
            this.transactions = txData;
        } finally {
            this.loading = false;
        }
    },

    isCredit(tx) {
        const creditTypes = ['trade_received', 'commission', 'bonus', 'admin_credit', 'super_mint', 'escrow_refund'];
        if (creditTypes.includes(tx.type)) return true;
        if (tx.type === 'escrow_release' && parseFloat(tx.balance_after) > parseFloat(tx.balance_before)) return true;
        return false;
    }
}">
    <!-- Header -->
    <div class="mb-6 md:mb-8 text-center md:text-left">
        <h1 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 dark:from-white dark:to-gray-300 bg-clip-text text-transparent flex items-center justify-center md:justify-start gap-2">
            <span>💼</span> Wallet
        </h1>
        <p class="text-xs md:text-sm text-gray-500 dark:text-gray-400 mt-1">Dual-balance tracking & audit history.</p>
    </div>

    <!-- Mobile Compact Balance Card -->
    <div class="md:hidden bg-gradient-to-br from-green-500 to-green-700 dark:from-green-600 dark:to-green-900 rounded-[20px] p-5 mb-6 text-white shadow-lg relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
        
        <div class="mb-4">
            <div class="text-[10px] font-bold text-green-100 uppercase tracking-wider mb-1">Available Balance</div>
            <div class="text-3xl font-bold tracking-tight">₹<span x-text="balance.toFixed(2)"></span></div>
        </div>
        
        <div class="pt-3 border-t border-white/20 flex justify-between items-center">
            <div>
                <div class="text-[10px] font-bold text-green-100 uppercase tracking-wider mb-0.5">Locked Escrow</div>
                <div class="text-base font-bold text-amber-300">₹<span x-text="escrow.toFixed(2)"></span></div>
            </div>
            <div class="text-2xl opacity-50">🔒</div>
        </div>
    </div>

    <!-- Desktop Balance Cards (Hidden on Mobile) -->
    <div class="hidden md:grid grid-cols-2 gap-6 mb-8">
        <div class="glass-card relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-green-400/10 to-transparent opacity-50 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
            <div class="relative p-8 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Available Balance</div>
                    <div class="text-4xl font-bold text-gray-900 dark:text-white tracking-tight">₹<span x-text="balance.toFixed(2)"></span></div>
                </div>
                <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-500/20 flex items-center justify-center text-green-600 dark:text-green-400 text-3xl shrink-0">
                    👛
                </div>
            </div>
        </div>
        
        <div class="glass-card relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-amber-400/10 to-transparent opacity-50 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
            <div class="relative p-8 flex items-center justify-between">
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Locked Escrow</div>
                    <div class="text-4xl font-bold text-amber-500 tracking-tight">₹<span x-text="escrow.toFixed(2)"></span></div>
                </div>
                <div class="w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-500/20 flex items-center justify-center text-amber-600 dark:text-amber-400 text-3xl shrink-0">
                    🔒
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Ledger -->
    <div class="bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 rounded-2xl md:p-6 overflow-hidden shadow-sm">
        <div class="flex items-center justify-between p-4 border-b border-gray-100 dark:border-white/10">
            <h3 class="text-base md:text-lg font-bold text-gray-900 dark:text-white">Recent Transactions</h3>
            <button @click="loadWalletData()" class="p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5 rounded-lg transition-colors" :class="{ 'animate-spin text-gold-500': loading && !initialLoad }">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </div>

        <template x-if="initialLoad || (loading && transactions.length === 0)">
            <div class="p-4 space-y-4">
                <template x-for="i in 5">
                    <div class="animate-pulse flex items-center justify-between py-2">
                        <div class="flex flex-col gap-2 w-1/2">
                            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                        </div>
                        <div class="h-5 bg-gray-200 dark:bg-gray-700 rounded w-16"></div>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="!loading && transactions.length === 0">
            <div class="text-center py-12 px-4">
                <div class="w-24 h-24 mx-auto mb-4 bg-gray-50 dark:bg-white/5 rounded-full flex items-center justify-center">
                    <span class="text-4xl opacity-50">📭</span>
                </div>
                <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-1">No Transactions Yet</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">When you complete trades or receive commissions, they will appear here.</p>
            </div>
        </template>

        <template x-if="!initialLoad && transactions.length > 0">
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                <template x-for="tx in transactions" :key="tx.id">
                    <div class="p-4 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                        <div class="flex items-center gap-3 md:gap-4 overflow-hidden">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                                 :class="isCredit(tx) ? 'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400'">
                                <span class="text-lg leading-none" x-text="isCredit(tx) ? '↓' : '↑'"></span>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-sm text-gray-900 dark:text-white truncate" x-text="tx.description_en"></div>
                                <div class="text-[10px] md:text-xs text-gray-500 dark:text-gray-400 mt-0.5" x-text="new Date(tx.created_at).toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })"></div>
                            </div>
                        </div>
                        <div class="text-right shrink-0 pl-2">
                            <div class="font-bold text-sm md:text-base" 
                                 :class="isCredit(tx) ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white'">
                                <span x-text="isCredit(tx) ? '+' : '-'"></span>₹<span x-text="tx.amount"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
@endsection
