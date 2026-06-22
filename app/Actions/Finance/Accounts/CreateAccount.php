<?php

namespace App\Actions\Finance\Accounts;

use App\Models\Account;

class CreateAccount
{
    public function __invoke(string $name, int $startingBalanceCents, bool $countsTowardGoals, int $minimumBalanceCents = 0): Account
    {
        return Account::create([
            'name' => $name,
            'starting_balance_cents' => $startingBalanceCents,
            'counts_toward_goals' => $countsTowardGoals,
            'minimum_balance_cents' => $minimumBalanceCents,
        ]);
    }
}
