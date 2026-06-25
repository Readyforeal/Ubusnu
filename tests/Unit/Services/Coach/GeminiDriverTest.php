<?php

use App\Services\Coach\Drivers\GeminiDriver;
use Illuminate\Support\Facades\Http;

it('throws when no API key is set', function () {
    $driver = new GeminiDriver(apiKey: null, model: 'gemini-2.5-flash');

    expect(fn () => iterator_to_array($driver->stream([], [])))
        ->toThrow(RuntimeException::class, 'API key');
});

it('parses text chunks from SSE stream', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(implode("\n", [
            'data: '.json_encode(['candidates' => [['content' => ['parts' => [['text' => 'Hello']]]]]]),
            '',
            'data: '.json_encode(['candidates' => [['content' => ['parts' => [['text' => ' world']]]]]]),
            '',
            'data: '.json_encode([
                'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '']]]]],
                'usageMetadata' => ['promptTokenCount' => 25, 'candidatesTokenCount' => 12],
            ]),
        ])),
    ]);

    $driver = new GeminiDriver(apiKey: 'AIza-test', model: 'gemini-2.5-flash');
    $chunks = iterator_to_array($driver->stream(
        messages: [['role' => 'user', 'content' => 'hi']],
        tools: [],
    ));

    $textChunks = array_values(array_filter($chunks, fn ($c) => $c->type === 'text'));
    $text = implode('', array_map(fn ($c) => $c->payload['delta'], $textChunks));
    expect($text)->toBe('Hello world');

    $usage = array_values(array_filter($chunks, fn ($c) => $c->type === 'usage'))[0];
    expect($usage->payload)->toBe(['input_tokens' => 25, 'output_tokens' => 12]);

    expect(end($chunks)->type)->toBe('done');
});

it('parses functionCall as tool_call chunk', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            'data: '.json_encode([
                'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
                    ['functionCall' => ['name' => 'top_movers', 'args' => ['limit' => 5]]],
                ]]]],
            ])
        ),
    ]);

    $driver = new GeminiDriver(apiKey: 'AIza-test', model: 'gemini-2.5-flash');
    $chunks = iterator_to_array($driver->stream([], []));

    $tool = array_values(array_filter($chunks, fn ($c) => $c->type === 'tool_call'))[0];
    expect($tool->payload['name'])->toBe('top_movers');
    expect($tool->payload['arguments'])->toBe(['limit' => 5]);
});

it('sends the API key as a query parameter', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            'data: '.json_encode(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '']]]]]])
        ),
    ]);

    $driver = new GeminiDriver(apiKey: 'AIza-test-key', model: 'gemini-2.5-flash');
    iterator_to_array($driver->stream([], []));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'key=AIza-test-key'));
});
