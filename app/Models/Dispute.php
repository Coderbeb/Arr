<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    use HasFactory;

    protected $table = 'disputes';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'trade_id',
        'raised_by',
        'status',
        'buyer_screenshot_url',
        'buyer_screen_recording_url',
        'buyer_bank_statement_url',
        'buyer_utr_number',
        'buyer_upi_screenshot_url',
        'buyer_ai_score',
        'buyer_ai_breakdown',
        'buyer_proof_analysis',
        'buyer_proof_submitted_at',
        'seller_screen_recording_url',
        'seller_txn_screenshot_url',
        'seller_profile_recording_url',
        'seller_ai_score',
        'seller_ai_breakdown',
        'seller_proof_analysis',
        'seller_proof_submitted_at',
        'ai_recommendation',
        'ai_confidence',
        'assigned_to',
        'resolved_by',
        'resolution_notes',
        'resolved_at',
        'proof_deadline',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'buyer_ai_score' => 'integer',
            'buyer_ai_breakdown' => 'array',
            'buyer_proof_analysis' => 'array',
            'buyer_proof_submitted_at' => 'datetime',
            'seller_ai_score' => 'integer',
            'seller_ai_breakdown' => 'array',
            'seller_proof_analysis' => 'array',
            'seller_proof_submitted_at' => 'datetime',
            'ai_confidence' => 'integer',
            'resolved_at' => 'datetime',
            'proof_deadline' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function trade()
    {
        return $this->belongsTo(Trade::class, 'trade_id');
    }

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
