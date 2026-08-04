<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'seller_id',
        'amount',
        'coin_amount',
        'commission_pct',
        'commission_amt',
        'seller_upi_id',
        'seller_upi_app',
        'seller_qr_url',
        'status',
        'cancel_requested',
        'created_at',
        'expires_at',
        'matched_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'coin_amount' => 'float',
            'commission_pct' => 'float',
            'commission_amt' => 'float',
            'cancel_requested' => 'boolean',
            'created_at' => 'datetime',
            'expires_at' => 'datetime',
            'matched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function trade()
    {
        return $this->hasOne(Trade::class, 'order_id');
    }
}
