<?php

use App\Actions\Finance\Balance\ComputeBalanceSeries;
use App\Models\Account;
use App\Models\Transaction;

it('returns a single point for a one-day range with no transactions', function () {
    $account = Account::factory()->withStartingBalance(50000)->create();

    $series = (new ComputeBalanceSeries)([$account], '2026-06-01', '2026-06-01');

    expect($series)->toHaveCount(1);
    expect($series[0])->toBe(['date' => '2026-06-01', 'balance_cents' => 50000]);
});

it('applies anchor: starts at balance through end of day before range', function () {
    $account = Account::factory()->withStartingBalance(10000)->create();
    Transaction::factory()->forAccount($account)->withAmount(5000)->onDate('2026-05-31')->create();
    Transaction::factory()->forAccount($account)->withAmount(2000)->onDate('2026-06-02')->create();

    $series = (new ComputeBalanceSeries)([$account], '2026-06-01', '2026-06-03');

    expect($series)->toBe([
        ['date' => '2026-06-01', 'balance_cents' => 15000],
        ['date' => '2026-06-02', 'balance_cents' => 17000],
        ['date' => '2026-06-03', 'balance_cents' => 17000],
    ]);
});

it('forward-fills days with no transactions', function () {
    $account = Account::factory()->withStartingBalance(0)->create();
    Transaction::factory()->forAccount($account)->withAmount(1000)->onDate('2026-06-01')->create();

    $series = (new ComputeBalanceSeries)([$account], '2026-06-01', '2026-06-04');

    expect($series)->toBe([
        ['date' => '2026-06-01', 'balance_cents' => 1000],
        ['date' => '2026-06-02', 'balance_cents' => 1000],
        ['date' => '2026-06-03', 'balance_cents' => 1000],
        ['date' => '2026-06-04', 'balance_cents' => 1000],
    ]);
});

it('sums multiple accounts per day for a household total', function () {
    $a = Account::factory()->withStartingBalance(1000)->create();
    $b = Account::factory()->withStartingBalance(2000)->create();
    Transaction::factory()->forAccount($a)->withAmount(500)->onDate('2026-06-01')->create();
    Transaction::factory()->forAccount($b)->withAmount(-300)->onDate('2026-06-02')->create();

    $series = (new ComputeBalanceSeries)([$a, $b], '2026-06-01', '2026-06-02');

    expect($series)->toBe([
        ['date' => '2026-06-01', 'balance_cents' => 3500],
        ['date' => '2026-06-02', 'balance_cents' => 3200],
    ]);
});

it('excludes soft-deleted transactions', function () {
    $account = Account::factory()->withStartingBalance(0)->create();
    $tx = Transaction::factory()->forAccount($account)->withAmount(5000)->onDate('2026-06-01')->create();
    Transaction::factory()->forAccount($account)->withAmount(2000)->onDate('2026-06-01')->create();
    $tx->delete();

    $series = (new ComputeBalanceSeries)([$account], '2026-06-01', '2026-06-01');

    expect($series[0]['balance_cents'])->toBe(2000);
});
