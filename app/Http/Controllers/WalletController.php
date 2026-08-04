<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use App\Models\BonusMilestone;
use App\Models\UserBonusClaimed;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WalletController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * GET /api/wallet/balance
     */
    public function getBalance(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'wallet_balance'   => (float) $user->wallet_balance,
            'escrow_balance'   => (float) $user->escrow_balance,
            'total_trades'     => (int) $user->total_trades,
            'reputation_score' => (int) $user->reputation_score,
            'is_verified'      => (bool) $user->is_verified,
        ]);
    }

    /**
     * GET /api/wallet/transactions
     */
    public function getTransactions(Request $request)
    {
        $user = $request->user();

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json($transactions);
    }

    /**
     * POST /api/wallet/claim-bonus/{milestone_id}
     */
    public function claimBonus(Request $request, string $milestoneId)
    {
        $user = $request->user();

        $milestone = BonusMilestone::where('id', $milestoneId)
            ->where('is_active', true)
            ->firstOrFail();

        if ($user->total_trades < $milestone->trade_count) {
            return response()->json(['error' => "You need at least {$milestone->trade_count} completed trades to claim this bonus."], 400);
        }

        $alreadyClaimed = UserBonusClaimed::where('user_id', $user->id)
            ->where('milestone_id', $milestone->id)
            ->exists();

        if ($alreadyClaimed) {
            return response()->json(['error' => 'You have already claimed this bonus.'], 400);
        }

        DB::transaction(function () use ($user, $milestone) {
            $userUser = $user->fresh();
            $balVal = (float) $userUser->wallet_balance;
            $bonusAmt = (float) $milestone->bonus_amount;

            $userUser->wallet_balance += $bonusAmt;
            $userUser->save();

            UserBonusClaimed::create([
                'id'           => (string) Str::uuid(),
                'user_id'      => $userUser->id,
                'milestone_id' => $milestone->id,
                'claimed_at'   => Carbon::now(),
            ]);

            WalletTransaction::create([
                'id'             => (string) Str::uuid(),
                'user_id'        => $userUser->id,
                'type'           => 'bonus',
                'amount'         => $bonusAmt,
                'balance_before' => $balVal,
                'balance_after'  => $userUser->wallet_balance,
                'description_en' => "🎉 Bonus for completing {$milestone->trade_count} trades!",
                'description_hi' => "🎉 {$milestone->trade_count} ट्रेड पूर्ण करने पर बोनस!",
            ]);
        });

        return response()->json(['message' => 'Bonus claimed successfully!']);
    }
}
