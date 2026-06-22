<?php

namespace App\Models;

use Carbon\CarbonImmutable;
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

    /**
     * @return array<int, string>
     */
    public function manuallyMarkedPeriods(): array
    {
        if (! $this->manually_marked_paid_periods) {
            return [];
        }

        return collect(explode(',', $this->manually_marked_paid_periods))
            ->map(fn (string $p) => trim($p))
            ->filter()
            ->values()
            ->all();
    }

    public function currentPeriodToken(): string
    {
        $now = CarbonImmutable::now();

        return $this->cadence === 'annual' ? $now->format('Y') : $now->format('Y-m');
    }

    public function addManuallyMarkedPeriod(string $period): void
    {
        $periods = $this->manuallyMarkedPeriods();
        if (! in_array($period, $periods, true)) {
            $periods[] = $period;
        }
        $this->update(['manually_marked_paid_periods' => implode(',', $periods)]);
    }

    public function removeManuallyMarkedPeriod(string $period): void
    {
        $periods = array_values(array_filter(
            $this->manuallyMarkedPeriods(),
            fn (string $p) => $p !== $period
        ));
        $this->update(['manually_marked_paid_periods' => $periods === [] ? null : implode(',', $periods)]);
    }

    public function nextDueDate(): CarbonImmutable
    {
        $today = CarbonImmutable::today();

        if ($this->cadence === 'annual') {
            $month = $this->due_month_of_year ?? 1;
            $thisYear = $this->buildDate($today->year, $month, $this->due_day_of_month);
            if ($thisYear->gte($today)) {
                return $thisYear;
            }

            return $this->buildDate($today->year + 1, $month, $this->due_day_of_month);
        }

        $thisMonth = $this->buildDate($today->year, $today->month, $this->due_day_of_month);
        if ($thisMonth->gte($today)) {
            return $thisMonth;
        }

        $nextMonth = $today->addMonth();

        return $this->buildDate($nextMonth->year, $nextMonth->month, $this->due_day_of_month);
    }

    private function buildDate(int $year, int $month, int $day): CarbonImmutable
    {
        $daysInMonth = CarbonImmutable::create($year, $month, 1)->daysInMonth;
        $safeDay = min($day, $daysInMonth);

        return CarbonImmutable::create($year, $month, $safeDay);
    }
}
