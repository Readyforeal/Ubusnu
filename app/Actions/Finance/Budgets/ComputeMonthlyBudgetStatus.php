<?php

namespace App\Actions\Finance\Budgets;

use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class ComputeMonthlyBudgetStatus
{
    /**
     * @return array{
     *     period: string,
     *     income_target_cents: int,
     *     income_actual_cents: int,
     *     buckets: array<int, array{id: int, name: string, color: ?string, target_percentage: int, target_cents: int, actual_cents: int, over_target: bool}>,
     *     unassigned_actual_cents: int
     * }
     */
    public function __invoke(?string $yearMonth = null): array
    {
        $period = $yearMonth ?? CarbonImmutable::today()->format('Y-m');
        $start = CarbonImmutable::parse($period.'-01');
        $end = $start->endOfMonth();

        $incomeTargetCents = AppSetting::current()->monthly_income_target_cents;

        $incomeActualCents = (int) Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.kind', 'income')
            ->whereBetween('transactions.occurred_on', [$start->toDateString(), $end->toDateTimeString()])
            ->sum('transactions.amount_cents');

        $bucketSums = Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.kind', 'spending')
            ->whereBetween('transactions.occurred_on', [$start->toDateString(), $end->toDateTimeString()])
            ->selectRaw('categories.bucket_id, SUM(transactions.amount_cents) AS net_cents')
            ->groupBy('categories.bucket_id')
            ->pluck('net_cents', 'bucket_id');

        $unassignedActual = (int) (-1 * ($bucketSums[null] ?? 0));

        $buckets = Bucket::query()->orderBy('sort_order')->orderBy('id')->get()->map(function (Bucket $bucket) use ($bucketSums, $incomeTargetCents) {
            $target = $bucket->targetCents($incomeTargetCents);
            $actual = (int) (-1 * ($bucketSums[$bucket->id] ?? 0));

            return [
                'id' => $bucket->id,
                'name' => $bucket->name,
                'color' => $bucket->color,
                'target_percentage' => $bucket->target_percentage,
                'target_cents' => $target,
                'actual_cents' => $actual,
                'over_target' => $target > 0 && $actual > $target,
            ];
        })->all();

        return [
            'period' => $period,
            'income_target_cents' => $incomeTargetCents,
            'income_actual_cents' => $incomeActualCents,
            'buckets' => $buckets,
            'unassigned_actual_cents' => $unassignedActual,
        ];
    }
}
