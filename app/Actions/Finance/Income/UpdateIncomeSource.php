<?php

namespace App\Actions\Finance\Income;

use App\Models\IncomeSource;

class UpdateIncomeSource
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(IncomeSource $source, array $attributes): IncomeSource
    {
        $source->update($attributes);

        return $source->fresh();
    }
}
