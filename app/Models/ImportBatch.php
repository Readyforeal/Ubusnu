<?php

namespace App\Models;

use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['account_id', 'user_id', 'filename', 'row_count', 'imported_count', 'skipped_duplicate_count', 'error_count', 'undone_at'])]
class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'undone_at' => 'datetime',
            'row_count' => 'integer',
            'imported_count' => 'integer',
            'skipped_duplicate_count' => 'integer',
            'error_count' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('undone_at');
    }

    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }
}
