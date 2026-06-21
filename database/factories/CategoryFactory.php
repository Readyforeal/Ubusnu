<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(2, false),
            'keywords' => null,
            'excluded_from_totals' => false,
            'color' => null,
        ];
    }

    public function excludedFromTotals(): static
    {
        return $this->state(['excluded_from_totals' => true]);
    }
}
