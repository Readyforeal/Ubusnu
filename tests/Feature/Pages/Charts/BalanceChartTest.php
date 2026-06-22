<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders for a specific account', function () {
    $account = Account::factory()->withStartingBalance(10000)->create();
    Transaction::factory()->forAccount($account)->withAmount(1000)->onDate(now()->subDays(5)->toDateString())->create();

    Livewire::test('pages::charts.balance-chart', ['accountId' => $account->id])
        ->assertOk()
        ->assertSet('range', '30d');
});

it('renders for the household total when accountId is null', function () {
    Account::factory()->count(2)->create();

    Livewire::test('pages::charts.balance-chart', ['accountId' => null])
        ->assertOk();
});

it('switches range on action', function () {
    $account = Account::factory()->create();

    Livewire::test('pages::charts.balance-chart', ['accountId' => $account->id])
        ->call('setRange', '90d')
        ->assertSet('range', '90d');
});
