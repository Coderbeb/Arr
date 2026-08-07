@extends('layouts.app')

@section('content')
<div x-data="referralDashboard()" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in pb-24">
    
    <!-- Header -->
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">Refer & Earn</h1>
    </div><template x-if="loading && !referral_code">
        <div class="space-y-8 animate-pulse">
            <!-- Skeleton for Code, Link & Stats -->
            <div class="flex flex-col gap-6 lg:flex-row lg:items-stretch">
                <div class="w-full lg:w-1/3 bg-gray-200 dark:bg-white/5 rounded-2xl h-64"></div>
                <div class="w-full lg:w-2/3 grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div class="bg-gray-200 dark:bg-white/5 rounded-2xl h-32 col-span-2 sm:col-span-1"></div>
                    <div class="bg-gray-200 dark:bg-white/5 rounded-2xl h-32"></div>
                    <div class="bg-gray-200 dark:bg-white/5 rounded-2xl h-32"></div>
                </div>
            </div>
            
            <!-- Skeleton for Milestones -->
            <div>
                <div class="h-6 bg-gray-200 dark:bg-white/5 rounded w-48 mb-4"></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 sm:gap-4">
                    <div class="bg-gray-200 dark:bg-white/5 rounded-2xl h-40"></div>
                    <div class="bg-gray-200 dark:bg-white/5 rounded-2xl h-40"></div>
                    <div class="bg-gray-200 dark:bg-white/5 rounded-2xl h-40"></div>
                    <div class="bg-gray-200 dark:bg-white/5 rounded-2xl h-40"></div>
                </div>
            </div>
        </div>
    </template>

    <template x-if="!loading || referral_code">
        <div class="space-y-8">
            
            <!-- Compact Code & Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Referral Code Minimal Card -->
                <div class="bg-gradient-to-r from-gray-900 to-black dark:from-black dark:to-gray-900 rounded-2xl p-4 text-white shadow-xl flex items-center justify-between border border-gray-800">
                    <div>
                        <div class="text-gray-400 text-xs font-bold tracking-widest uppercase mb-1">Your Code</div>
                        <div class="text-2xl font-black tracking-widest text-indigo-400" x-text="referral_code"></div>
                    </div>
                    <button @click="copyLink" class="bg-white/10 hover:bg-white/20 text-white px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 text-sm font-bold">
                        <span x-show="!copied">Copy Link</span>
                        <span x-show="copied" class="text-emerald-400">Copied!</span>
                    </button>
                </div>

                <!-- Minimal Stats Row -->
                <div class="flex gap-2">
                    <div class="flex-1 bg-white dark:bg-white/5 rounded-2xl p-3 border border-gray-100 dark:border-white/10 flex flex-col justify-center text-center shadow-sm">
                        <div class="text-[10px] sm:text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Total</div>
                        <div class="text-xl sm:text-2xl font-black text-gray-900 dark:text-white" x-text="stats.total"></div>
                    </div>
                    <div class="flex-1 bg-emerald-50 dark:bg-emerald-500/10 rounded-2xl p-3 border border-emerald-100 dark:border-emerald-500/20 flex flex-col justify-center text-center shadow-sm">
                        <div class="text-[10px] sm:text-xs font-bold text-emerald-600 dark:text-emerald-500 uppercase tracking-wider mb-1">Done</div>
                        <div class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400" x-text="stats.completed"></div>
                    </div>
                    <div class="flex-1 bg-amber-50 dark:bg-amber-500/10 rounded-2xl p-3 border border-amber-100 dark:border-amber-500/20 flex flex-col justify-center text-center shadow-sm">
                        <div class="text-[10px] sm:text-xs font-bold text-amber-600 dark:text-amber-500 uppercase tracking-wider mb-1">Wait</div>
                        <div class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-500" x-text="stats.pending"></div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="flex p-1 space-x-1 bg-gray-100 dark:bg-white/5 rounded-xl w-full mx-auto mb-4 mt-6">
                <button @click="activeTab = 'instructions'" :class="activeTab === 'instructions' ? 'bg-white dark:bg-black/40 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'" class="flex-1 px-2 py-2.5 text-xs sm:text-sm font-bold rounded-lg transition-all">Guide</button>
                <button @click="activeTab = 'track'" :class="activeTab === 'track' ? 'bg-white dark:bg-black/40 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'" class="flex-1 px-2 py-2.5 text-xs sm:text-sm font-bold rounded-lg transition-all">Track</button>
                <button @click="activeTab = 'bonus'" :class="activeTab === 'bonus' ? 'bg-white dark:bg-black/40 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'" class="flex-1 px-2 py-2.5 text-xs sm:text-sm font-bold rounded-lg transition-all flex items-center justify-center gap-1">
                    Bonus
                    <span x-show="eligiblePost10Claims() > 0 || (stats.completed >= 3 && !claims.includes('tier_1'))" class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                </button>
            </div>

            <!-- Instructions Tab -->
            <div x-show="activeTab === 'instructions'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="bg-white dark:bg-white/5 rounded-2xl p-5 sm:p-6 border border-gray-100 dark:border-white/10" style="display: none;">
                <h3 class="font-bold text-gray-900 dark:text-white mb-5 text-sm uppercase tracking-wider">How it works</h3>
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-black text-lg shrink-0">1</div>
                        <div>
                            <div class="font-bold text-gray-900 dark:text-white text-base mb-1">Share Code</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Send your unique code or link to your friends.</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black text-lg shrink-0">2</div>
                        <div>
                            <div class="font-bold text-gray-900 dark:text-white text-base mb-1">Friends Buy</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">They sign up and complete their first buy order.</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-gold-50 dark:bg-gold-500/10 text-gold-600 dark:text-gold-400 flex items-center justify-center font-black text-lg shrink-0">3</div>
                        <div>
                            <div class="font-bold text-gray-900 dark:text-white text-base mb-1">Earn Coins</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Unlock milestone rewards and claim your coins.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rewards Milestones (Bonus Tab) -->
            <div x-show="activeTab === 'bonus'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">
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

            <!-- Referrals List (Track Tab) -->
            <div x-show="activeTab === 'track'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="bg-white dark:bg-black/20 rounded-2xl border border-gray-100 dark:border-white/5 overflow-hidden">
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
@endsection

@push('scripts')
<script>
ArrRegister('referralDashboard', () => ({
        activeTab: 'instructions',
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
                const data = await ArrCache.fetch('/api/referrals', 5000);
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
</script>
@endpush
