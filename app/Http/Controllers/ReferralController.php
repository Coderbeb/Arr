<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ReferralClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReferralController extends Controller
{
    /**
     * Get data for the referrals dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get all referred users
        $referrals = User::where('referred_by', $user->id)
            ->select('id', 'full_name', 'created_at', 'total_trades')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalReferrals = $referrals->count();
        $completedReferrals = $referrals->where('total_trades', '>', 0)->count();
        $pendingReferrals = $totalReferrals - $completedReferrals;

        // Get claims
        $claims = ReferralClaim::where('user_id', $user->id)
            ->pluck('milestone')
            ->toArray();

        return response()->json([
            'referral_code' => $user->referral_code,
            'stats' => [
                'total' => $totalReferrals,
                'completed' => $completedReferrals,
                'pending' => $pendingReferrals,
            ],
            'claims' => $claims, // ['tier_1', 'tier_2', 'post_10_bonus_1', ...]
            'referrals' => $referrals,
        ]);
    }

    /**
     * Claim a referral milestone reward
     */
    public function claim(Request $request)
    {
        $request->validate([
            'milestone' => 'required|string|in:tier_1,tier_2,tier_3,post_10_bonus',
        ]);

        $user = clone $request->user();
        $milestone = $request->milestone;

        $completedCount = User::where('referred_by', $user->id)
            ->where('total_trades', '>', 0)
            ->count();

        return DB::transaction(function () use ($user, $milestone, $completedCount) {
            // Lock user for update to prevent race conditions on wallet
            $user = User::where('id', $user->id)->lockForUpdate()->first();
            
            $reward = 0;
            $claimIdStr = $milestone;

            if ($milestone === 'tier_1') {
                if ($completedCount < 3) return response()->json(['error' => 'Need 3 completed referrals.'], 400);
                if ($this->hasClaimed($user->id, 'tier_1')) return response()->json(['error' => 'Already claimed.'], 400);
                $reward = 300;
            } elseif ($milestone === 'tier_2') {
                if ($completedCount < 6) return response()->json(['error' => 'Need 6 completed referrals.'], 400);
                if ($this->hasClaimed($user->id, 'tier_2')) return response()->json(['error' => 'Already claimed.'], 400);
                $reward = 500;
            } elseif ($milestone === 'tier_3') {
                if ($completedCount < 10) return response()->json(['error' => 'Need 10 completed referrals.'], 400);
                if ($this->hasClaimed($user->id, 'tier_3')) return response()->json(['error' => 'Already claimed.'], 400);
                $reward = 800;
            } elseif ($milestone === 'post_10_bonus') {
                if ($completedCount <= 10) return response()->json(['error' => 'Must have more than 10.'], 400);
                
                // For post 10, calculate how many they *can* claim
                // Let's say they have 12. They claimed tier_3 (10). So they have 2 post-10 users.
                // We check how many 'post_10_bonus' claims they have made.
                $post10Claims = ReferralClaim::where('user_id', $user->id)->where('milestone', 'like', 'post_10_bonus_%')->count();
                $eligiblePost10 = $completedCount - 10;
                
                if ($post10Claims >= $eligiblePost10) {
                    return response()->json(['error' => 'No new completed users to claim.'], 400);
                }

                // Grant 1 claim (200 coins)
                $reward = 200;
                // Append the index to make it unique, e.g. post_10_bonus_1, post_10_bonus_2
                $claimIdStr = 'post_10_bonus_' . ($post10Claims + 1);
            }

            // Create claim record
            ReferralClaim::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'milestone' => $claimIdStr,
                'reward_coins' => $reward,
                'created_at' => Carbon::now(),
            ]);

            // Add coins to wallet
            $user->wallet_balance += $reward;
            $user->save();

            return response()->json([
                'message' => "Successfully claimed {$reward} coins!",
                'wallet_balance' => $user->wallet_balance
            ]);
        });
    }

    private function hasClaimed($userId, $milestone)
    {
        return ReferralClaim::where('user_id', $userId)->where('milestone', $milestone)->exists();
    }
}
