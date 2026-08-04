<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EarningsTracker extends Model
{
    use HasFactory;

    protected $table = 'earnings_tracker';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'date',
        'daily_earned',
        'weekly_earned',
        'week_start',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'daily_earned' => 'float',
            'weekly_earned' => 'float',
            'week_start' => 'date',
        ];
    }
}
