<?php

use App\Models\Account;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('shows the income source details', function () {
    $source = IncomeSource::factory()->create(['name' => 'Paycheck']);

    $this->get(route('income.show', $source))
        ->assertOk()
        ->assertSee('Paycheck');
});

it('shows matched deposits', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->create(['account_id' => $account->id]);
    Transaction::factory()->create([
        'account_id' => $account->id,
        'income_source_id' => $source->id,
        'description' => 'PAYROLL ALLAN MICHAEL',
        'amount_cents' => 250000,
        'occurred_on' => '2026-07-10',
    ]);

    $this->get(route('income.show', $source))
        ->assertOk()
        ->assertSee('PAYROLL ALLAN MICHAEL');
});
