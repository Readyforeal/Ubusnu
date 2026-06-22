<?php

namespace App\Actions\Finance\Bills;

use App\Models\Bill;

class UnmarkBillPaidThisPeriod
{
    public function __invoke(Bill $bill): void
    {
        $bill->removeManuallyMarkedPeriod($bill->currentPeriodToken());
    }
}
