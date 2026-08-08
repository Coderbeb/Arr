<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBonusClaimed extends Model
{
    use HasFactory;

    protected $table = 'user_bonuses_claimed';

    protected $keyType = 'string';
    public $incrementing = false;

    // The table only has claimed_at instead of standard timestamps
    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'milestone_id',
        'claimed_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function milestone()
    {
        return $this->belongsTo(BonusMilestone::class);
    }
}
