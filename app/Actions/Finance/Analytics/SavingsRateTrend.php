<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class SavingsRateTrend
{
    /**
     * @return array<int, array{month: string, income_cents: int, spend_cents: int, savings_rate_pct: float}>
     */
    public function __invoke(int $monthsBack = 12): array
    {
        $today = CarbonImmutable::today();
        $start = $today->startOfMonth()->subMonthsNoOverflow($monthsBack - 1);

        $incomeIds = Category::query()->where('kind', 'income')->pluck('id');
        $spendIds = Category::query()->where('kind', 'spending')->pluck('id');

        $out = [];
        for ($i = 0; $i < $monthsBack; $i++) {
            $m = $start->addMonthsNoOverflow($i);
            $mEnd = $m->endOfMonth();

            $income = $incomeIds->isEmpty() ? 0 : (int) Transaction::query()
                ->whereIn('category_id', $incomeIds)
                ->whereDate('occurred_on', '>=', $m->toDateString())
                ->whereDate('occurred_on', '<=', $mEnd->toDateString())
                ->where('amount_cents', '>', 0)
                ->whereNull('deleted_at')
                ->sum('amount_cents');

            $spend = $spendIds->isEmpty() ? 0 : (int) abs((int) Transaction::query()
                ->whereIn('category_id', $spendIds)
                ->whereDate('occurred_on', '>=', $m->toDateString())
                ->whereDate('occurred_on', '<=', $mEnd->toDateString())
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->sum('amount_cents'));

            $rate = $income > 0 ? round((($income - $spend) / $income) * 100, 2) : 0.0;

            $out[] = [
                'month' => $m->format('Y-m'),
                'income_cents' => $income,
                'spend_cents' => $spend,
                'savings_rate_pct' => (float) $rate,
            ];
        }

        return $out;
    }
}
