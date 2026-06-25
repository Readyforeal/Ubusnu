<?php

use App\Models\AppSetting;
use App\Models\ChatThread;
use App\Services\Coach\ChatLoop;
use App\Services\Coach\CoachConfig;
use App\Services\Coach\CoachTool;
use App\Services\Coach\OllamaClient;
use App\Services\Coach\ToolRegistry;

beforeEach(function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
});

it('persists user + assistant messages on a no-tool turn', function () {
    $thread = ChatThread::factory()->create(['title' => 'New chat']);

    $client = Mockery::mock(OllamaClient::class);
    $client->shouldReceive('stream')->andReturn((function () {
        yield ['message' => ['content' => 'Hello']];
        yield ['done' => true, 'message' => ['content' => ' world']];
    })());

    $loop = new ChatLoop($client, new ToolRegistry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'hi'));

    $messages = $thread->messages()->get();
    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe('user');
    expect($messages[1]->role)->toBe('assistant');
    expect($messages[1]->content)->toBe('Hello world');
});

it('auto-sets thread title from first user message', function () {
    $thread = ChatThread::factory()->create(['title' => 'New chat']);

    $client = Mockery::mock(OllamaClient::class);
    $client->shouldReceive('stream')->andReturn((function () {
        yield ['message' => ['content' => 'ok'], 'done' => true];
    })());

    $loop = new ChatLoop($client, new ToolRegistry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'How am I doing?'));

    expect($thread->fresh()->title)->toBe('How am I doing?');
});

it('executes a tool call and feeds the result back', function () {
    $thread = ChatThread::factory()->create();
    $registry = new ToolRegistry;
    $registry->register(new CoachTool(
        name: 'echo',
        description: 'echo',
        parameters: ['type' => 'object'],
        kind: 'read',
        requiresConfirmation: false,
        handler: fn (array $args) => ['echoed' => $args['msg'] ?? 'nothing'],
    ));

    $client = Mockery::mock(OllamaClient::class);
    $callCount = 0;
    $client->shouldReceive('stream')->andReturnUsing(function () use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            return (function () {
                yield ['message' => ['tool_calls' => [['function' => ['name' => 'echo', 'arguments' => ['msg' => 'hello']]]]], 'done' => true];
            })();
        }

        return (function () {
            yield ['message' => ['content' => 'The tool said hello.'], 'done' => true];
        })();
    });

    $loop = new ChatLoop($client, $registry, new CoachConfig);
    $events = iterator_to_array($loop->run($thread, 'echo hello'));

    $kinds = array_column($events, 'type');
    expect($kinds)->toContain('tool_call');

    $roles = $thread->messages()->get()->pluck('role')->all();
    expect($roles)->toBe(['user', 'tool', 'assistant']);
});

it('refuses write-kind tools in v1', function () {
    $thread = ChatThread::factory()->create();
    $registry = new ToolRegistry;
    $registry->register(new CoachTool(
        name: 'do_a_thing',
        description: 'writes',
        parameters: ['type' => 'object'],
        kind: 'write',
        requiresConfirmation: true,
        handler: fn (array $args) => throw new LogicException('should not run'),
    ));

    $client = Mockery::mock(OllamaClient::class);
    $callCount = 0;
    $client->shouldReceive('stream')->andReturnUsing(function () use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            return (function () {
                yield ['message' => ['tool_calls' => [['function' => ['name' => 'do_a_thing', 'arguments' => []]]]], 'done' => true];
            })();
        }

        return (function () {
            yield ['message' => ['content' => 'sorry, cannot.'], 'done' => true];
        })();
    });

    $loop = new ChatLoop($client, $registry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'do it'));

    $toolMsg = $thread->messages()->where('role', 'tool')->first();
    expect($toolMsg->content)->toContain('write tools are not enabled');
});

it('converts *_cents to *_dollars in tool results before sending to the model', function () {
    $thread = ChatThread::factory()->create();
    $registry = new ToolRegistry;
    $registry->register(new CoachTool(
        name: 'biggest_purchase',
        description: 'biggest',
        parameters: ['type' => 'object'],
        kind: 'read',
        requiresConfirmation: false,
        handler: fn (array $args) => [
            'description' => 'Walmart',
            'amount_cents' => 33694,
            'category_median_cents' => 5400,
            'nested' => ['planned_cents' => 100000],
        ],
    ));

    $client = Mockery::mock(OllamaClient::class);
    $callCount = 0;
    $client->shouldReceive('stream')->andReturnUsing(function () use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            return (function () {
                yield ['message' => ['tool_calls' => [['function' => ['name' => 'biggest_purchase', 'arguments' => []]]]], 'done' => true];
            })();
        }

        return (function () {
            yield ['message' => ['content' => 'OK.'], 'done' => true];
        })();
    });

    $loop = new ChatLoop($client, $registry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'show me'));

    $toolMsg = $thread->messages()->where('role', 'tool')->first();
    $payload = json_decode($toolMsg->content, true);

    expect($payload)->toHaveKey('amount_dollars');
    expect($payload['amount_dollars'])->toEqual(336.94);
    expect($payload)->not->toHaveKey('amount_cents');
    expect($payload['category_median_dollars'])->toEqual(54);
    expect($payload['nested']['planned_dollars'])->toEqual(1000);
});

it('persists partial content + error message when Ollama crashes mid-stream', function () {
    $thread = ChatThread::factory()->create(['title' => 'New chat']);

    $client = Mockery::mock(OllamaClient::class);
    $client->shouldReceive('stream')->andReturnUsing(function () {
        yield ['message' => ['content' => 'Looking '], 'done' => false];
        yield ['message' => ['content' => 'at your data...'], 'done' => false];
        throw new RuntimeException('connection reset');
    });

    $loop = new ChatLoop($client, new ToolRegistry, new CoachConfig);
    $events = iterator_to_array($loop->run($thread, 'how am i doing'));

    // Tokens stream out
    expect(collect($events)->pluck('type'))->toContain('token');
    // An error event is yielded at the end
    expect(collect($events)->last()['type'])->toBe('error');
    expect(collect($events)->last()['message'])->toContain('connection reset');

    // Partial content + error suffix are persisted, not lost
    $assistant = $thread->messages()->where('role', 'assistant')->first();
    expect($assistant->content)->toContain('Looking at your data');
    expect($assistant->content)->toContain('connection reset');
});
