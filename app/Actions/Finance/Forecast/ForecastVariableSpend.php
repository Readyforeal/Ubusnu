<?php

namespace App\Actions\Finance\Forecast;

use App\Models\AppSetting;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class ForecastVariableSpend
{
    /**
     * @return array<int, array{date: string, category_id: int, cents: int}>
     */
    public function __invoke(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $lookbackWeeks = (int) AppSetting::current()->forecast_lookback_weeks;
        $lookbackStart = CarbonImmutable::today()->subWeeks($lookbackWeeks)->startOfWeek();
        $lookbackEnd = CarbonImmutable::today()->subDay()->endOfDay();

        $billCategoryIds = Bill::query()->whereNotNull('category_id')->pluck('category_id')->all();

        $categories = Category::query()
            ->where('kind', 'spending')
            ->whereNotIn('id', $billCategoryIds)
            ->get();

        $out = [];

        foreach ($categories as $category) {
            $weeklyMedian = $this->weeklyMedianSpend($category->id, $lookbackStart, $lookbackEnd);
            if ($weeklyMedian === null) {
                continue;
            }

            $perDay = (int) round($weeklyMedian / 7);
            if ($perDay <= 0) {
                continue;
            }

            $cursor = $start;
            while ($cursor->lte($end)) {
                $out[] = [
                    'date' => $cursor->toDateString(),
                    'category_id' => (int) $category->id,
                    'cents' => $perDay,
                ];
                $cursor = $cursor->addDay();
            }
        }

        return $out;
    }

    private function weeklyMedianSpend(int $categoryId, CarbonImmutable $start, CarbonImmutable $end): ?int
    {
        $rows = Transaction::query()
            ->where('category_id', $categoryId)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->where('amount_cents', '<', 0)
            ->whereNull('deleted_at')
            ->get(['occurred_on', 'amount_cents']);

        if ($rows->isEmpty()) {
            return null;
        }

        $byWeek = [];
        foreach ($rows as $row) {
            $weekKey = CarbonImmutable::parse($row->occurred_on)->startOfWeek()->toDateString();
            $byWeek[$weekKey] = ($byWeek[$weekKey] ?? 0) + abs((int) $row->amount_cents);
        }

        if (count($byWeek) < 4) {
            return null;
        }

        $values = array_values($byWeek);
        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);

        return $count % 2 === 1
            ? $values[$mid]
            : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }
}
