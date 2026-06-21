<?php

use App\Actions\Finance\Accounts\UpdateAccount;
use App\Models\Account;

it('updates the account attributes', function () {
    $account = Account::factory()->create(['name' => 'Old', 'starting_balance_cents' => 1000]);

    (new UpdateAccount)($account, [
        'name' => 'New',
        'starting_balance_cents' => 2000,
        'counts_toward_goals' => true,
    ]);

    $account->refresh();
    expect($account->name)->toBe('New');
    expect($account->starting_balance_cents)->toBe(2000);
    expect($account->counts_toward_goals)->toBeTrue();
});

it('ignores keys not in the allowed list', function () {
    $account = Account::factory()->create();

    (new UpdateAccount)($account, ['id' => 999, 'name' => 'Renamed']);

    $account->refresh();
    expect($account->name)->toBe('Renamed');
    expect($account->id)->not->toBe(999);
});
