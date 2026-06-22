<?php

namespace App\Actions\Finance\Income;

use App\Models\IncomeSource;

class DeleteIncomeSource
{
    public function __invoke(IncomeSource $source): void
    {
        $source->delete();
    }
}
