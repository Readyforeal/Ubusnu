<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class SpendingVelocity
{
    /**
     * @return array{this_month_cents_so_far: int, last_month_cents_through_same_day: int, delta_pct: float, projected_full_month_cents: int}
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();
        $thisStart = $today->startOfMonth();
        $thisEnd = $today;
        $lastStart = $thisStart->subMonthsNoOverflow(1);
        $lastEndSameDay = $lastStart->setDay(min($today->day, $lastStart->daysInMonth));

        $spendIds = Category::query()->where('kind', 'spending')->pluck('id');

        $thisSpend = $spendIds->isEmpty() ? 0 : (int) abs((int) Transaction::query()
            ->whereIn('category_id', $spendIds)
            ->whereDate('occurred_on', '>=', $thisStart->toDateString())
            ->whereDate('occurred_on', '<=', $thisEnd->toDateString())
            ->where('amount_cents', '<', 0)
            ->whereNull('deleted_at')
            ->sum('amount_cents'));

        $lastSpend = $spendIds->isEmpty() ? 0 : (int) abs((int) Transaction::query()
            ->whereIn('category_id', $spendIds)
            ->whereDate('occurred_on', '>=', $lastStart->toDateString())
            ->whereDate('occurred_on', '<=', $lastEndSameDay->toDateString())
            ->where('amount_cents', '<', 0)
            ->whereNull('deleted_at')
            ->sum('amount_cents'));

        $deltaPct = $lastSpend > 0 ? round((($thisSpend - $lastSpend) / $lastSpend) * 100, 2) : 0.0;

        $daysElapsed = max(1, $today->day);
        $totalDays = $today->endOfMonth()->day;
        $projected = (int) round(($thisSpend / $daysElapsed) * $totalDays);

        return [
            'this_month_cents_so_far' => $thisSpend,
            'last_month_cents_through_same_day' => $lastSpend,
            'delta_pct' => (float) $deltaPct,
            'projected_full_month_cents' => $projected,
        ];
    }
}
