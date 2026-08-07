@extends('layouts.app')

@section('title', 'Assistance Center — Arr Wallet')

@section('content')
<div class="animate-fade-in-up pb-24" x-data="{
    disputes: [],
    loading: true,
    initialLoad: true,
    message: '',
    error: '',
    activeTab: 'unassigned',
    authUserId: '{{ Auth::id() }}',

    get unassignedDisputes() {
        return this.disputes.filter(d => !d.assigned_to);
    },
    
    get myDisputes() {
        return this.disputes.filter(d => d.assigned_to && d.assigned_to.id === this.authUserId);
    },

    async init() {
        await this.loadQueue();
        this.initialLoad = false;

        const self = this;
        ArrPolling.start('assistance-queue', async () => {
            await self.loadQueue();
        }, 10000, false);
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

    async claimDispute(disputeId) {
        this.loading = true;
        this.error = '';
        try {
            const res = await fetch(`/api/assistance/claim/${disputeId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });
            const data = await res.json();
            if (!res.ok) {
                this.error = data.error || 'Failed to claim dispute.';
                setTimeout(() => this.error = '', 4000);
            } else {
                this.message = 'Case successfully assigned to your queue.';
                this.activeTab = 'my_queue';
                await this.loadQueue();
                setTimeout(() => this.message = '', 4000);
            }
        } catch (e) {
            this.error = 'Network error.';
        } finally {
            this.loading = false;
        }
    },

    async resolveDispute(disputeId, winner) {
        if (!confirm(`Are you absolutely sure you want to resolve this case in favor of the ${winner.toUpperCase()}? This action cannot be undone.`)) return;
        this.loading = true;
        this.error = '';
        try {
            const res = await fetch(`/api/assistance/resolve/${disputeId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ winner: winner, notes: 'Resolved by assistance manager' })
            });
            const data = await res.json();
            if (!res.ok) {
                this.error = data.error || 'Failed to resolve.';
                setTimeout(() => this.error = '', 4000);
            } else {
                this.message = data.message;
                await this.loadQueue();
                setTimeout(() => this.message = '', 4000);
            }
        } catch (e) {
            console.error(e);
            this.error = 'Network error.';
        } finally {
            this.loading = false;
        }
    }
}">
    <!-- Header -->
    <div class="mb-8 px-4 border-b border-gray-200 dark:border-white/10 pb-6">
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight flex items-center gap-3">
            <svg class="w-8 h-8 text-blue-600 dark:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            Assistance Center
        </h1>
        <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">Manage open disputes, review forensic evidence, and securely resolve cases.</p>
    </div>

    <!-- Feedback Messages -->
    <div class="px-4 mb-6">
        <template x-if="message">
            <div class="bg-green-50/80 border border-green-200 text-green-800 p-4 rounded-xl text-sm font-medium flex items-center gap-3 shadow-sm animate-fade-in">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span x-text="message"></span>
            </div>
        </template>
        <template x-if="error">
            <div class="bg-red-50/80 border border-red-200 text-red-800 p-4 rounded-xl text-sm font-medium flex items-center gap-3 shadow-sm animate-fade-in">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span x-text="error"></span>
            </div>
        </template>
    </div>

    <!-- Tabs -->
    <div class="px-4 mb-8">
        <div class="flex p-1.5 bg-gray-100/80 dark:bg-black/20 rounded-xl border border-gray-200/60 dark:border-white/10 shadow-inner">
            <button @click="activeTab = 'unassigned'" :class="activeTab === 'unassigned' ? 'bg-white dark:bg-gray-800 shadow text-gray-900 dark:text-white font-semibold' : 'text-gray-500 dark:text-gray-400 font-medium hover:text-gray-700 dark:hover:text-gray-200'" class="flex-1 py-3 text-sm rounded-lg transition-all relative flex items-center justify-center gap-2">
                Unassigned Cases
                <template x-if="unassignedDisputes.length > 0">
                    <span class="bg-blue-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm" x-text="unassignedDisputes.length"></span>
                </template>
            </button>
            <button @click="activeTab = 'my_queue'" :class="activeTab === 'my_queue' ? 'bg-white dark:bg-gray-800 shadow text-gray-900 dark:text-white font-semibold' : 'text-gray-500 dark:text-gray-400 font-medium hover:text-gray-700 dark:hover:text-gray-200'" class="flex-1 py-3 text-sm rounded-lg transition-all relative flex items-center justify-center gap-2">
                My Queue
                <template x-if="myDisputes.length > 0">
                    <span class="bg-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm" x-text="myDisputes.length"></span>
                </template>
            </button>
        </div>
    </div>

    <!-- Initial Loader -->
    <template x-if="initialLoad">
        <div class="flex justify-center py-16">
            <svg class="animate-spin h-10 w-10 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        </div>
    </template>

    <div x-show="!initialLoad" class="px-4 space-y-6">
        
        <!-- UNASSIGNED TAB -->
        <div x-show="activeTab === 'unassigned'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            
            <template x-if="unassignedDisputes.length === 0">
                <div class="bg-white/50 dark:bg-white/5 border border-gray-200/80 dark:border-white/10 rounded-3xl p-12 flex flex-col items-center justify-center text-center shadow-sm">
                    <div class="w-20 h-20 bg-blue-50 dark:bg-blue-900/20 text-blue-500 rounded-2xl flex items-center justify-center shadow-inner mb-5">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Inbox Zero</h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-2 max-w-xs">There are no unassigned dispute cases in the network at this time.</p>
                </div>
            </template>

            <template x-for="d in unassignedDisputes" :key="d.id">
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200/80 dark:border-white/10 overflow-hidden mb-5 hover:shadow-md transition-shadow">
                    <div class="p-5 border-b border-gray-100 dark:border-white/5 flex justify-between items-center bg-gray-50/50 dark:bg-white/5">
                        <div class="flex items-center gap-3">
                            <div class="bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-400 p-2 rounded-lg">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Case ID</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white font-mono" x-text="d.id.slice(0, 8)"></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Disputed Amount</p>
                            <p class="text-base font-bold text-gray-900 dark:text-white">₹<span x-text="d.trade ? parseFloat(d.trade.amount).toFixed(2) : ''"></span></p>
                        </div>
                    </div>
                    <div class="p-5 bg-white dark:bg-gray-900">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-5 leading-relaxed">
                            This dispute was escalated by <strong class="text-gray-900 dark:text-white" x-text="d.raised_by ? d.raised_by.full_name : 'a user'"></strong>. To view the forensic evidence and issue a binding resolution, you must first claim this case.
                        </p>
                        <button class="w-full bg-gray-900 hover:bg-black dark:bg-white dark:hover:bg-gray-100 dark:text-black text-white font-semibold py-3.5 rounded-xl transition-all shadow-sm flex justify-center items-center gap-2" @click="claimDispute(d.id)" :disabled="loading">
                            <span x-show="!loading" class="flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                                Claim & Review Case
                            </span>
                            <span x-show="loading" class="animate-pulse flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Processing...
                            </span>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- MY QUEUE TAB -->
        <div x-show="activeTab === 'my_queue'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            
            <template x-if="myDisputes.length === 0">
                <div class="bg-white/50 dark:bg-white/5 border border-gray-200/80 dark:border-white/10 rounded-3xl p-12 flex flex-col items-center justify-center text-center shadow-sm">
                    <div class="w-20 h-20 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-500 rounded-2xl flex items-center justify-center shadow-inner mb-5">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Your Queue is Clear</h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-2 max-w-xs">You have no active cases assigned to you. Check the Unassigned Pool to claim cases.</p>
                </div>
            </template>

            <template x-for="d in myDisputes" :key="d.id">
                <div class="bg-white dark:bg-gray-900 rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] dark:shadow-[0_8px_30px_rgba(255,255,255,0.02)] border border-gray-200/60 dark:border-white/10 overflow-hidden mb-10 flex flex-col">
                    <!-- Case Header -->
                    <div class="p-5 border-b border-gray-100 dark:border-white/5 bg-gray-50/50 dark:bg-white/5">
                        <div class="flex justify-between items-center mb-1">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-widest bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Active Assignment
                            </span>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 font-mono">Case #<span x-text="d.id.slice(0, 8)"></span></p>
                        </div>
                        <h3 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight mt-3">Disputed Amount: ₹<span x-text="d.trade ? parseFloat(d.trade.amount).toFixed(2) : ''"></span></h3>
                    </div>

                    <div class="p-5 space-y-6 flex-1">
                        
                        <!-- BUYER EVIDENCE -->
                        <div class="rounded-2xl p-5 border border-emerald-100 bg-emerald-50/30 dark:border-emerald-900/30 dark:bg-emerald-900/10 relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500"></div>
                            
                            <div class="flex justify-between items-center mb-5 pb-4 border-b border-emerald-100 dark:border-emerald-900/30">
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white text-base">Buyer Evidence</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="d.trade && d.trade.buyer ? d.trade.buyer.full_name : 'Buyer'"></p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-700 dark:text-gray-300 shadow-sm flex flex-col items-end">
                                    <span class="text-[10px] text-gray-400 uppercase tracking-widest leading-none mb-1">AI Score</span>
                                    <span class="text-emerald-600 dark:text-emerald-400 text-sm leading-none" x-text="d.buyer_ai_score ? d.buyer_ai_score + '%' : 'N/A'"></span>
                                </div>
                            </div>

                            <div class="space-y-5">
                                <!-- Original Payment -->
                                <template x-if="d.trade && d.trade.buyer_payment_screenshot_url">
                                    <div class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                                        <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-2">Initial Transfer Receipt</p>
                                        <a :href="d.trade.buyer_payment_screenshot_url" target="_blank" class="block rounded-lg overflow-hidden border border-gray-100 dark:border-gray-600 bg-gray-50 dark:bg-gray-900">
                                            <img :src="d.trade.buyer_payment_screenshot_url" class="w-full h-auto max-h-36 object-contain" />
                                        </a>
                                        <div class="mt-3 flex items-center justify-between bg-gray-50 dark:bg-gray-900 p-2 rounded-lg border border-gray-100 dark:border-gray-700" x-show="d.trade.utr_number">
                                            <span class="text-[10px] font-bold text-gray-500 uppercase">UTR Number</span>
                                            <span class="font-mono font-bold text-gray-900 dark:text-white text-sm" x-text="d.trade.utr_number"></span>
                                        </div>
                                    </div>
                                </template>

                                <!-- Appeal Proofs -->
                                <div>
                                    <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-3">Submitted Appeal Proofs</p>
                                    <div class="grid grid-cols-3 gap-3">
                                        <template x-if="d.buyer_screen_recording_url"><a :href="d.buyer_screen_recording_url" target="_blank" class="flex flex-col items-center justify-center p-3 rounded-xl bg-white dark:bg-gray-800 border border-emerald-200 dark:border-emerald-900/50 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors shadow-sm"><svg class="w-6 h-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg><span class="text-[10px] font-bold uppercase tracking-wider">Video</span></a></template>
                                        <template x-if="d.buyer_bank_statement_url"><a :href="d.buyer_bank_statement_url" target="_blank" class="flex flex-col items-center justify-center p-3 rounded-xl bg-white dark:bg-gray-800 border border-emerald-200 dark:border-emerald-900/50 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors shadow-sm"><svg class="w-6 h-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg><span class="text-[10px] font-bold uppercase tracking-wider">PDF</span></a></template>
                                        <template x-if="d.buyer_screenshot_url"><a :href="d.buyer_screenshot_url" target="_blank" class="flex flex-col items-center justify-center p-3 rounded-xl bg-white dark:bg-gray-800 border border-emerald-200 dark:border-emerald-900/50 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors shadow-sm"><svg class="w-6 h-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg><span class="text-[10px] font-bold uppercase tracking-wider">Image</span></a></template>
                                    </div>
                                    <template x-if="!d.buyer_screen_recording_url && !d.buyer_bank_statement_url && !d.buyer_screenshot_url">
                                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center">
                                            <p class="text-sm text-gray-500 dark:text-gray-400">No appeal proofs submitted.</p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- SELLER EVIDENCE -->
                        <div class="rounded-2xl p-5 border border-rose-100 bg-rose-50/30 dark:border-rose-900/30 dark:bg-rose-900/10 relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-1 h-full bg-rose-500"></div>
                            
                            <div class="flex justify-between items-center mb-5 pb-4 border-b border-rose-100 dark:border-rose-900/30">
                                <div>
                                    <h4 class="font-bold text-gray-900 dark:text-white text-base">Seller Evidence</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-text="d.trade && d.trade.seller ? d.trade.seller.full_name : 'Seller'"></p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-700 dark:text-gray-300 shadow-sm flex flex-col items-end">
                                    <span class="text-[10px] text-gray-400 uppercase tracking-widest leading-none mb-1">AI Score</span>
                                    <span class="text-rose-600 dark:text-rose-400 text-sm leading-none" x-text="d.seller_ai_score ? d.seller_ai_score + '%' : 'N/A'"></span>
                                </div>
                            </div>

                            <div class="space-y-4">
                                <!-- Rejection Proofs -->
                                <div>
                                    <p class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest mb-3">Rejection Proofs</p>
                                    <div class="grid grid-cols-3 gap-3">
                                        <template x-if="d.seller_screen_recording_url"><a :href="d.seller_screen_recording_url" target="_blank" class="flex flex-col items-center justify-center p-3 rounded-xl bg-white dark:bg-gray-800 border border-rose-200 dark:border-rose-900/50 text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors shadow-sm"><svg class="w-6 h-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg><span class="text-[10px] font-bold uppercase tracking-wider">Video</span></a></template>
                                        <template x-if="d.seller_bank_statement_url"><a :href="d.seller_bank_statement_url" target="_blank" class="flex flex-col items-center justify-center p-3 rounded-xl bg-white dark:bg-gray-800 border border-rose-200 dark:border-rose-900/50 text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors shadow-sm"><svg class="w-6 h-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg><span class="text-[10px] font-bold uppercase tracking-wider">PDF</span></a></template>
                                        <template x-if="d.seller_txn_screenshot_url"><a :href="d.seller_txn_screenshot_url" target="_blank" class="flex flex-col items-center justify-center p-3 rounded-xl bg-white dark:bg-gray-800 border border-rose-200 dark:border-rose-900/50 text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 transition-colors shadow-sm"><svg class="w-6 h-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg><span class="text-[10px] font-bold uppercase tracking-wider">Image</span></a></template>
                                    </div>
                                    <template x-if="!d.seller_screen_recording_url && !d.seller_bank_statement_url && !d.seller_txn_screenshot_url">
                                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center">
                                            <p class="text-sm text-gray-500 dark:text-gray-400">No rejection proofs submitted.</p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RESOLVE ACTION BAR -->
                    <div class="bg-gray-900 dark:bg-black p-5 relative z-10 border-t border-gray-800">
                        <div class="text-center mb-4">
                            <p class="text-gray-400 text-xs uppercase tracking-widest font-bold">Final Verdict</p>
                            <p class="text-gray-500 text-[10px] mt-1">This action transfers funds and closes the case.</p>
                        </div>
                        <div class="space-y-3">
                            <button class="w-full bg-emerald-500 hover:bg-emerald-600 active:bg-emerald-700 text-white font-bold py-4 rounded-xl transition-colors shadow-sm flex items-center justify-center gap-2" @click="resolveDispute(d.id, 'buyer')" :disabled="loading">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Resolve: Buyer Wins
                            </button>
                            <button class="w-full bg-rose-500 hover:bg-rose-600 active:bg-rose-700 text-white font-bold py-4 rounded-xl transition-colors shadow-sm flex items-center justify-center gap-2" @click="resolveDispute(d.id, 'seller')" :disabled="loading">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Resolve: Seller Wins
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@endsection
