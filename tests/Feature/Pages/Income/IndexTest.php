<?php

use App\Models\Account;
use App\Models\IncomeSource;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists income sources', function () {
    $account = Account::factory()->create();
    IncomeSource::factory()->create(['name' => 'Paycheck', 'account_id' => $account->id]);

    $this->get('/income')->assertOk()->assertSee('Paycheck');
});

it('opens the form when New income is clicked', function () {
    Livewire::test('pages::income.index')
        ->call('startEdit', 0)
        ->assertSet('editingId', 0);
});

it('deletes an income source', function () {
    $source = IncomeSource::factory()->create();

    Livewire::test('pages::income.index')
        ->call('deleteSource', $source->id);

    expect(IncomeSource::find($source->id))->toBeNull();
});
