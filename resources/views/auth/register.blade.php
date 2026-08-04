@extends('layouts.guest')

@section('title', 'Register — Arr Wallet')

@section('content')
<div class="auth-page">
    <div class="auth-split-left">
        <h1>Your Gateway to Secure Trading</h1>
        <p>Step into the future of digital transactions. Enjoy total peace of mind with our advanced escrow protection, automated commission tracking, and lightning-fast settlements.</p>
    </div>

    <div class="auth-split-right">
        <div class="auth-card" x-data="{
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
                }
            }
        }">
            <div class="auth-logo">
                <div class="auth-logo-text">🪙 Arr Wallet</div>
                <div class="auth-logo-sub">Create a new account</div>
            </div>

            <template x-if="errorMessage">
                <div class="toast toast-error" style="position: static; transform: none; margin-bottom: 1rem; width: 100%;">
                    <span x-text="errorMessage"></span>
                </div>
            </template>

            <form @submit.prevent="handleRegister">
                <div class="input-group">
                    <label class="input-label">Full Name</label>
                    <input type="text" class="input" placeholder="e.g. Rahul Sharma" x-model="full_name" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Mobile Number</label>
                    <input type="tel" class="input" placeholder="e.g. 9876543210" x-model="mobile_number" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Date of Birth</label>
                    <input type="date" class="input" x-model="date_of_birth" required>
                </div>


                <div class="input-group">
                    <label class="input-label">Password</label>
                    <input type="password" class="input" placeholder="••••••••" x-model="password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top: 1rem;" :disabled="loading">
                    <span x-show="!loading">Create Account</span>
                    <span x-show="loading" class="spinner"></span>
                </button>

                <div style="text-align: center; margin-top: 1.5rem; color: var(--text-muted); font-size: 0.9rem;">
                    Already have an account? <a href="/login" style="color: var(--gold); text-decoration: none; font-weight: 600;">Sign In</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
