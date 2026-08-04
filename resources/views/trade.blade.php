@extends('layouts.app')

@section('title', 'Trade Room — Arr Wallet')

@section('content')
<div class="fade-in" x-data="{
    tradeAmounts: [],
    selectedAmountId: '',
    activeTab: 'buy',
    upiId: '{{ Auth::user()->upi_id }}',
    upiApp: '{{ Auth::user()->upi_app ?? 'gpay' }}',
    loading: false,
    message: '',
    error: '',

    async init() {
        await this.loadAmounts();
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
                this.message = 'Sell order created successfully! You can track it in your Dashboard.';
                // Optional: window.location.href = '/dashboard';
            }
        } catch (e) {
            this.error = 'Network Error.';
        } finally {
            this.loading = false;
        }
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
                this.message = data.message + ' Track your position in the Dashboard.';
                // Optional: window.location.href = '/dashboard';
            }
        } catch (e) {
            this.error = 'Network Error.';
        } finally {
            this.loading = false;
        }
    }
}">
    <h1>⚡ Real-Time P2P Trade Room</h1>
    <p style="margin-bottom: 2rem;">Fast matching engine with automated escrow locking. Place your buy or sell orders below.</p>

    <!-- Alerts -->
    <template x-if="message">
        <div class="toast toast-success" style="position: static; transform: none; margin-bottom: 1.5rem; width: 100%;" x-text="message"></div>
    </template>
    <template x-if="error">
        <div class="toast toast-error" style="position: static; transform: none; margin-bottom: 1.5rem; width: 100%;" x-text="error"></div>
    </template>

    <div class="card">
        <div class="tab-container">
            <button class="tab-btn" :class="{ 'tab-active': activeTab === 'buy' }" @click="activeTab = 'buy'; error = ''; message = '';">Buy Coins</button>
            <button class="tab-btn" :class="{ 'tab-active': activeTab === 'sell' }" @click="activeTab = 'sell'; error = ''; message = '';">Sell Coins</button>
        </div>

        <div class="input-group">
            <label class="input-label">Select Amount</label>
            <div class="amount-pill-grid">
                <template x-for="amt in tradeAmounts" :key="amt.id">
                    <div class="amount-pill" 
                         :class="{ 'amount-pill-active': selectedAmountId === amt.id }"
                         @click="selectedAmountId = amt.id"
                         x-text="'₹' + amt.amount"></div>
                </template>
            </div>
        </div>

        <template x-if="activeTab === 'sell'">
            <div>
                <div class="input-group">
                    <label class="input-label">Your UPI ID</label>
                    <input type="text" class="input" x-model="upiId" placeholder="name@upi" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Preferred UPI App</label>
                    <select class="input" x-model="upiApp">
                        <option value="gpay">Google Pay (GPay)</option>
                        <option value="phonepe">PhonePe</option>
                        <option value="paytm">Paytm</option>
                        <option value="bhim">BHIM UPI</option>
                    </select>
                </div>

                <button class="btn btn-primary btn-lg btn-full" style="margin-top: 1rem;" @click="handleSellOrder" :disabled="loading">
                    <span x-show="!loading">Sell Coins (Escrow)</span>
                    <span x-show="loading" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg class="spinner-svg" viewBox="0 0 50 50" style="width: 20px; height: 20px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
                        Processing...
                    </span>
                </button>
            </div>
        </template>

        <template x-if="activeTab === 'buy'">
            <div>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                    You will be matched with a verified seller and asked to pay via UPI.
                </p>
                <button class="btn btn-success btn-lg btn-full" @click="handleJoinQueue" :disabled="loading">
                    <span x-show="!loading">Join Buyer Queue</span>
                    <span x-show="loading" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                        <svg class="spinner-svg" viewBox="0 0 50 50" style="width: 20px; height: 20px;"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="5"></circle></svg>
                        Processing...
                    </span>
                </button>
            </div>
        </template>
    </div>
    
    <div style="margin-top: 2rem; text-align: center;">
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">
            Go to Dashboard to manage Live Orders
        </a>
    </div>
</div>
@endsection
