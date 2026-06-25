<?php

use App\Exceptions\CoachNotConfiguredException;
use App\Services\Coach\CoachTool;
use App\Services\Coach\Drivers\OllamaDriver;
use Illuminate\Support\Facades\Http;

it('throws when no base URL is configured', function () {
    $driver = new OllamaDriver(baseUrl: null, model: 'llama3.1:8b');

    expect(fn () => iterator_to_array($driver->stream([], [])))
        ->toThrow(CoachNotConfiguredException::class);
});

it('yields text chunks then done from a streaming response', function () {
    Http::fake([
        'homelab:11434/api/chat' => Http::response(
            implode("\n", [
                json_encode(['message' => ['content' => 'Hello']]),
                json_encode(['message' => ['content' => ' world'], 'done' => false]),
                json_encode(['done' => true, 'prompt_eval_count' => 42, 'eval_count' => 17, 'message' => ['content' => '']]),
            ])
        ),
    ]);

    $driver = new OllamaDriver(baseUrl: 'http://homelab:11434', model: 'llama3.1:8b');
    $chunks = iterator_to_array($driver->stream(
        messages: [['role' => 'user', 'content' => 'hi']],
        tools: [],
    ));

    $types = array_map(fn ($c) => $c->type, $chunks);
    expect($types)->toContain('text');
    expect($types)->toContain('usage');
    expect($types[count($types) - 1])->toBe('done');

    $textPayloads = array_map(fn ($c) => $c->payload['delta'], array_filter($chunks, fn ($c) => $c->type === 'text'));
    expect(implode('', $textPayloads))->toBe('Hello world');

    $usageChunk = array_values(array_filter($chunks, fn ($c) => $c->type === 'usage'))[0];
    expect($usageChunk->payload)->toBe(['input_tokens' => 42, 'output_tokens' => 17]);
});

it('yields tool_call chunks when the model requests a tool', function () {
    Http::fake([
        'homelab:11434/api/chat' => Http::response(
            json_encode([
                'done' => true,
                'message' => [
                    'tool_calls' => [
                        ['function' => ['name' => 'top_movers', 'arguments' => ['limit' => 5]]],
                    ],
                ],
            ])
        ),
    ]);

    $driver = new OllamaDriver(baseUrl: 'http://homelab:11434', model: 'llama3.1:8b');
    $chunks = iterator_to_array($driver->stream([], []));

    $toolChunks = array_values(array_filter($chunks, fn ($c) => $c->type === 'tool_call'));
    expect($toolChunks)->toHaveCount(1);
    expect($toolChunks[0]->payload['name'])->toBe('top_movers');
    expect($toolChunks[0]->payload['arguments'])->toBe(['limit' => 5]);
});

it('sends tools array translated from CoachTool', function () {
    Http::fake(['homelab:11434/api/chat' => Http::response(json_encode(['done' => true, 'message' => []]))]);

    $driver = new OllamaDriver(baseUrl: 'http://homelab:11434', model: 'llama3.1:8b');
    iterator_to_array($driver->stream(
        messages: [],
        tools: [new CoachTool(
            name: 'echo',
            description: 'echoes',
            parameters: ['type' => 'object'],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn () => [],
        )],
    ));

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $body['model'] === 'llama3.1:8b'
            && ($body['tools'][0]['function']['name'] ?? null) === 'echo';
    });
});
