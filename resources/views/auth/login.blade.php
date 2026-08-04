@extends('layouts.guest')

@section('title', 'Login — Arr Wallet')

@section('content')
<div class="auth-page">
    <div class="auth-split-left">
        <h1>Trade Smart. Earn Together.</h1>
        <p>Join a trusted community where buying and selling is fast, simple, and completely secure. Start your journey to financial success today.</p>
    </div>

    <div class="auth-split-right">
        <div class="auth-card" x-data="{
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
                }
            }
        }">
            <div class="auth-logo">
                <div class="auth-logo-text">🪙 Arr Wallet</div>
                <div class="auth-logo-sub">Sign in to your account</div>
            </div>

            <template x-if="errorMessage">
                <div class="toast toast-error" style="position: static; transform: none; margin-bottom: 1rem; width: 100%;">
                    <span x-text="errorMessage"></span>
                </div>
            </template>

            <form @submit.prevent="handleLogin">
                <div class="input-group">
                    <label class="input-label">Mobile Number</label>
                    <input type="tel" class="input" placeholder="e.g. 9876543210" x-model="mobile_number" required>
                </div>


                <div class="input-group">
                    <label class="input-label">Password</label>
                    <input type="password" class="input" placeholder="••••••••" x-model="password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top: 1rem;" :disabled="loading">
                    <span x-show="!loading">Sign In</span>
                    <span x-show="loading" class="spinner"></span>
                </button>

                <div style="text-align: center; margin-top: 1.5rem; color: var(--text-muted); font-size: 0.9rem;">
                    Don't have an account? <a href="/register" style="color: var(--gold); text-decoration: none; font-weight: 600;">Create Account</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
