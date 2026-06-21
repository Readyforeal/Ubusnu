<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'filename' => 'sample.csv',
            'row_count' => 10,
            'imported_count' => 10,
            'skipped_duplicate_count' => 0,
            'error_count' => 0,
            'undone_at' => null,
        ];
    }

    public function undone(): static
    {
        return $this->state(['undone_at' => now()]);
    }
}
