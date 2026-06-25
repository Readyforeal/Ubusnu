<?php

use App\Services\Coach\CoachTool;
use App\Services\Coach\ToolRegistry;

it('registers and finds tools by name', function () {
    $registry = new ToolRegistry;
    $registry->register(new CoachTool(
        name: 'echo',
        description: 'echo',
        parameters: ['type' => 'object', 'properties' => new stdClass],
        kind: 'read',
        requiresConfirmation: false,
        handler: fn (array $args) => $args,
    ));

    expect($registry->find('echo'))->not->toBeNull();
    expect($registry->find('unknown'))->toBeNull();
});

it('registers all 8 analytics tools on container boot', function () {
    $registry = app(ToolRegistry::class);
    $names = collect($registry->all())->pluck('name')->all();

    foreach (['top_movers', 'detect_anomalies', 'budget_variance', 'goal_pace_forecast', 'savings_rate_trend', 'detect_recurring_subscriptions', 'spending_velocity', 'fixed_variable_ratio'] as $expected) {
        expect($names)->toContain($expected);
    }
});
