<?php

namespace App\Actions\Finance\Balance;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class ComputeBalanceSeries
{
    /**
     * @param  array<int, Account>  $accounts
     * @return array<int, array{date: string, balance_cents: int}>
     */
    public function __invoke(array $accounts, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate);
        $end = CarbonImmutable::parse($endDate);

        $deltas = [];
        $anchor = 0;

        foreach ($accounts as $account) {
            $anchor += $account->starting_balance_cents + (int) Transaction::query()
                ->where('account_id', $account->id)
                ->whereDate('occurred_on', '<', $start->toDateString())
                ->whereNull('deleted_at')
                ->sum('amount_cents');

            $rows = Transaction::query()
                ->where('account_id', $account->id)
                ->whereDate('occurred_on', '>=', $start->toDateString())
                ->whereDate('occurred_on', '<=', $end->toDateString())
                ->whereNull('deleted_at')
                ->selectRaw('occurred_on, SUM(amount_cents) as delta')
                ->groupBy('occurred_on')
                ->pluck('delta', 'occurred_on');

            foreach ($rows as $date => $delta) {
                $key = CarbonImmutable::parse($date)->toDateString();
                $deltas[$key] = ($deltas[$key] ?? 0) + (int) $delta;
            }
        }

        $series = [];
        $running = $anchor;
        $cursor = $start;

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $running += ($deltas[$key] ?? 0);
            $series[] = ['date' => $key, 'balance_cents' => $running];
            $cursor = $cursor->addDay();
        }

        return $series;
    }
}
