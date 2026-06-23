<?php

namespace App\Providers;

use App\Actions\Finance\Analytics\BudgetVariance;
use App\Actions\Finance\Analytics\DetectAnomalies;
use App\Actions\Finance\Analytics\DetectRecurringSubscriptions;
use App\Actions\Finance\Analytics\FixedVariableRatio;
use App\Actions\Finance\Analytics\GoalPaceForecast;
use App\Actions\Finance\Analytics\SavingsRateTrend;
use App\Actions\Finance\Analytics\SpendingVelocity;
use App\Actions\Finance\Analytics\TopMovers;
use App\Services\Coach\CoachTool;
use App\Services\Coach\ToolRegistry;
use Illuminate\Support\ServiceProvider;
use stdClass;

class CoachServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function () {
            $registry = new ToolRegistry;
            $this->registerAnalyticsTools($registry);

            return $registry;
        });
    }

    private function registerAnalyticsTools(ToolRegistry $registry): void
    {
        $registry->register(new CoachTool(
            name: 'top_movers',
            description: 'Categories with the biggest month-over-month spending change.',
            parameters: ['type' => 'object', 'properties' => [
                'months_back' => ['type' => 'integer', 'default' => 1],
                'limit' => ['type' => 'integer', 'default' => 5],
            ]],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new TopMovers)(monthsBack: $args['months_back'] ?? 1, limit: $args['limit'] ?? 5),
        ));

        $registry->register(new CoachTool(
            name: 'detect_anomalies',
            description: 'Find transactions that are unusually large vs their category median.',
            parameters: ['type' => 'object', 'properties' => [
                'lookback_days' => ['type' => 'integer', 'default' => 90],
                'std_dev_threshold' => ['type' => 'number', 'default' => 2.0],
            ]],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new DetectAnomalies)(
                lookbackDays: $args['lookback_days'] ?? 90,
                stdDevThreshold: (float) ($args['std_dev_threshold'] ?? 2.0),
            ),
        ));

        $registry->register(new CoachTool(
            name: 'budget_variance',
            description: 'Per-bucket planned vs actual spending this month, with days remaining in the period.',
            parameters: ['type' => 'object', 'properties' => new stdClass],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new BudgetVariance)(),
        ));

        $registry->register(new CoachTool(
            name: 'goal_pace_forecast',
            description: 'For each savings goal, the projected hit date based on the last 3 months of net deposits.',
            parameters: ['type' => 'object', 'properties' => new stdClass],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new GoalPaceForecast)(),
        ));

        $registry->register(new CoachTool(
            name: 'savings_rate_trend',
            description: 'Monthly savings rate (income minus spend over income) for the past N months.',
            parameters: ['type' => 'object', 'properties' => [
                'months_back' => ['type' => 'integer', 'default' => 12],
            ]],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new SavingsRateTrend)(monthsBack: $args['months_back'] ?? 12),
        ));

        $registry->register(new CoachTool(
            name: 'detect_recurring_subscriptions',
            description: 'Find recurring same-amount transactions in the last 6 months that are not already tracked as bills.',
            parameters: ['type' => 'object', 'properties' => new stdClass],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new DetectRecurringSubscriptions)(),
        ));

        $registry->register(new CoachTool(
            name: 'spending_velocity',
            description: 'How fast spending is accumulating this month vs the same point last month.',
            parameters: ['type' => 'object', 'properties' => new stdClass],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new SpendingVelocity)(),
        ));

        $registry->register(new CoachTool(
            name: 'fixed_variable_ratio',
            description: 'Per-month ratio of fixed (bill-linked) spending to variable spending.',
            parameters: ['type' => 'object', 'properties' => [
                'months_back' => ['type' => 'integer', 'default' => 6],
            ]],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new FixedVariableRatio)(monthsBack: $args['months_back'] ?? 6),
        ));
    }
}
