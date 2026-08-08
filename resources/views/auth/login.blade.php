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
            
            <div class="lg:hidden mb-8 flex flex-col items-center justify-center gap-3 bg-gradient-to-br from-gold-500/10 to-amber-600/5 p-6 rounded-2xl border border-gold-500/20">
                <span class="text-5xl bg-white dark:bg-deep-800 text-gold-600 w-16 h-16 flex items-center justify-center rounded-2xl shadow-lg border border-gold-200 dark:border-gold-500/30">🪙</span>
                <span class="text-2xl font-bold font-outfit text-gray-900 dark:text-white tracking-tight">Arr Wallet</span>
            </div>

            <div class="mb-8 text-center lg:text-left">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">Welcome back!</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base">Please sign in to access your dashboard.</p>
            </div>

            <div x-data="{
                mobile_number: '',
                password: '',
                showPassword: false,
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
                    <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 p-4 rounded-xl mb-6 font-medium animate-fade-in text-sm sm:text-base" x-text="errorMessage"></div>
                </template>

                <form @submit.prevent="handleLogin" class="space-y-5 sm:space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Mobile Number</label>
                        <input type="tel" class="w-full px-4 py-3 sm:px-5 sm:py-4 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-gold-500/50 focus:border-gold-500 transition-all font-medium text-base sm:text-lg" placeholder="e.g. 9876543210" x-model="mobile_number" required>
                    </div>

                    <div class="relative">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Password</label>
                        <input :type="showPassword ? 'text' : 'password'" class="w-full px-4 py-3 sm:px-5 sm:py-4 pr-12 sm:pr-14 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-gold-500/50 focus:border-gold-500 transition-all font-medium text-base sm:text-lg" placeholder="••••••••" x-model="password" required>
                        <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-[36px] sm:top-[44px] text-gray-400 hover:text-gold-500 transition-colors p-1" title="Toggle password visibility">
                            <svg x-show="!showPassword" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <svg x-show="showPassword" class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.51-3.1m2.28-2.28A10.05 10.05 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.05 10.05 0 01-1.51 3.1m-2.28 2.28l-3.2 3.2M3 3l3.2 3.2m14.8 14.8l-3.2-3.2"></path></svg>
                        </button>
                    </div>

                    <div class="flex justify-end -mt-1">
                        <a href="/forgot-password" class="text-sm font-semibold text-red-500 dark:text-red-400 hover:underline">Forgot Password?</a>
                    </div>
                    <button type="submit" class="w-full bg-gold-500 hover:bg-gold-600 text-white font-bold text-base sm:text-lg py-3 sm:py-4 px-6 rounded-xl shadow-lg shadow-gold-500/30 hover:shadow-gold-500/50 transition-all transform hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-70 disabled:cursor-not-allowed disabled:transform-none mt-2" :disabled="loading">
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
