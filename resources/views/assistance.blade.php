@extends('layouts.app')

@section('title', 'Support Queue — Arr Wallet')

@section('content')
<div class="animate-fade-in-up" x-data="{
    disputes: [],
    loading: true,
    initialLoad: true,
    message: '',

    async init() {
        await this.loadQueue();
        this.initialLoad = false;
    },

    async loadQueue() {
        this.loading = true;
        try {
            const res = await fetch('/api/assistance/queue');
            this.disputes = await res.json();
        } finally {
            this.loading = false;
        }
    },

    async resolveDispute(disputeId, winner) {
        if (!confirm(`Are you sure you want to resolve in favor of ${winner.toUpperCase()}?`)) return;
        this.loading = true;
        try {
            const res = await fetch(`/api/assistance/resolve/${disputeId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ winner: winner, notes: 'Resolved by support staff' })
            });
            const data = await res.json();
            this.message = data.message;
            await this.loadQueue();
        } catch (e) {
            console.error(e);
        } finally {
            this.loading = false;
            setTimeout(() => this.message = '', 4000);
        }
    }
}">
    <div class="mb-8">
        <h1 class="text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-600 dark:from-white dark:to-gray-300 bg-clip-text text-transparent flex items-center gap-2">
            <span>🛡️</span> Support Dispute Resolution Queue
        </h1>
        <p class="text-gray-500 dark:text-gray-400 mt-2">Review buyer & seller video proofs and AI confidence scores</p>
    </div>

    <template x-if="message">
        <div class="glass-card !bg-green-50 dark:!bg-green-500/10 !border-green-200 dark:!border-green-500/20 p-4 mb-6 text-green-700 dark:text-green-400 font-medium animate-fade-in" x-text="message"></div>
    </template>

    <div class="glass-card p-6 md:p-8">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Pending Disputes</h3>
            <button @click="loadQueue()" class="p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-white/5 rounded-lg transition-colors" :class="{ 'animate-spin': loading && !initialLoad }">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </div>

        <template x-if="initialLoad">
            <div class="flex justify-center py-12">
                <svg class="animate-spin h-10 w-10 text-gold-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            </div>
        </template>

        <template x-if="!initialLoad && disputes.length === 0">
            <div class="border-2 border-dashed border-gray-200 dark:border-white/10 rounded-2xl py-16 flex flex-col items-center justify-center text-center">
                <div class="text-5xl mb-4">✨</div>
                <div class="text-xl font-bold text-gray-700 dark:text-gray-300">No active disputes in queue</div>
                <p class="text-gray-500 dark:text-gray-400 mt-2">All clean and handled!</p>
            </div>
        </template>

        <template x-if="!initialLoad && disputes.length > 0">
            <div class="space-y-6">
                <template x-for="d in disputes" :key="d.id">
                    <div class="bg-gray-50 dark:bg-white/5 rounded-2xl border border-gray-200 dark:border-white/10 p-6 sm:p-8">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 pb-4 border-b border-gray-200 dark:border-white/5 gap-4">
                            <h4 class="text-xl font-bold text-gray-900 dark:text-white">
                                Dispute #<span x-text="d.id.slice(0, 8)"></span> 
                                <span class="text-gold-500 ml-2">(Trade ₹<span x-text="d.trade ? parseFloat(d.trade.amount).toFixed(2) : ''"></span>)</span>
                            </h4>
                            <span class="inline-flex px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400" x-text="d.status"></span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <!-- Buyer Side -->
                            <div class="bg-white dark:bg-black/20 p-6 rounded-xl border border-gray-100 dark:border-white/5">
                                <div class="flex justify-between items-center mb-6 border-b border-gray-100 dark:border-white/5 pb-4">
                                    <h5 class="text-green-600 dark:text-green-400 font-bold text-lg flex items-center gap-2">
                                        🟢 Buyer Proofs
                                    </h5>
                                    <div class="text-sm text-gray-500">
                                        Score: <strong class="text-gold-500 text-lg" x-text="d.buyer_ai_score ? d.buyer_ai_score + '%' : 'Pending'"></strong>
                                    </div>
                                </div>
                                
                                <div class="space-y-4">
                                    <!-- Video -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">🎥 Screen Recording</label>
                                        <template x-if="d.buyer_screen_recording_url">
                                            <video controls class="w-full h-auto max-h-64 rounded-lg border border-gray-200 dark:border-white/10" :src="d.buyer_screen_recording_url"></video>
                                        </template>
                                        <template x-if="!d.buyer_screen_recording_url">
                                            <div class="text-center py-3 px-4 rounded-lg bg-gray-100 dark:bg-black/30 text-gray-500 text-sm">No video uploaded</div>
                                        </template>
                                    </div>

                                    <!-- PDF -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">📄 Bank Statement</label>
                                        <template x-if="d.buyer_bank_statement_url">
                                            <a :href="d.buyer_bank_statement_url" target="_blank" class="flex items-center justify-center w-full py-2.5 px-4 rounded-lg border border-gray-200 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/5 font-medium transition-colors">View PDF Document</a>
                                        </template>
                                        <template x-if="!d.buyer_bank_statement_url">
                                            <div class="text-center py-2.5 px-4 rounded-lg bg-gray-100 dark:bg-black/30 text-gray-500 text-sm">No PDF uploaded</div>
                                        </template>
                                    </div>

                                    <!-- Image -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">🖼️ Screenshot</label>
                                        <template x-if="d.buyer_screenshot_url">
                                            <a :href="d.buyer_screenshot_url" target="_blank" class="block rounded-lg overflow-hidden border border-gray-200 dark:border-white/10">
                                                <img :src="d.buyer_screenshot_url" class="w-full h-auto max-h-48 object-contain bg-black/5" />
                                            </a>
                                        </template>
                                        <template x-if="!d.buyer_screenshot_url">
                                            <div class="text-center py-3 px-4 rounded-lg bg-gray-100 dark:bg-black/30 text-gray-500 text-sm">No image uploaded</div>
                                        </template>
                                    </div>
                                    
                                    <!-- AI Breakdown -->
                                    <template x-if="d.buyer_ai_breakdown">
                                        <div class="mt-6 p-4 bg-gray-100 dark:bg-black/30 rounded-lg border border-gray-200 dark:border-white/5">
                                            <strong class="block text-sm text-gray-700 dark:text-gray-300 mb-2">AI Forensics Breakdown:</strong>
                                            <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                                <div class="flex justify-between"><span>Video Score:</span><strong x-text="(d.buyer_ai_breakdown.video_score || 0) + '%'"></strong></div>
                                                <div class="flex justify-between"><span>PDF Score:</span><strong x-text="(d.buyer_ai_breakdown.pdf_score || 0) + '%'"></strong></div>
                                                <div class="flex justify-between"><span>Image Score:</span><strong x-text="(d.buyer_ai_breakdown.image_score || 0) + '%'"></strong></div>
                                            </div>
                                            
                                            <template x-if="d.buyer_proof_analysis">
                                                <div class="mt-3 space-y-1 text-xs">
                                                    <template x-if="d.buyer_proof_analysis.video && d.buyer_proof_analysis.video.breakdown.fraud_flag">
                                                        <div class="text-red-500" x-text="'⚠️ Video: ' + d.buyer_proof_analysis.video.breakdown.fraud_flag"></div>
                                                    </template>
                                                    <template x-if="d.buyer_proof_analysis.pdf && d.buyer_proof_analysis.pdf.breakdown.fraud_flag">
                                                        <div class="text-red-500" x-text="'⚠️ PDF: ' + d.buyer_proof_analysis.pdf.breakdown.fraud_flag"></div>
                                                    </template>
                                                    <template x-if="d.buyer_proof_analysis.image && d.buyer_proof_analysis.image.breakdown.fraud_flag">
                                                        <div class="text-red-500" x-text="'⚠️ Image: ' + d.buyer_proof_analysis.image.breakdown.fraud_flag"></div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Seller Side -->
                            <div class="bg-white dark:bg-black/20 p-6 rounded-xl border border-gray-100 dark:border-white/5">
                                <div class="flex justify-between items-center mb-6 border-b border-gray-100 dark:border-white/5 pb-4">
                                    <h5 class="text-red-600 dark:text-red-400 font-bold text-lg flex items-center gap-2">
                                        🔴 Seller Proofs
                                    </h5>
                                    <div class="text-sm text-gray-500">
                                        Score: <strong class="text-gold-500 text-lg" x-text="d.seller_ai_score ? d.seller_ai_score + '%' : 'Pending'"></strong>
                                    </div>
                                </div>
                                
                                <div class="space-y-4">
                                    <!-- Video -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">🎥 Screen Recording</label>
                                        <template x-if="d.seller_screen_recording_url">
                                            <video controls class="w-full h-auto max-h-64 rounded-lg border border-gray-200 dark:border-white/10" :src="d.seller_screen_recording_url"></video>
                                        </template>
                                        <template x-if="!d.seller_screen_recording_url">
                                            <div class="text-center py-3 px-4 rounded-lg bg-gray-100 dark:bg-black/30 text-gray-500 text-sm">No video uploaded</div>
                                        </template>
                                    </div>

                                    <!-- PDF -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">📄 Bank Statement</label>
                                        <template x-if="d.seller_bank_statement_url">
                                            <a :href="d.seller_bank_statement_url" target="_blank" class="flex items-center justify-center w-full py-2.5 px-4 rounded-lg border border-gray-200 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/5 font-medium transition-colors">View PDF Document</a>
                                        </template>
                                        <template x-if="!d.seller_bank_statement_url">
                                            <div class="text-center py-2.5 px-4 rounded-lg bg-gray-100 dark:bg-black/30 text-gray-500 text-sm">No PDF uploaded</div>
                                        </template>
                                    </div>

                                    <!-- Image -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">🖼️ Screenshot</label>
                                        <template x-if="d.seller_txn_screenshot_url">
                                            <a :href="d.seller_txn_screenshot_url" target="_blank" class="block rounded-lg overflow-hidden border border-gray-200 dark:border-white/10">
                                                <img :src="d.seller_txn_screenshot_url" class="w-full h-auto max-h-48 object-contain bg-black/5" />
                                            </a>
                                        </template>
                                        <template x-if="!d.seller_txn_screenshot_url">
                                            <div class="text-center py-3 px-4 rounded-lg bg-gray-100 dark:bg-black/30 text-gray-500 text-sm">No image uploaded</div>
                                        </template>
                                    </div>
                                    
                                    <!-- AI Breakdown -->
                                    <template x-if="d.seller_ai_breakdown">
                                        <div class="mt-6 p-4 bg-gray-100 dark:bg-black/30 rounded-lg border border-gray-200 dark:border-white/5">
                                            <strong class="block text-sm text-gray-700 dark:text-gray-300 mb-2">AI Forensics Breakdown:</strong>
                                            <div class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                                <div class="flex justify-between"><span>Video Score:</span><strong x-text="(d.seller_ai_breakdown.video_score || 0) + '%'"></strong></div>
                                                <div class="flex justify-between"><span>PDF Score:</span><strong x-text="(d.seller_ai_breakdown.pdf_score || 0) + '%'"></strong></div>
                                                <div class="flex justify-between"><span>Image Score:</span><strong x-text="(d.seller_ai_breakdown.image_score || 0) + '%'"></strong></div>
                                            </div>
                                            
                                            <template x-if="d.seller_proof_analysis">
                                                <div class="mt-3 space-y-1 text-xs">
                                                    <template x-if="d.seller_proof_analysis.video && d.seller_proof_analysis.video.breakdown.fraud_flag">
                                                        <div class="text-red-500" x-text="'⚠️ Video: ' + d.seller_proof_analysis.video.breakdown.fraud_flag"></div>
                                                    </template>
                                                    <template x-if="d.seller_proof_analysis.pdf && d.seller_proof_analysis.pdf.breakdown.fraud_flag">
                                                        <div class="text-red-500" x-text="'⚠️ PDF: ' + d.seller_proof_analysis.pdf.breakdown.fraud_flag"></div>
                                                    </template>
                                                    <template x-if="d.seller_proof_analysis.image && d.seller_proof_analysis.image.breakdown.fraud_flag">
                                                        <div class="text-red-500" x-text="'⚠️ Image: ' + d.seller_proof_analysis.image.breakdown.fraud_flag"></div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4 mt-8 pt-6 border-t border-gray-200 dark:border-white/10">
                            <button class="btn-success flex-1 py-4 text-lg" @click="resolveDispute(d.id, 'buyer')" :disabled="loading">
                                🏆 Resolve Buyer Wins
                            </button>
                            <button class="btn-danger flex-1 py-4 text-lg" @click="resolveDispute(d.id, 'seller')" :disabled="loading">
                                🏆 Resolve Seller Wins
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
@endsection
