<?php

use App\Exceptions\CoachNotConfiguredException;
use App\Models\AppSetting;
use App\Services\Coach\CoachConfig;
use App\Services\Coach\OllamaClient;
use Illuminate\Support\Facades\Http;

it('throws when no base URL is configured', function () {
    AppSetting::current()->update(['ollama_base_url' => null]);

    $client = new OllamaClient(new CoachConfig);

    expect(fn () => $client->dryRun([], []))->toThrow(CoachNotConfiguredException::class);
});

it('sends model + messages + tools to the configured endpoint', function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434', 'ollama_model' => 'llama3.1:8b']);

    Http::fake([
        'http://homelab:11434/api/chat' => Http::response([
            'model' => 'llama3.1:8b',
            'message' => ['role' => 'assistant', 'content' => 'hi'],
            'done' => true,
        ], 200),
    ]);

    $client = new OllamaClient(new CoachConfig);
    $result = $client->dryRun(
        messages: [['role' => 'user', 'content' => 'hello']],
        tools: [['type' => 'function', 'function' => ['name' => 'echo', 'parameters' => ['type' => 'object']]]]
    );

    expect($result['message']['content'])->toBe('hi');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $body['model'] === 'llama3.1:8b'
            && $body['messages'][0]['content'] === 'hello'
            && $body['tools'][0]['function']['name'] === 'echo';
    });
});
