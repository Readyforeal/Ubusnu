<?php

namespace App\Actions\Finance\Goals;

use App\Models\Goal;

class CreateGoal
{
    public function __invoke(
        string $name,
        int $targetCents,
        int $priorityPercentage,
        ?string $color = null,
        ?string $notes = null,
    ): Goal {
        return Goal::create([
            'name' => $name,
            'target_cents' => $targetCents,
            'priority_percentage' => $priorityPercentage,
            'color' => $color,
            'notes' => $notes,
        ]);
    }
}
