<?php

use App\Actions\Finance\Accounts\CreateAccount;
use App\Models\Account;

it('creates an account with the given name and starting balance', function () {
    $account = (new CreateAccount)('Tangerine Chequing', 50000, false);

    expect($account)->toBeInstanceOf(Account::class);
    expect($account->name)->toBe('Tangerine Chequing');
    expect($account->starting_balance_cents)->toBe(50000);
    expect($account->counts_toward_goals)->toBeFalse();
});

it('accepts negative starting balance for credit cards', function () {
    $account = (new CreateAccount)('Visa', -150000, false);
    expect($account->starting_balance_cents)->toBe(-150000);
});

it('marks counts_toward_goals when requested', function () {
    $account = (new CreateAccount)('Savings', 0, true);
    expect($account->counts_toward_goals)->toBeTrue();
});
