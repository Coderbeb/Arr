<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\Trade;
use App\Models\Dispute;
use App\Models\TradeAmount;
use App\Models\PlatformSetting;
use App\Models\UtrRegistry;
use App\Services\WalletService;
use App\Services\UpiDeepLinkService;
use App\Services\ProofAnalyzerService;
use App\Services\NotificationService;
use App\Events\TradeStatusUpdated;
use App\Events\OrderCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SimpleQueue;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TradeController extends Controller
{
    protected WalletService $walletService;
    protected UpiDeepLinkService $upiService;
    protected ProofAnalyzerService $proofService;
    protected NotificationService $notificationService;

    public function __construct(
        WalletService $walletService,
        UpiDeepLinkService $upiService,
        ProofAnalyzerService $proofService,
        NotificationService $notificationService
    ) {
        $this->walletService = $walletService;
        $this->upiService = $upiService;
        $this->proofService = $proofService;
        $this->notificationService = $notificationService;
    }

    /**
     * GET /api/trade/amounts
     */
    public function getAmounts()
    {
        $amounts = TradeAmount::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get(['id', 'amount']);

        return response()->json($amounts);
    }

    /**
     * POST /api/trade/sell
     */
    public function sell(Request $request)
    {
        $request->validate([
            'amount_id' => 'required|exists:trade_amounts,id',
            'upi_id'    => 'nullable|string|max:100',
            'upi_app'   => 'nullable|string|in:gpay,phonepe,paytm,bhim',
        ]);

        $seller = $request->user();

        if ($seller->status !== 'active') {
            return response()->json(['error' => 'Your account is not active'], 403);
        }

        $tradeAmount = TradeAmount::where('id', $request->amount_id)
            ->where('is_active', true)
            ->firstOrFail();

        $amount = (float) $tradeAmount->amount;

        $effectiveUpiId = $request->upi_id ?? $seller->upi_id;
        $effectiveUpiApp = $request->upi_app ?? $seller->upi_app ?? 'gpay';

        if (!$effectiveUpiId || !str_contains($effectiveUpiId, '@')) {
            return response()->json(['error' => 'Please enter a valid UPI ID (e.g. name@upi)'], 400);
        }

        if ($effectiveUpiId !== $seller->upi_id || $effectiveUpiApp !== $seller->upi_app) {
            $seller->update(['upi_id' => $effectiveUpiId, 'upi_app' => $effectiveUpiApp]);
        }

        if ((float) $seller->wallet_balance < $amount) {
            return response()->json(['error' => 'Insufficient wallet balance'], 400);
        }

        $settings = PlatformSetting::first();
        $commissionPct = $settings ? (float) $settings->commission_percent : 8.00;
        $commissionAmt = round(($amount * $commissionPct) / 100, 2);
        $acceptMinutes = $settings ? $settings->trade_accept_minutes : 30;
        $paymentTimer = $settings ? $settings->payment_timer_minutes : 30;

        $order = DB::transaction(function () use ($seller, $amount, $commissionPct, $commissionAmt, $acceptMinutes, $effectiveUpiId, $effectiveUpiApp) {
            $sellerUser = User::where('id', $seller->id)->lockForUpdate()->first();
            $this->walletService->lockEscrow($sellerUser, $amount);

            $expiresAt = Carbon::now()->addMinutes($acceptMinutes);

            return Order::create([
                'id'             => (string) Str::uuid(),
                'seller_id'      => $sellerUser->id,
                'amount'         => $amount,
                'coin_amount'    => $amount,
                'commission_pct' => $commissionPct,
                'commission_amt' => $commissionAmt,
                'seller_upi_id'  => $effectiveUpiId,
                'seller_upi_app' => $effectiveUpiApp,
                'status'         => 'open',
                'expires_at'     => $expiresAt,
                'created_at'     => Carbon::now(),
            ]);
        });

        // Instant Match Check (Check Buyer Queue in Redis)
        $matchedTrade = null;
        try {
            while ($buyerId = SimpleQueue::pop("queue:buyers:{$amount}")) {
                // Found a buyer. Ensure they are valid and not the seller themselves.
                if ($buyerId !== $seller->id) {
                    $buyer = User::find($buyerId);
                    // Check if buyer is banned
                    if ($buyer && (!$buyer->buy_ban_until || Carbon::now()->greaterThan($buyer->buy_ban_until))) {
                        
                        // Match found! Create Trade
                        $matchedTrade = DB::transaction(function () use ($order, $buyer, $amount, $commissionAmt, $paymentTimer) {
                            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
                            $lockedOrder->update(['status' => 'matched', 'matched_at' => Carbon::now()]);

                            return Trade::create([
                                'id' => (string) Str::uuid(),
                                'order_id' => $lockedOrder->id,
                                'buyer_id' => $buyer->id,
                                'seller_id' => $lockedOrder->seller_id,
                                'amount' => $amount,
                                'commission_amount' => $commissionAmt,
                                'status' => 'pending_payment',
                                'payment_deadline' => Carbon::now()->addMinutes($paymentTimer),
                            ]);
                        });

                        broadcast(new TradeStatusUpdated($matchedTrade))->toOthers();
                        break; // Stop looking for buyers for this order
                    }
                }
            }
        } catch (\Throwable $e) {
            // Redis might not be installed on this environment
        }

        if (!$matchedTrade) {
            try {
                SimpleQueue::push("queue:sellers:{$amount}", $order->id);
            } catch (\Throwable $e) {
                // Ignore if Redis is missing
            }
            broadcast(new OrderCreated($order))->toOthers();

            $this->broadcastUserActivity($order->seller_id);
            return response()->json([
                'order'   => $order,
                'message' => 'Sell order posted successfully',
            ], 201);
        } else {
            $this->broadcastUserActivity($order->seller_id, $buyer->id);
            return response()->json([
                'order'   => $order,
                'message' => 'Instantly matched with a buyer!',
            ], 201);
        }
    }

    /**
     * POST /api/trade/buy/queue
     */
    public function joinBuyerQueue(Request $request)
    {
        $request->validate(['amount_id' => 'required|exists:trade_amounts,id']);

        $buyer = $request->user();

        if ($buyer->buy_ban_until && Carbon::now()->lessThan($buyer->buy_ban_until)) {
            $remainingMinutes = Carbon::now()->diffInMinutes($buyer->buy_ban_until) + 1;
            return response()->json([
                'error' => "You are temporarily blocked from buying due to repeated cancellations. Try again in {$remainingMinutes} minute(s)."
            ], 403);
        }

        $tradeAmount = TradeAmount::where('id', $request->amount_id)->firstOrFail();
        $amount = (float) $tradeAmount->amount;

        $settings = PlatformSetting::first();
        $paymentTimer = $settings ? $settings->payment_timer_minutes : 30;

        // Check if already in queue
        $currentQueue = \Illuminate\Support\Facades\Cache::get("queue:buyers:{$amount}", []);
        $pos = array_search($buyer->id, $currentQueue);
        if ($pos !== false) {
            return response()->json([
                'message' => 'You are already in the queue for this amount.',
                'position' => $pos + 1
            ]);
        }

        // Instant Match Check (Check Seller Queue in Redis)
        $matchedTrade = null;
        try {
            while ($orderId = SimpleQueue::pop("queue:sellers:{$amount}")) {
                $order = Order::find($orderId);
                
                if ($order && $order->status === 'open' && $order->seller_id !== $buyer->id && Carbon::now()->lessThan($order->expires_at)) {
                    // We found a valid sell order! Match them!
                    $matchedTrade = DB::transaction(function () use ($order, $buyer, $amount, $paymentTimer) {
                        $lockedOrder = Order::where('id', $order->id)->where('status', 'open')->lockForUpdate()->first();
                        if (!$lockedOrder) return null;

                        $lockedOrder->update(['status' => 'matched', 'matched_at' => Carbon::now()]);

                        return Trade::create([
                            'id' => (string) Str::uuid(),
                            'order_id' => $lockedOrder->id,
                            'buyer_id' => $buyer->id,
                            'seller_id' => $lockedOrder->seller_id,
                            'amount' => $amount,
                            'commission_amount' => $lockedOrder->commission_amt,
                            'status' => 'pending_payment',
                            'payment_deadline' => Carbon::now()->addMinutes($paymentTimer),
                        ]);
                    });

                    if ($matchedTrade) {
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Redis error
        }

        if ($matchedTrade) {
            broadcast(new TradeStatusUpdated($matchedTrade))->toOthers();
            $this->broadcastUserActivity($matchedTrade->seller_id, $buyer->id);
            return response()->json([
                'message'  => 'Matched with a seller instantly!',
                'position' => 0,
            ]);
        }

        // If no match found, put buyer in queue
        try {
            SimpleQueue::push("queue:buyers:{$amount}", $buyer->id);
        } catch (\Throwable $e) {}
        
        return response()->json([
            'message' => 'Joined buyer queue.',
            'position' => 1
        ]);
    }

    /**
     * POST /api/trade/pay/{trade_id}
     */
    public function submitPayment(Request $request, string $tradeId)
    {
        $request->validate([
            'utr_number'    => 'required|string|regex:/^[A-Za-z0-9]{12,22}$/',
            'buyer_upi_app' => 'nullable|string',
            'screenshot'    => 'required|file|image|max:10240',
        ]);

        $buyer = $request->user();

        // Anti-fraud UTR uniqueness check
        if (UtrRegistry::where('utr_number', $request->utr_number)->exists()) {
            return response()->json(['error' => 'This UTR number has already been used.'], 409);
        }

        $trade = Trade::where('id', $tradeId)
            ->where('buyer_id', $buyer->id)
            ->where('status', 'pending_payment')
            ->firstOrFail();

        if (Carbon::now()->greaterThan($trade->payment_deadline)) {
            return response()->json(['error' => 'Payment timer expired.'], 400);
        }

        $uploadResult = $this->proofService->storeAndHashProof(
            $request->file('screenshot'),
            $buyer->id,
            $trade->id,
            null,
            'buyer_payment'
        );

        DB::transaction(function () use ($trade, $buyer, $request, $uploadResult) {
            UtrRegistry::create([
                'id'         => (string) Str::uuid(),
                'utr_number' => $request->utr_number,
                'trade_id'   => $trade->id,
                'user_id'    => $buyer->id,
                'used_at'    => Carbon::now(),
            ]);

            $trade->update([
                'status'                       => 'payment_submitted',
                'utr_number'                   => $request->utr_number,
                'buyer_upi_app'                => $request->buyer_upi_app ?? 'gpay',
                'buyer_payment_screenshot_url' => $uploadResult['url'],
                'paid_at'                      => Carbon::now(),
            ]);

            $this->notificationService->createNotification(
                $trade->seller_id,
                'payment_submitted',
                'Payment Submitted with Proof',
                'à¤ªà¥à¤°à¤®à¤¾à¤£ à¤•à¥‡ à¤¸à¤¾à¤¥ à¤­à¥à¤—à¤¤à¤¾à¤¨ à¤œà¤®à¤¾',
                "Buyer submitted â‚¹{$trade->amount} payment with UTR: {$request->utr_number}.",
                "à¤–à¤°à¥€à¤¦à¤¾à¤° à¤¨à¥‡ â‚¹{$trade->amount} à¤•à¤¾ à¤­à¥à¤—à¤¤à¤¾à¤¨ UTR: {$request->utr_number} à¤•à¥‡ à¤¸à¤¾à¤¥ à¤œà¤®à¤¾ à¤•à¤¿à¤¯à¤¾à¥¤",
                $trade->id
            );
        });

        broadcast(new TradeStatusUpdated($trade))->toOthers();
        $this->broadcastUserActivity($trade->seller_id);

        return response()->json(['message' => 'Payment submitted with screenshot. Waiting for seller confirmation.']);
    }

    /**
     * POST /api/trade/confirm/{trade_id}
     */
    public function confirm(Request $request, string $tradeId)
    {
        $seller = $request->user();

        $trade = Trade::where('id', $tradeId)
            ->where('seller_id', $seller->id)
            ->where('status', 'payment_submitted')
            ->firstOrFail();

        $settlement = $this->walletService->settleCompletedTrade($trade);

        broadcast(new TradeStatusUpdated($trade))->toOthers();
        $this->broadcastUserActivity($trade->buyer_id, $trade->seller_id);

        return response()->json([
            'message' => 'Trade confirmed. Coins released to buyer.',
            'settlement' => $settlement,
        ]);
    }

    /**
     * POST /api/trade/reject/{trade_id}
     */
    public function reject(Request $request, string $tradeId)
    {
        $request->validate([
            'screen_recording' => 'required|file|mimes:mp4,mov,avi,webm|max:51200',
            'bank_statement'   => 'required|file|mimes:pdf|max:10240',
            'txn_screenshot'   => 'required|file|image|max:10240',
        ]);

        $seller = $request->user();

        $trade = Trade::where('id', $tradeId)
            ->where('seller_id', $seller->id)
            ->where('status', 'payment_submitted')
            ->firstOrFail();

        $settings = PlatformSetting::first();
        $proofMinutes = $settings ? $settings->dispute_proof_minutes : 30;
        $proofDeadline = Carbon::now()->addMinutes($proofMinutes);

        $recordingUpload = $this->proofService->storeAndHashProof($request->file('screen_recording'), $seller->id, $trade->id, null, 'seller_recording');
        $statementUpload = $this->proofService->storeAndHashProof($request->file('bank_statement'), $seller->id, $trade->id, null, 'seller_statement');
        $screenshotUpload = $this->proofService->storeAndHashProof($request->file('txn_screenshot'), $seller->id, $trade->id, null, 'seller_screenshot');

        $dispute = DB::transaction(function () use ($trade, $seller, $recordingUpload, $statementUpload, $screenshotUpload, $proofDeadline) {
            $trade->update(['status' => 'seller_rejected']);
            Order::where('id', $trade->order_id)->update(['status' => 'disputed']);

            return Dispute::create([
                'id'                           => (string) Str::uuid(),
                'trade_id'                     => $trade->id,
                'raised_by'                    => $trade->buyer_id, // Trade buyer gets flagged to appeal
                'status'                       => 'pending',
                'buyer_utr_number'             => $trade->utr_number,
                'buyer_upi_screenshot_url'     => $trade->buyer_payment_screenshot_url,
                'seller_screen_recording_url'  => $recordingUpload['url'],
                'seller_bank_statement_url'    => $statementUpload['url'],
                'seller_txn_screenshot_url'    => $screenshotUpload['url'],
                'seller_proof_submitted_at'    => Carbon::now(),
                'proof_deadline'               => $proofDeadline,
                'created_at'                   => Carbon::now(),
            ]);
        });

        // Async AI proof analysis
        $recAnalysis = $this->proofService->analyzeProof($recordingUpload['hash'], $recordingUpload['proof_file']->mime_type, $recordingUpload['proof_file']->file_size, $seller->id);
        $stmtAnalysis = $this->proofService->analyzeProof($statementUpload['hash'], $statementUpload['proof_file']->mime_type, $statementUpload['proof_file']->file_size, $seller->id);
        $imgAnalysis = $this->proofService->analyzeProof($screenshotUpload['hash'], $screenshotUpload['proof_file']->mime_type, $screenshotUpload['proof_file']->file_size, $seller->id);

        $combinedScore = round(($recAnalysis['score'] + $stmtAnalysis['score'] + $imgAnalysis['score']) / 3);

        $dispute->update([
            'seller_ai_score'     => $combinedScore,
            'seller_proof_analysis' => [
                'video' => $recAnalysis,
                'pdf' => $stmtAnalysis,
                'image' => $imgAnalysis
            ],
            'seller_ai_breakdown' => [
                'video_score' => $recAnalysis['score'],
                'pdf_score' => $stmtAnalysis['score'],
                'image_score' => $imgAnalysis['score']
            ]
        ]);

        broadcast(new TradeStatusUpdated($trade))->toOthers();
        $this->broadcastUserActivity($trade->buyer_id);

        return response()->json([
            'dispute_id' => $dispute->id,
            'message'    => 'Payment rejected. Buyer has been notified to appeal with 3 proofs.',
        ]);
    }

    /**
     * POST /api/trade/cancel/{trade_id}
     */
    public function cancel(Request $request, string $tradeId)
    {
        $buyer = $request->user();

        $trade = Trade::where('id', $tradeId)
            ->where('buyer_id', $buyer->id)
            ->where('status', 'pending_payment')
            ->firstOrFail();

        DB::transaction(function () use ($trade, $buyer) {
            $trade->update([
                'status'           => 'cancelled',
                'cancelled_reason' => 'buyer_cancelled',
            ]);

            $buyer->consecutive_cancels += 1;
            if ($buyer->consecutive_cancels >= 2) {
                $buyer->buy_ban_until = Carbon::now()->addMinutes(15);
            }
            $buyer->save();

            $order = Order::where('id', $trade->order_id)->lockForUpdate()->first();

            if ($order->cancel_requested) {
                $order->update(['status' => 'cancelled']);
                $seller = User::where('id', $trade->seller_id)->lockForUpdate()->first();
                $this->walletService->releaseEscrow(
                    $seller,
                    (float) $order->amount,
                    "Sell order cancelled by request. â‚¹{$order->amount} released from escrow.",
                    "à¤µà¤¿à¤•à¥à¤°à¤¯ à¤‘à¤°à¥à¤¡à¤° à¤…à¤¨à¥à¤°à¥‹à¤§ à¤ªà¤° à¤°à¤¦à¥à¤¦à¥¤ â‚¹{$order->amount} à¤à¤¸à¥à¤•à¥à¤°à¥‹ à¤¸à¥‡ à¤µà¤¾à¤ªà¤¸à¥¤"
                );
            } else {
                $order->update(['status' => 'open', 'matched_at' => null]);
                // Re-queue the order so a new buyer matches instantly (Case 3)
                try {
                    SimpleQueue::push("queue:sellers:{$order->amount}", $order->id);
                } catch (\Throwable $e) {}
            }
        });

        broadcast(new TradeStatusUpdated($trade))->toOthers();
        $this->broadcastUserActivity($trade->seller_id);

        return response()->json(['message' => 'Trade cancelled successfully.']);
    }

    /**
     * GET /api/trade/my-active
     */
    public function getMyActiveTrade(Request $request)
    {
        $user = $request->user();

        $trades = Trade::where(function ($query) use ($user) {
            $query->where('buyer_id', $user->id)
                  ->orWhere('seller_id', $user->id);
        })
        ->whereIn('status', ['pending_payment', 'payment_submitted', 'seller_rejected', 'disputed'])
        ->orderBy('matched_at', 'desc')
        ->with('order')
        ->get();

        $openOrders = Order::where('seller_id', $user->id)
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->get();

        $tradeAmounts = TradeAmount::where('is_active', true)->get();
        $activeQueues = [];
        foreach ($tradeAmounts as $ta) {
            $queue = \Illuminate\Support\Facades\Cache::get("queue:buyers:{$ta->amount}", []);
            $pos = array_search($user->id, $queue);
            if ($pos !== false) {
                $activeQueues[] = [
                    'amount_id' => $ta->id,
                    'amount' => $ta->amount,
                    'position' => $pos + 1
                ];
            }
        }

        $tradesData = [];
        foreach ($trades as $trade) {
             $tradeArr = $trade->toArray();
             if ($trade->buyer_id === $user->id && $trade->order) {
                 $tradeArr['deepLinks'] = $this->upiService->generateUpiDeepLinks($trade->order->seller_upi_id, (float) $trade->amount, $trade->id);
             }
             if (in_array($trade->status, ['seller_rejected', 'disputed'])) {
                 $tradeArr['dispute'] = Dispute::where('trade_id', $trade->id)->orderBy('created_at', 'desc')->first();
             }
             $tradesData[] = $tradeArr;
        }

        return response()->json([
            'trades'       => $tradesData,
            'openOrders'   => $openOrders,
            'activeQueues' => $activeQueues,
        ]);
    }

    /**
     * GET /api/trade/history
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $trades = Trade::where('buyer_id', $user->id)
            ->orWhere('seller_id', $user->id)
            ->with(['buyer:id,full_name,mobile_number', 'seller:id,full_name,mobile_number', 'order:id,seller_upi_id'])
            ->orderBy('matched_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json($trades);
    }

    /**
     * POST /api/trade/cancel-queue
     */
    public function cancelQueue(Request $request)
    {
        $request->validate(['amount_id' => 'required|exists:trade_amounts,id']);
        $buyer = $request->user();

        $tradeAmount = TradeAmount::where('id', $request->amount_id)->firstOrFail();
        $amount = (float) $tradeAmount->amount;

        try {
            SimpleQueue::remove("queue:buyers:{$amount}", $buyer->id);
        } catch (\Throwable $e) {}

        return response()->json(['message' => 'Left the queue successfully.']);
    }
    /**
     * POST /api/trade/seller-cancel/{order_id}
     */
    public function sellerCancel(Request $request, string $orderId)
    {
        $seller = $request->user();

        $order = Order::where('id', $orderId)
            ->where('seller_id', $seller->id)
            ->whereIn('status', ['open', 'matched'])
            ->firstOrFail();

        DB::transaction(function () use ($order, $seller) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($lockedOrder->status === 'open') {
                // Case 1: No buyer matched yet
                $lockedOrder->update(['status' => 'cancelled']);
                
                // Remove from Redis queue
                try {
                    SimpleQueue::remove("queue:sellers:{$lockedOrder->amount}", $lockedOrder->id);
                } catch (\Throwable $e) {}

                // Release escrow
                $this->walletService->releaseEscrow(
                    $seller,
                    (float) $lockedOrder->amount,
                    "Sell order cancelled. â‚¹{$lockedOrder->amount} released from escrow.",
                    "à¤µà¤¿à¤•à¥à¤°à¤¯ à¤‘à¤°à¥à¤¡à¤° à¤°à¤¦à¥à¤¦à¥¤ â‚¹{$lockedOrder->amount} à¤à¤¸à¥à¤•à¥à¤°à¥‹ à¤¸à¥‡ à¤µà¤¾à¤ªà¤¸à¥¤"
                );
            } else if ($lockedOrder->status === 'matched') {
                // Case 2: Buyer is matched. Mark as cancel_requested and wait.
                $lockedOrder->update(['cancel_requested' => true]);
            }
        });

        if ($order->status === 'matched') {
            $trade = Trade::where('order_id', $order->id)->whereIn('status', ['pending_payment', 'payment_submitted'])->first();
            if ($trade) {
                broadcast(new TradeStatusUpdated($trade))->toOthers();
                $this->broadcastUserActivity($trade->buyer_id);
            }
        }

        $this->broadcastUserActivity($order->seller_id);
        return response()->json(['message' => 'Cancellation processed.']);
    }
    public function checkExpirations()
    {
        // Force the console command to run synchronously
        \Illuminate\Support\Facades\Artisan::call('trades:check-expired');
        return response()->json(['status' => 'success']);
    }

    private function broadcastUserActivity(...$userIds)
    {
        foreach ($userIds as $id) {
            if ($id) {
                event(new \App\Events\UserActivityUpdated($id));
            }
        }
    }
}