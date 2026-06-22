<?php

use App\Actions\Finance\Goals\UpdateGoal;
use App\Models\Goal;

it('updates allowed attributes', function () {
    $goal = Goal::factory()->create(['name' => 'Old', 'target_cents' => 100000, 'priority_percentage' => 10]);

    (new UpdateGoal)($goal, [
        'name' => 'New',
        'target_cents' => 200000,
        'priority_percentage' => 25,
        'color' => '#abcdef',
        'notes' => 'updated',
    ]);

    $goal->refresh();
    expect($goal->name)->toBe('New');
    expect($goal->target_cents)->toBe(200000);
    expect($goal->priority_percentage)->toBe(25);
    expect($goal->color)->toBe('#abcdef');
    expect($goal->notes)->toBe('updated');
});

it('ignores attributes not in the allowed list', function () {
    $goal = Goal::factory()->create();

    (new UpdateGoal)($goal, ['id' => 999, 'name' => 'Renamed']);

    $goal->refresh();
    expect($goal->name)->toBe('Renamed');
    expect($goal->id)->not->toBe(999);
});
