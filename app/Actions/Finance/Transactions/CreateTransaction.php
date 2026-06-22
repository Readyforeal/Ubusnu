<?php

namespace App\Actions\Finance\Transactions;

use App\Models\Account;
use App\Models\Transaction;

class CreateTransaction
{
    public function __invoke(
        Account $account,
        string $occurredOn,
        string $description,
        int $amountCents,
        ?int $categoryId = null,
        ?string $notes = null,
        ?int $billId = null,
    ): Transaction {
        return Transaction::create([
            'account_id' => $account->id,
            'occurred_on' => $occurredOn,
            'description' => $description,
            'amount_cents' => $amountCents,
            'category_id' => $categoryId,
            'source' => 'manual',
            'notes' => $notes,
            'bill_id' => $billId,
        ]);
    }
}
