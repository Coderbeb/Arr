@extends('layouts.app')

@section('title', 'Super Dashboard')

@section('content')
<div class="animate-fade-in-up" x-data="superDashboard()">
    
    <div class="mb-6 md:mb-8 text-center md:text-left">
        <h1 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-purple-600 to-indigo-600 dark:from-purple-400 dark:to-indigo-400 bg-clip-text text-transparent flex items-center justify-center md:justify-start gap-2">
            <span>🌟</span> Super Account Dashboard
        </h1>
        <p class="text-xs md:text-sm text-gray-500 dark:text-gray-400 mt-1">Manage invisible liquidity and direct sell orders.</p>
    </div>

    <!-- Alerts -->
    <template x-if="message">
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 p-3 rounded-lg mb-4 text-green-700 dark:text-green-400 text-sm font-medium" x-text="message"></div>
    </template>
    <template x-if="error">
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 p-3 rounded-lg mb-4 text-red-700 dark:text-red-400 text-sm font-medium" x-text="error"></div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Minting Card -->
        <div class="bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-purple-200 dark:border-purple-500/30 p-6 rounded-3xl shadow-lg shadow-purple-500/10">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">🪙 Generate Coins (Minting)</h3>
            <div class="flex items-end justify-between mb-6">
                <div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Available Wallet Balance</div>
                    <div class="text-4xl font-black text-gray-900 dark:text-white tracking-tight">₹<span x-text="stats.wallet_balance.toFixed(2)">{{ number_format(Auth::user()->wallet_balance, 2) }}</span></div>
                </div>
            </div>
            
            <form @submit.prevent="generateCoins" class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Amount to Generate</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 font-bold">₹</div>
                        <input type="number" step="0.01" min="1" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl pl-8 pr-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500/50 outline-none transition-all" x-model="mintAmount" required>
                    </div>
                </div>
                <button type="submit" class="w-full py-3 rounded-xl font-bold text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 shadow-lg shadow-purple-500/30 transition-all flex justify-center items-center gap-2" :disabled="loading">
                    <span x-show="!loading">Generate Coins</span>
                    <span x-show="loading" class="animate-spin text-xl">⏳</span>
                </button>
            </form>
        </div>

        <!-- Direct Sell Card -->
        <div class="bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 p-6 rounded-3xl shadow-sm">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">⬆️ Post Priority Sell Order</h3>
            
            <form @submit.prevent="handleSellOrder" class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Select Amount to Sell</label>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                        <template x-for="amt in tradeAmounts" :key="amt.id">
                            <div class="py-2.5 px-1 text-center rounded-xl font-bold cursor-pointer transition-all border-2 text-sm"
                                 :class="selectedAmountId === amt.id ? 'bg-purple-400/10 border-purple-400 text-purple-600 dark:text-purple-400' : 'bg-gray-50 dark:bg-white/5 border-transparent text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-white/20'"
                                 @click="selectedAmountId = amt.id"
                                 x-text="'₹' + amt.amount"></div>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">UPI App</label>
                        <select class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500/50 outline-none transition-all appearance-none" x-model="upiApp">
                            <option value="gpay">Google Pay</option>
                            <option value="phonepe">PhonePe</option>
                            <option value="paytm">Paytm</option>
                            <option value="bhim">BHIM UPI</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">UPI ID</label>
                        <input type="text" class="w-full bg-gray-50 dark:bg-deep-900 border border-gray-200 dark:border-white/10 rounded-xl px-4 py-3 text-gray-900 dark:text-white focus:ring-2 focus:ring-purple-500/50 outline-none transition-all" x-model="upiId" required>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 rounded-xl font-bold text-white bg-gray-900 dark:bg-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors shadow-lg shadow-black/20 dark:shadow-white/20 flex justify-center items-center gap-2" :disabled="loading">
                    <span x-show="!loading">Post Sell Order</span>
                    <span x-show="loading" class="animate-spin text-xl">⏳</span>
                </button>
            </form>
        </div>
    </div>

    <!-- LIVE ORDERS DASHBOARD -->
    @include('components.live-orders')
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('superDashboard', () => ({
            stats: {
                wallet_balance: {{ Auth::user()->wallet_balance }},
            },
            mintAmount: '',
            loading: false,
            message: '',
            error: '',
            
            tradeAmounts: [],
            selectedAmountId: '',
            upiId: '{{ Auth::user()->upi_id }}',
            upiApp: '{{ Auth::user()->upi_app ?? "gpay" }}',

            async init() {
                const res = await fetch('/api/trade/amounts');
                this.tradeAmounts = await res.json();
                if (this.tradeAmounts.length > 0) this.selectedAmountId = this.tradeAmounts[0].id;
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
                        const balRes = await fetch('/api/wallet/balance', { headers: { 'Accept': 'application/json' } });
                        if (balRes.ok) {
                            const balData = await balRes.json();
                            this.stats.wallet_balance = balData.wallet_balance;
                            window.dispatchEvent(new CustomEvent('wallet-updated', { detail: balData.wallet_balance }));
                        }
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
    });
</script>
@endpush
