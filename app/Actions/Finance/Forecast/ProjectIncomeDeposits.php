<?php

namespace App\Actions\Finance\Forecast;

use App\Models\IncomeSource;
use Carbon\CarbonImmutable;

class ProjectIncomeDeposits
{
    /**
     * @param  array<int, IncomeSource>  $sources
     * @return array<int, array{date: string, account_id: int, cents: int, income_source_id: int}>
     */
    public function __invoke(array $sources, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $out = [];

        foreach ($sources as $source) {
            $cursor = $source->next_expected_on instanceof CarbonImmutable
                ? $source->next_expected_on
                : CarbonImmutable::parse($source->next_expected_on);

            $primaryDay = (int) ($source->primary_day_of_month ?? $cursor->day);
            $secondaryDay = (int) ($source->secondary_day_of_month ?? 0);

            while ($cursor->lte($end)) {
                if ($cursor->gte($start)) {
                    $out[] = [
                        'date' => $cursor->toDateString(),
                        'account_id' => (int) $source->account_id,
                        'cents' => (int) $source->expected_amount_cents,
                        'income_source_id' => (int) $source->id,
                    ];
                }
                $cursor = $this->advance($cursor, $source->cadence, $primaryDay, $secondaryDay);
            }
        }

        usort($out, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $out;
    }

    private function advance(CarbonImmutable $cursor, string $cadence, int $primaryDay, int $secondaryDay): CarbonImmutable
    {
        return match ($cadence) {
            'weekly' => $cursor->addWeek(),
            'biweekly' => $cursor->addWeeks(2),
            'semi_monthly' => $this->advanceSemiMonthly($cursor, $primaryDay, $secondaryDay),
            default => $this->safeDay($cursor->addMonthsNoOverflow(1)->startOfMonth(), $primaryDay),
        };
    }

    private function advanceSemiMonthly(CarbonImmutable $cursor, int $primaryDay, int $secondaryDay): CarbonImmutable
    {
        if ($cursor->day < $secondaryDay) {
            return $this->safeDay($cursor, $secondaryDay);
        }

        return $this->safeDay($cursor->addMonthsNoOverflow(1)->startOfMonth(), $primaryDay);
    }

    private function safeDay(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->setDay(max(1, min($day, $month->daysInMonth)));
    }
}
