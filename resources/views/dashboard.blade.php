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

    <!-- Mobile Compact Balance Card (Single Card, multi-value) -->
    <div class="md:hidden bg-gradient-to-br from-gray-900 to-black dark:from-white/10 dark:to-white/5 rounded-2xl p-4 mb-6 shadow-xl text-white relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-32 h-32 bg-gold-500/20 rounded-full blur-2xl"></div>
        <div class="flex justify-between items-end mb-4">
            <div>
                <div class="text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-1">Wallet Balance</div>
                <div class="text-3xl font-bold">₹{{ number_format(Auth::user()->wallet_balance, 2) }}</div>
            </div>
            <div class="text-right">
                <div class="text-[10px] font-semibold text-gray-300 uppercase tracking-wider mb-1 flex items-center justify-end gap-1"><span class="text-amber-400">🔒</span> Escrow</div>
                <div class="text-lg font-bold">₹{{ number_format(Auth::user()->escrow_balance, 2) }}</div>
            </div>
        </div>
        <div class="pt-3 border-t border-white/10 flex justify-between items-center text-xs">
            <span class="text-gray-300">Total Trades: {{ Auth::user()->total_trades }}</span>
            <a href="{{ route('trade') }}" class="text-gold-400 font-bold">Trade Now →</a>
        </div>
    </div>

    <!-- Desktop Balance Cards Grid -->
    <div class="hidden md:grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="glass-card relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-gold-400/20 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative p-6">
                <div class="text-4xl mb-3">👛</div>
                <div class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">₹{{ number_format(Auth::user()->wallet_balance, 2) }}</div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">Available Wallet Balance</div>
            </div>
        </div>
        <div class="glass-card relative overflow-hidden group text-center">
            <div class="absolute inset-0 bg-gradient-to-br from-amber-400/10 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative p-6">
                <div class="text-4xl mb-3">🔒</div>
                <div class="text-3xl font-bold text-amber-500 tracking-tight">₹{{ number_format(Auth::user()->escrow_balance, 2) }}</div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">Locked Escrow Balance</div>
            </div>
        </div>
        <div class="glass-card relative overflow-hidden group text-center">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-400/10 to-transparent opacity-50 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative p-6">
                <div class="text-4xl mb-3">✅</div>
                <div class="text-3xl font-bold text-blue-500 tracking-tight">{{ Auth::user()->total_trades }}</div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">Total Completed Trades</div>
            </div>
        </div>
    </div>

    <!-- LIVE ORDERS DASHBOARD -->
    <div x-data="liveOrders()" x-init="init()" class="relative">
        <div class="flex items-center justify-between mb-4 md:mb-6">
            <h2 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white">Active Activity</h2>
            <button @click="loadActiveState()" class="text-gold-500 dark:text-gold-400 font-bold text-sm md:text-base px-2 py-1" :disabled="loadingAction === 'refresh'">
                <span x-show="loadingAction !== 'refresh'">↻ Refresh</span>
                <span x-show="loadingAction === 'refresh'" class="flex items-center gap-1">
                    <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Updating...
                </span>
            </button>
        </div>

        <!-- Skeleton Loader -->
        <template x-if="initialLoad">
            <div class="space-y-3 md:space-y-4">
                <div class="bg-white dark:bg-black/20 rounded-xl p-4 md:p-6 animate-pulse flex flex-col gap-3">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                    <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
                </div>
                <div class="bg-white dark:bg-black/20 rounded-xl p-4 md:p-6 animate-pulse flex flex-col gap-3">
                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
                    <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
                </div>
            </div>
        </template>

        <template x-if="message">
            <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 p-3 rounded-lg mb-4 text-green-700 dark:text-green-400 text-sm font-medium" x-text="message"></div>
        </template>
        <template x-if="error">
            <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 p-3 rounded-lg mb-4 text-red-700 dark:text-red-400 text-sm font-medium" x-text="error"></div>
        </template>

        <!-- No Orders State -->
        <template x-if="!initialLoad && activeQueues.length === 0 && openOrders.length === 0 && trades.length === 0">
            <div class="bg-white dark:bg-black/20 border border-gray-100 dark:border-white/5 rounded-2xl p-8 md:p-12 flex flex-col items-center justify-center text-center">
                <div class="text-4xl md:text-6xl mb-3 opacity-50">📭</div>
                <h4 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white mb-1">No active activity</h4>
                <p class="text-sm md:text-base text-gray-500 dark:text-gray-400 mb-5">You don't have any pending requests.</p>
                <a href="{{ route('trade') }}" class="btn-primary py-2 px-6 rounded-xl text-sm md:text-base">Start Trading</a>
            </div>
        </template>

        <div class="flex flex-col gap-3 md:gap-4" x-show="!initialLoad" style="display: none;">
            
            <!-- ACTIVE TRADES (Matched) -->
            <template x-for="trade in trades" :key="trade.id">
                <div class="bg-white dark:bg-black/20 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden relative shadow-sm">
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gold-400"></div>
                    <div class="p-4 md:p-5">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <span class="inline-flex px-2 py-0.5 rounded text-[10px] md:text-xs font-bold uppercase tracking-wider bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400" x-text="trade.status.replace('_', ' ')"></span>
                                <div class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white mt-1">₹<span x-text="trade.amount"></span></div>
                                <div class="text-[10px] md:text-xs font-mono text-gray-500 dark:text-gray-400">ID: <span x-text="trade.id.slice(0, 8)"></span></div>
                            </div>
                            <span class="inline-flex px-2.5 py-1 rounded-md text-[10px] md:text-sm font-bold tracking-wider" 
                                  :class="trade.buyer_id === '{{ Auth::user()->id }}' ? 'bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400'">
                                <span x-text="trade.buyer_id === '{{ Auth::user()->id }}' ? 'BUYING' : 'SELLING'"></span>
                            </span>
                        </div>

                        <!-- BUYER ACTIONS -->
                        <template x-if="trade.buyer_id === '{{ Auth::user()->id }}'">
                            <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-3 md:p-4 border border-gray-100 dark:border-white/5">
                                <template x-if="trade.status === 'pending_payment'">
                                    <div>
                                        <p class="mb-3 text-xs md:text-sm text-gray-700 dark:text-gray-300">Seller UPI: <strong class="text-gold-500 font-mono" x-text="trade.order.seller_upi_id"></strong></p>
                                        <div class="flex gap-2 mb-4">
                                            <a :href="trade.deepLinks?.gpay" class="flex-1 text-center py-2 rounded-lg bg-gradient-to-r from-blue-500 to-green-500 text-white text-xs md:text-sm font-medium">GPay</a>
                                            <a :href="trade.deepLinks?.phonepe" class="flex-1 text-center py-2 rounded-lg bg-gradient-to-r from-purple-600 to-purple-800 text-white text-xs md:text-sm font-medium">PhonePe</a>
                                        </div>
                                        
                                        <div class="flex flex-col gap-2">
                                            <input type="text" class="w-full px-3 py-2 rounded-lg bg-white dark:bg-black/40 border border-gray-200 dark:border-white/10 text-xs md:text-sm uppercase font-mono" placeholder="UTR Number" x-model="utrNumbers[trade.id]">
                                            <label class="w-full text-center py-2 rounded-lg bg-gray-200 dark:bg-white/10 text-gray-700 dark:text-white text-xs md:text-sm cursor-pointer border border-transparent hover:border-gray-300 transition-colors">
                                                <span>📁 Attach Screenshot</span>
                                                <input type="file" accept="image/*" class="hidden" @change="screenshotFiles[trade.id] = $event.target.files[0]">
                                            </label>
                                        </div>
                                        <div x-show="screenshotFiles[trade.id]" class="text-[10px] font-semibold text-green-500 mt-1">Screenshot selected ✓</div>
                                        
                                        <div class="flex gap-2 mt-4">
                                            <button class="bg-gold-500 hover:bg-gold-600 text-white font-bold text-xs md:text-sm py-2 px-3 rounded-lg flex-1 transition-colors" @click="submitPayment(trade.id)" :disabled="loadingAction !== null">
                                                <span x-show="loadingAction !== 'submit-' + trade.id">Submit Proof</span>
                                                <span x-show="loadingAction === 'submit-' + trade.id">Processing...</span>
                                            </button>
                                            <button class="bg-red-100 hover:bg-red-200 text-red-700 dark:bg-red-500/20 dark:text-red-400 font-bold text-xs md:text-sm py-2 px-3 rounded-lg" @click="handleBuyerCancel(trade.id)" :disabled="loadingAction !== null">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="trade.status === 'payment_submitted'">
                                    <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 text-xs md:text-sm font-medium p-2">
                                        <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                        Proof submitted. Waiting for seller...
                                    </div>
                                </template>
                            </div>
                        </template>
                        <!-- SELLER ACTIONS -->
                        <template x-if="trade.seller_id === '{{ Auth::user()->id }}'">
                            <div class="bg-gray-50 dark:bg-white/5 rounded-xl p-3 md:p-4 border border-gray-100 dark:border-white/5">
                                <template x-if="trade.status === 'pending_payment'">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs md:text-sm text-gray-500">Waiting for buyer payment...</span>
                                        <button class="text-red-500 text-xs font-bold" @click="handleSellerCancelOrder(trade.order_id)">Cancel</button>
                                    </div>
                                </template>
                                <template x-if="trade.status === 'payment_submitted'">
                                    <div>
                                        <p class="mb-2 text-xs md:text-sm">Buyer UTR: <strong class="text-gold-500 font-mono" x-text="trade.utr_number"></strong></p>
                                        <template x-if="trade.buyer_payment_screenshot_url || trade.payment_screenshot_url">
                                            <a :href="(trade.buyer_payment_screenshot_url || trade.payment_screenshot_url)" target="_blank" class="block mb-4">
                                                <img :src="(trade.buyer_payment_screenshot_url || trade.payment_screenshot_url)" class="w-full max-h-32 object-contain rounded-lg bg-black/5" alt="Proof">
                                            </a>
                                        </template>
                                        <div class="flex gap-2">
                                            <button class="bg-green-500 hover:bg-green-600 text-white font-bold text-xs md:text-sm py-2 px-3 rounded-lg flex-1 transition-colors" @click="confirmReceipt(trade.id)" :disabled="loadingAction !== null">
                                                Confirm Receipt
                                            </button>
                                            <button class="bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400 font-bold text-xs md:text-sm py-2 px-3 rounded-lg transition-colors" @click="toggleRejectForm(trade.id)">
                                                Report
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- OPEN ORDERS (Unmatched Sells) -->
            <template x-for="order in openOrders" :key="order.id">
                <div class="bg-white dark:bg-black/20 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden relative shadow-sm">
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-blue-500"></div>
                    <div class="p-4 md:p-5 flex justify-between items-center gap-4">
                        <div>
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] md:text-xs font-bold uppercase tracking-wider bg-blue-100 text-blue-800 dark:bg-blue-500/20 dark:text-blue-400">OPEN SELL</span>
                            <div class="text-lg md:text-xl font-bold text-gray-900 dark:text-white mt-1">₹<span x-text="order.amount"></span></div>
                            <div class="text-[10px] md:text-xs text-gray-500">Waiting for match...</div>
                        </div>
                        <button class="text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 px-3 py-1.5 rounded-lg text-xs md:text-sm font-bold transition-colors" @click="handleSellerCancelOrder(order.id)" :disabled="loadingAction !== null">
                            Cancel
                        </button>
                    </div>
                </div>
            </template>

            <!-- BUYER QUEUES -->
            <template x-for="queue in activeQueues" :key="queue.amount_id">
                <div class="bg-white dark:bg-black/20 border border-gray-100 dark:border-white/5 rounded-2xl overflow-hidden relative shadow-sm">
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-green-500"></div>
                    <div class="p-4 md:p-5 flex justify-between items-center gap-4">
                        <div>
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] md:text-xs font-bold uppercase tracking-wider bg-green-100 text-green-800 dark:bg-green-500/20 dark:text-green-400">QUEUED BUY</span>
                            <div class="text-lg md:text-xl font-bold text-gray-900 dark:text-white mt-1">₹<span x-text="queue.amount"></span></div>
                            <div class="text-[10px] md:text-xs text-gray-500">Position: <strong class="text-gray-900 dark:text-white">#<span x-text="queue.position"></span></strong></div>
                        </div>
                        <button class="text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 px-3 py-1.5 rounded-lg text-xs md:text-sm font-bold transition-colors" @click="handleCancelQueue(queue.amount_id)" :disabled="loadingAction !== null">
                            Leave
                        </button>
                    </div>
                </div>
            </template>

        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function liveOrders() {
        return {
            trades: [],
            openOrders: [],
            activeQueues: [],
            loadingAction: null,
            initialLoad: true,
            message: '',
            error: '',
            utrNumbers: {},
            screenshotFiles: {},
            rejectFiles: {},
            appealFiles: {},
            showRejectForm: {},

            async init() {
                await this.loadActiveState();
                this.initialLoad = false;
                
                if (window.Echo) {
                    window.Echo.private(`user.{{ Auth::user()->id }}`)
                        .listen('.trade:update', (e) => {
                            this.loadActiveState();
                        });
                }
            },

            async loadActiveState() {
                if (!this.initialLoad) this.loadingAction = 'refresh';
                try {
                    const res = await fetch('/api/trade/my-active');
                    const data = await res.json();
                    this.trades = data.trades || [];
                    this.openOrders = data.openOrders || [];
                    this.activeQueues = data.activeQueues || [];
                } catch (e) {
                    console.error("Failed to load live orders");
                } finally {
                    this.loadingAction = null;
                }
            },

            async handleCancelQueue(amountId) {
                this.loadingAction = 'leave-queue-' + amountId;
                try {
                    const res = await fetch('/api/trade/cancel-queue', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ amount_id: amountId })
                    });
                    if (res.ok) {
                        this.message = 'Left the queue.';
                        await this.loadActiveState();
                    } else {
                        const data = await res.json();
                        this.error = data.error || 'Failed to cancel queue';
                    }
                } finally {
                    this.loadingAction = null;
                    setTimeout(() => this.message = this.error = '', 3000);
                }
            },

            async handleSellerCancelOrder(orderId) {
                if (!confirm('Cancel this sell order?')) return;
                this.loadingAction = 'seller-cancel-' + orderId;
                try {
                    const res = await fetch(`/api/trade/seller-cancel/${orderId}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                    const data = await res.json();
                    if (!res.ok) this.error = data.error || data.message;
                    else {
                        this.message = 'Order cancelled.';
                        await this.loadActiveState();
                        setTimeout(() => window.location.reload(), 1000);
                    }
                } finally {
                    this.loadingAction = null;
                    setTimeout(() => this.message = this.error = '', 3000);
                }
            },

            async handleBuyerCancel(tradeId) {
                if (!confirm('Cancel trade? Consecutive cancels result in a temporary block.')) return;
                this.loadingAction = 'cancel-' + tradeId;
                try {
                    const res = await fetch(`/api/trade/cancel/${tradeId}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                    const data = await res.json();
                    if (!res.ok) this.error = data.error || data.message;
                    else {
                        this.message = 'Trade cancelled.';
                        await this.loadActiveState();
                    }
                } finally {
                    this.loadingAction = null;
                    setTimeout(() => this.message = this.error = '', 3000);
                }
            },

            async submitPayment(tradeId) {
                const utr = this.utrNumbers[tradeId];
                const file = this.screenshotFiles[tradeId];
                if (!utr || !file) {
                    this.error = 'Please enter UTR and select a screenshot.';
                    setTimeout(() => this.error = '', 3000);
                    return;
                }
                this.error = ''; this.loadingAction = 'submit-' + tradeId;
                const formData = new FormData();
                formData.append('utr_number', utr);
                formData.append('screenshot', file);

                try {
                    const res = await fetch(`/api/trade/pay/${tradeId}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: formData
                    });
                    const data = await res.json();
                    if (!res.ok) this.error = data.error;
                    else {
                        this.message = 'Payment proof submitted!';
                        await this.loadActiveState();
                    }
                } finally {
                    this.loadingAction = null;
                    setTimeout(() => this.message = this.error = '', 3000);
                }
            },

            async confirmReceipt(tradeId) {
                this.loadingAction = 'confirm-' + tradeId;
                try {
                    const res = await fetch(`/api/trade/confirm/${tradeId}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                    });
                    const data = await res.json();
                    if (!res.ok) this.error = data.error;
                    else {
                        this.message = 'Coins released!';
                        await this.loadActiveState();
                        setTimeout(() => window.location.reload(), 1000);
                    }
                } finally {
                    this.loadingAction = null;
                    setTimeout(() => this.message = this.error = '', 3000);
                }
            },

            toggleRejectForm(tradeId) {
                this.showRejectForm[tradeId] = !this.showRejectForm[tradeId];
            }
        }
    }
</script>
@endsection
