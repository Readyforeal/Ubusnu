<?php

namespace App\Actions\Finance\Accounts;

use App\Models\Account;

class UpdateAccount
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Account $account, array $attributes): Account
    {
        $allowed = ['name', 'starting_balance_cents', 'counts_toward_goals'];

        $account->update(collect($attributes)->only($allowed)->all());

        return $account->fresh();
    }
}
