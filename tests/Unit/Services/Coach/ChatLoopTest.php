<?php

use App\Models\AppSetting;
use App\Models\ChatThread;
use App\Services\Coach\ChatLoop;
use App\Services\Coach\CoachConfig;
use App\Services\Coach\CoachDriver;
use App\Services\Coach\CoachTool;
use App\Services\Coach\StreamChunk;
use App\Services\Coach\ToolRegistry;

function fakeDriver(array $rounds): CoachDriver
{
    return new class($rounds) implements CoachDriver
    {
        private int $round = 0;

        public function __construct(private readonly array $rounds) {}

        public function name(): string
        {
            return 'fake';
        }

        public function stream(array $messages, array $tools): Generator
        {
            $chunks = $this->rounds[$this->round] ?? [StreamChunk::done()];
            $this->round++;
            foreach ($chunks as $chunk) {
                yield $chunk;
            }
        }
    };
}

function fakeDriverCapturing(array $rounds, array &$capturedMessages): CoachDriver
{
    return new class($rounds, $capturedMessages) implements CoachDriver
    {
        private int $round = 0;

        public function __construct(private readonly array $rounds, private array &$captured) {}

        public function name(): string
        {
            return 'fake';
        }

        public function stream(array $messages, array $tools): Generator
        {
            $this->captured[$this->round] = $messages;
            $chunks = $this->rounds[$this->round] ?? [StreamChunk::done()];
            $this->round++;
            foreach ($chunks as $chunk) {
                yield $chunk;
            }
        }
    };
}

beforeEach(function () {
    AppSetting::current()->update(['coach_provider' => 'gemini', 'coach_model' => 'gemini-2.5-flash']);
});

it('persists user + assistant messages on a no-tool turn', function () {
    $thread = ChatThread::factory()->create(['title' => 'New chat']);
    $driver = fakeDriver([[
        StreamChunk::text('Hello'),
        StreamChunk::text(' world'),
        StreamChunk::usage(10, 5),
        StreamChunk::done(),
    ]]);

    $loop = new ChatLoop($driver, new ToolRegistry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'hi'));

    $messages = $thread->messages()->get();
    expect($messages)->toHaveCount(2);
    expect($messages[1]->role)->toBe('assistant');
    expect($messages[1]->content)->toBe('Hello world');
    expect($messages[1]->input_tokens)->toBe(10);
    expect($messages[1]->output_tokens)->toBe(5);
    expect($messages[1]->provider)->toBe('fake');
});

it('auto-sets thread title from first user message', function () {
    $thread = ChatThread::factory()->create(['title' => 'New chat']);
    $driver = fakeDriver([[StreamChunk::text('ok'), StreamChunk::done()]]);

    $loop = new ChatLoop($driver, new ToolRegistry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'How am I doing?'));

    expect($thread->fresh()->title)->toBe('How am I doing?');
});

it('executes a tool call and feeds the result back', function () {
    AppSetting::current()->update(['coach_use_tools' => true]);

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

    $driver = fakeDriver([
        [StreamChunk::toolCall('id-1', 'echo', ['msg' => 'hello']), StreamChunk::done()],
        [StreamChunk::text('The tool said hello.'), StreamChunk::done()],
    ]);

    $loop = new ChatLoop($driver, $registry, new CoachConfig);
    $events = iterator_to_array($loop->run($thread, 'echo hello'));

    $kinds = array_column($events, 'type');
    expect($kinds)->toContain('tool_call');

    $roles = $thread->messages()->get()->pluck('role')->all();
    expect($roles)->toBe(['user', 'tool', 'assistant']);
});

it('refuses write-kind tools', function () {
    AppSetting::current()->update(['coach_use_tools' => true]);

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

    $driver = fakeDriver([
        [StreamChunk::toolCall('id-1', 'do_a_thing', []), StreamChunk::done()],
        [StreamChunk::text('blocked'), StreamChunk::done()],
    ]);

    $loop = new ChatLoop($driver, $registry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'go'));

    $toolRow = $thread->messages()->where('role', 'tool')->first();
    expect($toolRow)->not->toBeNull();
    expect($toolRow->content)->toContain('write tools are not enabled');
});

it('persists partial content and error suffix on driver failure', function () {
    $thread = ChatThread::factory()->create();
    $driver = fakeDriver([[
        StreamChunk::text('half-'),
        StreamChunk::error('connection refused'),
    ]]);

    $loop = new ChatLoop($driver, new ToolRegistry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'go'));

    $assistant = $thread->messages()->where('role', 'assistant')->first();
    expect($assistant->content)->toStartWith('half-');
    expect($assistant->content)->toContain('connection refused');
});

it('threads tool_use_id into the messages array passed to the driver on the second round', function () {
    AppSetting::current()->update(['coach_use_tools' => true]);

    $thread = ChatThread::factory()->create();
    $registry = new ToolRegistry;
    $registry->register(new CoachTool(
        name: 'ping',
        description: 'ping',
        parameters: ['type' => 'object'],
        kind: 'read',
        requiresConfirmation: false,
        handler: fn (array $args) => ['pong' => true],
    ));

    $captured = [];
    $driver = fakeDriverCapturing([
        [StreamChunk::toolCall('toolu_01abc', 'ping', []), StreamChunk::done()],
        [StreamChunk::text('done'), StreamChunk::done()],
    ], $captured);

    $loop = new ChatLoop($driver, $registry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'ping please'));

    // The second round (index 1) should contain the tool result message with the correct tool_use_id.
    $round2Messages = $captured[1] ?? [];
    $toolMessage = collect($round2Messages)->firstWhere('role', 'tool');

    expect($toolMessage)->not->toBeNull();
    expect($toolMessage['tool_use_id'])->toBe('toolu_01abc');
});
