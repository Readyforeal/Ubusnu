<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'occurred_on' => now()->toDateString(),
            'description' => $this->faker->company(),
            'amount_cents' => $this->faker->numberBetween(-100000, 100000),
            'category_id' => null,
            'import_batch_id' => null,
            'source' => 'manual',
            'notes' => null,
        ];
    }

    public function onDate(string $date): static
    {
        return $this->state(['occurred_on' => $date]);
    }

    public function withAmount(int $cents): static
    {
        return $this->state(['amount_cents' => $cents]);
    }

    public function forAccount(Account $account): static
    {
        return $this->state(['account_id' => $account->id]);
    }

    public function imported(): static
    {
        return $this->state(['source' => 'import']);
    }
}
