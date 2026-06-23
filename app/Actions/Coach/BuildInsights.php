<?php

namespace App\Actions\Coach;

use App\Actions\Finance\Analytics\BudgetVariance;
use App\Actions\Finance\Analytics\DetectAnomalies;
use App\Actions\Finance\Analytics\DetectRecurringSubscriptions;
use App\Actions\Finance\Analytics\FixedVariableRatio;
use App\Actions\Finance\Analytics\GoalPaceForecast;
use App\Actions\Finance\Analytics\SavingsRateTrend;
use App\Actions\Finance\Analytics\SpendingVelocity;
use App\Actions\Finance\Analytics\TopMovers;
use App\Coach\Insight;
use Carbon\CarbonImmutable;

class BuildInsights
{
    private const SEVERITY_RANK = ['critical' => 0, 'warning' => 1, 'info' => 2, 'positive' => 3];

    /**
     * @return array<int, Insight>
     */
    public function __invoke(): array
    {
        $insights = [];

        // Top movers
        foreach ((new TopMovers)() as $row) {
            if ($row['delta_pct'] === null) {
                continue;
            }
            if ($row['delta_pct'] >= 100.0) {
                $insights[] = new Insight('critical',
                    "{$row['name']} spending up {$row['delta_pct']}%",
                    'You spent $'.number_format($row['current_cents'] / 100, 2)." on {$row['name']} so far this month vs $".number_format($row['previous_cents'] / 100, 2).' last month.',
                    "How can I cut back on {$row['name']}?",
                    'top_movers',
                    $row,
                );
            } elseif ($row['delta_pct'] >= 50.0) {
                $insights[] = new Insight('warning',
                    "{$row['name']} spending up {$row['delta_pct']}%",
                    'You spent $'.number_format($row['current_cents'] / 100, 2)." on {$row['name']} so far this month vs $".number_format($row['previous_cents'] / 100, 2).' last month.',
                    "What's driving the increase in {$row['name']}?",
                    'top_movers',
                    $row,
                );
            }
        }

        // Anomalies (one per category)
        $seenCategories = [];
        foreach ((new DetectAnomalies)() as $row) {
            if ($row['std_devs_from_median'] < 3.0 || in_array($row['category_id'], $seenCategories, true)) {
                continue;
            }
            $seenCategories[] = $row['category_id'];
            $insights[] = new Insight('warning',
                'Unusual transaction: $'.number_format(abs($row['amount_cents']) / 100, 2),
                "{$row['description']} — your category median is $".number_format($row['category_median_cents'] / 100, 2),
                'Tell me about my recent unusual spending',
                'detect_anomalies',
                $row,
            );
        }

        // Budget variance
        $daysInMonth = CarbonImmutable::today()->endOfMonth()->day;
        foreach ((new BudgetVariance)() as $row) {
            if ($row['variance_pct'] >= 100.0) {
                $insights[] = new Insight('critical',
                    "{$row['name']} budget exceeded",
                    "You've spent ".$row['variance_pct']."% of your {$row['name']} budget with {$row['days_remaining_in_period']} days left.",
                    "Where am I overspending in {$row['name']}?",
                    'budget_variance',
                    $row,
                );
            } elseif ($row['variance_pct'] >= 90.0 && $row['days_remaining_in_period'] > intdiv($daysInMonth, 4)) {
                $insights[] = new Insight('warning',
                    "{$row['name']} budget almost gone",
                    "You're {$row['variance_pct']}% through your {$row['name']} budget with {$row['days_remaining_in_period']} days left.",
                    "What can I cut to stay under my {$row['name']} budget?",
                    'budget_variance',
                    $row,
                );
            }
        }

        // Goal pace (info-only — goals don't have deadlines in this app)
        foreach ((new GoalPaceForecast)() as $row) {
            if (! $row['projected_hit_date']) {
                continue;
            }
            $insights[] = new Insight('info',
                "{$row['name']} goal pace",
                'Projected to hit target by '.$row['projected_hit_date'].' at current savings rate.',
                "How can I save more for {$row['name']}?",
                'goal_pace_forecast',
                $row,
            );
        }

        // Savings rate trend
        $savings = (new SavingsRateTrend)(monthsBack: 2);
        if (count($savings) === 2) {
            $delta = $savings[1]['savings_rate_pct'] - $savings[0]['savings_rate_pct'];
            if ($delta < -10) {
                $insights[] = new Insight('warning',
                    'Savings rate dropping',
                    "Rate fell from {$savings[0]['savings_rate_pct']}% to {$savings[1]['savings_rate_pct']}% month-over-month.",
                    'Why did my savings rate drop?',
                    'savings_rate_trend',
                    ['previous' => $savings[0], 'current' => $savings[1]],
                );
            } elseif ($delta > 10) {
                $insights[] = new Insight('positive',
                    'Savings rate climbing',
                    "Rate rose from {$savings[0]['savings_rate_pct']}% to {$savings[1]['savings_rate_pct']}% month-over-month.",
                    null,
                    'savings_rate_trend',
                    ['previous' => $savings[0], 'current' => $savings[1]],
                );
            }
        }

        // Recurring subs
        foreach ((new DetectRecurringSubscriptions)() as $row) {
            if ($row['already_tracked_as_bill_id']) {
                continue;
            }
            $insights[] = new Insight('info',
                "Untracked subscription: {$row['merchant_pattern']}",
                'Recurring $'.number_format($row['monthly_avg_cents'] / 100, 2)." charge, {$row['occurrence_count']} times. Consider tracking as a bill.",
                "Tell me about the {$row['merchant_pattern']} subscription",
                'detect_recurring_subscriptions',
                $row,
            );
        }

        // Spending velocity
        $velocity = (new SpendingVelocity)();
        if ($velocity['delta_pct'] >= 30.0) {
            $insights[] = new Insight('warning',
                'Spending pace ahead of last month',
                'This month: $'.number_format($velocity['this_month_cents_so_far'] / 100, 2).' vs $'.number_format($velocity['last_month_cents_through_same_day'] / 100, 2).' last month at same point.',
                "What's driving my faster spending this month?",
                'spending_velocity',
                $velocity,
            );
        }

        // Fixed vs variable
        $fvr = (new FixedVariableRatio)(monthsBack: 3);
        if (count($fvr) === 3) {
            $delta = $fvr[2]['fixed_ratio_pct'] - $fvr[0]['fixed_ratio_pct'];
            if ($delta >= 5.0) {
                $insights[] = new Insight('info',
                    'Fixed costs taking a bigger share',
                    'Fixed-cost share rose '.round($delta, 1).' pts over the last 3 months.',
                    'What fixed costs grew the most?',
                    'fixed_variable_ratio',
                    ['series' => $fvr],
                );
            }
        }

        // Rank by severity; cap at 6
        usort($insights, fn (Insight $a, Insight $b) => self::SEVERITY_RANK[$a->severity] <=> self::SEVERITY_RANK[$b->severity]);

        return array_slice($insights, 0, 6);
    }
}
