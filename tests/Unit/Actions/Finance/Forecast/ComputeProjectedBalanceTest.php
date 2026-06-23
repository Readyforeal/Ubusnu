<?php

use App\Actions\Finance\Forecast\ComputeProjectedBalance;
use App\Models\Account;
use App\Models\Bill;
use App\Models\IncomeSource;
use Carbon\CarbonImmutable;

it('projects starting balance plus income minus bills minus variable per day', function () {
    $today = CarbonImmutable::today();
    $account = Account::factory()->withStartingBalance(500000)->create();

    IncomeSource::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'next_expected_on' => $today->addDays(2)->toDateString(),
        'expected_amount_cents' => 300000,
    ]);

    Bill::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(4)->day,
        'expected_amount_cents' => 100000,
    ]);

    $result = (new ComputeProjectedBalance)([$account->fresh()], $today, $today->addDays(5));

    $byKey = [];
    foreach ($result as $row) {
        $byKey[$row['account_id'].'|'.$row['date']] = $row['balance_cents'];
    }

    // Day 0: starting balance only
    expect($byKey[$account->id.'|'.$today->toDateString()])->toBe(500000);
    // Day 2: + income
    expect($byKey[$account->id.'|'.$today->addDays(2)->toDateString()])->toBe(800000);
    // Day 4: + income, - bill
    expect($byKey[$account->id.'|'.$today->addDays(4)->toDateString()])->toBe(700000);
});

it('projects independently per account', function () {
    $today = CarbonImmutable::today();
    $a = Account::factory()->withStartingBalance(100000)->create();
    $b = Account::factory()->withStartingBalance(50000)->create();

    IncomeSource::factory()->create([
        'account_id' => $a->id,
        'cadence' => 'monthly',
        'next_expected_on' => $today->addDay()->toDateString(),
        'expected_amount_cents' => 50000,
    ]);

    $result = (new ComputeProjectedBalance)([$a->fresh(), $b->fresh()], $today, $today->addDay());

    $byKey = [];
    foreach ($result as $row) {
        $byKey[$row['account_id'].'|'.$row['date']] = $row['balance_cents'];
    }

    expect($byKey[$a->id.'|'.$today->addDay()->toDateString()])->toBe(150000);
    expect($byKey[$b->id.'|'.$today->addDay()->toDateString()])->toBe(50000);
});
