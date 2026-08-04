<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    use HasFactory;

    protected $table = 'platform_settings';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'registration_open',
        'commission_percent',
        'max_daily_earning',
        'max_weekly_earning',
        'trade_accept_minutes',
        'payment_timer_minutes',
        'dispute_proof_minutes',
        'global_announcement',
        'updated_by',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'registration_open' => 'boolean',
            'commission_percent' => 'float',
            'max_daily_earning' => 'float',
            'max_weekly_earning' => 'float',
            'trade_accept_minutes' => 'integer',
            'payment_timer_minutes' => 'integer',
            'dispute_proof_minutes' => 'integer',
            'updated_at' => 'datetime',
        ];
    }
}
