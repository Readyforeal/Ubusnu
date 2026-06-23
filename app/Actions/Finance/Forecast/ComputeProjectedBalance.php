<?php

namespace App\Actions\Finance\Forecast;

use App\Actions\Finance\Balance\ComputeBalanceSeries;
use App\Models\Account;
use App\Models\Bill;
use App\Models\IncomeSource;
use Carbon\CarbonImmutable;

class ComputeProjectedBalance
{
    /**
     * @param  array<int, Account>  $accounts
     * @return array<int, array{account_id: int, date: string, balance_cents: int}>
     */
    public function __invoke(array $accounts, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $incomeRows = (new ProjectIncomeDeposits)(IncomeSource::all()->all(), $start, $end);
        $billRows = (new ProjectBillCharges)(Bill::all()->all(), $start, $end);
        $variableRows = (new ForecastVariableSpend)($start, $end);

        $perAccountDeltas = [];

        foreach ($incomeRows as $row) {
            $perAccountDeltas[$row['account_id']][$row['date']] = ($perAccountDeltas[$row['account_id']][$row['date']] ?? 0) + $row['cents'];
        }

        foreach ($billRows as $row) {
            $perAccountDeltas[$row['account_id']][$row['date']] = ($perAccountDeltas[$row['account_id']][$row['date']] ?? 0) - $row['cents'];
        }

        // Variable spend is account-agnostic; attribute it entirely to the first account passed in
        // (the user's primary). This is acceptable because variable spend is a forecast, not a precise per-account ledger.
        $primaryAccountId = isset($accounts[0]) ? (int) $accounts[0]->id : null;
        if ($primaryAccountId !== null) {
            foreach ($variableRows as $row) {
                $perAccountDeltas[$primaryAccountId][$row['date']] = ($perAccountDeltas[$primaryAccountId][$row['date']] ?? 0) - $row['cents'];
            }
        }

        $out = [];
        $startDate = $start->toDateString();

        foreach ($accounts as $account) {
            // Seed from existing balance series at $start
            $seed = (new ComputeBalanceSeries)([$account], $startDate, $startDate);
            $running = $seed[0]['balance_cents'] ?? 0;

            $cursor = $start;
            while ($cursor->lte($end)) {
                $key = $cursor->toDateString();
                $running += $perAccountDeltas[$account->id][$key] ?? 0;
                $out[] = [
                    'account_id' => (int) $account->id,
                    'date' => $key,
                    'balance_cents' => $running,
                ];
                $cursor = $cursor->addDay();
            }
        }

        return $out;
    }
}
