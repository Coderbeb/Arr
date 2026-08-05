<!-- LIVE ORDERS DASHBOARD COMPONENT -->
<div x-data="liveOrders()" x-init="init()" class="relative mt-8">
    <div class="flex items-center justify-between mb-4 md:mb-6">
        <h2 class="text-lg md:text-xl font-bold text-gray-900 dark:text-white">Active Activity</h2>
        <button @click="loadActiveState()" class="text-gold-500 dark:text-gold-400 font-bold text-sm md:text-base px-2 py-1" :disabled="loadingAction === 'refresh'" :class="loadingAction === 'refresh' ? 'opacity-50 cursor-not-allowed' : ''">
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
                                        <button class="bg-gold-500 hover:bg-gold-600 text-white font-bold text-xs md:text-sm py-2 px-3 rounded-lg flex-1 transition-colors" @click="submitPayment(trade.id)" :disabled="loadingAction !== null" :class="loadingAction !== null ? 'opacity-50 pointer-events-none' : ''">
                                            <span x-show="loadingAction !== 'submit-' + trade.id">Submit Proof</span>
                                            <span x-show="loadingAction === 'submit-' + trade.id">Processing...</span>
                                        </button>
                                        <button class="bg-red-100 hover:bg-red-200 text-red-700 dark:bg-red-500/20 dark:text-red-400 font-bold text-xs md:text-sm py-2 px-3 rounded-lg" @click="handleBuyerCancel(trade.id)" :disabled="loadingAction !== null" :class="loadingAction !== null ? 'opacity-50 pointer-events-none' : ''">
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
                            <template x-if="trade.status === 'seller_rejected'">
                                <div class="bg-red-50 dark:bg-red-500/10 p-3 rounded-lg border border-red-200 dark:border-red-500/20">
                                    <p class="text-red-700 dark:text-red-400 text-xs md:text-sm font-bold mb-2">⚠️ Seller rejected your payment!</p>
                                    <p class="text-xs text-gray-700 dark:text-gray-300 mb-3">You must appeal within 30 minutes by submitting 3 proofs.</p>
                                    <div class="flex flex-col gap-2">
                                        <label class="w-full text-center py-2 rounded-lg bg-white dark:bg-black/40 border border-gray-200 dark:border-white/10 text-xs cursor-pointer">
                                            <span>🎥 Screen Recording (Opening Bank App)</span>
                                            <input type="file" accept="video/*" class="hidden" @change="appealFiles[trade.id] = {...(appealFiles[trade.id] || {}), video: $event.target.files[0]}">
                                        </label>
                                        <div x-show="appealFiles[trade.id]?.video" class="text-[10px] text-green-500 text-center">Video attached ✓</div>

                                        <label class="w-full text-center py-2 rounded-lg bg-white dark:bg-black/40 border border-gray-200 dark:border-white/10 text-xs cursor-pointer">
                                            <span>📄 Bank Statement (PDF)</span>
                                            <input type="file" accept=".pdf" class="hidden" @change="appealFiles[trade.id] = {...(appealFiles[trade.id] || {}), pdf: $event.target.files[0]}">
                                        </label>
                                        <div x-show="appealFiles[trade.id]?.pdf" class="text-[10px] text-green-500 text-center">PDF attached ✓</div>

                                        <label class="w-full text-center py-2 rounded-lg bg-white dark:bg-black/40 border border-gray-200 dark:border-white/10 text-xs cursor-pointer">
                                            <span>📸 Transaction Screenshot</span>
                                            <input type="file" accept="image/*" class="hidden" @change="appealFiles[trade.id] = {...(appealFiles[trade.id] || {}), img: $event.target.files[0]}">
                                        </label>
                                        <div x-show="appealFiles[trade.id]?.img" class="text-[10px] text-green-500 text-center">Image attached ✓</div>
                                        
                                        <button class="bg-red-500 hover:bg-red-600 text-white font-bold text-xs py-2 mt-2 rounded-lg transition-colors" @click="submitAppeal(trade.id)" :disabled="loadingAction !== null" :class="loadingAction !== null ? 'opacity-50 pointer-events-none' : ''">
                                            <span x-show="loadingAction !== 'appeal-' + trade.id">Submit Appeal to Support</span>
                                            <span x-show="loadingAction === 'appeal-' + trade.id">Uploading...</span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="trade.status === 'disputed'">
                                <div class="flex items-center gap-2 text-red-600 dark:text-red-400 text-xs md:text-sm font-bold p-2">
                                    Disputed. Support team is reviewing the proofs.
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
                                    <button class="text-red-500 hover:text-red-600 text-xs font-bold" @click="handleSellerCancelOrder(trade.order_id)" :disabled="loadingAction !== null" :class="loadingAction !== null ? 'opacity-50 pointer-events-none' : ''">Cancel</button>
                                </div>
                            </template>
                            <template x-if="trade.status === 'payment_submitted'">
                                <div>
                                    <p class="mb-2 text-xs md:text-sm">Buyer UTR: <strong class="text-gold-500 font-mono" x-text="trade.utr_number"></strong></p>
                                    <template x-if="trade.buyer_payment_screenshot_url || trade.payment_screenshot_url">
                                        <a :href="(trade.buyer_payment_screenshot_url || trade.payment_screenshot_url)" target="_blank" class="block mb-4 hover:opacity-90 transition-opacity">
                                            <img :src="(trade.buyer_payment_screenshot_url || trade.payment_screenshot_url)" class="w-full max-h-32 object-contain rounded-lg bg-black/5" alt="Proof">
                                            <div class="text-[10px] text-gray-500 mt-1 text-center">Click image to view full size</div>
                                        </a>
                                    </template>
                                    <div class="flex gap-2 mb-3">
                                        <button class="bg-green-500 hover:bg-green-600 text-white font-bold text-xs md:text-sm py-2 px-3 rounded-lg flex-1 transition-colors" @click="confirmReceipt(trade.id)" :disabled="loadingAction !== null" :class="loadingAction !== null ? 'opacity-50 pointer-events-none' : ''">
                                            Confirm Receipt
                                        </button>
                                        <button class="bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-500/20 dark:text-red-400 dark:hover:bg-red-500/30 font-bold text-xs md:text-sm py-2 px-3 rounded-lg transition-colors" @click="toggleRejectForm(trade.id)" :disabled="loadingAction !== null" :class="loadingAction !== null ? 'opacity-50 pointer-events-none' : ''">
                                            Report
                                        </button>
                                    </div>
                                    
                                    <!-- REJECT FORM -->
                                    <div x-show="showRejectForm[trade.id]" x-transition class="mt-4 p-3 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-500/20 rounded-xl">
                                        <p class="text-xs text-red-700 dark:text-red-400 mb-3 font-medium">To reject this payment, you MUST upload 3 proofs. Failure to provide correct proofs may result in losing the dispute.</p>
                                        
                                        <div class="flex flex-col gap-2">
                                            <label class="w-full text-center py-2 rounded-lg bg-white dark:bg-black/40 border border-red-200 dark:border-red-500/20 text-xs cursor-pointer text-gray-700 dark:text-gray-300">
                                                <span>🎥 Screen Recording (Opening Bank App)</span>
                                                <input type="file" accept="video/*" class="hidden" @change="rejectFiles[trade.id] = {...(rejectFiles[trade.id] || {}), video: $event.target.files[0]}">
                                            </label>
                                            <div x-show="rejectFiles[trade.id]?.video" class="text-[10px] text-green-500 text-center">Video attached ✓</div>

                                            <label class="w-full text-center py-2 rounded-lg bg-white dark:bg-black/40 border border-red-200 dark:border-red-500/20 text-xs cursor-pointer text-gray-700 dark:text-gray-300">
                                                <span>📄 Bank Statement (PDF)</span>
                                                <input type="file" accept=".pdf" class="hidden" @change="rejectFiles[trade.id] = {...(rejectFiles[trade.id] || {}), pdf: $event.target.files[0]}">
                                            </label>
                                            <div x-show="rejectFiles[trade.id]?.pdf" class="text-[10px] text-green-500 text-center">PDF attached ✓</div>

                                            <label class="w-full text-center py-2 rounded-lg bg-white dark:bg-black/40 border border-red-200 dark:border-red-500/20 text-xs cursor-pointer text-gray-700 dark:text-gray-300">
                                                <span>📸 Transaction Screenshot</span>
                                                <input type="file" accept="image/*" class="hidden" @change="rejectFiles[trade.id] = {...(rejectFiles[trade.id] || {}), img: $event.target.files[0]}">
                                            </label>
                                            <div x-show="rejectFiles[trade.id]?.img" class="text-[10px] text-green-500 text-center">Image attached ✓</div>
                                            
                                            <button class="bg-red-600 hover:bg-red-700 text-white font-bold text-xs py-2.5 mt-2 rounded-lg transition-colors" @click="submitReject(trade.id)" :disabled="loadingAction !== null" :class="loadingAction !== null ? 'opacity-50 pointer-events-none' : ''">
                                                <span x-show="loadingAction !== 'reject-' + trade.id">Submit Rejection & Open Dispute</span>
                                                <span x-show="loadingAction === 'reject-' + trade.id" class="flex items-center justify-center gap-2">
                                                    <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                                    Uploading Proofs...
                                                </span>
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </template>
                            <template x-if="trade.status === 'seller_rejected'">
                                <div class="flex items-center gap-2 text-amber-600 dark:text-amber-400 text-xs md:text-sm font-bold p-2">
                                    Dispute raised. Waiting for buyer to appeal with proofs...
                                </div>
                            </template>
                            <template x-if="trade.status === 'disputed'">
                                <div class="flex items-center gap-2 text-red-600 dark:text-red-400 text-xs md:text-sm font-bold p-2">
                                    Disputed. Support team is reviewing the proofs.
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
                    <button class="text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 px-3 py-1.5 rounded-lg text-xs md:text-sm font-bold transition-colors" @click="handleSellerCancelOrder(order.id)" :disabled="loadingAction !== null" :class="loadingAction !== null ? 'opacity-50 pointer-events-none' : ''">
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
                    <button class="text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 px-3 py-1.5 rounded-lg text-xs md:text-sm font-bold transition-colors" @click="handleCancelQueue(queue.amount_id)" :disabled="loadingAction !== null" :class="loadingAction !== null ? 'opacity-50 pointer-events-none' : ''">
                        Leave
                    </button>
                </div>
            </div>
        </template>

    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('liveOrders', () => ({
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
                
                // Refresh when a new trade is created locally (e.g. from trade room)
                window.addEventListener('trade-updated', () => {
                    this.loadActiveState();
                });

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
                this.showRejectForm = { ...this.showRejectForm, [tradeId]: !this.showRejectForm[tradeId] };
            },

            async submitReject(tradeId) {
                const files = this.rejectFiles[tradeId];
                if (!files || !files.video || !files.pdf || !files.img) {
                    this.error = 'Please upload all 3 required proofs to reject the payment.';
                    setTimeout(() => this.error = '', 4000);
                    return;
                }

                this.error = ''; 
                this.loadingAction = 'reject-' + tradeId;
                const formData = new FormData();
                formData.append('screen_recording', files.video);
                formData.append('bank_statement', files.pdf);
                formData.append('txn_screenshot', files.img);

                try {
                    const res = await fetch(`/api/trade/reject/${tradeId}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: formData
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        if (data.errors) {
                            this.error = Object.values(data.errors).flat().join(', ');
                        } else {
                            this.error = data.error || 'Failed to reject payment';
                        }
                    } else {
                        this.message = 'Payment rejected and dispute raised.';
                        await this.loadActiveState();
                    }
                } catch (e) {
                    this.error = 'Network error while uploading proofs.';
                } finally {
                    this.loadingAction = null;
                    setTimeout(() => { this.message = ''; this.error = ''; }, 4000);
                }
            },

            async submitAppeal(tradeId) {
                const files = this.appealFiles[tradeId];
                if (!files || !files.video || !files.pdf || !files.img) {
                    this.error = 'Please upload all 3 required proofs to appeal.';
                    setTimeout(() => this.error = '', 4000);
                    return;
                }

                this.error = ''; 
                this.loadingAction = 'appeal-' + tradeId;
                const formData = new FormData();
                formData.append('screen_recording', files.video);
                formData.append('bank_statement', files.pdf);
                formData.append('screenshot', files.img);

                try {
                    const res = await fetch(`/api/dispute/appeal/${tradeId}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: formData
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        if (data.errors) {
                            this.error = Object.values(data.errors).flat().join(', ');
                        } else {
                            this.error = data.error || 'Failed to submit appeal';
                        }
                    } else {
                        this.message = 'Appeal submitted successfully.';
                        await this.loadActiveState();
                    }
                } catch (e) {
                    this.error = 'Network error while uploading proofs.';
                } finally {
                    this.loadingAction = null;
                    setTimeout(() => { this.message = ''; this.error = ''; }, 4000);
                }
            }
        }));
    });
</script>
@endpush
