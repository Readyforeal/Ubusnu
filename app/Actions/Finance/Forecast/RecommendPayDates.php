<?php

namespace App\Actions\Finance\Forecast;

use App\Models\Account;
use App\Models\Bill;
use Carbon\CarbonImmutable;

class RecommendPayDates
{
    /**
     * @param  array<int, Bill>  $bills
     * @param  array<int, array{account_id: int, date: string, balance_cents: int}>  $projection
     * @return array<int, array{bill_id: int, recommended_date: string, warning: bool}>
     */
    public function __invoke(array $bills, array $projection, CarbonImmutable $today, CarbonImmutable $horizonEnd): array
    {
        $byAccount = [];
        foreach ($projection as $row) {
            $byAccount[$row['account_id']][$row['date']] = (int) $row['balance_cents'];
        }

        $accountFloors = Account::query()->pluck('minimum_balance_cents', 'id')->all();

        $out = [];

        foreach ($bills as $bill) {
            if ($bill->account_id === null) {
                continue;
            }
            if ($this->isAlreadyPaid($bill, $today)) {
                continue;
            }

            $dueDate = $bill->nextDueDate();
            if ($dueDate->gt($horizonEnd)) {
                continue;
            }

            $floor = (int) ($accountFloors[$bill->account_id] ?? 0);
            $amount = (int) $bill->expected_amount_cents;
            $accountId = (int) $bill->account_id;

            $recommended = $this->earliestSafeDay($byAccount[$accountId] ?? [], $today, $dueDate, $amount, $floor);

            $out[] = [
                'bill_id' => (int) $bill->id,
                'recommended_date' => ($recommended ?? $dueDate)->toDateString(),
                'warning' => $recommended === null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $balanceByDate
     */
    private function earliestSafeDay(array $balanceByDate, CarbonImmutable $today, CarbonImmutable $dueDate, int $amount, int $floor): ?CarbonImmutable
    {
        $cursor = $today;
        while ($cursor->lte($dueDate)) {
            if ($this->staysSafe($balanceByDate, $cursor, $dueDate, $amount, $floor)) {
                return $cursor;
            }
            $cursor = $cursor->addDay();
        }

        return null;
    }

    /**
     * @param  array<string, int>  $balanceByDate
     */
    private function staysSafe(array $balanceByDate, CarbonImmutable $payDay, CarbonImmutable $dueDate, int $amount, int $floor): bool
    {
        $cursor = $payDay;
        while ($cursor->lte($dueDate)) {
            $balance = ($balanceByDate[$cursor->toDateString()] ?? 0) - $amount;
            if ($balance < $floor) {
                return false;
            }
            $cursor = $cursor->addDay();
        }

        return true;
    }

    private function isAlreadyPaid(Bill $bill, CarbonImmutable $today): bool
    {
        $period = $bill->cadence === 'annual' ? $today->format('Y') : $today->format('Y-m');

        if (in_array($period, $bill->manuallyMarkedPeriods(), true)) {
            return true;
        }

        return $bill->transactions()
            ->whereYear('occurred_on', $today->year)
            ->when($bill->cadence !== 'annual', fn ($q) => $q->whereMonth('occurred_on', $today->month))
            ->exists();
    }
}
