@extends('layouts.app')

@section('title', 'Sell Room')

@section('content')
<div class="animate-fade-in-up" x-data="{
    tradeAmounts: [],
    selectedAmountId: '',
    upiId: '{{ Auth::user()->upi_id }}',
    upiApp: '{{ Auth::user()->upi_app ?? "gpay" }}',
    loading: false,
    initialLoad: true,
    message: '',
    error: '',

    async init() {
        await this.loadAmounts();
        this.initialLoad = false;
    },

    async loadAmounts() {
        const res = await fetch('/api/trade/amounts');
        this.tradeAmounts = await res.json();
        if (this.tradeAmounts.length > 0) this.selectedAmountId = this.tradeAmounts[0].id;
    },

    async handleSellOrder() {
        this.error = ''; this.message = ''; this.loading = true;
        try {
            const res = await fetch('/api/trade/sell', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    amount_id: this.selectedAmountId,
                    upi_id: this.upiId,
                    upi_app: this.upiApp
                })
            });
            const data = await res.json();
            if (!res.ok) this.error = data.error || 'Failed to post order';
            else {
                this.message = 'Sell order created! Track it in Dashboard.';
                window.dispatchEvent(new Event('trade-updated'));
            }
        } catch (e) {
            this.error = 'Network Error.';
        } finally {
            this.loading = false;
            setTimeout(() => this.message = this.error = '', 4000);
        }
    }
}">
    <div class="mb-6 md:mb-8 text-center md:text-left">
        <h1 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 dark:from-white dark:to-gray-300 bg-clip-text text-transparent flex items-center justify-center md:justify-start gap-2">
            <span>⬆️</span> Sell Room
        </h1>
        <p class="text-xs md:text-sm text-gray-500 dark:text-gray-400 mt-1">Fast P2P matching with escrow locking.</p>
    </div>

    <!-- Alerts -->
    <template x-if="message">
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 p-3 rounded-lg mb-4 text-green-700 dark:text-green-400 text-sm font-medium" x-text="message"></div>
    </template>
    <template x-if="error">
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 p-3 rounded-lg mb-4 text-red-700 dark:text-red-400 text-sm font-medium" x-text="error"></div>
    </template>

    <div class="bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 p-4 md:p-8 rounded-2xl max-w-2xl mx-auto shadow-sm">
        <!-- Skeleton Loader for Amounts -->
        <template x-if="initialLoad">
            <div class="animate-pulse flex flex-col gap-4">
                <div class="space-y-2">
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                        <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                        <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                        <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-lg"></div>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="!initialLoad" style="display: none;">
            <!-- Amount Selection -->
            <div class="mb-6">
                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">Select Amount</label>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                    <template x-for="amt in tradeAmounts" :key="amt.id">
                        <div class="py-2.5 px-1 text-center rounded-xl font-bold cursor-pointer transition-all border-2 text-sm md:text-base"
                             :class="selectedAmountId === amt.id ? 'bg-gold-400/10 border-gold-400 text-gold-600 dark:text-gold-400' : 'bg-gray-50 dark:bg-white/5 border-transparent text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-white/20'"
                             @click="selectedAmountId = amt.id"
                             x-text="'₹' + amt.amount"></div>
                    </template>
                </div>
            </div>

            <!-- Sell Form -->
            <div class="animate-fade-in">
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">Your UPI ID</label>
                    <input type="text" class="w-full px-4 py-3 bg-gray-50 dark:bg-black/40 border border-gray-200 dark:border-white/10 rounded-xl font-mono text-sm focus:ring-2 focus:ring-gold-400 outline-none transition-all" x-model="upiId" placeholder="name@upi" required>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">Preferred UPI App</label>
                    <select class="w-full px-4 py-3 bg-gray-50 dark:bg-black/40 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-gold-400 outline-none transition-all appearance-none" x-model="upiApp">
                        <option value="gpay">Google Pay</option>
                        <option value="phonepe">PhonePe</option>
                        <option value="paytm">Paytm</option>
                        <option value="bhim">BHIM UPI</option>
                    </select>
                </div>

                <button class="w-full py-3.5 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-xl font-bold shadow-lg shadow-blue-500/30 transition-transform active:scale-95 flex justify-center items-center gap-2 text-sm md:text-base" @click="handleSellOrder" :disabled="loading">
                    <span x-show="!loading">Sell Coins</span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Processing...
                    </span>
                </button>
            </div>
        </div>
    </div>

    <!-- Live Orders in Trade Room too -->
    <div class="mt-8">
        @include('components.live-orders')
    </div>
</div>
@endsection
