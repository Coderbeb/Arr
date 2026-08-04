@extends('layouts.admin')

@section('title', 'Admin Control Panel')

@section('content')

    <!-- Top Loading Indicator -->
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: 2rem; gap: 1rem;">
        <div>
            <h1 style="font-size: clamp(1.5rem, 4vw, 2rem); color: #fff; margin: 0;">
                <span x-show="activeTab === 'analytics'">📈 Platform Analytics</span>
                <span x-show="activeTab === 'users'">👥 User Management</span>
                <span x-show="activeTab === 'settings'">⚙️ Global Configuration</span>
                <span x-show="activeTab === 'assistance'">🛡️ Support Queue</span>
                <span x-show="activeTab === 'logs'">📜 System Audit Logs</span>
            </h1>
        </div>
        <div x-show="loading" class="spinner" style="width: 24px; height: 24px; border-width: 3px; border-top-color: var(--gold);"></div>
    </div>

    <!-- Feedback Messages -->
    <template x-if="message">
        <div class="toast toast-success" style="position: static; transform: none; margin-bottom: 1.5rem; width: 100%;" x-text="message"></div>
    </template>
    <template x-if="errorMsg">
        <div class="toast toast-error" style="position: static; transform: none; margin-bottom: 1.5rem; width: 100%;" x-text="errorMsg"></div>
    </template>

    <!-- Analytics Tab -->
    <div x-show="activeTab === 'analytics'" class="fade-in">
        <template x-if="analytics">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                <!-- Financials -->
                <div class="card" style="border: 1px solid rgba(255, 215, 0, 0.3); background: rgba(255, 215, 0, 0.02);">
                    <div style="font-size: 2.2rem; font-weight: 700; color: var(--gold);">₹<span x-text="parseFloat(analytics.financials.total_commission).toFixed(2)"></span></div>
                    <div class="balance-label" style="font-size: 1rem; color: #bbb;">Total Commission Generated</div>
                </div>
                <div class="card" style="border: 1px solid rgba(255, 150, 0, 0.3); background: rgba(255, 150, 0, 0.02);">
                    <div style="font-size: 2.2rem; font-weight: 700; color: var(--warning);">₹<span x-text="parseFloat(analytics.financials.total_liquidity).toFixed(2)"></span></div>
                    <div class="balance-label" style="font-size: 1rem; color: #bbb;">Total Escrow Liquidity (Locked)</div>
                </div>
                <div class="card" style="border: 1px solid rgba(59, 130, 246, 0.3); background: rgba(59, 130, 246, 0.02);">
                    <div style="font-size: 2.2rem; font-weight: 700; color: var(--info);">₹<span x-text="parseFloat(analytics.financials.total_wallet_balance).toFixed(2)"></span></div>
                    <div class="balance-label" style="font-size: 1rem; color: #bbb;">Total Users Wallet Balance</div>
                </div>
                
                <!-- Users -->
                <div class="card" style="border: 1px solid rgba(0, 150, 255, 0.3); background: rgba(0, 150, 255, 0.02);">
                    <div style="font-size: 2.2rem; font-weight: 700; color: var(--info);" x-text="analytics.users.total"></div>
                    <div class="balance-label" style="font-size: 1rem; color: #bbb;">Total Registered Users</div>
                    <div style="margin-top: 1.5rem; font-size: 0.95rem; color: #888; display: flex; justify-content: space-between;">
                        <span>Active: <strong style="color: var(--success);" x-text="analytics.users.active"></strong></span>
                        <span>Suspended: <strong style="color: var(--warning);" x-text="analytics.users.suspended"></strong></span>
                        <span>Banned: <strong style="color: var(--danger);" x-text="analytics.users.banned"></strong></span>
                    </div>
                </div>

                <!-- Trades -->
                <div class="card" style="border: 1px solid rgba(0, 200, 100, 0.3); background: rgba(0, 200, 100, 0.02);">
                    <div style="font-size: 2.2rem; font-weight: 700; color: var(--success);" x-text="analytics.trades.completed"></div>
                    <div class="balance-label" style="font-size: 1rem; color: #bbb;">Completed Trades</div>
                    <div style="margin-top: 1.5rem; font-size: 0.95rem; color: #888; display: flex; justify-content: space-between;">
                        <span>Active: <strong style="color: var(--info);" x-text="analytics.trades.active"></strong></span>
                        <span>Disputed: <strong style="color: var(--danger);" x-text="analytics.trades.disputed"></strong></span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Platform Settings Tab -->
    <div x-show="activeTab === 'settings'" class="card fade-in">
        <form @submit.prevent="saveSettings">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 2rem;">
                
                <div class="input-group" style="grid-column: 1 / -1;">
                    <label class="input-label" style="color: var(--gold);">Global Announcement Banner (Leave empty to disable)</label>
                    <input type="text" class="input" style="background: rgba(255,255,255,0.05); font-size: 1.1rem; padding: 1rem;" placeholder="e.g. Platform maintenance tonight at 12 AM" x-model="settings.global_announcement">
                </div>

                <div class="input-group">
                    <label class="input-label">Registration Status</label>
                    <select class="input" style="background: rgba(255,255,255,0.05);" x-model="settings.registration_open">
                        <option value="1">Open (Enabled)</option>
                        <option value="0">Closed (Disabled)</option>
                    </select>
                </div>

                <div class="input-group">
                    <label class="input-label">Platform Commission (%)</label>
                    <input type="number" step="0.01" min="0" max="50" class="input" style="background: rgba(255,255,255,0.05);" x-model="settings.commission_percent" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Max Daily Earning (₹)</label>
                    <input type="number" step="0.01" min="0" class="input" style="background: rgba(255,255,255,0.05);" x-model="settings.max_daily_earning" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Max Weekly Earning (₹)</label>
                    <input type="number" step="0.01" min="0" class="input" style="background: rgba(255,255,255,0.05);" x-model="settings.max_weekly_earning" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Trade Accept Time (Mins)</label>
                    <input type="number" min="1" class="input" style="background: rgba(255,255,255,0.05);" x-model="settings.trade_accept_minutes" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Payment Timer (Mins)</label>
                    <input type="number" min="1" class="input" style="background: rgba(255,255,255,0.05);" x-model="settings.payment_timer_minutes" required>
                </div>

                <div class="input-group">
                    <label class="input-label">Dispute Proof Time (Mins)</label>
                    <input type="number" min="1" class="input" style="background: rgba(255,255,255,0.05);" x-model="settings.dispute_proof_minutes" required>
                </div>
                
            </div>

            <div style="margin-top: 2.5rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1.5rem; display: flex; justify-content: flex-end;">
                <button type="submit" class="btn btn-primary btn-lg" :disabled="loading" style="padding: 1rem 3rem; font-size: 1.1rem;">
                    <span x-show="!loading">Save Settings</span>
                    <span x-show="loading">Saving...</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Users Management Tab -->
    <div x-show="activeTab === 'users'" class="card fade-in">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <p style="color: var(--text-muted); margin: 0; font-size: 1.1rem;">Manage all registered accounts</p>
            <button class="btn btn-primary" @click="showStaffModal = true">➕ Create Support Staff</button>
        </div>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 900px;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(255,215,0,0.2); color: var(--gold); font-size: 0.9rem; text-transform: uppercase;">
                        <th style="padding: 1rem 0.75rem;">NAME / MOBILE</th>
                        <th style="padding: 1rem 0.75rem;">ROLE</th>
                        <th style="padding: 1rem 0.75rem;">WALLET BAL</th>
                        <th style="padding: 1rem 0.75rem;">JOINED</th>
                        <th style="padding: 1rem 0.75rem;">STATUS ACTION</th>
                        <th style="padding: 1rem 0.75rem;">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="u in users" :key="u.id">
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,215,0,0.02)'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 1rem 0.75rem;">
                                <div style="font-weight: 600; font-size: 1.05rem;" x-text="u.full_name"></div>
                                <div style="font-size: 0.85rem; color: #888;" x-text="u.mobile_number"></div>
                            </td>
                            <td style="padding: 1rem 0.75rem;">
                                <span class="badge" :class="u.role === 'super_admin' ? 'badge-gold' : (u.role === 'assistance' ? 'badge-warning' : 'badge-info')" x-text="u.role.replace('_', ' ').toUpperCase()"></span>
                            </td>
                            <td style="padding: 1rem 0.75rem; font-weight: 700; color: var(--gold); font-size: 1.1rem;" x-text="'₹' + parseFloat(u.wallet_balance).toFixed(2)"></td>
                            <td style="padding: 1rem 0.75rem; font-size: 0.9rem; color: #aaa;" x-text="formatDate(u.created_at)"></td>
                            <td style="padding: 1rem 0.75rem;">
                                <select class="input" style="padding: 0.5rem 0.8rem; font-size: 0.9rem; width: auto; background: rgba(0,0,0,0.5);" 
                                    x-model="u.status" 
                                    @change="updateUserStatus(u.id, u.status)"
                                    :disabled="u.role === 'super_admin' && u.id === '{{ Auth::id() }}'">
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="banned">Banned</option>
                                </select>
                            </td>
                            <td style="padding: 1rem 0.75rem;">
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <button class="btn btn-secondary btn-sm" style="border: 1px solid rgba(255,255,255,0.2);" @click="openWalletModal(u)">Manage Wallet</button>
                                    <button class="btn btn-danger btn-sm" x-show="u.role !== 'super_admin'" @click="deleteUser(u.id)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="users.length === 0 && !loading">
                        <td colspan="6" style="padding: 3rem; text-align: center; color: #888; font-size: 1.1rem;">No users found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Audit Logs Tab -->
    <div x-show="activeTab === 'logs'" class="card fade-in">
        <p style="color: var(--text-muted); margin-bottom: 1.5rem; font-size: 1.1rem;">Immutable record of all admin actions</p>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 900px;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); color: #888; font-size: 0.85rem; text-transform: uppercase;">
                        <th style="padding: 1rem 0.75rem;">TIMESTAMP</th>
                        <th style="padding: 1rem 0.75rem;">ADMIN ID</th>
                        <th style="padding: 1rem 0.75rem;">ACTION</th>
                        <th style="padding: 1rem 0.75rem;">TARGET (Type/ID)</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="log in auditLogs" :key="log.id">
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.95rem;">
                            <td style="padding: 1.2rem 0.75rem; color: #aaa;" x-text="formatDate(log.created_at)"></td>
                            <td style="padding: 1.2rem 0.75rem; font-family: monospace; font-size: 0.9rem;" x-text="log.admin_id.substring(0, 13) + '...'"></td>
                            <td style="padding: 1.2rem 0.75rem;">
                                <span class="badge badge-warning" x-text="log.action.replace(/_/g, ' ').toUpperCase()"></span>
                            </td>
                            <td style="padding: 1.2rem 0.75rem;">
                                <div style="font-weight: 600; text-transform: uppercase; font-size: 0.8rem; color: var(--gold);" x-text="log.target_type"></div>
                                <div style="font-family: monospace; font-size: 0.85rem; color: #888;" x-text="log.target_id || 'N/A'"></div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="auditLogs.length === 0 && !loading">
                        <td colspan="4" style="padding: 3rem; text-align: center; color: #888; font-size: 1.1rem;">No audit logs available.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Support Queue Tab -->
    <div x-show="activeTab === 'assistance'" class="card fade-in">
        <p style="color: var(--text-muted); margin-bottom: 2rem; font-size: 1.1rem;">Review buyer & seller video proofs and AI confidence scores</p>

        <template x-if="loading">
            <div style="text-align: center; padding: 3rem;"><div class="spinner" style="margin: 0 auto; width: 40px; height: 40px;"></div></div>
        </template>

        <template x-if="!loading && disputes.length === 0">
            <div style="text-align: center; padding: 4rem; color: #888; border: 1px dashed rgba(255,255,255,0.1); border-radius: var(--radius-md);">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✨</div>
                <div style="font-size: 1.2rem;">No active disputes in queue. All clean!</div>
            </div>
        </template>

        <template x-if="!loading && disputes.length > 0">
            <div>
                <template x-for="d in disputes" :key="d.id">
                    <div style="border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); border-radius: var(--radius-md); padding: 2rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <h4 style="font-size: 1.2rem; color: #fff;">Dispute #<span x-text="d.id.slice(0, 8)"></span> <span style="color: var(--gold);">(Trade ₹<span x-text="d.trade ? parseFloat(d.trade.amount).toFixed(2) : ''"></span>)</span></h4>
                            <span class="badge badge-danger" style="font-size: 0.9rem;" x-text="d.status"></span>
                        </div>

                        <div class="dispute-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                            <!-- Buyer Proof -->
                            <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: var(--radius-sm); border: 1px solid rgba(255,255,255,0.05);">
                                <label class="input-label" style="color: var(--success); font-size: 1.1rem;">Buyer Proof</label>
                                <div style="margin: 1rem 0;">
                                    <template x-if="d.buyer_screen_recording_url">
                                        <a :href="d.buyer_screen_recording_url" target="_blank" class="btn btn-ghost" style="border: 1px solid var(--success); color: var(--success); width: 100%;">📹 View Buyer Video</a>
                                    </template>
                                    <template x-if="!d.buyer_screen_recording_url">
                                        <div style="color: #888; text-align: center; padding: 0.5rem; background: rgba(0,0,0,0.5); border-radius: var(--radius-sm);">No video uploaded</div>
                                    </template>
                                </div>
                                <div style="font-size: 1.1rem; text-align: center; margin-top: 1rem;">
                                    AI Score: <strong :style="{ color: d.buyer_ai_score > 70 ? 'var(--success)' : 'var(--gold)' }" x-text="d.buyer_ai_score ? d.buyer_ai_score + '%' : 'Pending'"></strong>
                                </div>
                            </div>

                            <!-- Seller Proof -->
                            <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: var(--radius-sm); border: 1px solid rgba(255,255,255,0.05);">
                                <label class="input-label" style="color: var(--danger); font-size: 1.1rem;">Seller Proof</label>
                                <div style="margin: 1rem 0;">
                                    <template x-if="d.seller_screen_recording_url">
                                        <a :href="d.seller_screen_recording_url" target="_blank" class="btn btn-ghost" style="border: 1px solid var(--danger); color: var(--danger); width: 100%;">📹 View Seller Video</a>
                                    </template>
                                    <template x-if="!d.seller_screen_recording_url">
                                        <div style="color: #888; text-align: center; padding: 0.5rem; background: rgba(0,0,0,0.5); border-radius: var(--radius-sm);">No video uploaded</div>
                                    </template>
                                </div>
                                <div style="font-size: 1.1rem; text-align: center; margin-top: 1rem;">
                                    AI Score: <strong :style="{ color: d.seller_ai_score > 70 ? 'var(--success)' : 'var(--gold)' }" x-text="d.seller_ai_score ? d.seller_ai_score + '%' : 'Pending'"></strong>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 1rem;">
                            <button class="btn btn-success btn-lg" style="flex: 1;" @click="resolveDispute(d.id, 'buyer')">
                                🏆 Resolve: Buyer Wins
                            </button>
                            <button class="btn btn-danger btn-lg" style="flex: 1;" @click="resolveDispute(d.id, 'seller')">
                                🏆 Resolve: Seller Wins
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- Create Staff Modal -->
    <div x-show="showStaffModal" class="sidebar-overlay open" style="z-index: 100;" @click="showStaffModal = false"></div>
    <div x-show="showStaffModal" class="card fade-in" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 101; width: 90%; max-width: 450px; background: #111; border: 1px solid rgba(255,215,0,0.3); box-shadow: 0 10px 40px rgba(0,0,0,0.8);">
        <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem;">
            <h3 style="color: #fff;">Create Support Staff</h3>
            <button class="btn btn-ghost" style="padding: 0; color: #888;" @click="showStaffModal = false">✕</button>
        </div>
        <template x-if="errorMsg">
            <div style="background: rgba(255,0,0,0.1); color: var(--danger); padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; border: 1px solid rgba(255,0,0,0.3); font-size: 0.9rem;" x-text="errorMsg"></div>
        </template>
        <form @submit.prevent="createStaff">
            <div class="input-group">
                <label class="input-label" style="color: #ccc;">Full Name</label>
                <input type="text" class="input" style="background: rgba(0,0,0,0.5);" x-model="staffForm.full_name" required>
            </div>
            <div class="input-group">
                <label class="input-label" style="color: #ccc;">Mobile Number</label>
                <input type="text" class="input" style="background: rgba(0,0,0,0.5);" x-model="staffForm.mobile_number" required>
            </div>
            <div class="input-group">
                <label class="input-label" style="color: #ccc;">Password</label>
                <input type="password" class="input" style="background: rgba(0,0,0,0.5);" x-model="staffForm.password" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary btn-full btn-lg" style="margin-top: 1.5rem;" :disabled="loading">
                <span x-show="!loading">Create Staff Account</span>
                <span x-show="loading">Creating...</span>
            </button>
        </form>
    </div>

    <!-- Manage Wallet Modal -->
    <div x-show="showWalletModal" class="sidebar-overlay open" style="z-index: 100;" @click="showWalletModal = false"></div>
    <div x-show="showWalletModal" class="card fade-in" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 101; width: 90%; max-width: 450px; background: #111; border: 1px solid rgba(255,215,0,0.3); box-shadow: 0 10px 40px rgba(0,0,0,0.8);">
        <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem;">
            <h3 style="color: #fff;">Manage Wallet</h3>
            <button class="btn btn-ghost" style="padding: 0; color: #888;" @click="showWalletModal = false">✕</button>
        </div>
        <template x-if="errorMsg">
            <div style="background: rgba(255,0,0,0.1); color: var(--danger); padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; border: 1px solid rgba(255,0,0,0.3); font-size: 0.9rem;" x-text="errorMsg"></div>
        </template>
        <div style="margin-bottom: 1.5rem; color: var(--text-muted); font-size: 1rem; background: rgba(0,0,0,0.3); padding: 1rem; border-radius: var(--radius-sm);">
            User: <strong style="color: var(--gold); font-size: 1.1rem;" x-text="walletForm.full_name"></strong>
        </div>
        <form @submit.prevent="adjustWallet">
            <div class="input-group">
                <label class="input-label" style="color: #ccc;">Action</label>
                <select class="input" style="background: rgba(0,0,0,0.5);" x-model="walletForm.action">
                    <option value="add">Add Funds (Credit)</option>
                    <option value="deduct">Deduct Funds (Debit)</option>
                </select>
            </div>
            <div class="input-group">
                <label class="input-label" style="color: #ccc;">Amount (₹)</label>
                <input type="number" step="0.01" min="0.01" class="input" style="background: rgba(0,0,0,0.5);" x-model="walletForm.amount" required>
            </div>
            <div class="input-group">
                <label class="input-label" style="color: #ccc;">Admin Note (Reason)</label>
                <input type="text" class="input" style="background: rgba(0,0,0,0.5);" x-model="walletForm.note" placeholder="e.g. Refund for failed trade">
            </div>
            <button type="submit" class="btn btn-full btn-lg" :class="walletForm.action === 'add' ? 'btn-primary' : 'btn-danger'" style="margin-top: 1.5rem;" :disabled="loading">
                <span x-show="!loading">Confirm Adjustment</span>
                <span x-show="loading">Processing...</span>
            </button>
        </form>
    </div>

@endsection
