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

            $settings = PlatformSetting::first();
            $maxDaily = $settings ? (float) $settings->max_daily_earning : 500.00;
            $maxWeekly = $settings ? (float) $settings->max_weekly_earning : 2000.00;
            
            $buyCommissionPct = $settings ? (float) $settings->buy_commission_percent : 8.00;
            $sellCommissionPct = $settings ? (float) $settings->sell_commission_percent : 8.00;
            
            $buyCommAmt = round(($tradeAmt * $buyCommissionPct) / 100, 2);
            $sellCommAmt = round(($tradeAmt * $sellCommissionPct) / 100, 2);

            $today = Carbon::now()->toDateString();
            $d = Carbon::now();
            $dayOfWeek = $d->dayOfWeek;
            $mondayOffset = $dayOfWeek === 0 ? 6 : $dayOfWeek - 1;
            $weekStart = $d->subDays($mondayOffset)->toDateString();

            // Calculate capped commission for buyer
            $buyerEarningsTracker = EarningsTracker::where('user_id', $buyer->id)
                ->where('date', $today)
                ->first();

            $buyerDailyEarned = $buyerEarningsTracker ? (float) $buyerEarningsTracker->daily_earned : 0.0;
            $buyerWeeklyEarned = $buyerEarningsTracker ? (float) $buyerEarningsTracker->weekly_earned : 0.0;

            $buyerDailyRemaining = max(0, $maxDaily - $buyerDailyEarned);
            $buyerWeeklyRemaining = max(0, $maxWeekly - $buyerWeeklyEarned);

            $allowedBuyComm = round(min($buyCommAmt, $buyerDailyRemaining, $buyerWeeklyRemaining), 2);

            $buyerBalBefore = (float) $buyer->wallet_balance;
            $buyerReceives = $tradeAmt + $allowedBuyComm;

            $buyer->wallet_balance += $buyerReceives;
            $buyer->total_trades += 1;
            $buyer->consecutive_cancels = 0;
            $buyer->save();

            $buyCommNote = $allowedBuyComm < $buyCommAmt
                ? " (commission capped from ₹{$buyCommAmt} to ₹{$allowedBuyComm})"
                : "";

            WalletTransaction::create([
                'id' => (string) Str::uuid(),
                'user_id' => $buyer->id,
                'trade_id' => $trade->id,
                'type' => 'trade_received',
                'amount' => $buyerReceives,
                'balance_before' => $buyerBalBefore,
                'balance_after' => $buyer->wallet_balance,
                'description_en' => "Received ₹{$tradeAmt} coins + ₹{$allowedBuyComm} commission{$buyCommNote}.",
                'description_hi' => "₹{$tradeAmt} कॉइन + ₹{$allowedBuyComm} कमीशन प्राप्त हुआ{$buyCommNote}।",
            ]);
            
            // Calculate capped commission for seller
            $sellerEarningsTracker = EarningsTracker::where('user_id', $seller->id)
                ->where('date', $today)
                ->first();

            $sellerDailyEarned = $sellerEarningsTracker ? (float) $sellerEarningsTracker->daily_earned : 0.0;
            $sellerWeeklyEarned = $sellerEarningsTracker ? (float) $sellerEarningsTracker->weekly_earned : 0.0;

            $sellerDailyRemaining = max(0, $maxDaily - $sellerDailyEarned);
            $sellerWeeklyRemaining = max(0, $maxWeekly - $sellerWeeklyEarned);

            $allowedSellComm = round(min($sellCommAmt, $sellerDailyRemaining, $sellerWeeklyRemaining), 2);
            
            if ($allowedSellComm > 0) {
                $seller->wallet_balance += $allowedSellComm;
                $seller->save();
                
                $sellCommNote = $allowedSellComm < $sellCommAmt
                    ? " (capped from ₹{$sellCommAmt} to ₹{$allowedSellComm})"
                    : "";
                    
                WalletTransaction::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $seller->id,
                    'trade_id' => $trade->id,
                    'type' => 'commission',
                    'amount' => $allowedSellComm,
                    'balance_before' => $sellerBalBefore,
                    'balance_after' => $seller->wallet_balance,
                    'description_en' => "Earned ₹{$allowedSellComm} seller commission{$sellCommNote}.",
                    'description_hi' => "₹{$allowedSellComm} विक्रेता कमीशन कमाया{$sellCommNote}।",
                ]);
            }

            // Update Buyer Earnings Tracker
            if ($allowedBuyComm > 0) {
                if ($buyerEarningsTracker) {
                    $buyerEarningsTracker->daily_earned += $allowedBuyComm;
                    $buyerEarningsTracker->weekly_earned += $allowedBuyComm;
                    $buyerEarningsTracker->save();
                } else {
                    EarningsTracker::create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $buyer->id,
                        'date' => $today,
                        'week_start' => $weekStart,
                        'daily_earned' => $allowedBuyComm,
                        'weekly_earned' => $allowedBuyComm,
                    ]);
                }
            }
            
            // Update Seller Earnings Tracker
            if ($allowedSellComm > 0) {
                if ($sellerEarningsTracker) {
                    $sellerEarningsTracker->daily_earned += $allowedSellComm;
                    $sellerEarningsTracker->weekly_earned += $allowedSellComm;
                    $sellerEarningsTracker->save();
                } else {
                    EarningsTracker::create([
                        'id' => (string) Str::uuid(),
                        'user_id' => $seller->id,
                        'date' => $today,
                        'week_start' => $weekStart,
                        'daily_earned' => $allowedSellComm,
                        'weekly_earned' => $allowedSellComm,
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
