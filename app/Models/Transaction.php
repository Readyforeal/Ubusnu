<?php

namespace App\Models;

use App\Support\TransactionHash;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['account_id', 'occurred_on', 'description', 'amount_cents', 'category_id', 'dedup_hash', 'import_batch_id', 'source', 'notes'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            if (! $transaction->dedup_hash) {
                $transaction->dedup_hash = TransactionHash::for(
                    $transaction->account_id,
                    $transaction->occurred_on instanceof \DateTimeInterface
                        ? $transaction->occurred_on->format('Y-m-d')
                        : (string) $transaction->occurred_on,
                    $transaction->amount_cents,
                    $transaction->description,
                );
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
