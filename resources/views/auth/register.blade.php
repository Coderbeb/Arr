@extends('layouts.guest')

@section('title', 'Register — Arr Wallet')

@section('content')
<div class="w-full max-w-5xl mx-auto p-4 lg:p-8 animate-fade-in-up relative z-10">
    <div class="flex flex-col lg:flex-row-reverse bg-white/80 dark:bg-deep-800/80 backdrop-blur-xl rounded-3xl overflow-hidden shadow-2xl border border-white/40 dark:border-white/10 min-h-[600px]">
        
        <!-- Right Side (Hero/Branding) - Reversed for variety -->
        <div class="hidden lg:flex lg:w-5/12 bg-gradient-to-bl from-purple-600 to-indigo-800 p-12 text-white flex-col justify-between relative overflow-hidden">
            <div class="absolute inset-0 bg-black/10"></div>
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-white/20 rounded-full blur-3xl"></div>
            
            <div class="relative z-10">
                <div class="text-3xl font-bold font-outfit mb-2 flex items-center gap-3">
                    <span class="text-4xl bg-white text-indigo-600 w-12 h-12 flex items-center justify-center rounded-xl shadow-lg">🪙</span>
                    Arr Wallet
                </div>
            </div>
            
            <div class="relative z-10">
                <h1 class="text-4xl font-bold font-outfit leading-tight mb-6">Your Gateway to<br>Secure Trading</h1>
                <p class="text-white/80 text-lg leading-relaxed">Step into the future of digital transactions. Enjoy total peace of mind with our advanced escrow protection and lightning-fast settlements.</p>
            </div>
        </div>

        <!-- Left Side (Form) -->
        <div class="w-full lg:w-7/12 p-6 sm:p-10 lg:p-16 flex flex-col justify-center">
            
            <div class="lg:hidden mb-8 flex items-center gap-2">
                <span class="text-3xl">🪙</span>
                <span class="text-xl font-bold font-outfit text-gray-900 dark:text-white">Arr Wallet</span>
            </div>

            <div class="mb-8">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">Create an account</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base">Join thousands of traders today.</p>
            </div>

            <div x-data="{
                mobile_number: '',
                full_name: '',
                date_of_birth: '',
                password: '',
                errorMessage: '',
                loading: false,
                async handleRegister() {
                    this.errorMessage = '';
                    this.loading = true;
                    try {
                        const res = await fetch('/api/auth/register', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                mobile_number: this.mobile_number,
                                full_name: this.full_name,
                                date_of_birth: this.date_of_birth,
                                password: this.password
                            })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.errorMessage = data.error || (data.message ? data.message : 'Registration failed');
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

                <form @submit.prevent="handleRegister" class="space-y-4 sm:space-y-5">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1.5">Full Name</label>
                        <input type="text" class="w-full px-4 py-2.5 sm:px-5 sm:py-3 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium text-base" placeholder="e.g. Rahul Sharma" x-model="full_name" required>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1.5">Mobile Number</label>
                            <input type="tel" class="w-full px-4 py-2.5 sm:px-5 sm:py-3 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium text-base" placeholder="9876543210" x-model="mobile_number" required>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1.5">Date of Birth</label>
                            <input type="date" class="w-full px-4 py-2.5 sm:px-5 sm:py-3 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium text-base" x-model="date_of_birth" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1.5">Password</label>
                        <input type="password" class="w-full px-4 py-2.5 sm:px-5 sm:py-3 rounded-xl bg-gray-50 dark:bg-black/20 border border-gray-200 dark:border-white/10 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500 transition-all font-medium text-base" placeholder="••••••••" x-model="password" required>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-base sm:text-lg py-3 sm:py-4 px-6 rounded-xl shadow-lg shadow-indigo-500/30 hover:shadow-indigo-500/50 transition-all transform hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-70 disabled:cursor-not-allowed disabled:transform-none mt-2" :disabled="loading">
                        <span x-show="!loading">Create Account</span>
                        <span x-show="loading" class="flex items-center justify-center gap-2">
                            <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Creating...
                        </span>
                    </button>

                    <div class="text-center mt-6 text-gray-500 dark:text-gray-400">
                        Already have an account? 
                        <a href="/login" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline ml-1">Sign In</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
