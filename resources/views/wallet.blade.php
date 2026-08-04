@extends('layouts.app')

@section('title', 'Wallet & Ledger — Arr Wallet')

@section('content')
<div class="fade-in" x-data="{
    balance: 0,
    escrow: 0,
    transactions: [],
    loading: true,

    async init() {
        await this.loadWalletData();
    },

    async loadWalletData() {
        this.loading = true;
        try {
            const balRes = await fetch('/api/wallet/balance');
            const balData = await balRes.json();
            this.balance = balData.wallet_balance;
            this.escrow = balData.escrow_balance;

            const txRes = await fetch('/api/wallet/transactions');
            this.transactions = await txRes.json();
        } finally {
            this.loading = false;
        }
    }
}">
    <h1>💼 Wallet Ledger</h1>
    <p style="margin-bottom: 2rem;">Dual-balance tracking and complete audit history</p>

    <!-- Balances -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <div class="balance-card">
            <div class="balance-amount">₹<span x-text="balance.toFixed(2)"></span></div>
            <div class="balance-label">Available Balance</div>
        </div>
        <div class="card" style="text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--warning); margin-top: 0.5rem;">₹<span x-text="escrow.toFixed(2)"></span></div>
            <div class="balance-label">Locked Escrow</div>
        </div>
    </div>

    <!-- Transaction Ledger Table -->
    <div class="card">
        <h3>Transaction History</h3>
        <p style="margin-bottom: 1.5rem;">Recent credits, debits, escrow locks, and bonuses</p>

        <template x-if="loading">
            <div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div></div>
        </template>

        <template x-if="!loading && transactions.length === 0">
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">No transactions recorded yet.</div>
        </template>

        <template x-if="!loading && transactions.length > 0">
            <div>
                <template x-for="tx in transactions" :key="tx.id">
                    <div class="list-item">
                        <div>
                            <div style="font-weight: 600;" x-text="tx.description_en"></div>
                            <div style="font-size: 0.8rem; color: var(--text-muted);" x-text="new Date(tx.created_at).toLocaleString()"></div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-weight: 700; font-size: 1.1rem;" :style="{ color: tx.type.includes('credit') || tx.type.includes('release') || tx.type === 'bonus' ? 'var(--success)' : 'var(--danger)' }">
                                <span x-text="tx.type.includes('credit') || tx.type.includes('release') || tx.type === 'bonus' ? '+' : '-'"></span>₹<span x-text="tx.amount"></span>
                            </div>
                            <span class="badge badge-info" x-text="tx.type"></span>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
@endsection
