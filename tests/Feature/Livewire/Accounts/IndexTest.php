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
