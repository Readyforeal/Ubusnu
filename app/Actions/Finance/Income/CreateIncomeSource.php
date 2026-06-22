<?php

namespace App\Actions\Finance\Income;

use App\Models\IncomeSource;

class CreateIncomeSource
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): IncomeSource
    {
        return IncomeSource::create($attributes);
    }
}
