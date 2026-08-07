<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradeStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Trade $trade;

    public function __construct(Trade $trade)
    {
        $this->trade = $trade;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->trade->buyer_id}"),
            new PrivateChannel("user.{$this->trade->seller_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trade:update';
    }

    public function broadcastWith(): array
    {
        return [
            'trade_id' => $this->trade->id,
            'status'   => $this->trade->status,
            'amount'   => (float) $this->trade->amount,
        ];
    }
}
