<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;

it('persists token usage and provider columns', function () {
    $thread = ChatThread::factory()->create();

    $msg = ChatMessage::create([
        'chat_thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => 'hello',
        'input_tokens' => 42,
        'output_tokens' => 17,
        'provider' => 'gemini',
        'model' => 'gemini-2.5-flash',
    ]);

    $fresh = $msg->fresh();
    expect($fresh->input_tokens)->toBe(42);
    expect($fresh->output_tokens)->toBe(17);
    expect($fresh->provider)->toBe('gemini');
    expect($fresh->model)->toBe('gemini-2.5-flash');
});

it('leaves token columns null for legacy messages', function () {
    $thread = ChatThread::factory()->create();

    $msg = ChatMessage::create([
        'chat_thread_id' => $thread->id,
        'role' => 'user',
        'content' => 'hi',
    ]);

    expect($msg->fresh()->input_tokens)->toBeNull();
    expect($msg->fresh()->provider)->toBeNull();
});
