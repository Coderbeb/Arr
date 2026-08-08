@extends('layouts.app')

@section('title', 'Super Dashboard')

@section('content')
<div class="animate-fade-in-up" x-data="superDashboard()">
    
    <div class="mb-4 md:mb-6 text-center md:text-left">
        <h1 class="text-xl md:text-2xl font-bold bg-gradient-to-r from-purple-600 to-indigo-600 dark:from-purple-400 dark:to-indigo-400 bg-clip-text text-transparent flex items-center justify-center md:justify-start gap-2">
            <span>🌟</span> Super Account Dashboard
        </h1>
        <p class="text-[10px] md:text-xs text-gray-500 dark:text-gray-400 mt-1">Manage invisible liquidity and direct sell orders.</p>
    </div>

    <!-- Mobile-optimized Tabs Navigation -->
    <div class="flex overflow-x-auto hide-scrollbar gap-2 mb-6 bg-gray-100 dark:bg-black/30 p-1.5 rounded-xl border border-gray-200 dark:border-white/10">
        <button @click="tab = 'minting'" class="flex-1 py-2 px-3 rounded-lg text-xs font-bold whitespace-nowrap transition-all" :class="tab === 'minting' ? 'bg-white dark:bg-deep-800 text-purple-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-white/5'">🪙 Minting</button>
        <button @click="tab = 'trading'" class="flex-1 py-2 px-3 rounded-lg text-xs font-bold whitespace-nowrap transition-all" :class="tab === 'trading' ? 'bg-white dark:bg-deep-800 text-blue-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-white/5'">🔥 Sell & Live</button>
        <button @click="tab = 'ledger'" class="flex-1 py-2 px-3 rounded-lg text-xs font-bold whitespace-nowrap transition-all" :class="tab === 'ledger' ? 'bg-white dark:bg-deep-800 text-green-600 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-white/5'">🧾 Ledger</button>
    </div>

    <!-- Alerts -->
    <template x-if="message">
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 p-3 rounded-lg mb-4 text-green-700 dark:text-green-400 text-sm font-medium" x-text="message"></div>
    </template>
    <template x-if="error">
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 p-3 rounded-lg mb-4 text-red-700 dark:text-red-400 text-sm font-medium" x-text="error"></div>
    </template>

    <!-- Tabs Content Container -->
    <div class="relative">
        
        <!-- Tab 1: Minting -->
        <div x-show="tab === 'minting'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
            <!-- Analytics Overview -->
            <div class="grid grid-cols-2 gap-3 mb-5">
                <div class="bg-gradient-to-br from-indigo-500/10 to-purple-500/10 border border-indigo-500/20 p-4 rounded-2xl">
                    <div class="text-xs font-bold text-indigo-600 dark:text-indigo-400 mb-1">Minted</div>
                    <div class="text-lg md:text-xl font-black text-gray-900 dark:text-white truncate" x-text="'₹' + (analytics.total_minted || 0).toFixed(2)">₹0.00</div>
                </div>
                <div class="bg-gradient-to-br from-green-500/10 to-teal-500/10 border border-green-500/20 p-4 rounded-2xl">
                    <div class="text-xs font-bold text-green-600 dark:text-green-400 mb-1">Sold (Fiat)</div>
                    <div class="text-lg md:text-xl font-black text-gray-900 dark:text-white truncate" x-text="'₹' + (analytics.total_sold || 0).toFixed(2)">₹0.00</div>
                </div>
            </div>

            <!-- Minting Card -->
            <div class="max-w-2xl bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-purple-200 dark:border-purple-500/30 p-5 rounded-2xl shadow-sm mb-6">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-1.5">🪙 Generate Coins</h3>
                <div class="flex items-end justify-between mb-4">
                    <div>
                        <div class="text-[10px] md:text-xs font-medium text-gray-500 dark:text-gray-400 mb-0.5">Wallet Balance</div>
                        <div class="text-2xl md:text-3xl font-black text-gray-900 dark:text-white tracking-tight">₹<span x-text="stats.wallet_balance.toFixed(2)">{{ number_format(Auth::user()->wallet_balance, 2) }}</span></div>
                    </div>
                </div>
                
                <form @submit.prevent="generateCoins" class="space-y-3">
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">Amount</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 font-bold">₹</div>
                            <input type="number" step="0.01" min="1" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl pl-8 pr-3 py-2 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500/50 outline-none transition-all" x-model="mintAmount" required>
                        </div>
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl font-bold text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 shadow-lg shadow-purple-500/20 transition-all flex justify-center items-center gap-2 text-sm" :disabled="loading">
                        <span x-show="!loading">Generate Coins</span>
                        <span x-show="loading" class="animate-spin text-lg">⏳</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Tab 2: Trade & Live -->
        <div x-show="tab === 'trading'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
            
            <!-- Direct Sell Card -->
            <div class="bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 p-5 md:p-6 rounded-3xl shadow-sm mb-6">
                <h3 class="text-sm md:text-base font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-1.5">⬆️ Priority Sell Order</h3>
                
                <form @submit.prevent="handleSellOrder" class="space-y-5">
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">Select Amount</label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="amt in tradeAmounts" :key="amt.id">
                                <label class="flex-1 min-w-[30%] relative overflow-hidden rounded-xl cursor-pointer transition-all border-2 text-center py-3 px-2 select-none"
                                     :class="selectedAmountId === amt.id ? 'bg-purple-400/10 border-purple-400 text-purple-600 dark:text-purple-400 shadow-sm scale-[1.02]' : 'bg-gray-50 dark:bg-white/5 border-transparent text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-white/20'">
                                    <input type="radio" class="sr-only" :value="amt.id" x-model="selectedAmountId">
                                    <div class="text-sm md:text-base font-black transition-colors" x-text="'₹' + amt.amount"></div>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] md:text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">UPI App</label>
                            <select class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500/50 outline-none transition-all appearance-none" x-model="upiApp">
                                <option value="gpay">Google Pay</option>
                                <option value="phonepe">PhonePe</option>
                                <option value="paytm">Paytm</option>
                                <option value="bhim">BHIM UPI</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] md:text-xs font-bold text-gray-700 dark:text-gray-300 mb-2 uppercase tracking-wider">UPI ID</label>
                            <input type="text" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-sm text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500/50 outline-none transition-all" x-model="upiId" placeholder="name@upi" required>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3.5 rounded-xl font-bold text-white bg-gray-900 dark:bg-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors shadow-lg shadow-black/10 dark:shadow-white/10 flex justify-center items-center gap-2 text-sm" :disabled="loading">
                        <span x-show="!loading">Post Sell Order</span>
                        <span x-show="loading" class="animate-spin text-lg">⏳</span>
                    </button>
                </form>
            </div>

            <!-- Live Orders injected via Component -->
            @include('components.live-orders')
        </div>

        <!-- Tab 3: Ledger (Transaction History) -->
        <div x-show="tab === 'ledger'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
            <div class="bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 rounded-2xl shadow-sm overflow-hidden mt-4">
                <div class="p-4 border-b border-gray-200 dark:border-white/10 flex justify-between items-center bg-gray-50 dark:bg-white/5">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">Transaction History</h3>
                    <button class="text-[10px] md:text-xs font-bold text-purple-600 dark:text-purple-400" @click="ArrCache.invalidate('/api/super-account/analytics'); const a = await ArrCache.fetch('/api/super-account/analytics', 0); analytics = a;">↻ Refresh</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs md:text-sm">
                        <thead class="bg-gray-100 dark:bg-black/40 text-gray-500 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2 font-bold tracking-wider">Type</th>
                                <th class="px-4 py-2 font-bold tracking-wider text-right">Amount</th>
                                <th class="px-4 py-2 font-bold tracking-wider hidden sm:table-cell">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            <template x-for="tx in analytics.transactions" :key="tx.id">
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center shrink-0" 
                                                :class="isCredit(tx) ? 'bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-400' : 'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400'">
                                                <span x-show="isCredit(tx)" class="text-xs">↓</span>
                                                <span x-show="!isCredit(tx)" class="text-xs">↑</span>
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-900 dark:text-white text-xs truncate w-24 sm:w-auto" x-text="tx.type.replace('_', ' ').toUpperCase()"></div>
                                                <div class="text-[10px] text-gray-500 sm:hidden" x-text="new Date(tx.created_at).toLocaleDateString(undefined, {month:'short', day:'numeric'})"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold" 
                                        :class="isCredit(tx) ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-white'">
                                        <span x-show="isCredit(tx)">+</span><span x-show="!isCredit(tx)">-</span>₹<span x-text="parseFloat(tx.amount).toFixed(2)"></span>
                                    </td>
                                    <td class="px-4 py-3 text-[10px] text-gray-500 hidden sm:table-cell" x-text="new Date(tx.created_at).toLocaleString()"></td>
                                </tr>
                            </template>
                            <template x-if="!analytics.transactions || analytics.transactions.length === 0">
                                <tr>
                                    <td colspan="3" class="px-4 py-12 text-center">
                                        <div class="text-4xl mb-3 opacity-50">📭</div>
                                        <div class="text-gray-500 dark:text-gray-400 text-sm font-medium">No transactions found.</div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
    ArrRegister('superDashboard', () => ({
        tab: 'trading',
        stats: {
                wallet_balance: {{ Auth::user()->wallet_balance }},
                escrow_balance: {{ Auth::user()->escrow_balance }},
            },
            mintAmount: '',
            loading: false,
            message: '',
            error: '',
            
            tradeAmounts: [],
            selectedAmountId: '',
            upiId: '{{ Auth::user()->upi_id }}',
            upiApp: '{{ Auth::user()->upi_app ?? "gpay" }}',
            
            analytics: {
                total_minted: 0,
                total_sold: 0,
                transactions: []
            },

            isCredit(tx) {
                return ['deposit', 'trade_received', 'bonus', 'commission', 'super_mint', 'escrow_refund'].includes(tx.type);
            },

            async init() {
                const amounts = await ArrCache.fetch('/api/trade/amounts', 30000);
                this.tradeAmounts = amounts;
                if (this.tradeAmounts.length > 0) this.selectedAmountId = this.tradeAmounts[0].id;

                // Smart polling every 5 seconds (visibility-aware)
                const self = this;
                ArrPolling.start('super-balance', async () => {
                    try {
                        const data = await ArrCache.fetch('/api/wallet/balance', 3000);
                        self.stats.wallet_balance = data.wallet_balance;
                        self.stats.escrow_balance = data.escrow_balance;
                        window.dispatchEvent(new CustomEvent('wallet-updated', { detail: data.wallet_balance }));
                        
                        const analyticsData = await ArrCache.fetch('/api/super-account/analytics', 5000);
                        self.analytics = analyticsData;
                    } catch (e) {}
                }, 5000, false);
            },

            async generateCoins() {
                this.error = ''; this.message = ''; this.loading = true;
                try {
                    const res = await fetch('/api/super-account/generate-coins', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ amount: this.mintAmount })
                    });
                    const data = await res.json();
                    if (!res.ok) this.error = data.error || 'Failed to generate coins';
                    else {
                        this.message = data.message;
                        this.stats.wallet_balance = data.new_balance;
                        window.dispatchEvent(new CustomEvent('wallet-updated', { detail: data.new_balance }));
                        this.mintAmount = '';
                        
                        // Force refresh analytics
                        ArrCache.invalidate('/api/super-account/analytics');
                        try {
                            this.analytics = await ArrCache.fetch('/api/super-account/analytics', 0);
                        } catch (e) {}
                    }
                } catch (e) {
                    this.error = 'Network Error.';
                } finally {
                    this.loading = false;
                    setTimeout(() => this.message = this.error = '', 4000);
                }
            },

            async handleSellOrder() {
                this.error = ''; this.message = ''; this.loading = true;
                try {
                    const res = await fetch('/api/trade/sell', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            amount_id: this.selectedAmountId,
                            upi_id: this.upiId,
                            upi_app: this.upiApp
                        })
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = data.error || 'Failed to post order';
                    } else {
                        this.message = 'Priority Sell order created!';
                        // Update wallet balance to reflect escrow locking
                        ArrCache.invalidate('/api/wallet/balance');
                        try {
                            const balData = await ArrCache.fetch('/api/wallet/balance', 0);
                            this.stats.wallet_balance = balData.wallet_balance;
                            window.dispatchEvent(new CustomEvent('wallet-updated', { detail: balData.wallet_balance }));
                            
                            // Force refresh analytics
                            ArrCache.invalidate('/api/super-account/analytics');
                            this.analytics = await ArrCache.fetch('/api/super-account/analytics', 0);
                        } catch (e) {}
                        window.dispatchEvent(new Event('trade-updated'));
                    }
                } catch (e) {
                    this.error = 'Network Error.';
                } finally {
                    this.loading = false;
                    setTimeout(() => this.message = this.error = '', 4000);
                }
            }
    }));
</script>
@endpush
