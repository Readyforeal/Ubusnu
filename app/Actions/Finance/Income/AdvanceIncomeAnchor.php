<?php

namespace App\Actions\Finance\Income;

use App\Models\IncomeSource;

class AdvanceIncomeAnchor
{
    public function __invoke(IncomeSource $source): IncomeSource
    {
        $source->update(['next_expected_on' => $source->advanceAnchor()->toDateString()]);

        return $source->fresh();
    }
}
