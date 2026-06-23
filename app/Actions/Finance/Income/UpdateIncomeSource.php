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
        $allowed = [
            'name', 'cadence', 'next_expected_on', 'primary_day_of_month',
            'secondary_day_of_month', 'expected_amount_cents', 'account_id',
            'category_id', 'match_description', 'color', 'notes', 'sort_order',
        ];

        $source->update(collect($attributes)->only($allowed)->all());

        return $source->fresh();
    }
}
