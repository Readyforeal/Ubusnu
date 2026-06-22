<?php

namespace Database\Factories;

use App\Models\Goal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'target_cents' => 100000,
            'priority_percentage' => 10,
            'color' => null,
            'notes' => null,
            'sort_order' => 0,
        ];
    }
}
