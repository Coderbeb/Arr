@extends('layouts.app')

@section('title', 'Support Queue — Arr Wallet')

@section('content')
<div class="fade-in" x-data="{
    disputes: [],
    loading: true,
    message: '',

    async init() {
        await this.loadQueue();
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
        } catch (e) {}
    }
}">
    <h1>🛡️ Support Dispute Resolution Queue</h1>
    <p style="margin-bottom: 2rem;">Review buyer & seller video proofs and AI confidence scores</p>

    <template x-if="message">
        <div class="toast toast-success" style="position: static; transform: none; margin-bottom: 1rem; width: 100%;" x-text="message"></div>
    </template>

    <div class="card">
        <h3>Pending Disputes</h3>

        <template x-if="loading">
            <div style="text-align: center; padding: 2rem;"><div class="spinner" style="margin: 0 auto;"></div></div>
        </template>

        <template x-if="!loading && disputes.length === 0">
            <div style="text-align: center; padding: 2rem; color: var(--text-muted);">No active disputes in queue. All clean!</div>
        </template>

        <template x-if="!loading && disputes.length > 0">
            <div>
                <template x-for="d in disputes" :key="d.id">
                    <div style="border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h4>Dispute #<span x-text="d.id.slice(0, 8)"></span> (Trade ₹<span x-text="d.trade ? d.trade.amount : ''"></span>)</h4>
                            <span class="badge badge-danger" x-text="d.status"></span>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1rem;">
                            <!-- Buyer Side -->
                            <div style="background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                                <h5 style="color: var(--success); margin-bottom: 1rem; font-size: 1.1rem;">
                                    🟢 Buyer Proofs
                                    <span style="float: right; font-size: 1.2rem;">Score: <strong style="color: var(--gold);" x-text="d.buyer_ai_score ? d.buyer_ai_score + '%' : 'Pending'"></strong></span>
                                </h5>
                                
                                <!-- Video -->
                                <div style="margin-bottom: 1rem;">
                                    <label class="input-label">🎥 Screen Recording</label>
                                    <template x-if="d.buyer_screen_recording_url">
                                        <video controls style="width: 100%; border-radius: 4px; border: 1px solid #444;" :src="d.buyer_screen_recording_url"></video>
                                    </template>
                                    <template x-if="!d.buyer_screen_recording_url"><span style="color: var(--text-muted);">No video uploaded</span></template>
                                </div>

                                <!-- PDF -->
                                <div style="margin-bottom: 1rem;">
                                    <label class="input-label">📄 Bank Statement</label>
                                    <template x-if="d.buyer_bank_statement_url">
                                        <a :href="d.buyer_bank_statement_url" target="_blank" class="btn btn-secondary btn-sm" style="display: block; text-align: center;">View PDF</a>
                                    </template>
                                    <template x-if="!d.buyer_bank_statement_url"><span style="color: var(--text-muted);">No PDF uploaded</span></template>
                                </div>

                                <!-- Image -->
                                <div style="margin-bottom: 1rem;">
                                    <label class="input-label">🖼️ Screenshot</label>
                                    <template x-if="d.buyer_screenshot_url">
                                        <a :href="d.buyer_screenshot_url" target="_blank"><img :src="d.buyer_screenshot_url" style="width: 100%; border-radius: 4px; border: 1px solid #444;" /></a>
                                    </template>
                                    <template x-if="!d.buyer_screenshot_url"><span style="color: var(--text-muted);">No image uploaded</span></template>
                                </div>
                                
                                <!-- AI Breakdown -->
                                <template x-if="d.buyer_ai_breakdown">
                                    <div style="font-size: 0.8rem; background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 4px;">
                                        <strong>AI Forensics:</strong>
                                        <div style="display: flex; justify-content: space-between;"><span>Video Score:</span><strong x-text="(d.buyer_ai_breakdown.video_score || 0) + '%'"></strong></div>
                                        <div style="display: flex; justify-content: space-between;"><span>PDF Score:</span><strong x-text="(d.buyer_ai_breakdown.pdf_score || 0) + '%'"></strong></div>
                                        <div style="display: flex; justify-content: space-between;"><span>Image Score:</span><strong x-text="(d.buyer_ai_breakdown.image_score || 0) + '%'"></strong></div>
                                        
                                        <template x-if="d.buyer_proof_analysis">
                                            <div style="margin-top: 0.5rem;">
                                                <template x-if="d.buyer_proof_analysis.video && d.buyer_proof_analysis.video.breakdown.fraud_flag">
                                                    <div style="color: var(--danger);" x-text="'⚠️ Video: ' + d.buyer_proof_analysis.video.breakdown.fraud_flag"></div>
                                                </template>
                                                <template x-if="d.buyer_proof_analysis.pdf && d.buyer_proof_analysis.pdf.breakdown.fraud_flag">
                                                    <div style="color: var(--danger);" x-text="'⚠️ PDF: ' + d.buyer_proof_analysis.pdf.breakdown.fraud_flag"></div>
                                                </template>
                                                <template x-if="d.buyer_proof_analysis.image && d.buyer_proof_analysis.image.breakdown.fraud_flag">
                                                    <div style="color: var(--danger);" x-text="'⚠️ Image: ' + d.buyer_proof_analysis.image.breakdown.fraud_flag"></div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- Seller Side -->
                            <div style="background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">
                                <h5 style="color: var(--danger); margin-bottom: 1rem; font-size: 1.1rem;">
                                    🔴 Seller Proofs
                                    <span style="float: right; font-size: 1.2rem;">Score: <strong style="color: var(--gold);" x-text="d.seller_ai_score ? d.seller_ai_score + '%' : 'Pending'"></strong></span>
                                </h5>
                                
                                <!-- Video -->
                                <div style="margin-bottom: 1rem;">
                                    <label class="input-label">🎥 Screen Recording</label>
                                    <template x-if="d.seller_screen_recording_url">
                                        <video controls style="width: 100%; border-radius: 4px; border: 1px solid #444;" :src="d.seller_screen_recording_url"></video>
                                    </template>
                                    <template x-if="!d.seller_screen_recording_url"><span style="color: var(--text-muted);">No video uploaded</span></template>
                                </div>

                                <!-- PDF -->
                                <div style="margin-bottom: 1rem;">
                                    <label class="input-label">📄 Bank Statement</label>
                                    <template x-if="d.seller_bank_statement_url">
                                        <a :href="d.seller_bank_statement_url" target="_blank" class="btn btn-secondary btn-sm" style="display: block; text-align: center;">View PDF</a>
                                    </template>
                                    <template x-if="!d.seller_bank_statement_url"><span style="color: var(--text-muted);">No PDF uploaded</span></template>
                                </div>

                                <!-- Image -->
                                <div style="margin-bottom: 1rem;">
                                    <label class="input-label">🖼️ Screenshot</label>
                                    <template x-if="d.seller_txn_screenshot_url">
                                        <a :href="d.seller_txn_screenshot_url" target="_blank"><img :src="d.seller_txn_screenshot_url" style="width: 100%; border-radius: 4px; border: 1px solid #444;" /></a>
                                    </template>
                                    <template x-if="!d.seller_txn_screenshot_url"><span style="color: var(--text-muted);">No image uploaded</span></template>
                                </div>
                                
                                <!-- AI Breakdown -->
                                <template x-if="d.seller_ai_breakdown">
                                    <div style="font-size: 0.8rem; background: rgba(0,0,0,0.2); padding: 0.5rem; border-radius: 4px;">
                                        <strong>AI Forensics:</strong>
                                        <div style="display: flex; justify-content: space-between;"><span>Video Score:</span><strong x-text="(d.seller_ai_breakdown.video_score || 0) + '%'"></strong></div>
                                        <div style="display: flex; justify-content: space-between;"><span>PDF Score:</span><strong x-text="(d.seller_ai_breakdown.pdf_score || 0) + '%'"></strong></div>
                                        <div style="display: flex; justify-content: space-between;"><span>Image Score:</span><strong x-text="(d.seller_ai_breakdown.image_score || 0) + '%'"></strong></div>
                                        
                                        <template x-if="d.seller_proof_analysis">
                                            <div style="margin-top: 0.5rem;">
                                                <template x-if="d.seller_proof_analysis.video && d.seller_proof_analysis.video.breakdown.fraud_flag">
                                                    <div style="color: var(--danger);" x-text="'⚠️ Video: ' + d.seller_proof_analysis.video.breakdown.fraud_flag"></div>
                                                </template>
                                                <template x-if="d.seller_proof_analysis.pdf && d.seller_proof_analysis.pdf.breakdown.fraud_flag">
                                                    <div style="color: var(--danger);" x-text="'⚠️ PDF: ' + d.seller_proof_analysis.pdf.breakdown.fraud_flag"></div>
                                                </template>
                                                <template x-if="d.seller_proof_analysis.image && d.seller_proof_analysis.image.breakdown.fraud_flag">
                                                    <div style="color: var(--danger);" x-text="'⚠️ Image: ' + d.seller_proof_analysis.image.breakdown.fraud_flag"></div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                            <button class="btn btn-success btn-full" @click="resolveDispute(d.id, 'buyer')">
                                Resolve Buyer Wins
                            </button>
                            <button class="btn btn-danger btn-full" @click="resolveDispute(d.id, 'seller')">
                                Resolve Seller Wins
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>
@endsection
