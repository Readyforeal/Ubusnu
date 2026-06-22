<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists transactions across all accounts', function () {
    Transaction::factory()->count(3)->create(['description' => 'Coffee']);

    Livewire::test('pages::transactions.index')
        ->assertOk()
        ->assertSee('Coffee');
});

it('filters by account', function () {
    $a = Account::factory()->create();
    $b = Account::factory()->create();
    Transaction::factory()->forAccount($a)->count(2)->create(['description' => 'fromA']);
    Transaction::factory()->forAccount($b)->count(3)->create(['description' => 'fromB']);

    Livewire::test('pages::transactions.index')
        ->set('accountFilter', $a->id)
        ->assertSee('fromA')
        ->assertDontSee('fromB');
});

it('filters by description search', function () {
    Transaction::factory()->create(['description' => 'Starbucks Coffee']);
    Transaction::factory()->create(['description' => 'Loblaws Groceries']);

    Livewire::test('pages::transactions.index')
        ->set('search', 'Star')
        ->assertSee('Starbucks')
        ->assertDontSee('Loblaws');
});

it('deletes a transaction via component method', function () {
    $tx = Transaction::factory()->create();

    Livewire::test('pages::transactions.index')->call('deleteTransaction', $tx->id);

    expect(Transaction::find($tx->id))->toBeNull();
});
