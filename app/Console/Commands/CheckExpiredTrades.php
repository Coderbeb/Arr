<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Trade;
use App\Models\Order;
use App\Models\Dispute;
use App\Models\User;
use App\Services\WalletService;
use App\Events\TradeStatusUpdated;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckExpiredTrades extends Command
{
    protected $signature = 'trades:check-expired';
    protected $description = 'Auto-cancels expired trades, releases open order escrows, and auto-resolves expired disputes.';

    public function handle(WalletService $walletService)
    {
        $this->info('⏱️ Running Expired Trades & Disputes Timer Watcher...');

        // 1. Expire unpaid trades
        $expiredTrades = Trade::where('status', 'pending_payment')
            ->where('payment_deadline', '<', Carbon::now())
            ->get();

        foreach ($expiredTrades as $trade) {
            DB::transaction(function () use ($trade, $walletService) {
                $trade->update([
                    'status'           => 'cancelled',
                    'cancelled_reason' => 'payment_timeout',
                ]);

                $order = Order::where('id', $trade->order_id)->lockForUpdate()->first();

                if ($order && $order->cancel_requested) {
                    $order->update(['status' => 'cancelled']);

                    $seller = User::where('id', $trade->seller_id)->lockForUpdate()->first();
                    $walletService->releaseEscrow(
                        $seller,
                        (float) $order->amount,
                        "Sell order cancelled by request (buyer timeout). ₹{$order->amount} released from escrow.",
                        "क्रेता के समय समाप्त होने पर विक्रय ऑर्डर रद्द। ₹{$order->amount} एस्क्रो से वापस।"
                    );
                } elseif ($order) {
                    $order->update(['status' => 'open', 'matched_at' => null]);
                }

                $buyer = User::where('id', $trade->buyer_id)->lockForUpdate()->first();
                if ($buyer) {
                    $buyer->consecutive_cancels += 1;
                    if ($buyer->consecutive_cancels >= 2) {
                        $buyer->buy_ban_until = Carbon::now()->addMinutes(15);
                    }
                    $buyer->save();
                }

                broadcast(new TradeStatusUpdated($trade));
            });

            event(new \App\Events\UserActivityUpdated($trade->buyer_id));
            event(new \App\Events\UserActivityUpdated($trade->seller_id));
            $this->info("Cancelled unpaid trade {$trade->id}");
        }

        // 2. Expire unmatched open orders
        $expiredOrders = Order::where('status', 'open')
            ->where('expires_at', '<', Carbon::now())
            ->get();

        foreach ($expiredOrders as $order) {
            DB::transaction(function () use ($order, $walletService) {
                $order->update(['status' => 'cancelled']);

                $seller = User::where('id', $order->seller_id)->lockForUpdate()->first();
                $walletService->releaseEscrow(
                    $seller,
                    (float) $order->amount,
                    "Sell order expired. ₹{$order->amount} released from escrow.",
                    "विक्रय ऑर्डर समाप्त। ₹{$order->amount} एस्क्रो से वापस।"
                );
            });

            event(new \App\Events\UserActivityUpdated($order->seller_id));
            $this->info("Released expired order {$order->id}");
        }

        // 3. Auto-resolve expired dispute proof deadlines
        $expiredDisputes = Dispute::where('status', 'pending')
            ->where('proof_deadline', '<', Carbon::now())
            ->get();

        foreach ($expiredDisputes as $dispute) {
            DB::transaction(function () use ($dispute, $walletService) {
                $hasBuyerProof = !empty($dispute->buyer_proof_submitted_at);
                $hasSellerProof = !empty($dispute->seller_proof_submitted_at);

                $winner = ($hasBuyerProof && !$hasSellerProof) ? 'buyer' : 'seller';
                $resolutionStatus = ($winner === 'buyer') ? 'resolved_buyer' : 'resolved_seller';

                $dispute->update([
                    'status'           => $resolutionStatus,
                    'resolution_notes' => 'Auto-resolved: proof deadline expired',
                    'resolved_at'      => Carbon::now(),
                ]);

                $trade = Trade::where('id', $dispute->trade_id)->first();
                if ($trade) {
                    $tradeAmt = (float) $trade->amount;
                    if ($winner === 'buyer') {
                        $seller = User::where('id', $trade->seller_id)->lockForUpdate()->first();
                        $buyer = User::where('id', $trade->buyer_id)->lockForUpdate()->first();

                        $seller->escrow_balance -= $tradeAmt;
                        $seller->save();

                        $buyer->wallet_balance += $tradeAmt;
                        $buyer->save();

                        $trade->update(['status' => 'completed', 'completed_at' => Carbon::now()]);
                    } else {
                        $seller = User::where('id', $trade->seller_id)->lockForUpdate()->first();
                        $walletService->releaseEscrow(
                            $seller,
                            $tradeAmt,
                            "Dispute auto-resolved in favor of seller. ₹{$tradeAmt} released from escrow.",
                            "विवाद स्वतः विक्रेता के पक्ष में हल। ₹{$tradeAmt} एस्क्रो से वापस।"
                        );
                        $trade->update(['status' => 'refunded']);
                    }

                    broadcast(new TradeStatusUpdated($trade));
                }
            });

            $this->info("Auto-resolved dispute {$dispute->id}");
        }

        $this->info('✅ Timer Watcher completed.');
    }
}
