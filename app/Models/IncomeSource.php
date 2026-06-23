<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IncomeSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'cadence', 'next_expected_on', 'secondary_day_of_month', 'primary_day_of_month',
    'expected_amount_cents', 'account_id', 'category_id',
    'match_description', 'color', 'notes', 'sort_order',
])]
class IncomeSource extends Model
{
    /** @use HasFactory<IncomeSourceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'next_expected_on' => 'immutable_date',
            'secondary_day_of_month' => 'integer',
            'primary_day_of_month' => 'integer',
            'expected_amount_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (IncomeSource $source) {
            if ($source->primary_day_of_month === null && $source->next_expected_on) {
                $day = $source->next_expected_on instanceof CarbonImmutable
                    ? $source->next_expected_on->day
                    : CarbonImmutable::parse($source->next_expected_on)->day;
                $source->primary_day_of_month = $day;
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function advanceAnchor(): CarbonImmutable
    {
        $current = $this->next_expected_on;

        return match ($this->cadence) {
            'weekly' => $current->addWeek(),
            'biweekly' => $current->addWeeks(2),
            'monthly' => $this->safeDay($current->addMonthsNoOverflow(1), $this->primary_day_of_month ?? $current->day),
            'semi_monthly' => $this->advanceSemiMonthly($current),
            default => $current->addMonthsNoOverflow(1),
        };
    }

    private function advanceSemiMonthly(CarbonImmutable $current): CarbonImmutable
    {
        $primary = (int) ($this->primary_day_of_month ?? $current->day);
        $secondary = (int) ($this->secondary_day_of_month ?? 15);

        // If we're currently on or before the primary day, advance to the secondary day in same month.
        // Otherwise (we're on/past secondary), advance to the primary day of next month.
        if ($current->day < $secondary) {
            return $this->safeDay($current, $secondary);
        }

        return $this->safeDay($current->addMonthsNoOverflow(1)->startOfMonth(), $primary);
    }

    private function safeDay(CarbonImmutable $month, int $day): CarbonImmutable
    {
        $daysInMonth = $month->daysInMonth;

        return $month->setDay(min($day, $daysInMonth));
    }
}
