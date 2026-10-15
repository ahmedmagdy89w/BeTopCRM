<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\Auditable;

class EmailTemplate extends Model
{
    use HasFactory, Auditable;

    protected $fillable = ['name', 'subject', 'body', 'placeholders', 'created_by'];

    protected $casts = [
        'placeholders' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $template) {
            preg_match_all('/\{\{(\w+)\}\}/', $template->body, $matches);
            $template->placeholders = array_values(array_unique($matches[1]));
        });
    }
}
