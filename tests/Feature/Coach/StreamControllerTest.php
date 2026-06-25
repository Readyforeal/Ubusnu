<?php

use App\Models\AppSetting;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    AppSetting::current()->update([
        'coach_provider' => 'gemini',
        'coach_model' => 'gemini-2.5-flash',
        'gemini_api_key' => 'test-key',
    ]);
    $this->actingAs(User::factory()->create());
});

it('streams NDJSON tokens from the coach driver', function () {
    $thread = ChatThread::factory()->create(['user_id' => auth()->id()]);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(implode("\n", [
            'data: '.json_encode(['candidates' => [['content' => ['parts' => [['text' => 'hi']]]]]]),
            'data: '.json_encode([
                'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => ' there']]]]],
                'usageMetadata' => ['promptTokenCount' => 4, 'candidatesTokenCount' => 2],
            ]),
        ])),
    ]);

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

it('returns 503 when not configured (no API key)', function () {
    AppSetting::current()->update(['gemini_api_key' => null]);
    $thread = ChatThread::factory()->create(['user_id' => auth()->id()]);

    $this->post(route('chat.stream', $thread), ['message' => 'hi'])->assertStatus(503);
});
