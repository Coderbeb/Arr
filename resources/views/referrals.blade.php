@extends('layouts.app')

@section('content')
<div x-data="referralDashboard()" x-init="init()" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in pb-24">
    
    <!-- Header -->
    <div class="mb-6 sm:mb-8 text-center sm:text-left">
        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white tracking-tight">Refer & Earn</h1>
        <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400 mt-1 sm:mt-2">Invite friends and earn coins when they complete their first buy order.</p>
    </div>

    <template x-if="loading && !referral_code">
        <div class="flex justify-center items-center h-64">
            <svg class="animate-spin h-10 w-10 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        </div>
    </template>

    <template x-if="!loading || referral_code">
        <div class="space-y-8">
            
            <!-- Code, Link & Stats -->
            <div class="flex flex-col gap-6 lg:flex-row lg:items-stretch">
                <!-- Referral Info Card (Mobile First) -->
                <div class="w-full lg:w-1/3 bg-gradient-to-br from-indigo-600 to-purple-700 rounded-2xl p-5 sm:p-6 text-white shadow-lg shadow-indigo-500/20 relative overflow-hidden flex flex-col justify-center">
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="relative z-10 space-y-5">
                        
                        <!-- Referral Code -->
                        <div>
                            <h3 class="text-indigo-100 text-sm font-medium mb-2">Your Referral Code</h3>
                            <div class="flex items-center gap-2">
                                <div class="bg-black/20 px-4 py-2 rounded-xl border border-white/10 text-2xl sm:text-3xl font-black tracking-widest w-full text-center" x-text="referral_code"></div>
                            </div>
                        </div>
                        
                        <!-- Referral Link -->
                        <div>
                            <h3 class="text-indigo-100 text-sm font-medium mb-2">Share Link</h3>
                            <div class="flex items-center gap-2 bg-black/20 rounded-xl p-1.5 backdrop-blur-sm border border-white/10">
                                <input type="text" readonly class="bg-transparent border-none text-white w-full px-2 text-xs sm:text-sm focus:outline-none" :value="shareUrl">
                                <button @click="copyLink" class="bg-white text-indigo-600 hover:bg-indigo-50 px-3 sm:px-4 py-2 rounded-lg text-xs sm:text-sm font-bold transition-colors whitespace-nowrap">
                                    <span x-show="!copied">Copy</span>
                                    <span x-show="copied">Copied!</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="w-full lg:w-2/3 grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div class="bg-white dark:bg-black/20 rounded-2xl p-4 sm:p-5 border border-gray-100 dark:border-white/5 flex flex-col justify-center col-span-2 sm:col-span-1">
                        <div class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm font-semibold mb-1 uppercase tracking-wide">Total</div>
                        <div class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white" x-text="stats.total"></div>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-900/10 rounded-2xl p-4 sm:p-5 border border-emerald-100 dark:border-emerald-500/20 flex flex-col justify-center">
                        <div class="text-emerald-600 dark:text-emerald-400 text-xs sm:text-sm font-semibold mb-1 uppercase tracking-wide">Completed</div>
                        <div class="text-3xl sm:text-4xl font-black text-emerald-700 dark:text-emerald-300 flex items-baseline gap-1 sm:gap-2">
                            <span x-text="stats.completed"></span>
                        </div>
                    </div>
                    <div class="bg-amber-50 dark:bg-amber-900/10 rounded-2xl p-4 sm:p-5 border border-amber-100 dark:border-amber-500/20 flex flex-col justify-center">
                        <div class="text-amber-600 dark:text-amber-400 text-xs sm:text-sm font-semibold mb-1 uppercase tracking-wide">Pending</div>
                        <div class="text-3xl sm:text-4xl font-black text-amber-700 dark:text-amber-300" x-text="stats.pending"></div>
                    </div>
                </div>
            </div>

            <!-- Rewards Milestones -->
            <div>
                <h3 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-white mb-3 sm:mb-4">Milestone Rewards</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
                    
                    <!-- Tier 1 -->
                    <div class="bg-white dark:bg-black/20 rounded-2xl p-5 border border-gray-100 dark:border-white/5 relative overflow-hidden group hover:border-indigo-200 dark:hover:border-indigo-500/30 transition-colors">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="text-xs font-bold text-indigo-500 tracking-wider uppercase mb-1">Tier 1</div>
                                <div class="font-black text-gray-900 dark:text-white text-lg">3 Users</div>
                            </div>
                            <div class="bg-gold-50 text-gold-600 dark:bg-gold-500/20 dark:text-gold-400 px-3 py-1 rounded-full text-sm font-bold flex items-center gap-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9.5 4a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM12 15H8v-2h1v-3H8V8h3v5h1v2z"></path></svg>
                                300
                            </div>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-white/5 rounded-full h-2 mb-4 overflow-hidden">
                            <div class="bg-indigo-500 h-2 rounded-full transition-all duration-1000" :style="'width: ' + Math.min((stats.completed / 3) * 100, 100) + '%'"></div>
                        </div>
                        <button @click="claim('tier_1')" class="w-full py-2.5 rounded-xl font-bold text-sm transition-all" 
                            :class="claims.includes('tier_1') ? 'bg-gray-100 text-gray-400 dark:bg-white/5 cursor-not-allowed' : (stats.completed >= 3 ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-lg shadow-indigo-500/30' : 'bg-gray-100 text-gray-400 dark:bg-white/5 cursor-not-allowed')"
                            :disabled="claims.includes('tier_1') || stats.completed < 3 || claiming">
                            <span x-text="claims.includes('tier_1') ? 'Claimed' : (stats.completed >= 3 ? 'Claim 300 Coins' : 'Locked')"></span>
                        </button>
                    </div>

                    <!-- Tier 2 -->
                    <div class="bg-white dark:bg-black/20 rounded-2xl p-5 border border-gray-100 dark:border-white/5 relative overflow-hidden group hover:border-purple-200 dark:hover:border-purple-500/30 transition-colors">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="text-xs font-bold text-purple-500 tracking-wider uppercase mb-1">Tier 2</div>
                                <div class="font-black text-gray-900 dark:text-white text-lg">6 Users</div>
                            </div>
                            <div class="bg-gold-50 text-gold-600 dark:bg-gold-500/20 dark:text-gold-400 px-3 py-1 rounded-full text-sm font-bold flex items-center gap-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9.5 4a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM12 15H8v-2h1v-3H8V8h3v5h1v2z"></path></svg>
                                500
                            </div>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-white/5 rounded-full h-2 mb-4 overflow-hidden">
                            <div class="bg-purple-500 h-2 rounded-full transition-all duration-1000" :style="'width: ' + Math.min((stats.completed / 6) * 100, 100) + '%'"></div>
                        </div>
                        <button @click="claim('tier_2')" class="w-full py-2.5 rounded-xl font-bold text-sm transition-all" 
                            :class="claims.includes('tier_2') ? 'bg-gray-100 text-gray-400 dark:bg-white/5 cursor-not-allowed' : (stats.completed >= 6 ? 'bg-purple-600 hover:bg-purple-700 text-white shadow-lg shadow-purple-500/30' : 'bg-gray-100 text-gray-400 dark:bg-white/5 cursor-not-allowed')"
                            :disabled="claims.includes('tier_2') || stats.completed < 6 || claiming">
                            <span x-text="claims.includes('tier_2') ? 'Claimed' : (stats.completed >= 6 ? 'Claim 500 Coins' : 'Locked')"></span>
                        </button>
                    </div>

                    <!-- Tier 3 -->
                    <div class="bg-white dark:bg-black/20 rounded-2xl p-5 border border-gray-100 dark:border-white/5 relative overflow-hidden group hover:border-pink-200 dark:hover:border-pink-500/30 transition-colors">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="text-xs font-bold text-pink-500 tracking-wider uppercase mb-1">Tier 3</div>
                                <div class="font-black text-gray-900 dark:text-white text-lg">10 Users</div>
                            </div>
                            <div class="bg-gold-50 text-gold-600 dark:bg-gold-500/20 dark:text-gold-400 px-3 py-1 rounded-full text-sm font-bold flex items-center gap-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9.5 4a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM12 15H8v-2h1v-3H8V8h3v5h1v2z"></path></svg>
                                800
                            </div>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-white/5 rounded-full h-2 mb-4 overflow-hidden">
                            <div class="bg-pink-500 h-2 rounded-full transition-all duration-1000" :style="'width: ' + Math.min((stats.completed / 10) * 100, 100) + '%'"></div>
                        </div>
                        <button @click="claim('tier_3')" class="w-full py-2.5 rounded-xl font-bold text-sm transition-all" 
                            :class="claims.includes('tier_3') ? 'bg-gray-100 text-gray-400 dark:bg-white/5 cursor-not-allowed' : (stats.completed >= 10 ? 'bg-pink-600 hover:bg-pink-700 text-white shadow-lg shadow-pink-500/30' : 'bg-gray-100 text-gray-400 dark:bg-white/5 cursor-not-allowed')"
                            :disabled="claims.includes('tier_3') || stats.completed < 10 || claiming">
                            <span x-text="claims.includes('tier_3') ? 'Claimed' : (stats.completed >= 10 ? 'Claim 800 Coins' : 'Locked')"></span>
                        </button>
                    </div>

                    <!-- Infinite Tier -->
                    <div class="bg-gradient-to-b from-gray-50 to-white dark:from-white/5 dark:to-transparent rounded-2xl p-5 border border-gray-200 dark:border-white/10 relative overflow-hidden group">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="text-xs font-bold text-gray-500 dark:text-gray-400 tracking-wider uppercase mb-1">Bonus Tier</div>
                                <div class="font-black text-gray-900 dark:text-white text-lg">Every User (11+)</div>
                            </div>
                            <div class="bg-gold-50 text-gold-600 dark:bg-gold-500/20 dark:text-gold-400 px-3 py-1 rounded-full text-sm font-bold flex items-center gap-1">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zM9.5 4a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM12 15H8v-2h1v-3H8V8h3v5h1v2z"></path></svg>
                                200
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-4 h-4">
                            <span x-show="stats.completed > 10">You have <strong class="text-gray-900 dark:text-white" x-text="eligiblePost10Claims()"></strong> bonus claims available!</span>
                            <span x-show="stats.completed <= 10">Unlock this after 10 completed referrals.</span>
                        </div>
                        
                        <button @click="claim('post_10_bonus')" class="w-full py-2.5 rounded-xl font-bold text-sm transition-all border-2 border-gray-900 dark:border-white" 
                            :class="eligiblePost10Claims() > 0 ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900 hover:opacity-90' : 'bg-transparent text-gray-400 border-gray-200 dark:border-white/10 dark:text-gray-600 cursor-not-allowed'"
                            :disabled="eligiblePost10Claims() === 0 || claiming">
                            <span x-text="eligiblePost10Claims() > 0 ? 'Claim 200 Coins' : 'Locked'"></span>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Referrals List -->
            <div class="bg-white dark:bg-black/20 rounded-2xl border border-gray-100 dark:border-white/5 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-white/5 flex justify-between items-center">
                    <h3 class="font-bold text-gray-900 dark:text-white">Your Referrals</h3>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Total: <span x-text="stats.total"></span></span>
                </div>
                
                <template x-if="referrals.length === 0">
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-white/10 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <p class="font-medium">No referrals yet.</p>
                        <p class="text-sm mt-1">Share your link to start earning!</p>
                    </div>
                </template>

                <div x-show="referrals.length > 0">
                    <template x-for="ref in referrals" :key="ref.id">
                        <!-- Mobile-friendly list item -->
                        <div class="p-4 sm:p-5 border-b border-gray-100 dark:border-white/5 hover:bg-gray-50 dark:hover:bg-white/5 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-lg">
                                    <span x-text="ref.full_name.charAt(0)"></span>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900 dark:text-white text-sm sm:text-base" x-text="ref.full_name"></div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Joined <span x-text="new Date(ref.created_at).toLocaleDateString()"></span></div>
                                </div>
                            </div>
                            
                            <div>
                                <span x-show="ref.total_trades > 0" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20 w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Completed
                                </span>
                                <span x-show="ref.total_trades === 0" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20 w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Pending Buy
                                </span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('referralDashboard', () => ({
        loading: true,
        claiming: false,
        referral_code: '',
        copied: false,
        stats: { total: 0, completed: 0, pending: 0 },
        claims: [],
        referrals: [],

        get shareUrl() {
            return window.location.origin + '/register?ref=' + this.referral_code;
        },

        eligiblePost10Claims() {
            if (this.stats.completed <= 10) return 0;
            const eligible = this.stats.completed - 10;
            const claimed = this.claims.filter(c => c.startsWith('post_10_bonus_')).length;
            return Math.max(0, eligible - claimed);
        },

        async init() {
            try {
                const res = await fetch('/api/referrals', {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.referral_code = data.referral_code;
                this.stats = data.stats;
                this.claims = data.claims || [];
                this.referrals = data.referrals || [];
            } catch (e) {
                console.error("Failed to load referral data", e);
            } finally {
                this.loading = false;
            }
        },

        copyLink() {
            navigator.clipboard.writeText(this.shareUrl);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },

        async claim(milestone) {
            this.claiming = true;
            try {
                const res = await fetch('/api/referrals/claim', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ milestone })
                });
                
                const data = await res.json();
                
                if (res.ok) {
                    alert(data.message);
                    window.dispatchEvent(new CustomEvent('wallet-updated', { detail: { balance: data.wallet_balance } }));
                    // Refresh data
                    await this.init();
                } else {
                    alert(data.error || 'Failed to claim');
                }
            } catch (e) {
                alert('Network error while claiming.');
            } finally {
                this.claiming = false;
            }
        }
    }));
});
</script>
@endsection
