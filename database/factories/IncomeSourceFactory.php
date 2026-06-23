<?php

namespace Database\Factories;

use App\Models\IncomeSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeSource>
 */
class IncomeSourceFactory extends Factory
{
    protected $model = IncomeSource::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'cadence' => 'monthly',
            'next_expected_on' => CarbonImmutable::today()->addDays(7),
            'primary_day_of_month' => null,
            'secondary_day_of_month' => null,
            'expected_amount_cents' => $this->faker->numberBetween(100000, 500000),
            'account_id' => null,
            'category_id' => null,
            'match_description' => null,
            'color' => null,
            'notes' => null,
            'sort_order' => 0,
        ];
    }

    public function biweekly(): static
    {
        return $this->state(['cadence' => 'biweekly']);
    }

    public function weekly(): static
    {
        return $this->state(['cadence' => 'weekly']);
    }

    public function semiMonthly(): static
    {
        return $this->state([
            'cadence' => 'semi_monthly',
            'primary_day_of_month' => 1,
            'secondary_day_of_month' => 15,
        ]);
    }
}
