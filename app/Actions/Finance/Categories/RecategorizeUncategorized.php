<?php

namespace App\Actions\Finance\Categories;

use App\Models\Transaction;
use App\Support\KeywordMatcher;

class RecategorizeUncategorized
{
    /**
     * @return array{updated: int, still_uncategorized: int}
     */
    public function __invoke(): array
    {
        $matcher = new KeywordMatcher;
        $updated = 0;
        $still = 0;

        Transaction::query()
            ->whereNull('category_id')
            ->chunkById(500, function ($rows) use ($matcher, &$updated, &$still): void {
                foreach ($rows as $tx) {
                    $cid = $matcher->match($tx->description);
                    if ($cid !== null) {
                        $tx->update(['category_id' => $cid]);
                        $updated++;
                    } else {
                        $still++;
                    }
                }
            });

        return ['updated' => $updated, 'still_uncategorized' => $still];
    }
}
