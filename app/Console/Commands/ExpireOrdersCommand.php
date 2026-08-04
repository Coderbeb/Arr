<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\User;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class ExpireOrdersCommand extends Command
{
    protected $signature = 'app:expire-orders';
    protected $description = 'Expire open orders that have passed their 30-minute timer.';

    public function handle(WalletService $walletService)
    {
        $expiredOrders = Order::where('status', 'open')
            ->where('expires_at', '<', Carbon::now())
            ->get();

        $count = 0;
        foreach ($expiredOrders as $order) {
            DB::transaction(function () use ($order, $walletService) {
                $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
                if ($lockedOrder->status !== 'open') return;

                $lockedOrder->update(['status' => 'cancelled']);

                Redis::lrem("queue:sellers:{$lockedOrder->amount}", 0, $lockedOrder->id);

                $seller = User::where('id', $lockedOrder->seller_id)->lockForUpdate()->first();
                $walletService->releaseEscrow(
                    $seller,
                    (float) $lockedOrder->amount,
                    "Sell order expired after 30 minutes. ₹{$lockedOrder->amount} released from escrow.",
                    "विक्रय ऑर्डर 30 मिनट बाद समाप्त। ₹{$lockedOrder->amount} एस्क्रो से वापस।"
                );
            });
            $count++;
        }

        $this->info("Expired {$count} orders.");
    }
}
