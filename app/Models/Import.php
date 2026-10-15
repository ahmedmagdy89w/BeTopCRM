<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'entity_type', 'original_filename', 'status',
        'total_rows', 'processed_rows', 'successful_rows', 'failed_rows',
        'errors', 'completed_at',
    ];

    protected $casts = [
        'errors' => 'array',
        'completed_at' => 'datetime',
    ];

    const STATUSES = ['pending', 'processing', 'completed', 'failed'];

    const ENTITY_TYPES = ['leads', 'customers', 'opportunities', 'products', 'categories'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
