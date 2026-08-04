@extends('layouts.app')

@section('title', 'Dashboard — Arr Wallet')

@section('content')
<div class="fade-in">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
        <div>
            <h1>Welcome, {{ Auth::user()->full_name }} 👋</h1>
            <p>P2P Fiat Trading & Dual-Balance Escrow System</p>
        </div>
        <span class="badge badge-success">Active Trader</span>
    </div>

    @if(empty(Auth::user()->upi_id))
    <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.5rem; color: var(--warning); display: flex; align-items: center; gap: 0.5rem;">
        <span style="font-size: 1.2rem;">⚠️</span>
        <span><strong>Action Required:</strong> Please set your UPI ID before your next trade to receive payments smoothly.</span>
    </div>
    @endif

    <!-- Balance Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="balance-card">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">👛</div>
            <div class="balance-amount">₹{{ number_format(Auth::user()->wallet_balance, 2) }}</div>
            <div class="balance-label">Available Wallet Balance</div>
        </div>

        <div class="card" style="text-align: center;">
            <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🔒</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--warning);">₹{{ number_format(Auth::user()->escrow_balance, 2) }}</div>
            <div class="balance-label">Locked Escrow Balance</div>
        </div>

        <div class="card" style="text-align: center;">
            <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">✅</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--info);">{{ Auth::user()->total_trades }}</div>
            <div class="balance-label">Total Completed Trades</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card card-glow" style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(245,166,35,0.05));">
        <div>
            <h3 style="color: var(--text-primary); margin-bottom: 0.25rem;">Start P2P Trade</h3>
            <p>Create a sell order or match with active buyers in real-time.</p>
        </div>
        <a href="{{ route('trade') }}" class="btn btn-primary btn-lg">
            ⚡ Open Trade Room
        </a>
    </div>

    <!-- LIVE ORDERS DASHBOARD -->
    <div x-data="liveOrders()" x-init="init()">
        <div class="section-title" style="margin-bottom: 1.5rem; font-size: 1.2rem; font-weight: 700; color: var(--text-primary); display: flex; justify-content: space-between; align-items: center;">
            <span>Live Orders</span>
            <button @click="loadActiveState()" class="btn btn-secondary btn-sm" :disabled="loadingAction === 'refresh'">
                <span x-show="loadingAction !== 'refresh'">↻ Refresh</span>
                <span x-show="loadingAction === 'refresh'" style="display: flex; align-items: center; gap: 0.5rem;">
                    <svg class="spinner-svg" viewBox="0 0 50 50" style="width: 16px; height: 16px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
                    Refreshing...
                </span>
            </button>
        </div>

        <template x-if="message">
            <div class="toast toast-success" style="position: static; transform: none; margin-bottom: 1rem; width: 100%;" x-text="message"></div>
        </template>
        <template x-if="error">
            <div class="toast toast-error" style="position: static; transform: none; margin-bottom: 1rem; width: 100%;" x-text="error"></div>
        </template>

        <!-- No Orders State -->
        <template x-if="!loading && activeQueues.length === 0 && openOrders.length === 0 && trades.length === 0">
            <div style="text-align: center; padding: 2rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border);">
                <div style="font-size: 2rem; margin-bottom: 1rem; color: var(--text-muted);">📭</div>
                <h4 style="color: var(--text-muted);">No active orders</h4>
                <p style="margin-bottom: 1rem;">You don't have any pending requests.</p>
                <a href="{{ route('trade') }}" class="btn btn-primary btn-sm">Create New Order</a>
            </div>
        </template>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            
            <!-- ACTIVE TRADES (Matched) -->
            <template x-for="trade in trades" :key="trade.id">
                <div class="card card-glow" style="border-left: 4px solid var(--gold);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                        <div>
                            <span class="badge badge-warning" x-text="trade.status"></span>
                            <div style="font-size: 1.5rem; font-weight: 700; margin-top: 0.5rem;">₹<span x-text="trade.amount"></span></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Trade ID: <span x-text="trade.id.slice(0, 8)"></span></div>
                        </div>
                        <div style="text-align: right;">
                            <span class="badge" :class="trade.buyer_id === '{{ Auth::user()->id }}' ? 'badge-success' : 'badge-primary'">
                                <span x-text="trade.buyer_id === '{{ Auth::user()->id }}' ? 'BUYING' : 'SELLING'"></span>
                            </span>
                        </div>
                    </div>

                    <!-- BUYER ACTIONS -->
                    <template x-if="trade.buyer_id === '{{ Auth::user()->id }}'">
                        <div>
                            <template x-if="trade.status === 'pending_payment'">
                                <div>
                                    <p style="margin-bottom: 0.5rem; font-size: 0.9rem;">Pay to Seller UPI: <strong x-text="trade.order.seller_upi_id" style="color: var(--gold);"></strong></p>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 0.5rem; margin-bottom: 1rem;">
                                        <a :href="trade.deepLinks?.gpay" class="btn btn-gpay btn-sm">GPay</a>
                                        <a :href="trade.deepLinks?.phonepe" class="btn btn-phonepe btn-sm">PhonePe</a>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <input type="text" class="input input-sm font-mono" placeholder="UTR Number" x-model="utrNumbers[trade.id]">
                                        <label class="btn btn-secondary btn-sm" style="cursor: pointer; margin: 0; white-space: nowrap;">
                                            📁 Image
                                            <input type="file" accept="image/*" style="display: none;" @change="screenshotFiles[trade.id] = $event.target.files[0]">
                                        </label>
                                    </div>
                                    <div x-show="screenshotFiles[trade.id]" style="font-size: 0.8rem; color: var(--success); margin: 0.5rem 0;">Screenshot selected</div>
                                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                        <button class="btn btn-primary btn-sm btn-full" @click="submitPayment(trade.id)" :disabled="loadingAction !== null">
                                            <span x-show="loadingAction !== 'submit-' + trade.id">Submit Proof</span>
                                            <span x-show="loadingAction === 'submit-' + trade.id" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                                <svg class="spinner-svg" viewBox="0 0 50 50" style="width: 16px; height: 16px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
                                                Processing...
                                            </span>
                                        </button>
                                        <button class="btn btn-secondary btn-sm btn-full" @click="handleBuyerCancel(trade.id)" :disabled="loadingAction !== null">
                                            <span x-show="loadingAction !== 'cancel-' + trade.id">Cancel</span>
                                            <span x-show="loadingAction === 'cancel-' + trade.id" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                                <svg class="spinner-svg" viewBox="0 0 50 50" style="width: 16px; height: 16px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
                                                ...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="trade.status === 'payment_submitted'">
                                <div style="color: var(--info); font-size: 0.9rem;">
                                    ⏳ Proof submitted. Waiting for seller to confirm.
                                </div>
                            </template>
                            <template x-if="trade.status === 'seller_rejected'">
                                <div style="background: rgba(220,38,38,0.1); border-radius: var(--radius-sm); padding: 1rem; border: 1px solid var(--danger);">
                                    <p style="color: var(--danger); font-weight: 700; margin-bottom: 0.5rem;">⚠️ Seller rejected your payment.</p>
                                    <p style="font-size: 0.9rem; margin-bottom: 1rem;">Please appeal with 3 proofs.</p>
                                    
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                                        <label class="btn btn-secondary btn-sm" style="cursor: pointer; text-align: left;">
                                            🎥 Screen Recording
                                            <input type="file" accept="video/*" style="display: none;" @change="appealFiles[trade.id] = { ...appealFiles[trade.id], video: $event.target.files[0] }">
                                        </label>
                                        <div x-show="appealFiles[trade.id]?.video" style="font-size: 0.8rem; color: var(--success);">Recording selected</div>
                                        
                                        <label class="btn btn-secondary btn-sm" style="cursor: pointer; text-align: left;">
                                            📄 Bank Statement (PDF)
                                            <input type="file" accept="application/pdf" style="display: none;" @change="appealFiles[trade.id] = { ...appealFiles[trade.id], pdf: $event.target.files[0] }">
                                        </label>
                                        <div x-show="appealFiles[trade.id]?.pdf" style="font-size: 0.8rem; color: var(--success);">PDF selected</div>

                                        <label class="btn btn-secondary btn-sm" style="cursor: pointer; text-align: left;">
                                            🖼️ Screenshot
                                            <input type="file" accept="image/*" style="display: none;" @change="appealFiles[trade.id] = { ...appealFiles[trade.id], image: $event.target.files[0] }">
                                        </label>
                                        <div x-show="appealFiles[trade.id]?.image" style="font-size: 0.8rem; color: var(--success);">Screenshot selected</div>
                                    </div>

                                    <button class="btn btn-danger btn-sm btn-full" @click="handleAppeal(trade.id)" :disabled="loadingAction !== null">
                                        <span x-show="loadingAction !== 'appeal-' + trade.id">Submit Appeal</span>
                                        <span x-show="loadingAction === 'appeal-' + trade.id" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;"><svg class="spinner-svg" viewBox="0 0 50 50" style="width: 16px; height: 16px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg> Processing...</span>
                                    </button>
                                </div>
                            </template>
                            <template x-if="trade.status === 'disputed'">
                                <div style="color: var(--warning); font-size: 0.9rem;">
                                    ⚠️ Trade is under dispute review by an admin. Please wait.
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- SELLER ACTIONS -->
                    <template x-if="trade.seller_id === '{{ Auth::user()->id }}'">
                        <div>
                            <template x-if="trade.status === 'pending_payment'">
                                <div>
                                    <p style="margin-bottom: 1rem; font-size: 0.9rem;">⏳ Waiting for buyer to send payment.</p>
                                    <button class="btn btn-secondary btn-sm" @click="handleSellerCancelOrder(trade.order_id)" :disabled="loadingAction !== null">
                                        <span x-show="loadingAction !== 'seller-cancel-' + trade.order_id">Request Cancel</span>
                                        <span x-show="loadingAction === 'seller-cancel-' + trade.order_id" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                            <svg class="spinner-svg" viewBox="0 0 50 50" style="width: 16px; height: 16px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
                                            Processing...
                                        </span>
                                    </button>
                                </div>
                            </template>
                            <template x-if="trade.status === 'payment_submitted'">
                                <div>
                                    <p style="margin-bottom: 1rem; font-size: 0.9rem;">Buyer claims payment made. UTR: <strong style="color: var(--gold);" x-text="trade.utr_number"></strong></p>
                                    
                                    <template x-if="trade.buyer_payment_screenshot_url || trade.payment_screenshot_url">
                                        <div style="margin-bottom: 1rem; text-align: center;">
                                            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; text-align: left;">Payment Screenshot:</p>
                                            <a :href="(trade.buyer_payment_screenshot_url || trade.payment_screenshot_url).replace('http://localhost:8000', '')" target="_blank" title="Click to view full size">
                                                <img :src="(trade.buyer_payment_screenshot_url || trade.payment_screenshot_url).replace('http://localhost:8000', '')" alt="Payment Proof" style="max-width: 100%; max-height: 250px; border-radius: var(--radius-sm); border: 1px solid var(--border); transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                                            </a>
                                        </div>
                                    </template>

                                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                                        <button class="btn btn-success btn-sm btn-full" @click="confirmReceipt(trade.id)" :disabled="loadingAction !== null">
                                            <span x-show="loadingAction !== 'confirm-' + trade.id">Confirm Payment Received</span>
                                            <span x-show="loadingAction === 'confirm-' + trade.id" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                                <svg class="spinner-svg" viewBox="0 0 50 50" style="width: 16px; height: 16px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
                                                Processing...
                                            </span>
                                        </button>
                                        <button class="btn btn-danger btn-sm btn-full" @click="toggleRejectForm(trade.id)" :disabled="loadingAction !== null">
                                            Report Fraud
                                        </button>
                                    </div>

                                    <div x-show="showRejectForm[trade.id]" style="margin-top: 1rem; background: rgba(220,38,38,0.1); border-radius: var(--radius-sm); padding: 1rem; border: 1px solid var(--danger);">
                                        <p style="color: var(--danger); font-weight: 700; margin-bottom: 0.5rem;">Reject Payment</p>
                                        <p style="font-size: 0.9rem; margin-bottom: 1rem;">Upload 3 proofs to reject.</p>
                                        
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                                            <label class="btn btn-secondary btn-sm" style="cursor: pointer; text-align: left;">
                                                🎥 Screen Recording
                                                <input type="file" accept="video/*" style="display: none;" @change="rejectFiles[trade.id] = { ...rejectFiles[trade.id], video: $event.target.files[0] }">
                                            </label>
                                            <div x-show="rejectFiles[trade.id]?.video" style="font-size: 0.8rem; color: var(--success);">Recording selected</div>
                                            
                                            <label class="btn btn-secondary btn-sm" style="cursor: pointer; text-align: left;">
                                                📄 Bank Statement (PDF)
                                                <input type="file" accept="application/pdf" style="display: none;" @change="rejectFiles[trade.id] = { ...rejectFiles[trade.id], pdf: $event.target.files[0] }">
                                            </label>
                                            <div x-show="rejectFiles[trade.id]?.pdf" style="font-size: 0.8rem; color: var(--success);">PDF selected</div>

                                            <label class="btn btn-secondary btn-sm" style="cursor: pointer; text-align: left;">
                                                🖼️ Screenshot
                                                <input type="file" accept="image/*" style="display: none;" @change="rejectFiles[trade.id] = { ...rejectFiles[trade.id], image: $event.target.files[0] }">
                                            </label>
                                            <div x-show="rejectFiles[trade.id]?.image" style="font-size: 0.8rem; color: var(--success);">Screenshot selected</div>
                                        </div>

                                        <button class="btn btn-danger btn-sm btn-full" @click="handleReject(trade.id)" :disabled="loadingAction !== null">
                                            <span x-show="loadingAction !== 'reject-' + trade.id">Submit Reject</span>
                                            <span x-show="loadingAction === 'reject-' + trade.id" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;"><svg class="spinner-svg" viewBox="0 0 50 50" style="width: 16px; height: 16px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg> Processing...</span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="trade.status === 'disputed'">
                                <div style="color: var(--warning); font-size: 0.9rem;">
                                    ⚠️ Under review by admin.
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <!-- OPEN ORDERS (Unmatched Sells) -->
            <template x-for="order in openOrders" :key="order.id">
                <div class="card" style="border-left: 4px solid var(--primary);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span class="badge badge-info">OPEN SELL ORDER</span>
                            <div style="font-size: 1.2rem; font-weight: 700; margin-top: 0.25rem;">₹<span x-text="order.amount"></span></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Waiting for buyer...</div>
                        </div>
                        <button class="btn btn-secondary btn-sm" @click="handleSellerCancelOrder(order.id)" :disabled="loadingAction !== null">
                            <span x-show="loadingAction !== 'seller-cancel-' + order.id">Cancel</span>
                            <span x-show="loadingAction === 'seller-cancel-' + order.id" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                <svg class="spinner-svg" viewBox="0 0 50 50" style="width: 16px; height: 16px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
                                ...
                            </span>
                        </button>
                    </div>
                </div>
            </template>

            <!-- BUYER QUEUES -->
            <template x-for="queue in activeQueues" :key="queue.amount_id">
                <div class="card" style="border-left: 4px solid var(--success);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span class="badge badge-success">BUYER QUEUE</span>
                            <div style="font-size: 1.2rem; font-weight: 700; margin-top: 0.25rem;">₹<span x-text="queue.amount"></span></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);">Position: #<span x-text="queue.position"></span></div>
                        </div>
                        <button class="btn btn-secondary btn-sm" @click="handleCancelQueue(queue.amount_id)" :disabled="loadingAction !== null">
                            <span x-show="loadingAction !== 'leave-queue-' + queue.amount_id">Leave Queue</span>
                            <span x-show="loadingAction === 'leave-queue-' + queue.amount_id" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                <svg class="spinner-svg" viewBox="0 0 50 50" style="width: 16px; height: 16px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
                                ...
                            </span>
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
            message: '',
            error: '',
            utrNumbers: {},
            screenshotFiles: {},
            rejectFiles: {},
            appealFiles: {},
            showRejectForm: {},

            async init() {
                await this.loadActiveState();
                if (window.Echo) {
                    window.Echo.private(`user.{{ Auth::user()->id }}`)
                        .listen('.trade:update', (e) => {
                            this.loadActiveState();
                            // Also refresh balances silently without full page reload if possible,
                            // but for now a simple refresh of state is fine.
                        });
                }
            },

            async loadActiveState() {
                this.loadingAction = 'refresh';
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
                }
            },

            async handleSellerCancelOrder(orderId) {
                if (!confirm('Are you sure you want to cancel this sell order?')) return;
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
                        setTimeout(() => window.location.reload(), 1000); // Reload to update balances
                    }
                } finally {
                    this.loadingAction = null;
                }
            },

            async handleBuyerCancel(tradeId) {
                if (!confirm('Are you sure you want to cancel? Consecutive cancels result in a temporary block.')) return;
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
                }
            },

            async submitPayment(tradeId) {
                const utr = this.utrNumbers[tradeId];
                const file = this.screenshotFiles[tradeId];
                if (!utr || !file) {
                    this.error = 'Please enter UTR number and select a screenshot.';
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
                        this.message = 'Trade confirmed and coins released!';
                        await this.loadActiveState();
                        setTimeout(() => window.location.reload(), 1000); // Reload to update balances
                    }
                } finally {
                    this.loadingAction = null;
                }
            },

            toggleRejectForm(tradeId) {
                this.showRejectForm[tradeId] = !this.showRejectForm[tradeId];
            },

            async handleReject(tradeId) {
                const files = this.rejectFiles[tradeId];
                if (!files || !files.video || !files.pdf || !files.image) {
                    this.error = 'Please upload all 3 proofs (Recording, PDF, Screenshot).';
                    return;
                }
                this.error = ''; this.loadingAction = 'reject-' + tradeId;
                const formData = new FormData();
                formData.append('screen_recording', files.video);
                formData.append('bank_statement', files.pdf);
                formData.append('txn_screenshot', files.image);

                try {
                    const res = await fetch(`/api/trade/reject/${tradeId}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: formData
                    });
                    const data = await res.json();
                    if (!res.ok) this.error = data.error || data.message;
                    else {
                        this.message = data.message || 'Payment rejected.';
                        await this.loadActiveState();
                    }
                } finally {
                    this.loadingAction = null;
                }
            },

            async handleAppeal(tradeId) {
                const files = this.appealFiles[tradeId];
                if (!files || !files.video || !files.pdf || !files.image) {
                    this.error = 'Please upload all 3 proofs (Recording, PDF, Screenshot).';
                    return;
                }
                this.error = ''; this.loadingAction = 'appeal-' + tradeId;
                const formData = new FormData();
                formData.append('screen_recording', files.video);
                formData.append('bank_statement', files.pdf);
                formData.append('screenshot', files.image);

                try {
                    const res = await fetch(`/api/dispute/appeal/${tradeId}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: formData
                    });
                    const data = await res.json();
                    if (!res.ok) this.error = data.error || data.message;
                    else {
                        this.message = data.message || 'Appeal submitted.';
                        await this.loadActiveState();
                    }
                } finally {
                    this.loadingAction = null;
                }
            }
        }
    }
</script>
@endsection
