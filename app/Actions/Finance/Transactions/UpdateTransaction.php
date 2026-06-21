<?php

namespace App\Actions\Finance\Transactions;

use App\Models\Transaction;
use App\Support\TransactionHash;

class UpdateTransaction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Transaction $transaction, array $attributes): Transaction
    {
        $allowed = ['occurred_on', 'description', 'amount_cents', 'category_id', 'notes'];
        $updates = collect($attributes)->only($allowed)->all();

        $transaction->fill($updates);

        if ($transaction->isDirty(['occurred_on', 'description', 'amount_cents'])) {
            $transaction->dedup_hash = TransactionHash::for(
                $transaction->account_id,
                $transaction->occurred_on instanceof \DateTimeInterface
                    ? $transaction->occurred_on->format('Y-m-d')
                    : (string) $transaction->occurred_on,
                $transaction->amount_cents,
                $transaction->description,
            );
        }

        $transaction->save();

        return $transaction->fresh();
    }
}
