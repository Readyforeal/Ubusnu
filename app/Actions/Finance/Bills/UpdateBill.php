<?php

namespace App\Actions\Finance\Bills;

use App\Models\Bill;

class UpdateBill
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Bill $bill, array $attributes): Bill
    {
        $allowed = [
            'name', 'cadence', 'due_day_of_month', 'due_month_of_year',
            'expected_amount_cents', 'account_id', 'category_id',
            'match_description', 'color', 'notes', 'sort_order',
            'payment_url', 'username', 'password',
        ];

        $bill->update(collect($attributes)->only($allowed)->all());

        return $bill->fresh();
    }
}
