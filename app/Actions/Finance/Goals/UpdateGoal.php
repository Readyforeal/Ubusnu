<?php

namespace App\Actions\Finance\Goals;

use App\Models\Goal;

class UpdateGoal
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Goal $goal, array $attributes): Goal
    {
        $allowed = ['name', 'target_cents', 'priority_percentage', 'color', 'notes', 'sort_order'];

        $goal->update(collect($attributes)->only($allowed)->all());

        return $goal->fresh();
    }
}
