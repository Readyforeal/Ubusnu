<?php

namespace App\Actions\Finance\Goals;

use App\Models\Account;
use App\Models\Goal;
use App\Models\Transaction;

class ComputeGoalsStatus
{
    /**
     * @return array{
     *     pool_cents: int,
     *     goals: array<int, array{id: int, name: string, color: ?string, priority_percentage: int, target_cents: int, raw_allocation_cents: int, capped_allocation_cents: int, funded_percentage: int, overflow_cents: int, is_fully_funded: bool}>,
     *     total_allocated_cents: int,
     *     unallocated_cents: int,
     *     total_priority_percentage: int
     * }
     */
    public function __invoke(): array
    {
        $startingBalance = (int) Account::query()
            ->where('counts_toward_goals', true)
            ->whereNull('archived_at')
            ->sum('starting_balance_cents');

        $transactionNet = (int) Transaction::query()
            ->join('accounts', 'transactions.account_id', '=', 'accounts.id')
            ->where('accounts.counts_toward_goals', true)
            ->whereNull('accounts.archived_at')
            ->sum('transactions.amount_cents');

        $pool = $startingBalance + $transactionNet;

        $goals = Goal::query()->orderBy('sort_order')->orderBy('id')->get();

        $rows = [];
        $totalAllocated = 0;
        $totalPriority = 0;

        foreach ($goals as $goal) {
            $raw = intdiv($pool * $goal->priority_percentage, 100);
            $capped = min($goal->target_cents, max(0, $raw));
            $overflow = $raw - $capped;
            $funded = $goal->target_cents > 0
                ? min(100, intdiv($capped * 100, $goal->target_cents))
                : 0;

            $rows[] = [
                'id' => $goal->id,
                'name' => $goal->name,
                'color' => $goal->color,
                'priority_percentage' => $goal->priority_percentage,
                'target_cents' => $goal->target_cents,
                'raw_allocation_cents' => $raw,
                'capped_allocation_cents' => $capped,
                'funded_percentage' => $funded,
                'overflow_cents' => $overflow,
                'is_fully_funded' => $capped === $goal->target_cents,
            ];

            $totalAllocated += $capped;
            $totalPriority += $goal->priority_percentage;
        }

        return [
            'pool_cents' => $pool,
            'goals' => $rows,
            'total_allocated_cents' => $totalAllocated,
            'unallocated_cents' => $pool - $totalAllocated,
            'total_priority_percentage' => $totalPriority,
        ];
    }
}
