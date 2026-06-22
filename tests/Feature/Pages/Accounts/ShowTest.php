<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('shows account name and current balance', function () {
    $account = Account::factory()->withStartingBalance(10000)->create(['name' => 'Chequing']);
    Transaction::factory()->forAccount($account)->withAmount(5000)->create();

    Livewire::test('pages::accounts.show', ['account' => $account])
        ->assertSee('Chequing')
        ->assertSee('$150.00');
});

it('lists transactions for this account only', function () {
    $account = Account::factory()->create();
    $other = Account::factory()->create();
    Transaction::factory()->forAccount($account)->count(2)->create(['description' => 'Mine']);
    Transaction::factory()->forAccount($other)->count(3)->create(['description' => 'Other']);

    Livewire::test('pages::accounts.show', ['account' => $account])
        ->assertSee('Mine')
        ->assertDontSee('Other');
});
