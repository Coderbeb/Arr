<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Create in-app notification in English and Hindi
     */
    public function createNotification(string $userId, string $type, string $titleEn, string $titleHi, string $bodyEn, string $bodyHi, ?string $tradeId = null, ?string $disputeId = null): Notification
    {
        return Notification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'type' => $type,
            'title_en' => $titleEn,
            'title_hi' => $titleHi,
            'body_en' => $bodyEn,
            'body_hi' => $bodyHi,
            'is_read' => false,
            'trade_id' => $tradeId,
            'dispute_id' => $disputeId,
        ]);
    }
}
