@extends('layouts.guest')

@section('title', 'Forgot Password — Arr Wallet')

@section('content')
<div class="w-full max-w-5xl mx-auto p-4 lg:p-8 animate-fade-in-up relative z-10">
    <div class="flex flex-col lg:flex-row bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl rounded-3xl overflow-hidden shadow-2xl border border-white/40 dark:border-white/10 min-h-[600px]">
        
        <!-- Left Side (Hero/Branding) -->
        <div class="hidden lg:flex lg:w-5/12 bg-gradient-to-br from-red-500 to-rose-700 p-12 text-white flex-col justify-between relative overflow-hidden">
            <div class="absolute inset-0 bg-black/10"></div>
            <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-white/20 rounded-full blur-3xl"></div>
            
            <div class="relative z-10">
                <div class="text-3xl font-bold font-outfit mb-2 flex items-center gap-3">
                    <span class="text-4xl bg-white text-red-600 w-12 h-12 flex items-center justify-center rounded-xl shadow-lg">🔒</span>
                    Arr Wallet
                </div>
            </div>
            
            <div class="relative z-10">
                <h1 class="text-4xl font-bold font-outfit leading-tight mb-6">Reset Your<br>Password</h1>
                <p class="text-white/80 text-lg leading-relaxed">Verify your identity using your date of birth, then set a new password. Your account security is our top priority.</p>
            </div>
        </div>

        <!-- Right Side (Form) -->
        <div class="w-full lg:w-7/12 p-6 sm:p-10 lg:p-16 flex flex-col justify-center">
            
            <div class="lg:hidden mb-8 flex flex-col items-center justify-center gap-3 bg-gradient-to-br from-red-500/10 to-rose-600/5 p-6 rounded-2xl border border-red-500/20">
                <span class="text-5xl bg-white dark:bg-deep-800 text-red-600 w-16 h-16 flex items-center justify-center rounded-2xl shadow-lg border border-red-200 dark:border-red-500/30">🔒</span>
                <span class="text-2xl font-bold font-outfit text-gray-900 dark:text-white tracking-tight">Reset Password</span>
            </div>

            <div class="mb-8 text-center lg:text-left">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">Forgot your password?</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base">Verify your mobile number and date of birth to reset it.</p>
            </div>

            <div x-data="{
                step: 1,
                mobile_number: '',
                date_of_birth: '',
                new_password: '',
                confirm_password: '',
                showPassword: false,
                errorMessage: '',
                successMessage: '',
                loading: false,
                lockedUntil: null,

                get isLocked() {
                    if (!this.lockedUntil) return false;
                    return new Date() < new Date(this.lockedUntil);
                },

                get lockoutText() {
                    if (!this.lockedUntil) return '';
                    const diff = Math.ceil((new Date(this.lockedUntil) - new Date()) / 60000);
                    return diff > 0 ? `Account locked. Try again in ${diff} minute(s).` : '';
                },

                async handleReset() {
                    this.errorMessage = '';
                    this.successMessage = '';

                    if (this.new_password !== this.confirm_password) {
                        this.errorMessage = 'Passwords do not match.';
                        return;
                    }
                    if (this.new_password.length < 6) {
                        this.errorMessage = 'Password must be at least 6 characters.';
                        return;
                    }

                    this.loading = true;
                    try {
                        const res = await fetch('/api/auth/reset-password', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                mobile_number: this.mobile_number,
                                date_of_birth: this.date_of_birth,
                                new_password: this.new_password
                            })
                        });
                        const data = await res.json();

                        if (!res.ok) {
                            this.errorMessage = data.error || 'Reset failed.';
                            if (data.locked_until) {
                                this.lockedUntil = data.locked_until;
                            }
                            // Stay on step 1 if DOB didn't match
                            if (res.status === 401 || res.status === 429) {
                                this.step = 1;
                            }
                        } else {
                            this.successMessage = data.message;
                            this.step = 3;
                        }
                    } catch (e) {
                        this.errorMessage = 'Network error. Please try again.';
                    } finally {
                        this.loading = false;
                    }
                }
            }">
                <!-- Error Message -->
                <template x-if="errorMessage">
                    <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-700 dark:text-red-400 p-4 rounded-xl mb-6 font-medium animate-fade-in text-sm sm:text-base" x-text="errorMessage"></div>
                </template>

                <!-- Success Message -->
                <template x-if="successMessage">
                    <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 text-green-700 dark:text-green-400 p-4 rounded-xl mb-6 font-medium animate-fade-in text-sm sm:text-base" x-text="successMessage"></div>
                </template>

                <!-- Lockout Warning -->
                <template x-if="isLocked">
                    <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-amber-700 dark:text-amber-400 p-4 rounded-xl mb-6 font-medium animate-fade-in text-sm flex items-center gap-2">
                        <span class="text-xl">⏳</span>
                        <span x-text="lockoutText"></span>
                    </div>
                </template>

                <!-- Step 1: Mobile + DOB -->
                <div x-show="step === 1" x-transition>
                    <form @submit.prevent="step = 2" class="space-y-5 sm:space-y-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Mobile Number</label>
                            <input type="tel" class="w-full px-4 py-3 sm:px-5 sm:py-4 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500 transition-all font-medium text-base sm:text-lg" placeholder="e.g. 9876543210" x-model="mobile_number" required>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Date of Birth</label>
                            <input type="date" class="w-full px-4 py-3 sm:px-5 sm:py-4 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500 transition-all font-medium text-base sm:text-lg" x-model="date_of_birth" required>
                            <p class="text-xs text-gray-400 mt-1.5">Enter the same date of birth you used during registration.</p>
                        </div>

                        <button type="submit" class="w-full text-white font-bold text-base sm:text-lg py-3 sm:py-4 px-6 rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-70 disabled:cursor-not-allowed mt-2" style="background: linear-gradient(to right, #ef4444, #e11d48); box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3);" :disabled="isLocked">
                            Verify Identity →
                        </button>

                        <div class="text-center mt-6 text-gray-500 dark:text-gray-400">
                            Remember your password? 
                            <a href="/login" class="text-gold-600 dark:text-gold-400 font-bold hover:underline ml-1">Sign In</a>
                        </div>
                    </form>
                </div>

                <!-- Step 2: New Password -->
                <div x-show="step === 2" x-transition style="display: none;">
                    <form @submit.prevent="handleReset" class="space-y-5 sm:space-y-6">
                        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/20 p-3 rounded-xl mb-2 text-green-700 dark:text-green-400 text-sm font-medium flex items-center gap-2">
                            <span>✅</span> Identity verified for <strong x-text="mobile_number"></strong>. Set your new password below.
                        </div>

                        <div class="relative">
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">New Password</label>
                            <input :type="showPassword ? 'text' : 'password'" class="w-full px-4 py-3 sm:px-5 sm:py-4 pr-12 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500 transition-all font-medium text-base sm:text-lg" placeholder="••••••••" x-model="new_password" required minlength="6">
                            <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-[36px] sm:top-[44px] text-gray-400 hover:text-red-500 transition-colors p-1">
                                <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.05 10.05 0 011.51-3.1m2.28-2.28A10.05 10.05 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.05 10.05 0 01-1.51 3.1m-2.28 2.28l-3.2 3.2M3 3l3.2 3.2m14.8 14.8l-3.2-3.2"></path></svg>
                            </button>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Confirm New Password</label>
                            <input type="password" class="w-full px-4 py-3 sm:px-5 sm:py-4 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-red-500/50 focus:border-red-500 transition-all font-medium text-base sm:text-lg" placeholder="••••••••" x-model="confirm_password" required minlength="6">
                        </div>

                        <div class="flex gap-3">
                            <button type="button" @click="step = 1; errorMessage = ''" class="flex-1 py-3 sm:py-4 px-4 rounded-xl font-bold text-sm sm:text-base border-2 border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition-all">
                                ← Back
                            </button>
                            <button type="submit" class="flex-[2] text-white font-bold text-sm sm:text-base py-3 sm:py-4 px-6 rounded-xl shadow-lg transition-all transform active:scale-95 disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2" style="background: linear-gradient(to right, #ef4444, #e11d48); box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3);" :disabled="loading">
                                <span x-show="!loading">Reset Password</span>
                                <span x-show="loading" class="flex items-center gap-2">
                                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Resetting...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Step 3: Success -->
                <div x-show="step === 3" x-transition style="display: none;">
                    <div class="text-center py-8">
                        <div class="text-6xl mb-4">🎉</div>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Password Reset!</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-8">Your password has been successfully changed. You can now sign in with your new password.</p>
                        <a href="/login" class="inline-block text-white font-bold text-base py-3 px-8 rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5 active:translate-y-0" style="background: linear-gradient(to right, #eab308, #d97706); box-shadow: 0 10px 15px -3px rgba(234, 179, 8, 0.3);">
                            Go to Login →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
