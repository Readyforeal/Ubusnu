<?php

namespace App\Actions\Finance\Accounts;

use App\Models\Account;

class ArchiveAccount
{
    public function __invoke(Account $account): Account
    {
        if (! $account->isArchived()) {
            $account->update(['archived_at' => now()]);
        }

        return $account->fresh();
    }
}
