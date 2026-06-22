<?php

use App\Models\Account;
use App\Models\Goal;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('hides the widget content when no goals exist', function () {
    Livewire::test('pages::dashboard.goal-progress')
        ->assertDontSee('Savings pool');
});

it('shows the pool and per-goal progress rows when goals exist', function () {
    Account::factory()->withStartingBalance(1000000)->countsTowardGoals()->create();
    Goal::factory()->create(['name' => 'Camera', 'target_cents' => 150000, 'priority_percentage' => 10]);
    Goal::factory()->create(['name' => 'Debt', 'target_cents' => 300000, 'priority_percentage' => 30]);

    Livewire::test('pages::dashboard.goal-progress')
        ->assertSee('Savings pool')
        ->assertSee('$10,000.00')
        ->assertSee('Camera')
        ->assertSee('Debt');
});

it('shows a fully-funded badge on goals at 100%', function () {
    Account::factory()->withStartingBalance(1000000)->countsTowardGoals()->create();
    Goal::factory()->create(['name' => 'Debt', 'target_cents' => 200000, 'priority_percentage' => 30]);

    Livewire::test('pages::dashboard.goal-progress')
        ->assertSee('Funded');
});
