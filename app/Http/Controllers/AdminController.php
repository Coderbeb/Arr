<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PlatformSetting;
use App\Models\AdminAuditLog;
use App\Models\WalletTransaction;
use App\Models\Trade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $users = User::orderBy('created_at', 'desc')->paginate(50);
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
            'registration_open'     => 'required|boolean',
            'commission_percent'    => 'required|numeric|min:0|max:50',
            'max_daily_earning'     => 'required|numeric|min:0',
            'max_weekly_earning'    => 'required|numeric|min:0',
            'trade_accept_minutes'  => 'required|integer|min:1',
            'payment_timer_minutes' => 'required|integer|min:1',
            'dispute_proof_minutes' => 'required|integer|min:1',
            'global_announcement'   => 'nullable|string|max:255',
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

        $user = User::findOrFail($user_id);

        \App\Models\Order::where('seller_id', $user_id)->delete();
        \App\Models\Trade::where('buyer_id', $user_id)->orWhere('seller_id', $user_id)->delete();
        \App\Models\Dispute::where('raised_by', $user_id)->delete();
        \App\Models\WalletTransaction::where('user_id', $user_id)->delete();
        \App\Models\Notification::where('user_id', $user_id)->delete();

        $user->delete();

        AdminAuditLog::create([
            'id'          => (string) Str::uuid(),
            'admin_id'    => $admin->id,
            'action'      => 'delete_user',
            'target_type' => 'user',
            'target_id'   => $user_id,
            'created_at'  => Carbon::now(),
        ]);

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * GET /api/admin/analytics
     */
    public function analytics(Request $request)
    {
        $admin = $request->user();
        if ($admin->role !== 'super_admin') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $suspendedUsers = User::where('status', 'suspended')->count();
        $bannedUsers = User::where('status', 'banned')->count();

        $totalLiquidity = (float) User::sum('escrow_balance');
        $totalWalletBalance = (float) User::sum('wallet_balance');
        $totalCommission = (float) Trade::where('status', 'completed')->sum('commission_amount');

        $activeTrades = Trade::whereIn('status', ['pending_payment', 'payment_submitted'])->count();
        $completedTrades = Trade::where('status', 'completed')->count();
        $disputedTrades = Trade::where('status', 'disputed')->count();

        return response()->json([
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'suspended' => $suspendedUsers,
                'banned' => $bannedUsers,
            ],
            'financials' => [
                'total_commission' => $totalCommission,
                'total_liquidity' => $totalLiquidity,
                'total_wallet_balance' => $totalWalletBalance,
            ],
            'trades' => [
                'active' => $activeTrades,
                'completed' => $completedTrades,
                'disputed' => $disputedTrades,
            ]
        ]);
    }
}
