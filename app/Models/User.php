<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false; // We use created_at default NOW() in DB

    protected $fillable = [
        'id',
        'mobile_number',
        'email',
        'full_name',
        'date_of_birth',
        'password_hash',
        'upi_id',
        'upi_app',
        'upi_qr_image_url',
        'city',
        'language',
        'role',
        'status',
        'wallet_balance',
        'escrow_balance',
        'total_trades',
        'reputation_score',
        'strike_count',
        'is_verified',
        'failed_dob_attempts',
        'dob_lockout_until',
        'consecutive_cancels',
        'buy_ban_until',
        'created_at',
        'last_login',
        'referral_code',
        'referred_by',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'wallet_balance' => 'float',
            'escrow_balance' => 'float',
            'total_trades' => 'integer',
            'reputation_score' => 'integer',
            'strike_count' => 'integer',
            'is_verified' => 'boolean',
            'failed_dob_attempts' => 'integer',
            'dob_lockout_until' => 'datetime',
            'consecutive_cancels' => 'integer',
            'buy_ban_until' => 'datetime',
            'created_at' => 'datetime',
            'last_login' => 'datetime',
        ];
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->id)) {
                $user->id = (string) Str::uuid();
            }
            if (empty($user->referral_code)) {
                $user->referral_code = strtoupper(Str::random(8));
            }
        });
    }

    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function tradesAsBuyer()
    {
        return $this->hasMany(Trade::class, 'buyer_id');
    }

    public function tradesAsSeller()
    {
        return $this->hasMany(Trade::class, 'seller_id');
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class, 'user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }
}
