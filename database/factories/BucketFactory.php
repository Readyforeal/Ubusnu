<?php

namespace Database\Factories;

use App\Models\Bucket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bucket>
 */
class BucketFactory extends Factory
{
    protected $model = Bucket::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'target_percentage' => 25,
            'color' => null,
            'sort_order' => 0,
        ];
    }
}
