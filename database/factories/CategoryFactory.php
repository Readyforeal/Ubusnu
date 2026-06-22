<?php

namespace Database\Factories;

use App\Models\Bucket;
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
            'name' => $this->faker->unique()->words(2, true),
            'kind' => 'spending',
            'bucket_id' => null,
            'keywords' => null,
            'color' => null,
        ];
    }

    public function incomeKind(): static
    {
        return $this->state(['kind' => 'income']);
    }

    public function transferKind(): static
    {
        return $this->state(['kind' => 'transfer']);
    }

    public function inBucket(Bucket $bucket): static
    {
        return $this->state([
            'kind' => 'spending',
            'bucket_id' => $bucket->id,
        ]);
    }
}
