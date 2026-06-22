<?php

namespace App\Actions\Finance\Bills;

use App\Models\Bill;

class DeleteBill
{
    public function __invoke(Bill $bill): void
    {
        $bill->delete();
    }
}
