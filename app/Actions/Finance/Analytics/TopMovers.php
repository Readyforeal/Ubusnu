<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TopMovers
{
    /**
     * @return array<int, array{category_id: int, name: string, current_cents: int, previous_cents: int, delta_pct: float, direction: string}>
     */
    public function __invoke(int $monthsBack = 1, int $limit = 5): array
    {
        $today = CarbonImmutable::today();
        $currentStart = $today->startOfMonth();
        $currentEnd = $today->endOfMonth();
        $previousStart = $currentStart->subMonthsNoOverflow($monthsBack);
        $previousEnd = $previousStart->endOfMonth();

        $spendingCategories = Category::query()->where('kind', 'spending')->pluck('name', 'id');

        if ($spendingCategories->isEmpty()) {
            return [];
        }

        $sumByCategory = function (CarbonImmutable $start, CarbonImmutable $end) use ($spendingCategories) {
            return Transaction::query()
                ->whereIn('category_id', $spendingCategories->keys())
                ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->select('category_id', DB::raw('SUM(ABS(amount_cents)) as cents'))
                ->groupBy('category_id')
                ->pluck('cents', 'category_id');
        };

        $current = $sumByCategory($currentStart, $currentEnd);
        $previous = $sumByCategory($previousStart, $previousEnd);

        $rows = [];
        foreach ($spendingCategories as $id => $name) {
            $curr = (int) ($current[$id] ?? 0);
            $prev = (int) ($previous[$id] ?? 0);
            if ($curr === 0 && $prev === 0) {
                continue;
            }
            if ($prev === 0) {
                $pct = 100.0;
            } else {
                $pct = round((($curr - $prev) / $prev) * 100, 2);
            }
            $rows[] = [
                'category_id' => (int) $id,
                'name' => $name,
                'current_cents' => $curr,
                'previous_cents' => $prev,
                'delta_pct' => (float) $pct,
                'direction' => $pct >= 0 ? 'up' : 'down',
            ];
        }

        usort($rows, fn ($a, $b) => abs($b['delta_pct']) <=> abs($a['delta_pct']));

        return array_slice($rows, 0, $limit);
    }
}
