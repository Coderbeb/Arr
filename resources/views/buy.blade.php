@extends('layouts.app')

@section('title', 'Buy Room')

@section('content')
<div class="animate-fade-in-up" x-data="{
    tradeAmounts: [],
    selectedAmountId: '',
    loading: false,
    initialLoad: true,
    message: '',
    error: '',

    async init() {
        await this.loadAmounts();
        this.initialLoad = false;
    },

    async loadAmounts() {
        const data = await ArrCache.fetch('/api/trade/amounts', 30000);
        this.tradeAmounts = data;
        if (this.tradeAmounts.length > 0) this.selectedAmountId = this.tradeAmounts[0].id;
    },

    async handleJoinQueue() {
        this.error = ''; this.message = ''; this.loading = true;
        try {
            const res = await fetch('/api/trade/buy/queue', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ amount_id: this.selectedAmountId })
            });
            const data = await res.json();
            if (!res.ok) {
                this.error = data.error || 'Failed to join queue.';
            } else {
                this.message = data.message + ' Track position in Dashboard.';
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
    <div class="mb-5 md:mb-8 text-center md:text-left">
        <h1 class="text-xl md:text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 dark:from-white dark:to-gray-300 bg-clip-text text-transparent flex items-center justify-center md:justify-start gap-2">
            <span>⬇️</span> Purchase Coins
        </h1>
        <p class="text-[10px] md:text-sm text-gray-500 dark:text-gray-400 mt-1">Fast P2P matching with secure escrow locking.</p>
    </div>

    @if(isset($settings) && $settings->buy_commission_percent > 0)
    <div class="bg-gray-900 dark:bg-black rounded-2xl p-4 md:p-6 mb-6 md:mb-8 flex items-center justify-between shadow-lg shadow-gray-900/20 relative overflow-hidden group">
        <div class="absolute -right-10 -top-10 w-32 h-32 bg-white/10 rounded-full blur-2xl group-hover:bg-white/20 transition-all"></div>
        <div class="text-left z-10 w-full flex items-center gap-4">
            <div class="hidden sm:flex text-4xl">💎</div>
            <div class="flex-1">
                <p class="text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-widest mb-0.5">Guaranteed Rewards</p>
                <h3 class="text-lg md:text-2xl font-extrabold text-white">Earn {{ (float)$settings->buy_commission_percent }}% Commission</h3>
                <p class="text-[10px] md:text-sm text-gray-300 mt-1 font-medium leading-tight">Credited instantly to your wallet upon seller approval.</p>
            </div>
            <div class="text-3xl sm:hidden animate-pulse">💸</div>
        </div>
    </div>
    @endif

    <!-- Alerts -->
    <template x-if="message">
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 p-3 rounded-lg mb-4 text-green-700 dark:text-green-400 text-sm font-medium" x-text="message"></div>
    </template>
    <template x-if="error">
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 p-3 rounded-lg mb-4 text-red-700 dark:text-red-400 text-sm font-medium" x-text="error"></div>
    </template>

    <div class="bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl border border-gray-200 dark:border-white/10 p-5 md:p-8 rounded-3xl max-w-2xl mx-auto shadow-sm">
        <!-- Skeleton Loader for Amounts -->
        <template x-if="initialLoad">
            <div class="animate-pulse flex flex-col gap-4">
                <div class="space-y-3">
                    <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div class="h-14 bg-gray-200 dark:bg-gray-700 rounded-xl"></div>
                        <div class="h-14 bg-gray-200 dark:bg-gray-700 rounded-xl"></div>
                        <div class="h-14 bg-gray-200 dark:bg-gray-700 rounded-xl"></div>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="!initialLoad" style="display: none;" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
            <!-- Amount Selection -->
            <div class="mb-6 md:mb-8">
                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span> Select Purchase Amount
                </label>
                <div class="flex flex-wrap gap-2 md:gap-3">
                    <template x-for="amt in tradeAmounts" :key="amt.id">
                        <label class="flex-1 min-w-[30%] relative overflow-hidden rounded-xl cursor-pointer transition-all border-2 text-center py-3 md:py-4 px-2 select-none"
                             :class="selectedAmountId === amt.id ? 'bg-gold-400/10 border-gold-400 shadow-md shadow-gold-500/10 scale-[1.02]' : 'bg-gray-50 dark:bg-black/30 border-gray-200 dark:border-white/5 hover:border-gold-400/50 hover:bg-gold-50/50 dark:hover:bg-gold-900/10'">
                            
                            <input type="radio" style="display: none;" :value="amt.id" x-model="selectedAmountId">
                                 
                            <div class="text-sm md:text-lg font-black transition-colors"
                                 :class="selectedAmountId === amt.id ? 'text-gold-600 dark:text-gold-400' : 'text-gray-900 dark:text-white'"
                                 x-text="'₹' + amt.amount"></div>
                                 
                            @if(isset($settings) && $settings->buy_commission_percent > 0)
                            <div class="text-[9px] md:text-[10px] font-bold mt-0.5"
                                 :class="selectedAmountId === amt.id ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'">
                                +₹<span x-text="(amt.amount * {{ $settings->buy_commission_percent }} / 100).toFixed(1)"></span> bonus
                            </div>
                            @endif
                        </label>
                    </template>
                </div>
            </div>

            <!-- Buy Form -->
            <div class="text-center pt-2 md:pt-4 border-t border-gray-100 dark:border-white/5">
                <p class="text-[10px] md:text-xs text-gray-500 dark:text-gray-400 mb-4 bg-gray-50 dark:bg-white/5 p-3 rounded-lg flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    You will be securely matched with a verified seller.
                </p>
                <button class="w-full py-3 md:py-4 text-white rounded-xl font-bold text-sm md:text-base transition-all transform active:scale-95 flex justify-center items-center gap-2 hover:opacity-90" style="background: linear-gradient(to right, #10b981, #047857); box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.3);" @click="handleJoinQueue" :disabled="loading">
                    <span x-show="!loading" class="tracking-wide">FIND SELLER NOW</span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
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
