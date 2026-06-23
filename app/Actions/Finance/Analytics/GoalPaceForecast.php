<?php

namespace App\Actions\Finance\Analytics;

use App\Actions\Finance\Goals\ComputeGoalsStatus;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class GoalPaceForecast
{
    /**
     * @return array<int, array{goal_id: int, name: string, target_cents: int, current_cents: int, monthly_pace_cents: int, projected_hit_date: ?string}>
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();
        $threeMonthsAgo = $today->subMonthsNoOverflow(3);

        $netDeltaCents = (int) Transaction::query()
            ->whereHas('account', fn ($q) => $q->where('counts_toward_goals', true)->whereNull('archived_at'))
            ->whereDate('occurred_on', '>=', $threeMonthsAgo->toDateString())
            ->whereDate('occurred_on', '<=', $today->toDateString())
            ->whereNull('deleted_at')
            ->sum('amount_cents');
        $monthlyPace = (int) round($netDeltaCents / 3);

        $status = (new ComputeGoalsStatus)();

        $out = [];
        foreach ($status['goals'] as $goal) {
            $current = (int) $goal['capped_allocation_cents'];
            $remaining = max(0, (int) $goal['target_cents'] - $current);

            $projectedHitDate = null;
            if ($remaining === 0) {
                $projectedHitDate = $today->toDateString();
            } elseif ($monthlyPace > 0) {
                $monthsToHit = (int) ceil($remaining / $monthlyPace);
                $projectedHitDate = $today->addMonthsNoOverflow($monthsToHit)->toDateString();
            }

            $out[] = [
                'goal_id' => (int) $goal['id'],
                'name' => (string) $goal['name'],
                'target_cents' => (int) $goal['target_cents'],
                'current_cents' => $current,
                'monthly_pace_cents' => $monthlyPace,
                'projected_hit_date' => $projectedHitDate,
            ];
        }

        return $out;
    }
}
