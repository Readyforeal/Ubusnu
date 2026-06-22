<?php

namespace App\Actions\Finance\Bills;

use App\Models\Transaction;
use App\Support\BillMatcher;

class RematchUnlinkedBills
{
    /**
     * @return array{updated: int, still_unlinked: int}
     */
    public function __invoke(): array
    {
        $matcher = new BillMatcher;
        $updated = 0;
        $still = 0;

        Transaction::query()
            ->whereNull('bill_id')
            ->chunkById(500, function ($rows) use ($matcher, &$updated, &$still): void {
                foreach ($rows as $tx) {
                    $billId = $matcher->match($tx->description);
                    if ($billId !== null) {
                        $tx->update(['bill_id' => $billId]);
                        $updated++;
                    } else {
                        $still++;
                    }
                }
            });

        return ['updated' => $updated, 'still_unlinked' => $still];
    }
}
