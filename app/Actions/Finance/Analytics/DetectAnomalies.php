<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Category;
use App\Models\Transaction;
use App\Support\Stats;
use Carbon\CarbonImmutable;

class DetectAnomalies
{
    /**
     * @return array<int, array{transaction_id: int, description: string, amount_cents: int, category_id: int, category_median_cents: int, std_devs_from_median: float}>
     */
    public function __invoke(int $lookbackDays = 90, float $stdDevThreshold = 2.0): array
    {
        $start = CarbonImmutable::today()->subDays($lookbackDays);
        $end = CarbonImmutable::today();

        $categories = Category::query()->where('kind', 'spending')->get();

        $out = [];
        foreach ($categories as $category) {
            $rows = Transaction::query()
                ->where('category_id', $category->id)
                ->whereDate('occurred_on', '>=', $start->toDateString())
                ->whereDate('occurred_on', '<=', $end->toDateString())
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->get(['id', 'description', 'amount_cents']);

            if ($rows->count() < 10) {
                continue;
            }

            $amounts = $rows->map(fn ($r) => abs((int) $r->amount_cents))->all();
            $median = Stats::median($amounts);
            $stddev = Stats::stdDev($amounts);
            if ($median === null || $stddev === null || $stddev <= 0) {
                continue;
            }

            foreach ($rows as $row) {
                $abs = abs((int) $row->amount_cents);
                $devs = ($abs - $median) / $stddev;
                if ($devs >= $stdDevThreshold) {
                    $out[] = [
                        'transaction_id' => (int) $row->id,
                        'description' => (string) $row->description,
                        'amount_cents' => (int) $row->amount_cents,
                        'category_id' => (int) $category->id,
                        'category_median_cents' => (int) round($median),
                        'std_devs_from_median' => round($devs, 2),
                    ];
                }
            }
        }

        usort($out, fn ($a, $b) => $b['std_devs_from_median'] <=> $a['std_devs_from_median']);

        return $out;
    }
}
