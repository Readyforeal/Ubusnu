<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Bill;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class FixedVariableRatio
{
    /**
     * @return array<int, array{month: string, fixed_cents: int, variable_cents: int, fixed_ratio_pct: float}>
     */
    public function __invoke(int $monthsBack = 6): array
    {
        $today = CarbonImmutable::today();
        $start = $today->startOfMonth()->subMonthsNoOverflow($monthsBack - 1);

        $billCategoryIds = Bill::query()->whereNotNull('category_id')->pluck('category_id')->all();

        $out = [];
        for ($i = 0; $i < $monthsBack; $i++) {
            $m = $start->addMonthsNoOverflow($i);
            $mEnd = $m->endOfMonth();

            $fixed = $billCategoryIds === [] ? 0 : (int) abs((int) Transaction::query()
                ->whereIn('category_id', $billCategoryIds)
                ->whereDate('occurred_on', '>=', $m->toDateString())
                ->whereDate('occurred_on', '<=', $mEnd->toDateString())
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->sum('amount_cents'));

            $variable = (int) abs((int) Transaction::query()
                ->whereNotIn('category_id', $billCategoryIds === [] ? [0] : $billCategoryIds)
                ->whereNotNull('category_id')
                ->whereDate('occurred_on', '>=', $m->toDateString())
                ->whereDate('occurred_on', '<=', $mEnd->toDateString())
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->sum('amount_cents'));

            $total = $fixed + $variable;
            $ratio = $total > 0 ? round(($fixed / $total) * 100, 2) : 0.0;

            $out[] = [
                'month' => $m->format('Y-m'),
                'fixed_cents' => $fixed,
                'variable_cents' => $variable,
                'fixed_ratio_pct' => (float) $ratio,
            ];
        }

        return $out;
    }
}
