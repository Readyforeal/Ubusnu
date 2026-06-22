<?php

use App\Models\Goal;

it('persists all goal attributes', function () {
    $goal = Goal::factory()->create([
        'name' => 'Camera',
        'target_cents' => 150000,
        'priority_percentage' => 10,
        'color' => '#3b82f6',
        'notes' => 'Sony A7 IV',
        'sort_order' => 2,
    ]);

    expect($goal->name)->toBe('Camera');
    expect($goal->target_cents)->toBe(150000);
    expect($goal->priority_percentage)->toBe(10);
    expect($goal->color)->toBe('#3b82f6');
    expect($goal->notes)->toBe('Sony A7 IV');
    expect($goal->sort_order)->toBe(2);
});

it('casts integer columns to int', function () {
    $goal = Goal::factory()->create([
        'target_cents' => 100000,
        'priority_percentage' => 25,
        'sort_order' => 1,
    ]);

    expect($goal->target_cents)->toBeInt();
    expect($goal->priority_percentage)->toBeInt();
    expect($goal->sort_order)->toBeInt();
});

it('allows null color and notes', function () {
    $goal = Goal::factory()->create(['color' => null, 'notes' => null]);

    expect($goal->color)->toBeNull();
    expect($goal->notes)->toBeNull();
});
