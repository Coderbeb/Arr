<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PlatformSetting;
use App\Models\AdminAuditLog;
use App\Models\WalletTransaction;
use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * GET /api/admin/users
     */
    public function users(Request $request)
    {
        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('mobile_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(50);
        return response()->json($users);
    }

    /**
     * POST /api/admin/users/{user_id}/status
     */
    public function updateUserStatus(Request $request, string $userId)
    {
        $request->validate(['status' => 'required|string|in:active,suspended,banned']);

        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $targetUser = User::findOrFail($userId);
        $targetUser->update(['status' => $request->status]);

        AdminAuditLog::create([
            'id'          => (string) Str::uuid(),
            'admin_id'    => $admin->id,
            'action'      => "update_user_status_{$request->status}",
            'target_type' => 'user',
            'target_id'   => $targetUser->id,
            'created_at'  => Carbon::now(),
        ]);

        return response()->json(['message' => "User status updated to {$request->status}"]);
    }

    /**
     * GET /api/admin/settings
     */
    public function getSettings(Request $request)
    {
        $settings = PlatformSetting::first();
        return response()->json($settings);
    }

    /**
     * POST /api/admin/settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'registration_open'       => 'required|boolean',
            'buy_commission_percent'  => 'required|numeric|min:0|max:50',
            'sell_commission_percent' => 'required|numeric|min:0|max:50',
            'max_daily_earning'       => 'required|numeric|min:0',
            'max_weekly_earning'      => 'required|numeric|min:0',
            'trade_accept_minutes'    => 'required|integer|min:1',
            'payment_timer_minutes'   => 'required|integer|min:1',
            'dispute_proof_minutes'   => 'required|integer|min:1',
            'trade_suspended'         => 'required|boolean',
            'trade_suspended_message' => 'nullable|string|max:255',
            'allowed_trade_amounts'   => 'nullable|string|max:1000',
            'global_announcement'     => 'nullable|string|max:255',
        ]);

        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $settings = PlatformSetting::first();
        if ($settings) {
            $settings->update(array_merge(
                $request->all(),
                ['updated_by' => $admin->id, 'updated_at' => Carbon::now()]
            ));
        } else {
            $settings = PlatformSetting::create(array_merge(
                $request->all(),
                ['id' => (string) Str::uuid(), 'updated_by' => $admin->id]
            ));
        }
        
        // Sync Trade Amounts if provided
        if ($request->has('allowed_trade_amounts')) {
            $amountString = $request->input('allowed_trade_amounts');
            \App\Models\TradeAmount::query()->update(['is_active' => false]);
            if (!empty($amountString)) {
                $amounts = array_map('trim', explode(',', $amountString));
                $sort = 1;
                foreach ($amounts as $amt) {
                    if (is_numeric($amt) && $amt > 0) {
                        \App\Models\TradeAmount::updateOrCreate(
                            ['amount' => (float) $amt],
                            ['is_active' => true, 'sort_order' => $sort++]
                        );
                    }
                }
            }
        }

        AdminAuditLog::create([
            'id'          => (string) Str::uuid(),
            'admin_id'    => $admin->id,
            'action'      => 'update_platform_settings',
            'target_type' => 'platform_setting',
            'target_id'   => $settings->id,
            'metadata'    => $request->all(),
            'created_at'  => Carbon::now(),
        ]);

        return response()->json(['message' => 'Settings updated successfully', 'settings' => $settings]);
    }

    /**
     * POST /api/admin/profile
     */
    public function updateAdminProfile(Request $request)
    {
        $admin = $request->user();
        
        $request->validate([
            'mobile_number' => 'required|string|max:15|unique:users,mobile_number,' . $admin->id,
            'password' => 'nullable|string|min:6',
        ]);

        $admin->mobile_number = $request->mobile_number;
        
        if ($request->filled('password')) {
            $admin->password = Hash::make($request->password);
        }
        
        $admin->save();

        AdminAuditLog::create([
            'id'          => (string) Str::uuid(),
            'admin_id'    => $admin->id,
            'action'      => 'update_admin_profile',
            'target_type' => 'user',
            'target_id'   => $admin->id,
            'metadata'    => ['mobile_number' => $request->mobile_number, 'password_changed' => $request->filled('password')],
            'created_at'  => Carbon::now(),
        ]);

        return response()->json(['message' => 'Profile updated successfully', 'user' => $admin]);
    }

    /**
     * GET /api/admin/audit-logs
     */
    public function auditLogs(Request $request)
    {
        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $logs = AdminAuditLog::orderBy('created_at', 'desc')->limit(100)->get();
        return response()->json($logs);
    }

    /**
     * POST /api/admin/staff/create
     */
    public function createStaff(Request $request)
    {
        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'full_name'     => 'required|string|max:100',
            'mobile_number' => 'required|string|max:15|unique:users',
            'password'      => 'required|string|min:6',
        ]);

        $user = User::create([
            'id'            => (string) Str::uuid(),
            'full_name'     => $request->full_name,
            'mobile_number' => $request->mobile_number,
            'password_hash' => Hash::make($request->password),
            'role'          => 'assistance',
            'status'        => 'active',
            'date_of_birth' => '2000-01-01', // Default DOB for staff
        ]);

        AdminAuditLog::create([
            'id'          => (string) Str::uuid(),
            'admin_id'    => $admin->id,
            'action'      => 'create_assistance_staff',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'created_at'  => Carbon::now(),
        ]);

        return response()->json(['message' => 'Staff account created successfully']);
    }

    /**
     * POST /api/admin/super-account
     */
    public function createSuperAccount(Request $request)
    {
        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'mobile_number' => 'required|string|unique:users',
            'full_name'     => 'required|string|max:100',
            'password'      => 'required|string|min:6',
        ]);

        $user = User::create([
            'id'            => (string) Str::uuid(),
            'mobile_number' => $request->mobile_number,
            'full_name'     => $request->full_name,
            'password_hash' => Hash::make($request->password),
            'role'          => 'super_account',
            'status'        => 'active',
            'date_of_birth' => '2000-01-01',
            'upi_id'        => 'super@upi',
            'wallet_balance'=> 0,
        ]);

        AdminAuditLog::create([
            'id'          => (string) Str::uuid(),
            'admin_id'    => $admin->id,
            'action'      => 'create_super_account',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'created_at'  => Carbon::now(),
        ]);

        return response()->json(['message' => 'Super Account created successfully']);
    }

    /**
     * POST /api/admin/users/{user_id}/wallet-adjust
     */
    public function adjustWallet(Request $request, string $userId)
    {
        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'action' => 'required|in:add,deduct',
            'amount' => 'required|numeric|min:0.01',
            'note'   => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($request, $userId, $admin) {
            $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();
            $balBefore = (float) $user->wallet_balance;
            $amt = (float) $request->amount;

            if ($request->action === 'add') {
                $user->wallet_balance += $amt;
                $desc = "Funds credited by Admin. Note: {$request->note}";
            } else {
                if ($user->wallet_balance < $amt) {
                    return response()->json(['error' => 'Insufficient wallet balance to deduct'], 400);
                }
                $user->wallet_balance -= $amt;
                $desc = "Funds deducted by Admin. Note: {$request->note}";
            }
            $user->save();

            WalletTransaction::create([
                'id'             => (string) Str::uuid(),
                'user_id'        => $user->id,
                'type'           => $request->action === 'add' ? 'admin_credit' : 'admin_debit',
                'amount'         => $amt,
                'balance_before' => $balBefore,
                'balance_after'  => $user->wallet_balance,
                'description_en' => $desc,
                'description_hi' => $desc,
            ]);

            AdminAuditLog::create([
                'id'          => (string) Str::uuid(),
                'admin_id'    => $admin->id,
                'action'      => "wallet_{$request->action}",
                'target_type' => 'user',
                'target_id'   => $user->id,
                'metadata'    => ['amount' => $amt, 'note' => $request->note],
                'created_at'  => Carbon::now(),
            ]);

            return response()->json(['message' => 'Wallet adjusted successfully', 'new_balance' => $user->wallet_balance]);
        });
    }

    public function deleteUser(Request $request, $user_id)
    {
        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        if ($admin->id === $user_id) {
            return response()->json(['error' => 'Cannot delete yourself'], 400);
        }

        try {
            DB::transaction(function () use ($user_id, $admin) {
                $user = User::findOrFail($user_id);

                // 1. Collect all trade IDs involving this user (as buyer OR seller)
                $tradeIds = \App\Models\Trade::where('buyer_id', $user_id)
                    ->orWhere('seller_id', $user_id)
                    ->pluck('id');

                // 2. Collect all order IDs for this user
                $orderIds = \App\Models\Order::where('seller_id', $user_id)->pluck('id');

                // 3. Collect all dispute IDs linked to the user's trades or raised by the user
                $disputeIds = collect();
                if ($tradeIds->isNotEmpty()) {
                    $disputeIds = $disputeIds->merge(
                        \App\Models\Dispute::whereIn('trade_id', $tradeIds)->pluck('id')
                    );
                }
                $disputeIds = $disputeIds->merge(
                    \App\Models\Dispute::where('raised_by', $user_id)->pluck('id')
                )->unique();

                // --- Delete from leaf tables first, working inward ---

                // 4a. proof_files (references trades, disputes, users)
                if ($tradeIds->isNotEmpty()) {
                    DB::table('proof_files')->whereIn('trade_id', $tradeIds)->delete();
                }
                if ($disputeIds->isNotEmpty()) {
                    DB::table('proof_files')->whereIn('dispute_id', $disputeIds)->delete();
                }
                DB::table('proof_files')->where('uploaded_by', $user_id)->delete();

                // 4b. fraud_hashes (references disputes, users)
                if ($disputeIds->isNotEmpty()) {
                    DB::table('fraud_hashes')->whereIn('dispute_id', $disputeIds)->delete();
                }
                DB::table('fraud_hashes')->where('flagged_by', $user_id)->delete();

                // 5. notifications (references trades, disputes, users)
                if ($tradeIds->isNotEmpty()) {
                    \App\Models\Notification::whereIn('trade_id', $tradeIds)->delete();
                }
                \App\Models\Notification::where('user_id', $user_id)->delete();

                // 6. wallet_transactions (references trades, users) — nullify trade FK first
                if ($tradeIds->isNotEmpty()) {
                    \App\Models\WalletTransaction::whereIn('trade_id', $tradeIds)->update(['trade_id' => null]);
                }
                \App\Models\WalletTransaction::where('user_id', $user_id)->delete();

                // 7. utr_registry (references trades, users)
                if ($tradeIds->isNotEmpty()) {
                    \App\Models\UtrRegistry::whereIn('trade_id', $tradeIds)->delete();
                }

                // 8. disputes (references trades, users)
                if ($disputeIds->isNotEmpty()) {
                    \App\Models\Dispute::whereIn('id', $disputeIds)->delete();
                }

                // 9. trades (references orders, users)
                if ($tradeIds->isNotEmpty()) {
                    \App\Models\Trade::whereIn('id', $tradeIds)->delete();
                }

                // 10. orders (references users)
                if ($orderIds->isNotEmpty()) {
                    \App\Models\Order::whereIn('id', $orderIds)->delete();
                }

                // 11. earnings_tracker (references users)
                DB::table('earnings_tracker')->where('user_id', $user_id)->delete();

                // 12. user_bonuses_claimed (references users)
                DB::table('user_bonuses_claimed')->where('user_id', $user_id)->delete();

                // 13. referral_claims (references users)
                if (\Illuminate\Support\Facades\Schema::hasTable('referral_claims')) {
                    DB::table('referral_claims')->where('user_id', $user_id)->delete();
                }

                // 14. admin_audit_log (references users) — keep logs but nullify is not possible since admin_id is non-nullable, so delete
                DB::table('admin_audit_log')->where('admin_id', $user_id)->delete();

                // 15. Nullify referred_by on other users who were referred by this user
                User::where('referred_by', $user_id)->update(['referred_by' => null]);

                // 16. Finally delete the user
                $user->delete();

                AdminAuditLog::create([
                    'id'          => (string) Str::uuid(),
                    'admin_id'    => $admin->id,
                    'action'      => 'delete_user',
                    'target_type' => 'user',
                    'target_id'   => $user_id,
                    'created_at'  => Carbon::now(),
                ]);
            });

            return response()->json(['message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to delete user $user_id: " . $e->getMessage());
            return response()->json(['error' => 'Failed to delete user due to constraints. ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/admin/analytics
     * Optimized: 2 aggregate queries + 30s server cache + "today" metrics
     */
    public function analytics(Request $request)
    {
        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = Cache::remember('admin_analytics', 30, function () {
            $today = Carbon::today();

            // --- Single aggregate query for ALL user stats ---
            $superAccountIds = User::where('role', 'super_account')->pluck('id')->toArray();

            $userStats = User::where('role', '!=', 'super_account')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                    SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned,
                    COALESCE(SUM(escrow_balance), 0) as total_escrow,
                    COALESCE(SUM(wallet_balance), 0) as total_wallet
                ")
                ->first();

            $newUsersToday = User::where('role', '!=', 'super_account')
                ->whereDate('created_at', $today)
                ->count();

            // --- Single aggregate query for ALL trade stats ---
            $tradeQuery = Trade::query();
            if (!empty($superAccountIds)) {
                $tradeQuery->whereNotIn('seller_id', $superAccountIds);
            }

            $tradeStats = (clone $tradeQuery)
                ->selectRaw("
                    SUM(CASE WHEN status IN ('pending_payment','payment_submitted') THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'disputed' THEN 1 ELSE 0 END) as disputed,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN commission_amount ELSE 0 END), 0) as total_commission
                ")
                ->first();

            // --- Today's trade metrics ---
            $todayTradeStats = (clone $tradeQuery)
                ->whereDate('completed_at', $today)
                ->where('status', 'completed')
                ->selectRaw("
                    COUNT(*) as trades_today,
                    COALESCE(SUM(commission_amount), 0) as commission_today
                ")
                ->first();

            return [
                'users' => [
                    'total'     => (int) ($userStats->total ?? 0),
                    'active'    => (int) ($userStats->active ?? 0),
                    'suspended' => (int) ($userStats->suspended ?? 0),
                    'banned'    => (int) ($userStats->banned ?? 0),
                ],
                'financials' => [
                    'total_commission'      => (float) ($tradeStats->total_commission ?? 0),
                    'total_liquidity'       => (float) ($userStats->total_escrow ?? 0),
                    'total_wallet_balance'  => (float) ($userStats->total_wallet ?? 0),
                ],
                'trades' => [
                    'active'    => (int) ($tradeStats->active ?? 0),
                    'completed' => (int) ($tradeStats->completed ?? 0),
                    'disputed'  => (int) ($tradeStats->disputed ?? 0),
                ],
                'today' => [
                    'commission'  => (float) ($todayTradeStats->commission_today ?? 0),
                    'trades'      => (int) ($todayTradeStats->trades_today ?? 0),
                    'new_users'   => $newUsersToday,
                ],
            ];
        });

        return response()->json($data);
    }

    /**
     * Reset password for super_account or assistance staff (super_admin only)
     * POST /api/admin/users/{user_id}/reset-password
     */
    public function resetStaffPassword(Request $request, string $userId)
    {
        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'new_password' => 'required|string|min:6',
        ]);

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        if (!in_array($user->role, ['super_account', 'assistance'])) {
            return response()->json(['error' => 'You can only reset passwords for Super Account and Assistance staff.'], 403);
        }

        $user->password_hash = Hash::make($request->new_password);
        $user->failed_dob_attempts = 0;
        $user->dob_lockout_until = null;
        $user->save();

        AdminAuditLog::create([
            'id'          => (string) Str::uuid(),
            'admin_id'    => $admin->id,
            'action'      => 'reset_staff_password',
            'target_type' => 'user',
            'target_id'   => $user->id,
            'created_at'  => Carbon::now(),
        ]);

        return response()->json(['message' => "Password for {$user->full_name} has been reset successfully."]);
    }
}
