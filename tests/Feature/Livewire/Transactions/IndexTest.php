<?php

use App\Livewire\Transactions\Index;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists transactions across all accounts', function () {
    Transaction::factory()->count(3)->create(['description' => 'Coffee']);

    Livewire::test(Index::class)
        ->assertOk()
        ->assertViewHas('transactions', fn ($t) => $t->count() === 3);
});

it('filters by account', function () {
    $a = Account::factory()->create();
    $b = Account::factory()->create();
    Transaction::factory()->forAccount($a)->count(2)->create();
    Transaction::factory()->forAccount($b)->count(3)->create();

    Livewire::test(Index::class)
        ->set('accountFilter', $a->id)
        ->assertViewHas('transactions', fn ($t) => $t->count() === 2);
});

it('filters by description search', function () {
    Transaction::factory()->create(['description' => 'Starbucks Coffee']);
    Transaction::factory()->create(['description' => 'Loblaws Groceries']);

    Livewire::test(Index::class)
        ->set('search', 'Star')
        ->assertViewHas('transactions', fn ($t) => $t->count() === 1);
});

it('deletes a transaction via component method', function () {
    $tx = Transaction::factory()->create();

    Livewire::test(Index::class)->call('deleteTransaction', $tx->id);

    expect(Transaction::find($tx->id))->toBeNull();
});
