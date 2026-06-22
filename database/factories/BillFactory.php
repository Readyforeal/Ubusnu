<?php

namespace Database\Factories;

use App\Models\Bill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'cadence' => 'monthly',
            'due_day_of_month' => $this->faker->numberBetween(1, 28),
            'due_month_of_year' => null,
            'expected_amount_cents' => $this->faker->numberBetween(1000, 500000),
            'account_id' => null,
            'category_id' => null,
            'match_description' => null,
            'manually_marked_paid_periods' => null,
            'color' => null,
            'notes' => null,
            'sort_order' => 0,
        ];
    }

    public function annual(): static
    {
        return $this->state([
            'cadence' => 'annual',
            'due_month_of_year' => $this->faker->numberBetween(1, 12),
        ]);
    }
}
