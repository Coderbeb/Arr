<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProofFile extends Model
{
    use HasFactory;

    protected $table = 'proof_files';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'dispute_id',
        'trade_id',
        'uploaded_by',
        'file_type',
        'file_url',
        'file_hash',
        'file_size',
        'mime_type',
        'analysis',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'analysis' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
