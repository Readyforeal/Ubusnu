<?php

use App\Models\AppSetting;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\Coach\ChatLoop;

beforeEach(function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    $this->actingAs(User::factory()->create());
});

it('streams NDJSON tokens from ChatLoop', function () {
    $thread = ChatThread::factory()->create(['user_id' => auth()->id()]);

    $this->app->bind(ChatLoop::class, function () {
        $mock = Mockery::mock(ChatLoop::class);
        $mock->shouldReceive('run')->andReturn((function () {
            yield ['type' => 'token', 'content' => 'hi'];
            yield ['type' => 'token', 'content' => ' there'];
        })());

        return $mock;
    });

    $response = $this->post(route('chat.stream', $thread), ['message' => 'hello']);

    $response->assertOk();
    $body = $response->streamedContent();
    expect($body)->toContain('"content":"hi"');
    expect($body)->toContain('"content":" there"');
});

it('refuses streaming for a thread that belongs to another user', function () {
    $other = User::factory()->create();
    $thread = ChatThread::factory()->create(['user_id' => $other->id]);

    $this->post(route('chat.stream', $thread), ['message' => 'hi'])->assertForbidden();
});

it('requires a non-empty message', function () {
    $thread = ChatThread::factory()->create(['user_id' => auth()->id()]);

    $this->post(route('chat.stream', $thread), ['message' => ''])->assertStatus(422);
});
