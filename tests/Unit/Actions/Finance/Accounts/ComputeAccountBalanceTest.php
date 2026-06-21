<?php

use App\Actions\Finance\Accounts\ComputeAccountBalance;
use App\Models\Account;
use App\Models\Transaction;

it('returns starting balance for account with no transactions', function () {
    $account = Account::factory()->withStartingBalance(50000)->create();

    expect((new ComputeAccountBalance)($account))->toBe(50000);
});

it('sums transactions on top of starting balance', function () {
    $account = Account::factory()->withStartingBalance(50000)->create();
    Transaction::factory()->forAccount($account)->withAmount(10000)->onDate('2026-06-01')->create();
    Transaction::factory()->forAccount($account)->withAmount(-3000)->onDate('2026-06-02')->create();

    expect((new ComputeAccountBalance)($account))->toBe(57000);
});

it('excludes transactions after asOf date', function () {
    $account = Account::factory()->withStartingBalance(0)->create();
    Transaction::factory()->forAccount($account)->withAmount(1000)->onDate('2026-05-31')->create();
    Transaction::factory()->forAccount($account)->withAmount(2000)->onDate('2026-06-01')->create();
    Transaction::factory()->forAccount($account)->withAmount(4000)->onDate('2026-06-02')->create();

    expect((new ComputeAccountBalance)($account, '2026-06-01'))->toBe(3000);
});

it('excludes soft-deleted transactions', function () {
    $account = Account::factory()->withStartingBalance(0)->create();
    $tx = Transaction::factory()->forAccount($account)->withAmount(5000)->create();
    Transaction::factory()->forAccount($account)->withAmount(2000)->create();

    $tx->delete();

    expect((new ComputeAccountBalance)($account))->toBe(2000);
});

it('handles negative starting balance for credit-card style account', function () {
    $account = Account::factory()->withStartingBalance(-150000)->create();
    Transaction::factory()->forAccount($account)->withAmount(50000)->create();

    expect((new ComputeAccountBalance)($account))->toBe(-100000);
});
