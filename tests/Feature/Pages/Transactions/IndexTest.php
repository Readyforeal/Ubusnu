<?php

use App\Models\Account;
use App\Models\Category;
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

it('filters to uncategorized transactions when the toggle is on', function () {
    $cat = Category::factory()->create();
    Transaction::factory()->create(['description' => 'Has category', 'category_id' => $cat->id]);
    Transaction::factory()->create(['description' => 'No category', 'category_id' => null]);

    Livewire::test('pages::transactions.index')
        ->set('uncategorizedOnly', true)
        ->assertSee('No category')
        ->assertDontSee('Has category');
});

it('uncategorized toggle ignores categoryFilter when both are set', function () {
    $cat = Category::factory()->create();
    Transaction::factory()->create(['description' => 'In picked category', 'category_id' => $cat->id]);
    Transaction::factory()->create(['description' => 'Truly uncategorized', 'category_id' => null]);

    Livewire::test('pages::transactions.index')
        ->set('categoryFilter', $cat->id)
        ->set('uncategorizedOnly', true)
        ->assertSee('Truly uncategorized')
        ->assertDontSee('In picked category');
});
