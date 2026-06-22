<?php

use App\Models\Account;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a new account with starting balance in dollars', function () {
    Livewire::test('pages::accounts.form', ['accountId' => 0])
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

    Livewire::test('pages::accounts.form', ['accountId' => $account->id])
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->name)->toBe('New');
});

it('archives an account', function () {
    $account = Account::factory()->create();

    Livewire::test('pages::accounts.form', ['accountId' => $account->id])
        ->call('archive')
        ->assertHasNoErrors();

    expect($account->fresh()->isArchived())->toBeTrue();
});

it('requires a name', function () {
    Livewire::test('pages::accounts.form', ['accountId' => 0])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('saves the minimum balance on create', function () {
    Livewire::test('pages::accounts.form', ['accountId' => 0])
        ->set('name', 'Checking')
        ->set('startingBalanceDollars', '1000')
        ->set('minimumBalanceDollars', '500')
        ->call('save')
        ->assertHasNoErrors();

    expect(Account::where('name', 'Checking')->first()->minimum_balance_cents)->toBe(50000);
});

it('updates the minimum balance', function () {
    $account = Account::factory()->create(['minimum_balance_cents' => 0]);

    Livewire::test('pages::accounts.form', ['accountId' => $account->id])
        ->set('minimumBalanceDollars', '250.50')
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->minimum_balance_cents)->toBe(25050);
});
