<?php

use App\Actions\Finance\Income\CreateIncomeSource;
use App\Models\Account;
use App\Models\IncomeSource;

it('creates an income source with the given attributes', function () {
    $account = Account::factory()->create();

    $source = (new CreateIncomeSource)([
        'name' => 'Paycheck',
        'cadence' => 'biweekly',
        'next_expected_on' => '2026-07-10',
        'expected_amount_cents' => 250000,
        'account_id' => $account->id,
    ]);

    expect($source)->toBeInstanceOf(IncomeSource::class);
    expect($source->name)->toBe('Paycheck');
    expect($source->cadence)->toBe('biweekly');
});
