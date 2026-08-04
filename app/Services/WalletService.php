<?php

namespace App\Services;

use App\Models\User;
use App\Models\Trade;
use App\Models\Order;
use App\Models\WalletTransaction;
use App\Models\PlatformSetting;
use App\Models\EarningsTracker;
use App\Models\BonusMilestone;
use App\Models\UserBonusClaimed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class WalletService
{
    /**
     * Lock coins in escrow for a sell order
     */
    public function lockEscrow(User $seller, float $amount): void
    {
        $balBefore = (float) $seller->wallet_balance;
        $seller->wallet_balance -= $amount;
        $seller->escrow_balance += $amount;
        $seller->save();

        WalletTransaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $seller->id,
            'type' => 'escrow_lock',
            'amount' => $amount,
            'balance_before' => $balBefore,
            'balance_after' => $seller->wallet_balance,
            'description_en' => "Coins locked in escrow for sell order of ₹{$amount}",
            'description_hi' => "₹{$amount} के विक्रय ऑर्डर के लिए कॉइन एस्क्रो में लॉक किए गए",
        ]);
    }

    /**
     * Release coins from escrow back to seller's wallet
     */
    public function releaseEscrow(User $seller, float $amount, string $reasonEn, string $reasonHi): void
    {
        $balBefore = (float) $seller->wallet_balance;
        $seller->escrow_balance -= $amount;
        $seller->wallet_balance += $amount;
        $seller->save();

        WalletTransaction::create([
            'id' => (string) Str::uuid(),
            'user_id' => $seller->id,
            'type' => 'escrow_release',
            'amount' => $amount,
            'balance_before' => $balBefore,
            'balance_after' => $seller->wallet_balance,
            'description_en' => $reasonEn,
            'description_hi' => $reasonHi,
        ]);
    }

    /**
     * Settle completed trade: release seller escrow & credit buyer wallet (applying earnings cap)
     */
    public function settleCompletedTrade(Trade $trade): array
    {
        return DB::transaction(function () use ($trade) {
            $seller = User::where('id', $trade->seller_id)->lockForUpdate()->first();
            $buyer = User::where('id', $trade->buyer_id)->lockForUpdate()->first();

            $tradeAmt = (float) $trade->amount;
            $commAmt = (float) $trade->commission_amount;

            // Update trade & order status
            $trade->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
            ]);

            Order::where('id', $trade->order_id)->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
            ]);

            // Seller: release escrow (coins sold to buyer)
            $sellerBalBefore = (float) $seller->wallet_balance;
            $seller->escrow_balance -= $tradeAmt;
            $seller->total_trades += 1;
            $seller->save();

            WalletTransaction::create([
                'id' => (string) Str::uuid(),
                'user_id' => $seller->id,
                'trade_id' => $trade->id,
                'type' => 'escrow_release',
                'amount' => $tradeAmt,
                'balance_before' => $sellerBalBefore,
                'balance_after' => $sellerBalBefore,
                'description_en' => "Sold ₹{$tradeAmt} coins via trade. Escrow released.",
                'description_hi' => "₹{$tradeAmt} कॉइन ट्रेड के माध्यम से बेचे। एस्क्रो जारी।",
            ]);

            // Calculate capped commission for buyer
            $settings = PlatformSetting::first();
            $maxDaily = $settings ? (float) $settings->max_daily_earning : 500.00;
            $maxWeekly = $settings ? (float) $settings->max_weekly_earning : 2000.00;

            $today = Carbon::now()->toDateString();
            $d = Carbon::now();
            $dayOfWeek = $d->dayOfWeek;
            $mondayOffset = $dayOfWeek === 0 ? 6 : $dayOfWeek - 1;
            $weekStart = $d->subDays($mondayOffset)->toDateString();

            $earningsTracker = EarningsTracker::where('user_id', $buyer->id)
                ->where('date', $today)
                ->first();

            $dailyEarned = $earningsTracker ? (float) $earningsTracker->daily_earned : 0.0;
            $weeklyEarned = $earningsTracker ? (float) $earningsTracker->weekly_earned : 0.0;

            $dailyRemaining = max(0, $maxDaily - $dailyEarned);
            $weeklyRemaining = max(0, $maxWeekly - $weeklyEarned);

            $allowedComm = round(min($commAmt, $dailyRemaining, $weeklyRemaining), 2);

            $buyerBalBefore = (float) $buyer->wallet_balance;
            $buyerReceives = $tradeAmt + $allowedComm;

            $buyer->wallet_balance += $buyerReceives;
            $buyer->total_trades += 1;
            $buyer->consecutive_cancels = 0;
            $buyer->save();

            $commNote = $allowedComm < $commAmt
                ? " (commission capped from ₹{$commAmt} to ₹{$allowedComm})"
                : "";

            WalletTransaction::create([
                'id' => (string) Str::uuid(),
                'user_id' => $buyer->id,
                'trade_id' => $trade->id,
                'type' => 'credit_commission',
                'amount' => $buyerReceives,
                'balance_before' => $buyerBalBefore,
                'balance_after' => $buyer->wallet_balance,
                'description_en' => "Purchased ₹{$tradeAmt} coins + ₹{$allowedComm} commission.{$commNote}",
                'description_hi' => "₹{$tradeAmt} कॉइन खरीदे + ₹{$allowedComm} कमीशन।",
            ]);

            // Update earnings tracker
            if ($allowedComm > 0) {
                if ($earningsTracker) {
                    $earningsTracker->daily_earned += $allowedComm;
                    $earningsTracker->weekly_earned += $allowedComm;
                    $earningsTracker->save();
                } else {
                    EarningsTracker::create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $buyer->id,
                        'date' => $today,
                        'daily_earned' => $allowedComm,
                        'weekly_earned' => $allowedComm,
                        'week_start' => $weekStart,
                    ]);
                }
            }

            // Award milestone bonuses
            $this->checkBonusMilestones($buyer);

            return [
                'buyer_receives' => $buyerReceives,
                'seller_amount' => $tradeAmt,
            ];
        });
    }

    /**
     * Automatically award bonus milestones based on total completed trades
     */
    public function checkBonusMilestones(User $user): void
    {
        $milestones = BonusMilestone::where('is_active', true)
            ->where('trade_count', '<=', $user->total_trades)
            ->whereNotIn('id', function ($query) use ($user) {
                $query->select('milestone_id')
                    ->from('user_bonuses_claimed')
                    ->where('user_id', $user->id);
            })
            ->get();

        foreach ($milestones as $milestone) {
            $balVal = (float) $user->wallet_balance;
            $bonusAmt = (float) $milestone->bonus_amount;

            $user->wallet_balance += $bonusAmt;
            $user->save();

            UserBonusClaimed::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'milestone_id' => $milestone->id,
                'claimed_at' => Carbon::now(),
            ]);

            WalletTransaction::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'type' => 'bonus',
                'amount' => $bonusAmt,
                'balance_before' => $balVal,
                'balance_after' => $user->wallet_balance,
                'description_en' => "🎉 Bonus for completing {$milestone->trade_count} trades!",
                'description_hi' => "🎉 {$milestone->trade_count} ट्रेड पूर्ण करने पर बोनस!",
            ]);
        }
    }
}
