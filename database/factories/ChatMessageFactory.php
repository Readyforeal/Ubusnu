<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'chat_thread_id' => ChatThread::factory(),
            'role' => 'user',
            'content' => $this->faker->sentence(),
            'tool_calls' => null,
            'model' => null,
        ];
    }

    public function assistant(): static
    {
        return $this->state(['role' => 'assistant', 'model' => 'llama3.1:8b']);
    }

    public function tool(): static
    {
        return $this->state(['role' => 'tool']);
    }
}
