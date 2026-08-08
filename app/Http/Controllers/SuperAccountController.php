<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuperAccountController extends Controller
{
    /**
     * POST /api/super-account/generate-coins
     */
    public function generateCoins(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'super_account') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $amount = (float) $request->amount;

        DB::transaction(function () use ($user, $amount) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->first();
            $balanceBefore = $lockedUser->wallet_balance;
            
            $lockedUser->wallet_balance += $amount;
            $lockedUser->save();

            WalletTransaction::create([
                'id'             => (string) Str::uuid(),
                'user_id'        => $lockedUser->id,
                'type'           => 'super_mint',
                'amount'         => $amount,
                'balance_before' => $balanceBefore,
                'balance_after'  => $lockedUser->wallet_balance,
                'description_en' => "Generated {$amount} coins in Super Account",
                'description_hi' => "Super Account में {$amount} सिक्के बनाए गए",
            ]);
        });

        return response()->json([
            'message' => "Successfully generated {$amount} coins!",
            'new_balance' => $user->fresh()->wallet_balance
        ]);
    }

    /**
     * GET /api/super-account/analytics
     */
    public function getAnalytics(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'super_account') {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $totalMinted = WalletTransaction::where('user_id', $user->id)
            ->where('type', 'super_mint')
            ->sum('amount');

        $totalSold = \App\Models\Trade::where('seller_id', $user->id)
            ->where('status', 'completed')
            ->sum('amount');

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'total_minted' => (float) $totalMinted,
            'total_sold' => (float) $totalSold,
            'transactions' => $transactions
        ]);
    }
}
