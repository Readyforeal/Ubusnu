<?php

use App\Models\Goal;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a new goal', function () {
    Livewire::test('pages::goals.form', ['goalId' => 0])
        ->set('name', 'Camera')
        ->set('targetDollars', '1500')
        ->set('priorityPercentage', 10)
        ->set('color', '#3b82f6')
        ->set('notes', 'Sony A7 IV')
        ->call('saveGoal')
        ->assertHasNoErrors();

    $goal = Goal::where('name', 'Camera')->first();
    expect($goal)->not->toBeNull();
    expect($goal->target_cents)->toBe(150000);
    expect($goal->priority_percentage)->toBe(10);
    expect($goal->color)->toBe('#3b82f6');
    expect($goal->notes)->toBe('Sony A7 IV');
});

it('updates an existing goal', function () {
    $goal = Goal::factory()->create(['name' => 'Old']);

    Livewire::test('pages::goals.form', ['goalId' => $goal->id])
        ->set('name', 'New')
        ->call('saveGoal')
        ->assertHasNoErrors();

    expect($goal->fresh()->name)->toBe('New');
});

it('requires name, target ≥ 0.01, and priority 0-100', function () {
    Livewire::test('pages::goals.form', ['goalId' => 0])
        ->set('name', '')
        ->set('targetDollars', '0')
        ->set('priorityPercentage', 150)
        ->call('saveGoal')
        ->assertHasErrors(['name', 'targetDollars', 'priorityPercentage']);
});

it('dispatches goal-saved on success', function () {
    Livewire::test('pages::goals.form', ['goalId' => 0])
        ->set('name', 'Emergency Fund')
        ->set('targetDollars', '10000')
        ->set('priorityPercentage', 20)
        ->call('saveGoal')
        ->assertDispatched('goal-saved');
});
