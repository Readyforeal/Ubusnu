<?php

use App\Actions\Finance\Goals\CreateGoal;
use App\Models\Goal;

it('creates a goal with the given fields', function () {
    $goal = (new CreateGoal)(
        name: 'Camera',
        targetCents: 150000,
        priorityPercentage: 10,
        color: '#3b82f6',
        notes: 'Sony A7 IV',
    );

    expect($goal)->toBeInstanceOf(Goal::class);
    expect($goal->name)->toBe('Camera');
    expect($goal->target_cents)->toBe(150000);
    expect($goal->priority_percentage)->toBe(10);
    expect($goal->color)->toBe('#3b82f6');
    expect($goal->notes)->toBe('Sony A7 IV');
});

it('allows null color and notes', function () {
    $goal = (new CreateGoal)('Quick goal', 50000, 5);

    expect($goal->color)->toBeNull();
    expect($goal->notes)->toBeNull();
});
