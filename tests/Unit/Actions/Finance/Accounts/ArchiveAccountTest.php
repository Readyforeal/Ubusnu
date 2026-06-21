<?php

use App\Actions\Finance\Accounts\ArchiveAccount;
use App\Models\Account;

it('sets archived_at on the account', function () {
    $account = Account::factory()->create();

    (new ArchiveAccount)($account);

    expect($account->fresh()->isArchived())->toBeTrue();
});

it('is idempotent — re-archiving an archived account does not error', function () {
    $account = Account::factory()->archived()->create();

    (new ArchiveAccount)($account);

    expect($account->fresh()->isArchived())->toBeTrue();
});
