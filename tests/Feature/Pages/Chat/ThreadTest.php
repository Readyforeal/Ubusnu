<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders existing messages for a thread', function () {
    $thread = ChatThread::factory()->create(['user_id' => auth()->id()]);
    ChatMessage::factory()->create(['chat_thread_id' => $thread->id, 'role' => 'user', 'content' => 'Hello']);
    ChatMessage::factory()->assistant()->create(['chat_thread_id' => $thread->id, 'content' => 'Hi back']);

    Livewire::test('pages::chat.thread', ['threadId' => $thread->id])
        ->assertSee('Hello')
        ->assertSee('Hi back');
});

it('uses the initial prompt for a fresh thread input', function () {
    Livewire::test('pages::chat.thread', ['threadId' => null, 'initialPrompt' => 'Test prompt'])
        ->assertSet('initialPrompt', 'Test prompt');
});
