<?php

namespace App\Actions\Finance\Bills;

use App\Models\Bill;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class ComputeBillsStatus
{
    /**
     * @return array{
     *     bills: array<int, array<string, mixed>>,
     *     total_upcoming_cents: int,
     *     total_paid_this_period_cents: int
     * }
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();
        $bills = Bill::query()->orderBy('sort_order')->orderBy('id')->get();

        $rows = [];
        $totalUpcoming = 0;
        $totalPaid = 0;

        foreach ($bills as $bill) {
            [$periodStart, $periodEnd] = $this->periodBoundaries($bill, $today);
            $period = $bill->currentPeriodToken();

            $matched = Transaction::query()
                ->where('bill_id', $bill->id)
                ->whereBetween('occurred_on', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->first();

            $manuallyPaid = in_array($period, $bill->manuallyMarkedPeriods(), true);
            $isPaid = $matched !== null || $manuallyPaid;
            $source = $matched ? 'transaction' : ($manuallyPaid ? 'manual' : null);

            $nextDue = $bill->nextDueDate();
            $daysUntilDue = (int) $today->diffInDays($nextDue, false);

            $rows[] = [
                'id' => $bill->id,
                'name' => $bill->name,
                'cadence' => $bill->cadence,
                'due_day_of_month' => $bill->due_day_of_month,
                'due_month_of_year' => $bill->due_month_of_year,
                'next_due_date' => $nextDue->toDateString(),
                'days_until_due' => $daysUntilDue,
                'expected_amount_cents' => $bill->expected_amount_cents,
                'is_paid_this_period' => $isPaid,
                'payment_source' => $source,
                'last_paid_transaction_id' => $matched?->id,
                'account_name' => $bill->account?->name,
                'category_name' => $bill->category?->name,
                'color' => $bill->color,
            ];

            if ($isPaid) {
                $totalPaid += $bill->expected_amount_cents;
            } else {
                $totalUpcoming += $bill->expected_amount_cents;
            }
        }

        return [
            'bills' => $rows,
            'total_upcoming_cents' => $totalUpcoming,
            'total_paid_this_period_cents' => $totalPaid,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodBoundaries(Bill $bill, CarbonImmutable $today): array
    {
        if ($bill->cadence === 'annual') {
            return [$today->startOfYear(), $today->endOfYear()];
        }

        return [$today->startOfMonth(), $today->endOfMonth()];
    }
}
