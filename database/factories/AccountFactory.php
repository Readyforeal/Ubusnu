<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(2, false),
            'starting_balance_cents' => 0,
            'counts_toward_goals' => false,
            'archived_at' => null,
            'import_profile' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }

    public function countsTowardGoals(): static
    {
        return $this->state(['counts_toward_goals' => true]);
    }

    public function withStartingBalance(int $cents): static
    {
        return $this->state(['starting_balance_cents' => $cents]);
    }
}
