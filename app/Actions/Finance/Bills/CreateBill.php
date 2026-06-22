<?php

namespace App\Actions\Finance\Bills;

use App\Models\Bill;

class CreateBill
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): Bill
    {
        return Bill::create($attributes);
    }
}
