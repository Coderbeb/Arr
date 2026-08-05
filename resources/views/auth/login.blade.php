@extends('layouts.guest')

@section('title', 'Login — Arr Wallet')

@section('content')
<div class="w-full max-w-5xl mx-auto p-4 lg:p-8 animate-fade-in-up relative z-10">
    <div class="flex flex-col lg:flex-row bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl rounded-3xl overflow-hidden shadow-2xl border border-white/40 dark:border-white/10 min-h-[600px]">
        
        <!-- Left Side (Hero/Branding) -->
        <div class="hidden lg:flex lg:w-5/12 bg-gradient-to-br from-gold-500 to-amber-600 p-12 text-white flex-col justify-between relative overflow-hidden">
            <div class="absolute inset-0 bg-black/10"></div>
            <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-white/20 rounded-full blur-3xl"></div>
            
            <div class="relative z-10">
                <div class="text-3xl font-bold font-outfit mb-2 flex items-center gap-3">
                    <span class="text-4xl bg-white text-gold-600 w-12 h-12 flex items-center justify-center rounded-xl shadow-lg">🪙</span>
                    Arr Wallet
                </div>
            </div>
            
            <div class="relative z-10">
                <h1 class="text-4xl font-bold font-outfit leading-tight mb-6">Trade Smart.<br>Earn Together.</h1>
                <p class="text-white/80 text-lg leading-relaxed">Join a trusted community where buying and selling is fast, simple, and completely secure. Start your journey to financial success today.</p>
            </div>
        </div>

        <!-- Right Side (Form) -->
        <div class="w-full lg:w-7/12 p-6 sm:p-10 lg:p-16 flex flex-col justify-center">
            
            <div class="lg:hidden mb-8 flex items-center gap-2">
                <span class="text-3xl">🪙</span>
                <span class="text-xl font-bold font-outfit text-gray-900 dark:text-white">Arr Wallet</span>
            </div>

            <div class="mb-8">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">Welcome back!</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base">Please sign in to access your dashboard.</p>
            </div>

            <div x-data="{
                mobile_number: '',
                password: '',
                errorMessage: '',
                loading: false,
                async handleLogin() {
                    this.errorMessage = '';
                    this.loading = true;
                    try {
                        const res = await fetch('/api/auth/login', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                mobile_number: this.mobile_number,
                                password: this.password
                            })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.errorMessage = data.error || 'Login failed';
                        } else {
                            window.location.href = '/dashboard';
                        }
                    } catch (e) {
                        this.errorMessage = 'Network error. Please try again.';
                    } finally {
                        this.loading = false;
                        setTimeout(() => this.errorMessage = '', 4000);
                    }
                }
            }">
                <template x-if="errorMessage">
                    <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 p-4 rounded-xl mb-6 font-medium animate-fade-in" x-text="errorMessage"></div>
                </template>

                <form @submit.prevent="handleLogin" class="space-y-5 sm:space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Mobile Number</label>
                        <input type="tel" class="w-full px-4 py-3 sm:px-5 sm:py-4 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-gold-500/50 focus:border-gold-500 transition-all font-medium text-base sm:text-lg" placeholder="e.g. 9876543210" x-model="mobile_number" required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Password</label>
                        <input type="password" class="w-full px-4 py-3 sm:px-5 sm:py-4 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-gold-500/50 focus:border-gold-500 transition-all font-medium text-base sm:text-lg" placeholder="••••••••" x-model="password" required>
                    </div>

                    <button type="submit" class="w-full bg-gold-500 hover:bg-gold-600 text-white font-bold text-base sm:text-lg py-3 sm:py-4 px-6 rounded-xl shadow-lg shadow-gold-500/30 hover:shadow-gold-500/50 transition-all transform hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-70 disabled:cursor-not-allowed disabled:transform-none" :disabled="loading">
                        <span x-show="!loading">Sign In to Dashboard</span>
                        <span x-show="loading" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Authenticating...
                        </span>
                    </button>

                    <div class="text-center mt-8 text-gray-500 dark:text-gray-400">
                        Don't have an account? 
                        <a href="/register" class="text-gold-600 dark:text-gold-400 font-bold hover:underline ml-1">Create Account</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
