<?php

namespace App\Models;

use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'cadence', 'due_day_of_month', 'due_month_of_year',
    'expected_amount_cents', 'account_id', 'category_id',
    'match_description', 'manually_marked_paid_periods',
    'color', 'notes', 'sort_order',
])]
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'due_day_of_month' => 'integer',
            'due_month_of_year' => 'integer',
            'expected_amount_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
