<?php

use App\Models\Account;
use App\Models\Goal;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists existing goals', function () {
    Goal::factory()->create(['name' => 'Camera']);
    Goal::factory()->create(['name' => 'Emergency Fund']);

    Livewire::test('pages::goals.index')
        ->assertOk()
        ->assertSee('Camera')
        ->assertSee('Emergency Fund');
});

it('shows the savings pool amount', function () {
    Account::factory()->withStartingBalance(1000000)->countsTowardGoals()->create();

    Livewire::test('pages::goals.index')
        ->assertSee('$10,000.00');
});

it('opens the form via startEdit and closes on goal-saved event', function () {
    $goal = Goal::factory()->create();

    Livewire::test('pages::goals.index')
        ->call('startEdit', $goal->id)
        ->assertSet('editingId', $goal->id)
        ->call('closeForm')
        ->assertSet('editingId', null);
});

it('deletes a goal via component method', function () {
    $goal = Goal::factory()->create();

    Livewire::test('pages::goals.index')
        ->call('deleteGoal', $goal->id);

    expect(Goal::find($goal->id))->toBeNull();
});

it('shows allocation summary including unallocated dollars', function () {
    Account::factory()->withStartingBalance(1000000)->countsTowardGoals()->create();
    Goal::factory()->create(['target_cents' => 300000, 'priority_percentage' => 30]);

    Livewire::test('pages::goals.index')
        ->assertSee('30%')
        ->assertSee('Unallocated');
});

it('requires authentication', function () {
    auth()->logout();
    $this->get(route('goals.index'))->assertRedirect(route('login'));
});
