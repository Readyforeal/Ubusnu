<?php

use App\Livewire\Accounts\Form;
use App\Models\Account;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a new account with starting balance in dollars', function () {
    Livewire::test(Form::class, ['accountId' => 0])
        ->set('name', 'Chequing')
        ->set('startingBalanceDollars', '500')
        ->set('countsTowardGoals', false)
        ->call('save')
        ->assertHasNoErrors();

    $account = Account::where('name', 'Chequing')->first();
    expect($account->starting_balance_cents)->toBe(50000);
});

it('updates an existing account', function () {
    $account = Account::factory()->create(['name' => 'Old']);

    Livewire::test(Form::class, ['accountId' => $account->id])
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->name)->toBe('New');
});

it('archives an account', function () {
    $account = Account::factory()->create();

    Livewire::test(Form::class, ['accountId' => $account->id])
        ->call('archive')
        ->assertHasNoErrors();

    expect($account->fresh()->isArchived())->toBeTrue();
});

it('requires a name', function () {
    Livewire::test(Form::class, ['accountId' => 0])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});
