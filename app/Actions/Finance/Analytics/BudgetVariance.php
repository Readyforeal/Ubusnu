<?php

namespace App\Actions\Finance\Analytics;

use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BudgetVariance
{
    /**
     * @return array<int, array{bucket_id: int, name: string, planned_cents: int, actual_cents: int, variance_pct: float, days_remaining_in_period: int}>
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();
        $daysRemaining = (int) $today->diffInDays($monthEnd) + 1;

        $incomeTarget = (int) AppSetting::current()->monthly_income_target_cents;

        $buckets = Bucket::query()->with('categories')->orderBy('sort_order')->orderBy('name')->get();

        $out = [];
        foreach ($buckets as $bucket) {
            $planned = $bucket->targetCents($incomeTarget);
            $categoryIds = $bucket->categories->pluck('id')->all();

            $actual = $categoryIds === [] ? 0 : (int) Transaction::query()
                ->whereIn('category_id', $categoryIds)
                ->whereDate('occurred_on', '>=', $monthStart->toDateString())
                ->whereDate('occurred_on', '<=', $monthEnd->toDateString())
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->sum(DB::raw('ABS(amount_cents)'));

            $pct = $planned > 0 ? round(($actual / $planned) * 100, 2) : 0.0;

            $out[] = [
                'bucket_id' => (int) $bucket->id,
                'name' => $bucket->name,
                'planned_cents' => $planned,
                'actual_cents' => $actual,
                'variance_pct' => (float) $pct,
                'days_remaining_in_period' => $daysRemaining,
            ];
        }

        return $out;
    }
}
