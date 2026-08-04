<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    use HasFactory;

    protected $table = 'trades';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'order_id',
        'buyer_id',
        'seller_id',
        'amount',
        'commission_amount',
        'buyer_upi_app',
        'utr_number',
        'payment_screenshot_url',
        'buyer_payment_screenshot_url',
        'status',
        'matched_at',
        'payment_deadline',
        'paid_at',
        'completed_at',
        'cancelled_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'commission_amount' => 'float',
            'matched_at' => 'datetime',
            'payment_deadline' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function dispute()
    {
        return $this->hasOne(Dispute::class, 'trade_id');
    }
}
