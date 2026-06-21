<?php

namespace App\Actions\Finance\Transactions;

use App\Models\Category;
use App\Models\Transaction;

class CategorizeTransaction
{
    public function __invoke(Transaction $transaction, ?Category $category): Transaction
    {
        $transaction->update(['category_id' => $category?->id]);

        return $transaction->fresh();
    }
}
