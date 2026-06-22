<?php

namespace App\Actions\Finance\Budgets;

use App\Models\Bucket;

class CreateBucket
{
    public function __invoke(string $name, int $targetPercentage, ?string $color = null): Bucket
    {
        return Bucket::create([
            'name' => $name,
            'target_percentage' => $targetPercentage,
            'color' => $color,
        ]);
    }
}
