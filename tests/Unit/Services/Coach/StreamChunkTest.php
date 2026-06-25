<?php

use App\Services\Coach\StreamChunk;

it('builds text chunks', function () {
    $c = StreamChunk::text('hello');
    expect($c->type)->toBe('text');
    expect($c->payload)->toBe(['delta' => 'hello']);
});

it('builds tool_call chunks', function () {
    $c = StreamChunk::toolCall('id-1', 'top_movers', ['limit' => 5]);
    expect($c->type)->toBe('tool_call');
    expect($c->payload['id'])->toBe('id-1');
    expect($c->payload['name'])->toBe('top_movers');
    expect($c->payload['arguments'])->toBe(['limit' => 5]);
});

it('builds usage chunks', function () {
    $c = StreamChunk::usage(42, 17);
    expect($c->type)->toBe('usage');
    expect($c->payload)->toBe(['input_tokens' => 42, 'output_tokens' => 17]);
});

it('builds done and error chunks', function () {
    expect(StreamChunk::done()->type)->toBe('done');
    expect(StreamChunk::error('boom')->payload['message'])->toBe('boom');
});
