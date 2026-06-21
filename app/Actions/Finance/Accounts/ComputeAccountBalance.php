<?php

namespace App\Actions\Finance\Accounts;

use App\Models\Account;
use App\Models\Transaction;

class ComputeAccountBalance
{
    public function __invoke(Account $account, ?string $asOf = null): int
    {
        $query = Transaction::query()->where('account_id', $account->id);

        if ($asOf !== null) {
            $query->whereDate('occurred_on', '<=', $asOf);
        }

        $sum = (int) $query->sum('amount_cents');

        return $account->starting_balance_cents + $sum;
    }
}
