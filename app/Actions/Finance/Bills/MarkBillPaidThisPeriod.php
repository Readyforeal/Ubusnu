<?php

namespace App\Actions\Finance\Bills;

use App\Models\Bill;

class MarkBillPaidThisPeriod
{
    public function __invoke(Bill $bill): void
    {
        $bill->addManuallyMarkedPeriod($bill->currentPeriodToken());
    }
}
