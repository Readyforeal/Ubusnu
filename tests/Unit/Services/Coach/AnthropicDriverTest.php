<?php

use App\Services\Coach\Drivers\AnthropicDriver;
use Illuminate\Support\Facades\Http;

it('throws when no API key is set', function () {
    $driver = new AnthropicDriver(apiKey: null, model: 'claude-sonnet-4-6');

    expect(fn () => iterator_to_array($driver->stream([], [])))
        ->toThrow(RuntimeException::class, 'API key');
});

it('parses text deltas from the SSE stream', function () {
    $sse = implode("\n", [
        'data: '.json_encode(['type' => 'message_start', 'message' => ['usage' => ['input_tokens' => 30, 'output_tokens' => 1]]]),
        '',
        'data: '.json_encode(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '']]),
        '',
        'data: '.json_encode(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Hello']]),
        '',
        'data: '.json_encode(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => ' world']]),
        '',
        'data: '.json_encode(['type' => 'content_block_stop', 'index' => 0]),
        '',
        'data: '.json_encode(['type' => 'message_delta', 'usage' => ['output_tokens' => 14]]),
        '',
        'data: '.json_encode(['type' => 'message_stop']),
    ]);

    Http::fake(['api.anthropic.com/*' => Http::response($sse)]);

    $driver = new AnthropicDriver(apiKey: 'sk-ant-test', model: 'claude-sonnet-4-6');
    $chunks = iterator_to_array($driver->stream(
        messages: [['role' => 'user', 'content' => 'hi']],
        tools: [],
    ));

    $text = implode('', array_map(
        fn ($c) => $c->payload['delta'],
        array_filter($chunks, fn ($c) => $c->type === 'text'),
    ));
    expect($text)->toBe('Hello world');

    $usage = array_values(array_filter($chunks, fn ($c) => $c->type === 'usage'))[0];
    expect($usage->payload)->toBe(['input_tokens' => 30, 'output_tokens' => 14]);

    expect(end($chunks)->type)->toBe('done');
});

it('parses a tool_use content block into a tool_call chunk', function () {
    $sse = implode("\n", [
        'data: '.json_encode(['type' => 'message_start', 'message' => ['usage' => ['input_tokens' => 10, 'output_tokens' => 1]]]),
        '',
        'data: '.json_encode(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'tool_use', 'id' => 'toolu_01abc', 'name' => 'top_movers', 'input' => []]]),
        '',
        'data: '.json_encode(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"limit":5}']]),
        '',
        'data: '.json_encode(['type' => 'content_block_stop', 'index' => 0]),
        '',
        'data: '.json_encode(['type' => 'message_delta', 'usage' => ['output_tokens' => 8]]),
        '',
        'data: '.json_encode(['type' => 'message_stop']),
    ]);

    Http::fake(['api.anthropic.com/*' => Http::response($sse)]);

    $driver = new AnthropicDriver(apiKey: 'sk-ant-test', model: 'claude-sonnet-4-6');
    $chunks = iterator_to_array($driver->stream([], []));

    $tool = array_values(array_filter($chunks, fn ($c) => $c->type === 'tool_call'))[0];
    expect($tool->payload['id'])->toBe('toolu_01abc');
    expect($tool->payload['name'])->toBe('top_movers');
    expect($tool->payload['arguments'])->toBe(['limit' => 5]);
});

it('sends auth header and anthropic-version', function () {
    Http::fake(['api.anthropic.com/*' => Http::response('data: '.json_encode(['type' => 'message_stop']))]);

    $driver = new AnthropicDriver(apiKey: 'sk-ant-test-key', model: 'claude-sonnet-4-6');
    iterator_to_array($driver->stream([], []));

    Http::assertSent(function ($request) {
        return $request->hasHeader('x-api-key', 'sk-ant-test-key')
            && $request->hasHeader('anthropic-version', '2023-06-01');
    });
});
