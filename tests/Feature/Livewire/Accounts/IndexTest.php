<?php

use App\Livewire\Accounts\Index;
use App\Models\Account;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists active accounts with current balance', function () {
    Account::factory()->withStartingBalance(50000)->create(['name' => 'Chequing']);

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee('Chequing')
        ->assertSee('$500.00');
});

it('hides archived accounts by default', function () {
    Account::factory()->create(['name' => 'Active']);
    Account::factory()->archived()->create(['name' => 'Archived']);

    Livewire::test(Index::class)
        ->assertSee('Active')
        ->assertDontSee('Archived');
});

it('computes balances with a single aggregate query (no N+1)', function () {
    Account::factory()->count(5)->create();

    DB::enableQueryLog();
    Livewire::test(Index::class)->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Expect: accounts query + at most 1 aggregate query (plus any framework queries like session).
    // We're permissive: assert the SUM aggregation didn't run 5 separate times.
    $sumQueries = collect($queries)->filter(fn ($q) => str_contains($q['query'], 'SUM(amount_cents)'))->count();
    expect($sumQueries)->toBeLessThanOrEqual(1);
});
