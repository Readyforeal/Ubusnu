<?php

namespace App\Actions\Finance\Transactions;

use App\Models\Transaction;

class DeleteTransaction
{
    public function __invoke(Transaction $transaction): void
    {
        $transaction->delete();
    }
}
