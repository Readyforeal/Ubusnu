<?php

namespace App\Actions\Finance\Budgets;

use App\Models\Bucket;

class UpdateBucket
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Bucket $bucket, array $attributes): Bucket
    {
        $allowed = ['name', 'target_percentage', 'color', 'sort_order'];

        $bucket->update(collect($attributes)->only($allowed)->all());

        return $bucket->fresh();
    }
}
