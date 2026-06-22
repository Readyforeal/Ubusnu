<?php

use App\Models\Account;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists active accounts with current balance', function () {
    Account::factory()->withStartingBalance(50000)->create(['name' => 'Chequing']);

    Livewire::test('pages::accounts.index')
        ->assertOk()
        ->assertSee('Chequing')
        ->assertSee('$500.00');
});

it('hides archived accounts by default', function () {
    Account::factory()->create(['name' => 'Active']);
    Account::factory()->archived()->create(['name' => 'Archived']);

    Livewire::test('pages::accounts.index')
        ->assertSee('Active')
        ->assertDontSee('Archived');
});

it('computes balances with a single aggregate query (no N+1)', function () {
    Account::factory()->count(5)->create();

    DB::enableQueryLog();
    Livewire::test('pages::accounts.index')->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $sumQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'SUM(amount_cents)'))->count();
    expect($sumQueries)->toBeLessThanOrEqual(1);
});
