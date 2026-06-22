<?php

namespace App\Actions\Finance\Forecast;

use App\Models\Bill;
use Carbon\CarbonImmutable;

class ProjectBillCharges
{
    /**
     * @param  array<int, Bill>  $bills
     * @return array<int, array{date: string, account_id: int, cents: int, bill_id: int}>
     */
    public function __invoke(array $bills, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $out = [];

        foreach ($bills as $bill) {
            if ($bill->account_id === null) {
                continue;
            }

            if ($bill->cadence === 'annual') {
                $this->emitAnnual($bill, $start, $end, $out);
            } else {
                $this->emitMonthly($bill, $start, $end, $out);
            }
        }

        usort($out, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $out;
    }

    /**
     * @param  array<int, array{date: string, account_id: int, cents: int, bill_id: int}>  $out
     */
    private function emitMonthly(Bill $bill, CarbonImmutable $start, CarbonImmutable $end, array &$out): void
    {
        $cursor = $this->safeDay($start->startOfMonth(), (int) $bill->due_day_of_month);

        while ($cursor->lte($end)) {
            if ($cursor->gte($start)) {
                $out[] = $this->row($bill, $cursor);
            }
            $cursor = $this->safeDay($cursor->addMonthsNoOverflow(1)->startOfMonth(), (int) $bill->due_day_of_month);
        }
    }

    /**
     * @param  array<int, array{date: string, account_id: int, cents: int, bill_id: int}>  $out
     */
    private function emitAnnual(Bill $bill, CarbonImmutable $start, CarbonImmutable $end, array &$out): void
    {
        $month = (int) ($bill->due_month_of_year ?? 1);
        $day = (int) $bill->due_day_of_month;

        $year = $start->year;
        while (true) {
            $candidate = $this->safeDay(CarbonImmutable::create($year, $month, 1), $day);
            if ($candidate->gt($end)) {
                break;
            }
            if ($candidate->gte($start)) {
                $out[] = $this->row($bill, $candidate);
            }
            $year++;
        }
    }

    /**
     * @return array{date: string, account_id: int, cents: int, bill_id: int}
     */
    private function row(Bill $bill, CarbonImmutable $date): array
    {
        return [
            'date' => $date->toDateString(),
            'account_id' => (int) $bill->account_id,
            'cents' => (int) $bill->expected_amount_cents,
            'bill_id' => (int) $bill->id,
        ];
    }

    private function safeDay(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->setDay(max(1, min($day, $month->daysInMonth)));
    }
}
