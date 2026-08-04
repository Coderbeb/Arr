<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BonusMilestone extends Model
{
    use HasFactory;

    protected $table = 'bonus_milestones';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'trade_count',
        'bonus_amount',
        'is_active',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'trade_count' => 'integer',
            'bonus_amount' => 'float',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
