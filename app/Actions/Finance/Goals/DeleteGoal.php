<?php

namespace App\Actions\Finance\Goals;

use App\Models\Goal;

class DeleteGoal
{
    public function __invoke(Goal $goal): void
    {
        $goal->delete();
    }
}
