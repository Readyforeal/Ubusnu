<?php

namespace Database\Factories;

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatThread>
 */
class ChatThreadFactory extends Factory
{
    protected $model = ChatThread::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'pinned_at' => null,
            'last_message_at' => now(),
        ];
    }
}
