<?php

namespace App\Actions\Finance\Budgets;

use App\Models\Bucket;

class DeleteBucket
{
    public function __invoke(Bucket $bucket): void
    {
        $bucket->delete();
    }
}
