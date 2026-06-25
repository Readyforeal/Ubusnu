# Multi-Provider Coach Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single-provider Ollama coach with a pluggable `CoachDriver` abstraction. Default to paid Gemini API, with Anthropic and Ollama also available. Track per-message token usage and surface today/MTD spend in settings.

**Architecture:** `ChatLoop` becomes provider-agnostic, consuming normalized `StreamChunk` objects from any `CoachDriver` implementation. Three drivers ship: `GeminiDriver` (default), `AnthropicDriver`, `OllamaDriver` (refactor of existing client). API keys live encrypted in `app_settings`. Token usage persisted on every `ChatMessage` row.

**Tech Stack:** Laravel 13, Livewire 4 SFC, MaryUI, Pest 4, Laravel's `Http` facade for all provider calls (no SDK packages), SQLite, `encrypted` cast for API keys.

**Spec:** `docs/superpowers/specs/2026-06-25-multi-provider-coach-design.md`

**Branch:** Create `multi-provider-coach` off `main` before starting.

---

## Task 1: Settings columns for provider, model, and API keys

**Files:**
- Create: `database/migrations/2026_06_26_010000_add_coach_provider_settings_to_app_settings.php`
- Modify: `app/Models/AppSetting.php`
- Test: `tests/Unit/Models/AppSettingTest.php` (new file)

- [ ] **Step 1: Create the migration file**

```bash
php artisan make:migration add_coach_provider_settings_to_app_settings --table=app_settings
```

Replace the body with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('coach_provider', 32)->default('gemini');
            $table->string('coach_model', 64)->nullable();
            $table->text('gemini_api_key')->nullable();
            $table->text('anthropic_api_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['coach_provider', 'coach_model', 'gemini_api_key', 'anthropic_api_key']);
        });
    }
};
```

- [ ] **Step 2: Update the AppSetting model**

Modify `app/Models/AppSetting.php` to add the four new columns to `#[Fillable]` and add `encrypted` casts for the two API key columns:

```php
#[Fillable([
    'monthly_income_target_cents', 'forecast_lookback_weeks',
    'ollama_base_url', 'ollama_model', 'coach_use_tools',
    'coach_provider', 'coach_model', 'gemini_api_key', 'anthropic_api_key',
])]
class AppSetting extends Model
{
    // ...

    protected function casts(): array
    {
        return [
            'monthly_income_target_cents' => 'integer',
            'forecast_lookback_weeks' => 'integer',
            'coach_use_tools' => 'boolean',
            'gemini_api_key' => 'encrypted',
            'anthropic_api_key' => 'encrypted',
        ];
    }
```

- [ ] **Step 3: Create the failing test**

Create `tests/Unit/Models/AppSettingTest.php`:

```php
<?php

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;

it('defaults coach_provider to gemini', function () {
    expect(AppSetting::current()->coach_provider)->toBe('gemini');
});

it('persists coach_model and reads it back', function () {
    AppSetting::current()->update(['coach_model' => 'gemini-2.5-pro']);

    expect(AppSetting::current()->fresh()->coach_model)->toBe('gemini-2.5-pro');
});

it('encrypts gemini_api_key at rest', function () {
    AppSetting::current()->update(['gemini_api_key' => 'AIza-secret-test-key']);

    expect(AppSetting::current()->fresh()->gemini_api_key)->toBe('AIza-secret-test-key');

    $raw = DB::table('app_settings')->where('id', 1)->value('gemini_api_key');
    expect($raw)->not->toBeNull();
    expect($raw)->not->toContain('AIza-secret-test-key');
});

it('encrypts anthropic_api_key at rest', function () {
    AppSetting::current()->update(['anthropic_api_key' => 'sk-ant-secret-test-key']);

    expect(AppSetting::current()->fresh()->anthropic_api_key)->toBe('sk-ant-secret-test-key');

    $raw = DB::table('app_settings')->where('id', 1)->value('anthropic_api_key');
    expect($raw)->not->toContain('sk-ant-secret-test-key');
});
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=AppSettingTest`
Expected: 4 passed.

- [ ] **Step 5: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: `{"tool":"pint","result":"passed"}`

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_26_010000_add_coach_provider_settings_to_app_settings.php app/Models/AppSetting.php tests/Unit/Models/AppSettingTest.php
git commit -m "feat: add coach_provider settings columns with encrypted API keys"
```

---

## Task 2: Token usage columns on chat_messages

**Files:**
- Create: `database/migrations/2026_06_26_010100_add_token_usage_to_chat_messages.php`
- Modify: `app/Models/ChatMessage.php`
- Test: `tests/Unit/Models/ChatMessageTest.php` (new file)

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration add_token_usage_to_chat_messages --table=chat_messages
```

Replace the body:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->string('provider', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['input_tokens', 'output_tokens', 'provider']);
        });
    }
};
```

Note: The `model` column already exists on `chat_messages`. We're only adding three.

- [ ] **Step 2: Update the ChatMessage model**

Modify `app/Models/ChatMessage.php`:

```php
#[Fillable(['chat_thread_id', 'role', 'content', 'tool_calls', 'model', 'input_tokens', 'output_tokens', 'provider'])]
class ChatMessage extends Model
{
    // ...

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'created_at' => 'datetime',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }
```

- [ ] **Step 3: Create the failing test**

Create `tests/Unit/Models/ChatMessageTest.php`:

```php
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
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=ChatMessageTest`
Expected: 2 passed.

- [ ] **Step 5: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_06_26_010100_add_token_usage_to_chat_messages.php app/Models/ChatMessage.php tests/Unit/Models/ChatMessageTest.php
git commit -m "feat: add token usage tracking columns to chat_messages"
```

---

## Task 3: StreamChunk value object + CoachDriver interface

**Files:**
- Create: `app/Services/Coach/StreamChunk.php`
- Create: `app/Services/Coach/CoachDriver.php`
- Test: `tests/Unit/Services/Coach/StreamChunkTest.php` (new file)

- [ ] **Step 1: Create the value object**

Create `app/Services/Coach/StreamChunk.php`:

```php
<?php

namespace App\Services\Coach;

final class StreamChunk
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $type,
        public readonly array $payload = [],
    ) {}

    public static function text(string $delta): self
    {
        return new self('text', ['delta' => $delta]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function toolCall(string $id, string $name, array $arguments): self
    {
        return new self('tool_call', ['id' => $id, 'name' => $name, 'arguments' => $arguments]);
    }

    public static function usage(int $inputTokens, int $outputTokens): self
    {
        return new self('usage', ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens]);
    }

    public static function done(): self
    {
        return new self('done');
    }

    public static function error(string $message): self
    {
        return new self('error', ['message' => $message]);
    }
}
```

- [ ] **Step 2: Create the interface**

Create `app/Services/Coach/CoachDriver.php`:

```php
<?php

namespace App\Services\Coach;

interface CoachDriver
{
    public function name(): string;

    /**
     * Stream a single round of model completion as normalized chunks.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, CoachTool>  $tools
     * @return \Generator<StreamChunk>
     */
    public function stream(array $messages, array $tools): \Generator;
}
```

- [ ] **Step 3: Create the failing test**

Create `tests/Unit/Services/Coach/StreamChunkTest.php`:

```php
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
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=StreamChunkTest`
Expected: 4 passed.

- [ ] **Step 5: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Coach/StreamChunk.php app/Services/Coach/CoachDriver.php tests/Unit/Services/Coach/StreamChunkTest.php
git commit -m "feat: add CoachDriver interface and StreamChunk value object"
```

---

## Task 4: Refactor OllamaClient → OllamaDriver

**Files:**
- Delete: `app/Services/Coach/OllamaClient.php`
- Create: `app/Services/Coach/Drivers/OllamaDriver.php`
- Modify: `tests/Unit/Services/Coach/OllamaClientTest.php` → rename to `OllamaDriverTest.php`

- [ ] **Step 1: Create the new driver file**

Create `app/Services/Coach/Drivers/OllamaDriver.php`:

```php
<?php

namespace App\Services\Coach\Drivers;

use App\Exceptions\CoachNotConfiguredException;
use App\Services\Coach\CoachDriver;
use App\Services\Coach\CoachTool;
use App\Services\Coach\StreamChunk;
use Illuminate\Support\Facades\Http;

class OllamaDriver implements CoachDriver
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly string $model,
    ) {}

    public function name(): string
    {
        return 'ollama';
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, CoachTool>  $tools
     */
    public function stream(array $messages, array $tools): \Generator
    {
        if (! $this->baseUrl) {
            throw new CoachNotConfiguredException;
        }

        $body = [
            'model' => $this->model,
            'messages' => $messages,
            'stream' => true,
        ];
        if ($tools !== []) {
            $body['tools'] = array_map(fn (CoachTool $t) => [
                'type' => 'function',
                'function' => [
                    'name' => $t->name,
                    'description' => $t->description,
                    'parameters' => $t->parameters,
                ],
            ], $tools);
        }

        $response = Http::timeout(300)
            ->withOptions(['stream' => true])
            ->post(rtrim($this->baseUrl, '/').'/api/chat', $body);

        if ($response->status() >= 400) {
            $errorBody = (string) $response->toPsrResponse()->getBody();
            throw new \RuntimeException("Ollama returned HTTP {$response->status()}: ".mb_substr($errorBody, 0, 200));
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        $promptTokens = 0;
        $completionTokens = 0;

        while (! $stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($newlineAt = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlineAt));
                $buffer = substr($buffer, $newlineAt + 1);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (! is_array($decoded)) {
                    continue;
                }

                $msg = $decoded['message'] ?? [];
                if (isset($msg['content']) && $msg['content'] !== '') {
                    yield StreamChunk::text((string) $msg['content']);
                }
                if (! empty($msg['tool_calls'])) {
                    foreach ($msg['tool_calls'] as $i => $tc) {
                        $args = $tc['function']['arguments'] ?? [];
                        yield StreamChunk::toolCall(
                            id: (string) ($tc['id'] ?? "ollama-{$i}"),
                            name: (string) ($tc['function']['name'] ?? ''),
                            arguments: is_array($args) ? $args : [],
                        );
                    }
                }
                if (isset($decoded['prompt_eval_count'])) {
                    $promptTokens = (int) $decoded['prompt_eval_count'];
                }
                if (isset($decoded['eval_count'])) {
                    $completionTokens = (int) $decoded['eval_count'];
                }
                if ($decoded['done'] ?? false) {
                    if ($promptTokens > 0 || $completionTokens > 0) {
                        yield StreamChunk::usage($promptTokens, $completionTokens);
                    }
                    yield StreamChunk::done();

                    return;
                }
            }
        }
    }
}
```

- [ ] **Step 2: Delete the old client**

```bash
rm app/Services/Coach/OllamaClient.php
```

- [ ] **Step 3: Rename and rewrite the test**

```bash
git mv tests/Unit/Services/Coach/OllamaClientTest.php tests/Unit/Services/Coach/OllamaDriverTest.php
```

Replace the body of `tests/Unit/Services/Coach/OllamaDriverTest.php`:

```php
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
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=OllamaDriverTest`
Expected: 4 passed.

- [ ] **Step 5: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Coach/Drivers/OllamaDriver.php tests/Unit/Services/Coach/OllamaDriverTest.php
git rm app/Services/Coach/OllamaClient.php
git commit -m "refactor: convert OllamaClient to OllamaDriver implementing CoachDriver"
```

After this commit `ChatLoop` still references the deleted `OllamaClient` — that's fixed in Task 5. The test suite as a whole will not pass between commits until then.

---

## Task 5: Refactor ChatLoop to consume StreamChunk

**Files:**
- Modify: `app/Services/Coach/ChatLoop.php`
- Modify: `tests/Unit/Services/Coach/ChatLoopTest.php`

- [ ] **Step 1: Rewrite ChatLoop**

Replace `app/Services/Coach/ChatLoop.php` body entirely:

```php
<?php

namespace App\Services\Coach;

use App\Models\ChatMessage;
use App\Models\ChatThread;

class ChatLoop
{
    public function __construct(
        private readonly CoachDriver $driver,
        private readonly ToolRegistry $registry,
        private readonly CoachConfig $config,
    ) {}

    /**
     * @return \Generator<array{type: string, content?: string, tool_name?: string, summary?: string, message?: string}>
     */
    public function run(ChatThread $thread, string $userMessage): \Generator
    {
        ChatMessage::create([
            'chat_thread_id' => $thread->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);
        $thread->touchLastMessage();

        if ($thread->title === '' || str_starts_with((string) $thread->title, 'New chat')) {
            $thread->update(['title' => mb_substr($userMessage, 0, 60)]);
        }

        $useTools = $this->config->useTools();
        $systemPrompt = $this->loadSystemPrompt($useTools);
        $tools = $useTools ? $this->registry->all() : [];

        $maxRounds = 5;
        $toolCallsRecord = [];
        $assistantBuffer = '';
        $inputTokens = 0;
        $outputTokens = 0;

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($thread->messages()->get() as $m) {
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        try {
            for ($round = 0; $round < $maxRounds; $round++) {
                $roundToolCalls = [];

                foreach ($this->driver->stream($messages, $tools) as $chunk) {
                    if ($chunk->type === 'text') {
                        $assistantBuffer .= $chunk->payload['delta'];
                        yield ['type' => 'token', 'content' => $chunk->payload['delta']];
                    } elseif ($chunk->type === 'tool_call') {
                        $roundToolCalls[] = $chunk->payload;
                    } elseif ($chunk->type === 'usage') {
                        $inputTokens += $chunk->payload['input_tokens'];
                        $outputTokens += $chunk->payload['output_tokens'];
                    } elseif ($chunk->type === 'error') {
                        throw new \RuntimeException($chunk->payload['message']);
                    } elseif ($chunk->type === 'done') {
                        break;
                    }
                }

                if ($roundToolCalls === []) {
                    if ($this->looksLikeToolCallJson($assistantBuffer)) {
                        $assistantBuffer = $this->toolCallFallbackMessage();
                    }

                    $this->persistAssistant($thread, $assistantBuffer, $toolCallsRecord, $inputTokens, $outputTokens);

                    return;
                }

                foreach ($roundToolCalls as $tc) {
                    $name = $tc['name'];
                    $args = $tc['arguments'];
                    $tool = $this->registry->find($name);

                    if (! $tool) {
                        $resultJson = json_encode(['error' => "unknown tool: $name"]);
                    } elseif ($tool->kind === 'write') {
                        $resultJson = json_encode(['error' => 'write tools are not enabled in v1']);
                    } else {
                        try {
                            $result = ($tool->handler)(is_array($args) ? $args : []);
                            $resultJson = json_encode($this->convertCentsToDollars($result));
                        } catch (\Throwable $e) {
                            $resultJson = json_encode(['error' => $e->getMessage()]);
                        }
                    }

                    yield ['type' => 'tool_call', 'tool_name' => $name, 'summary' => mb_substr((string) $resultJson, 0, 200)];

                    $toolCallsRecord[] = [
                        'name' => $name,
                        'arguments' => $args,
                        'result_summary_text' => mb_substr((string) $resultJson, 0, 200),
                    ];

                    ChatMessage::create([
                        'chat_thread_id' => $thread->id,
                        'role' => 'tool',
                        'content' => (string) $resultJson,
                    ]);
                    $messages[] = ['role' => 'tool', 'content' => (string) $resultJson];
                }
            }

            $this->persistAssistant(
                $thread,
                $assistantBuffer !== '' ? $assistantBuffer : '(no response — max rounds reached)',
                $toolCallsRecord,
                $inputTokens,
                $outputTokens,
            );
        } catch (\Throwable $e) {
            $errorSuffix = "\n\n_(error: ".mb_substr($e->getMessage(), 0, 200).')_';
            $persistedContent = $assistantBuffer !== '' ? $assistantBuffer.$errorSuffix : trim($errorSuffix);

            $this->persistAssistant($thread, $persistedContent, $toolCallsRecord, $inputTokens, $outputTokens);

            yield ['type' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $toolCallsRecord
     */
    private function persistAssistant(ChatThread $thread, string $content, array $toolCallsRecord, int $inputTokens, int $outputTokens): void
    {
        ChatMessage::create([
            'chat_thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $content,
            'tool_calls' => $toolCallsRecord === [] ? null : $toolCallsRecord,
            'model' => $this->config->model(),
            'provider' => $this->driver->name(),
            'input_tokens' => $inputTokens > 0 ? $inputTokens : null,
            'output_tokens' => $outputTokens > 0 ? $outputTokens : null,
        ]);
        $thread->touchLastMessage();
    }

    private function loadSystemPrompt(bool $useTools): string
    {
        if (! $useTools) {
            return 'You are a personal finance coach for the Ubusnu app. Reply to the user conversationally in plain English. Do NOT emit JSON. Do NOT try to call any tool — there are no tools available to you this turn. Answer general questions, offer guidance, and ask clarifying questions when the user is vague. Be concise.';
        }

        $path = resource_path('prompts/coach.md');

        return is_file($path) ? (string) file_get_contents($path) : 'You are a helpful financial coach.';
    }

    private function looksLikeToolCallJson(string $content): bool
    {
        $trimmed = trim($content);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return false;
        }
        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded)) {
            return false;
        }

        return array_key_exists('name', $decoded) && array_key_exists('parameters', $decoded);
    }

    private function toolCallFallbackMessage(): string
    {
        return "I tried to call an internal tool but couldn't produce a real answer. This usually means the model is too small for tool calling. Try a larger model, or turn off tool calling in /settings/coach for general conversation.";
    }

    private function convertCentsToDollars(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $key => $v) {
            if (is_string($key) && str_ends_with($key, '_cents') && is_numeric($v)) {
                $newKey = substr($key, 0, -6).'_dollars';
                $out[$newKey] = (float) round(((int) $v) / 100, 2);
            } elseif (is_array($v)) {
                $out[$key] = $this->convertCentsToDollars($v);
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }
}
```

- [ ] **Step 2: Update ChatLoop tests**

Replace `tests/Unit/Services/Coach/ChatLoopTest.php` body. First the helper (anonymous fake driver) and then rewritten tests:

```php
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

        public function stream(array $messages, array $tools): \Generator
        {
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
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=ChatLoopTest`
Expected: 5 passed.

- [ ] **Step 4: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 5: Run full suite**

Run: `php artisan test --compact`
Expected: all passing except the StreamController feature test, which will be fixed in Task 9.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Coach/ChatLoop.php tests/Unit/Services/Coach/ChatLoopTest.php
git commit -m "refactor: ChatLoop consumes CoachDriver via normalized StreamChunks"
```

---

## Task 6: GeminiDriver

**Files:**
- Create: `app/Services/Coach/Drivers/GeminiDriver.php`
- Test: `tests/Unit/Services/Coach/GeminiDriverTest.php` (new file)

- [ ] **Step 1: Create the driver**

Create `app/Services/Coach/Drivers/GeminiDriver.php`:

```php
<?php

namespace App\Services\Coach\Drivers;

use App\Services\Coach\CoachDriver;
use App\Services\Coach\CoachTool;
use App\Services\Coach\StreamChunk;
use Illuminate\Support\Facades\Http;

class GeminiDriver implements CoachDriver
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
    ) {}

    public function name(): string
    {
        return 'gemini';
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, CoachTool>  $tools
     */
    public function stream(array $messages, array $tools): \Generator
    {
        if (! $this->apiKey) {
            throw new \RuntimeException('Gemini API key is not configured');
        }

        $body = [
            'contents' => $this->translateMessages($messages),
            'systemInstruction' => $this->extractSystemInstruction($messages),
        ];
        if ($tools !== []) {
            $body['tools'] = [[
                'functionDeclarations' => array_map(fn (CoachTool $t) => [
                    'name' => $t->name,
                    'description' => $t->description,
                    'parameters' => $t->parameters,
                ], $tools),
            ]];
        }

        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:streamGenerateContent?alt=sse&key=%s',
            urlencode($this->model),
            urlencode($this->apiKey),
        );

        $response = Http::timeout(300)
            ->withOptions(['stream' => true])
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $body);

        if ($response->status() >= 400) {
            $errorBody = (string) $response->toPsrResponse()->getBody();
            throw new \RuntimeException("Gemini returned HTTP {$response->status()}: ".mb_substr($errorBody, 0, 300));
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($newlineAt = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlineAt);
                $buffer = substr($buffer, $newlineAt + 1);
                $line = trim($line);
                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $json = trim(substr($line, 5));
                $decoded = json_decode($json, true);
                if (! is_array($decoded)) {
                    continue;
                }

                $parts = $decoded['candidates'][0]['content']['parts'] ?? [];
                foreach ($parts as $part) {
                    if (isset($part['text']) && $part['text'] !== '') {
                        yield StreamChunk::text((string) $part['text']);
                    } elseif (isset($part['functionCall'])) {
                        yield StreamChunk::toolCall(
                            id: 'gemini-'.uniqid(),
                            name: (string) $part['functionCall']['name'],
                            arguments: $part['functionCall']['args'] ?? [],
                        );
                    }
                }

                if (isset($decoded['usageMetadata'])) {
                    yield StreamChunk::usage(
                        (int) ($decoded['usageMetadata']['promptTokenCount'] ?? 0),
                        (int) ($decoded['usageMetadata']['candidatesTokenCount'] ?? 0),
                    );
                }

                if (($decoded['candidates'][0]['finishReason'] ?? null) !== null) {
                    yield StreamChunk::done();

                    return;
                }
            }
        }

        yield StreamChunk::done();
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, array<string, mixed>>
     */
    private function translateMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                continue;
            }
            $role = match ($m['role']) {
                'assistant' => 'model',
                'tool' => 'function',
                default => 'user',
            };
            $part = $m['role'] === 'tool'
                ? ['functionResponse' => ['name' => 'tool_result', 'response' => ['content' => $m['content']]]]
                : ['text' => $m['content']];

            $out[] = ['role' => $role, 'parts' => [$part]];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, mixed>|null
     */
    private function extractSystemInstruction(array $messages): ?array
    {
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                return ['parts' => [['text' => $m['content']]]];
            }
        }

        return null;
    }
}
```

- [ ] **Step 2: Create the test**

Create `tests/Unit/Services/Coach/GeminiDriverTest.php`:

```php
<?php

use App\Services\Coach\Drivers\GeminiDriver;
use App\Services\Coach\StreamChunk;
use Illuminate\Support\Facades\Http;

it('throws when no API key is set', function () {
    $driver = new GeminiDriver(apiKey: null, model: 'gemini-2.5-flash');

    expect(fn () => iterator_to_array($driver->stream([], [])))
        ->toThrow(\RuntimeException::class, 'API key');
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
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=GeminiDriverTest`
Expected: 4 passed.

- [ ] **Step 4: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Coach/Drivers/GeminiDriver.php tests/Unit/Services/Coach/GeminiDriverTest.php
git commit -m "feat: add GeminiDriver implementing CoachDriver"
```

---

## Task 7: AnthropicDriver

**Files:**
- Create: `app/Services/Coach/Drivers/AnthropicDriver.php`
- Test: `tests/Unit/Services/Coach/AnthropicDriverTest.php` (new file)

- [ ] **Step 1: Create the driver**

Create `app/Services/Coach/Drivers/AnthropicDriver.php`:

```php
<?php

namespace App\Services\Coach\Drivers;

use App\Services\Coach\CoachDriver;
use App\Services\Coach\CoachTool;
use App\Services\Coach\StreamChunk;
use Illuminate\Support\Facades\Http;

class AnthropicDriver implements CoachDriver
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
    ) {}

    public function name(): string
    {
        return 'anthropic';
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, CoachTool>  $tools
     */
    public function stream(array $messages, array $tools): \Generator
    {
        if (! $this->apiKey) {
            throw new \RuntimeException('Anthropic API key is not configured');
        }

        [$system, $translated] = $this->translateMessages($messages);

        $body = [
            'model' => $this->model,
            'max_tokens' => 4096,
            'system' => $system,
            'messages' => $translated,
            'stream' => true,
        ];
        if ($tools !== []) {
            $body['tools'] = array_map(fn (CoachTool $t) => [
                'name' => $t->name,
                'description' => $t->description,
                'input_schema' => $t->parameters,
            ], $tools);
        }

        $response = Http::timeout(300)
            ->withOptions(['stream' => true])
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.anthropic.com/v1/messages', $body);

        if ($response->status() >= 400) {
            $errorBody = (string) $response->toPsrResponse()->getBody();
            throw new \RuntimeException("Anthropic returned HTTP {$response->status()}: ".mb_substr($errorBody, 0, 300));
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        $inputTokens = 0;
        $outputTokens = 0;
        $pendingToolCall = null;
        $pendingToolJson = '';

        while (! $stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($newlineAt = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlineAt);
                $buffer = substr($buffer, $newlineAt + 1);
                $line = trim($line);
                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $json = trim(substr($line, 5));
                $event = json_decode($json, true);
                if (! is_array($event)) {
                    continue;
                }

                $type = $event['type'] ?? '';

                if ($type === 'message_start' && isset($event['message']['usage']['input_tokens'])) {
                    $inputTokens = (int) $event['message']['usage']['input_tokens'];
                } elseif ($type === 'content_block_start' && ($event['content_block']['type'] ?? '') === 'tool_use') {
                    $pendingToolCall = [
                        'id' => (string) $event['content_block']['id'],
                        'name' => (string) $event['content_block']['name'],
                    ];
                    $pendingToolJson = '';
                } elseif ($type === 'content_block_delta') {
                    $delta = $event['delta'] ?? [];
                    if (($delta['type'] ?? '') === 'text_delta' && isset($delta['text'])) {
                        yield StreamChunk::text((string) $delta['text']);
                    } elseif (($delta['type'] ?? '') === 'input_json_delta' && isset($delta['partial_json'])) {
                        $pendingToolJson .= (string) $delta['partial_json'];
                    }
                } elseif ($type === 'content_block_stop' && $pendingToolCall !== null) {
                    $args = $pendingToolJson === '' ? [] : (json_decode($pendingToolJson, true) ?: []);
                    yield StreamChunk::toolCall($pendingToolCall['id'], $pendingToolCall['name'], $args);
                    $pendingToolCall = null;
                    $pendingToolJson = '';
                } elseif ($type === 'message_delta' && isset($event['usage']['output_tokens'])) {
                    $outputTokens = (int) $event['usage']['output_tokens'];
                } elseif ($type === 'message_stop') {
                    if ($inputTokens > 0 || $outputTokens > 0) {
                        yield StreamChunk::usage($inputTokens, $outputTokens);
                    }
                    yield StreamChunk::done();

                    return;
                }
            }
        }

        yield StreamChunk::done();
    }

    /**
     * Pull out the system message and translate the rest.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @return array{0: string, 1: array<int, array<string, mixed>>}
     */
    private function translateMessages(array $messages): array
    {
        $system = '';
        $out = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system .= $m['content'];

                continue;
            }
            if ($m['role'] === 'tool') {
                $out[] = [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => 'anthropic-tool-result',
                        'content' => $m['content'],
                    ]],
                ];

                continue;
            }
            $out[] = ['role' => $m['role'], 'content' => $m['content']];
        }

        return [$system, $out];
    }
}
```

- [ ] **Step 2: Create the test**

Create `tests/Unit/Services/Coach/AnthropicDriverTest.php`:

```php
<?php

use App\Services\Coach\Drivers\AnthropicDriver;
use Illuminate\Support\Facades\Http;

it('throws when no API key is set', function () {
    $driver = new AnthropicDriver(apiKey: null, model: 'claude-sonnet-4-6');

    expect(fn () => iterator_to_array($driver->stream([], [])))
        ->toThrow(\RuntimeException::class, 'API key');
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
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=AnthropicDriverTest`
Expected: 4 passed.

- [ ] **Step 4: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Coach/Drivers/AnthropicDriver.php tests/Unit/Services/Coach/AnthropicDriverTest.php
git commit -m "feat: add AnthropicDriver implementing CoachDriver"
```

---

## Task 8: Refactor CoachConfig for multi-provider

**Files:**
- Modify: `app/Services/Coach/CoachConfig.php`
- Modify: `tests/Unit/Services/Coach/CoachConfigTest.php`

- [ ] **Step 1: Rewrite CoachConfig**

Replace `app/Services/Coach/CoachConfig.php` body:

```php
<?php

namespace App\Services\Coach;

use App\Models\AppSetting;
use App\Services\Coach\Drivers\AnthropicDriver;
use App\Services\Coach\Drivers\GeminiDriver;
use App\Services\Coach\Drivers\OllamaDriver;

class CoachConfig
{
    private const DEFAULT_MODELS = [
        'gemini' => 'gemini-2.5-flash',
        'anthropic' => 'claude-sonnet-4-6',
        'ollama' => 'llama3.1:8b',
    ];

    public function provider(): string
    {
        return (string) (AppSetting::current()->coach_provider ?: 'gemini');
    }

    public function model(): string
    {
        $stored = (string) (AppSetting::current()->coach_model ?? '');
        if ($stored !== '') {
            return $stored;
        }

        return self::DEFAULT_MODELS[$this->provider()] ?? 'gemini-2.5-flash';
    }

    public function apiKey(): ?string
    {
        $setting = AppSetting::current();

        return match ($this->provider()) {
            'gemini' => $setting->gemini_api_key,
            'anthropic' => $setting->anthropic_api_key,
            default => null,
        };
    }

    public function ollamaBaseUrl(): ?string
    {
        $url = AppSetting::current()->ollama_base_url;

        return $url ? rtrim((string) $url, '/') : null;
    }

    public function isConfigured(): bool
    {
        return match ($this->provider()) {
            'gemini', 'anthropic' => ! empty($this->apiKey()),
            'ollama' => ! empty($this->ollamaBaseUrl()),
            default => false,
        };
    }

    public function useTools(): bool
    {
        return (bool) AppSetting::current()->coach_use_tools;
    }

    public function driver(): CoachDriver
    {
        return match ($this->provider()) {
            'gemini' => new GeminiDriver($this->apiKey(), $this->model()),
            'anthropic' => new AnthropicDriver($this->apiKey(), $this->model()),
            'ollama' => new OllamaDriver($this->ollamaBaseUrl(), $this->model()),
            default => throw new \RuntimeException('Unknown coach provider: '.$this->provider()),
        };
    }
}
```

- [ ] **Step 2: Rewrite CoachConfigTest**

Replace `tests/Unit/Services/Coach/CoachConfigTest.php`:

```php
<?php

use App\Models\AppSetting;
use App\Services\Coach\CoachConfig;
use App\Services\Coach\Drivers\AnthropicDriver;
use App\Services\Coach\Drivers\GeminiDriver;
use App\Services\Coach\Drivers\OllamaDriver;

it('defaults provider to gemini', function () {
    expect((new CoachConfig)->provider())->toBe('gemini');
});

it('returns the stored coach_model when set', function () {
    AppSetting::current()->update(['coach_model' => 'gemini-2.5-pro']);

    expect((new CoachConfig)->model())->toBe('gemini-2.5-pro');
});

it('falls back to gemini-2.5-flash when no model is stored', function () {
    AppSetting::current()->update(['coach_provider' => 'gemini', 'coach_model' => null]);

    expect((new CoachConfig)->model())->toBe('gemini-2.5-flash');
});

it('falls back to claude-sonnet-4-6 when provider is anthropic and model is unset', function () {
    AppSetting::current()->update(['coach_provider' => 'anthropic', 'coach_model' => null]);

    expect((new CoachConfig)->model())->toBe('claude-sonnet-4-6');
});

it('returns the right API key per provider', function () {
    AppSetting::current()->update([
        'coach_provider' => 'gemini',
        'gemini_api_key' => 'gem-key',
        'anthropic_api_key' => 'ant-key',
    ]);
    expect((new CoachConfig)->apiKey())->toBe('gem-key');

    AppSetting::current()->update(['coach_provider' => 'anthropic']);
    expect((new CoachConfig)->apiKey())->toBe('ant-key');

    AppSetting::current()->update(['coach_provider' => 'ollama']);
    expect((new CoachConfig)->apiKey())->toBeNull();
});

it('isConfigured: gemini needs an API key', function () {
    AppSetting::current()->update(['coach_provider' => 'gemini', 'gemini_api_key' => null]);
    expect((new CoachConfig)->isConfigured())->toBeFalse();

    AppSetting::current()->update(['gemini_api_key' => 'gem-key']);
    expect((new CoachConfig)->isConfigured())->toBeTrue();
});

it('isConfigured: ollama needs a base URL', function () {
    AppSetting::current()->update(['coach_provider' => 'ollama', 'ollama_base_url' => null]);
    expect((new CoachConfig)->isConfigured())->toBeFalse();

    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    expect((new CoachConfig)->isConfigured())->toBeTrue();
});

it('driver() returns the right concrete class for each provider', function () {
    AppSetting::current()->update(['coach_provider' => 'gemini', 'gemini_api_key' => 'k']);
    expect((new CoachConfig)->driver())->toBeInstanceOf(GeminiDriver::class);

    AppSetting::current()->update(['coach_provider' => 'anthropic', 'anthropic_api_key' => 'k']);
    expect((new CoachConfig)->driver())->toBeInstanceOf(AnthropicDriver::class);

    AppSetting::current()->update(['coach_provider' => 'ollama', 'ollama_base_url' => 'http://h:11434']);
    expect((new CoachConfig)->driver())->toBeInstanceOf(OllamaDriver::class);
});
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=CoachConfigTest`
Expected: 8 passed.

- [ ] **Step 4: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Coach/CoachConfig.php tests/Unit/Services/Coach/CoachConfigTest.php
git commit -m "refactor: CoachConfig dispatches by provider, returns driver instance"
```

---

## Task 9: Wire StreamController to use the new driver

**Files:**
- Modify: `app/Http/Controllers/Coach/StreamController.php`
- Modify: `tests/Feature/Coach/StreamControllerTest.php`

- [ ] **Step 1: Update the controller**

Modify `app/Http/Controllers/Coach/StreamController.php`:

```php
<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Services\Coach\ChatLoop;
use App\Services\Coach\CoachConfig;
use App\Services\Coach\ToolRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function stream(Request $request, ChatThread $thread, CoachConfig $config, ToolRegistry $registry): Response
    {
        abort_unless($thread->user_id === $request->user()->id, 403);

        $message = (string) $request->input('message', '');
        abort_if($message === '', 422, 'message is required');

        if (! $config->isConfigured()) {
            return response()->json(['error' => 'Coach is not configured'], 503);
        }

        $loop = new ChatLoop($config->driver(), $registry, $config);

        return new StreamedResponse(function () use ($thread, $loop, $message) {
            @set_time_limit(0);
            ob_implicit_flush(true);

            foreach ($loop->run($thread, $message) as $event) {
                echo json_encode($event)."\n";
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

- [ ] **Step 2: Look at existing controller test to understand the patch needed**

Read the file at `tests/Feature/Coach/StreamControllerTest.php` to find the existing setup. Update any reference to `OllamaClient` mock to instead set up `coach_provider = 'gemini'`, `gemini_api_key = 'k'`, and mock the HTTP layer via `Http::fake([...])` rather than mocking a service object.

A representative patch — change tests that previously did:

```php
$client = Mockery::mock(OllamaClient::class);
$client->shouldReceive('stream')->andReturn(...);
$this->app->instance(OllamaClient::class, $client);
```

…to:

```php
AppSetting::current()->update([
    'coach_provider' => 'gemini',
    'gemini_api_key' => 'test-key',
    'coach_model' => 'gemini-2.5-flash',
]);
Http::fake([
    'generativelanguage.googleapis.com/*' => Http::response(
        'data: '.json_encode([
            'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => 'hi']]]]],
            'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 2],
        ])
    ),
]);
```

For tests that previously relied on Ollama being unconfigured (the 503 path), keep them but set `coach_provider = 'gemini'` and leave `gemini_api_key = null`. `isConfigured()` will return false the same way.

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=StreamControllerTest`
Expected: all passing.

- [ ] **Step 4: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 5: Run full suite**

Run: `php artisan test --compact`
Expected: all passing.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Coach/StreamController.php tests/Feature/Coach/StreamControllerTest.php
git commit -m "feat: StreamController dispatches to provider-selected CoachDriver"
```

---

## Task 10: EstimateCost action

**Files:**
- Create: `app/Actions/Coach/EstimateCost.php`
- Test: `tests/Unit/Actions/Coach/EstimateCostTest.php` (new file)

- [ ] **Step 1: Create the action**

Create `app/Actions/Coach/EstimateCost.php`:

```php
<?php

namespace App\Actions\Coach;

class EstimateCost
{
    /**
     * Cents per million tokens.
     *
     * @var array<string, array<string, array{input: int, output: int}>>
     */
    private const PRICING = [
        'gemini' => [
            'gemini-2.5-flash' => ['input' => 30, 'output' => 250],
            'gemini-2.5-pro' => ['input' => 125, 'output' => 1000],
        ],
        'anthropic' => [
            'claude-haiku-4-5-20251001' => ['input' => 100, 'output' => 500],
            'claude-sonnet-4-6' => ['input' => 300, 'output' => 1500],
            'claude-opus-4-7' => ['input' => 1500, 'output' => 7500],
        ],
    ];

    /**
     * Returns the dollar cost in cents.
     */
    public function __invoke(string $provider, string $model, int $inputTokens, int $outputTokens): int
    {
        $rates = self::PRICING[$provider][$model] ?? null;
        if (! $rates) {
            return 0;
        }

        $inputCents = ($inputTokens * $rates['input']) / 1_000_000;
        $outputCents = ($outputTokens * $rates['output']) / 1_000_000;

        return (int) round($inputCents + $outputCents);
    }
}
```

- [ ] **Step 2: Create the test**

Create `tests/Unit/Actions/Coach/EstimateCostTest.php`:

```php
<?php

use App\Actions\Coach\EstimateCost;

it('returns zero for unknown provider/model', function () {
    expect((new EstimateCost)('unknown', 'unknown', 1000, 1000))->toBe(0);
    expect((new EstimateCost)('gemini', 'made-up', 1000, 1000))->toBe(0);
});

it('estimates Gemini Flash cost', function () {
    // 1M input × $0.30 = $0.30 = 30 cents; 1M output × $2.50 = $2.50 = 250 cents
    expect((new EstimateCost)('gemini', 'gemini-2.5-flash', 1_000_000, 1_000_000))->toBe(280);
});

it('estimates Sonnet cost', function () {
    // 1M input × $3.00 = 300; 1M output × $15.00 = 1500
    expect((new EstimateCost)('anthropic', 'claude-sonnet-4-6', 1_000_000, 1_000_000))->toBe(1800);
});

it('rounds tiny token counts to zero cents', function () {
    expect((new EstimateCost)('gemini', 'gemini-2.5-flash', 100, 50))->toBe(0);
});

it('estimates a realistic chat turn cost', function () {
    // 5,000 input tokens + 1,500 output for Flash
    // input: 5,000 × 30 / 1M = 0.15 cents
    // output: 1,500 × 250 / 1M = 0.375 cents
    // total ≈ 0.525 cents, rounded to 1 cent
    expect((new EstimateCost)('gemini', 'gemini-2.5-flash', 5_000, 1_500))->toBe(1);
});

it('returns zero for ollama (no pricing)', function () {
    expect((new EstimateCost)('ollama', 'llama3.1:8b', 50_000, 10_000))->toBe(0);
});
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=EstimateCostTest`
Expected: 6 passed.

- [ ] **Step 4: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Coach/EstimateCost.php tests/Unit/Actions/Coach/EstimateCostTest.php
git commit -m "feat: add EstimateCost action with provider pricing table"
```

---

## Task 11: SummarizeCoachUsage action

**Files:**
- Create: `app/Actions/Coach/SummarizeCoachUsage.php`
- Test: `tests/Unit/Actions/Coach/SummarizeCoachUsageTest.php` (new file)

- [ ] **Step 1: Create the action**

Create `app/Actions/Coach/SummarizeCoachUsage.php`:

```php
<?php

namespace App\Actions\Coach;

use App\Models\ChatMessage;
use Carbon\CarbonImmutable;

class SummarizeCoachUsage
{
    public function __construct(private readonly EstimateCost $estimateCost) {}

    /**
     * @return array{
     *     today: array{input: int, output: int, cents: int},
     *     month: array{input: int, output: int, cents: int}
     * }
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();

        return [
            'today' => $this->summarize($today->startOfDay(), $today->endOfDay()),
            'month' => $this->summarize($today->startOfMonth(), $today->endOfMonth()),
        ];
    }

    /**
     * @return array{input: int, output: int, cents: int}
     */
    private function summarize(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = ChatMessage::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('provider')
            ->whereNotNull('model')
            ->selectRaw('provider, model, COALESCE(SUM(input_tokens), 0) as input_total, COALESCE(SUM(output_tokens), 0) as output_total')
            ->groupBy('provider', 'model')
            ->get();

        $totalInput = 0;
        $totalOutput = 0;
        $totalCents = 0;

        foreach ($rows as $row) {
            $input = (int) $row->input_total;
            $output = (int) $row->output_total;
            $totalInput += $input;
            $totalOutput += $output;
            $totalCents += ($this->estimateCost)($row->provider, $row->model, $input, $output);
        }

        return ['input' => $totalInput, 'output' => $totalOutput, 'cents' => $totalCents];
    }
}
```

- [ ] **Step 2: Create the test**

Create `tests/Unit/Actions/Coach/SummarizeCoachUsageTest.php`:

```php
<?php

use App\Actions\Coach\EstimateCost;
use App\Actions\Coach\SummarizeCoachUsage;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use Carbon\CarbonImmutable;

it('returns zero totals when no messages exist', function () {
    $result = (new SummarizeCoachUsage(new EstimateCost))();

    expect($result['today']['input'])->toBe(0);
    expect($result['today']['cents'])->toBe(0);
    expect($result['month']['input'])->toBe(0);
});

it('sums tokens and estimates cost for today', function () {
    CarbonImmutable::setTestNow('2026-06-25 12:00:00');

    $thread = ChatThread::factory()->create();
    ChatMessage::create([
        'chat_thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => 'a',
        'provider' => 'gemini',
        'model' => 'gemini-2.5-flash',
        'input_tokens' => 1_000_000,
        'output_tokens' => 1_000_000,
    ]);

    $result = (new SummarizeCoachUsage(new EstimateCost))();
    expect($result['today']['input'])->toBe(1_000_000);
    expect($result['today']['output'])->toBe(1_000_000);
    expect($result['today']['cents'])->toBe(280);

    CarbonImmutable::setTestNow();
});

it('excludes messages without a provider', function () {
    $thread = ChatThread::factory()->create();
    ChatMessage::create([
        'chat_thread_id' => $thread->id,
        'role' => 'user',
        'content' => 'hi',
    ]);

    $result = (new SummarizeCoachUsage(new EstimateCost))();
    expect($result['today']['input'])->toBe(0);
});

it('aggregates across multiple providers and models', function () {
    CarbonImmutable::setTestNow('2026-06-25 09:00:00');

    $thread = ChatThread::factory()->create();
    ChatMessage::create([
        'chat_thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => 'a',
        'provider' => 'gemini',
        'model' => 'gemini-2.5-flash',
        'input_tokens' => 500_000,
        'output_tokens' => 100_000,
    ]);
    ChatMessage::create([
        'chat_thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => 'b',
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-4-6',
        'input_tokens' => 50_000,
        'output_tokens' => 10_000,
    ]);

    $result = (new SummarizeCoachUsage(new EstimateCost))();
    expect($result['today']['input'])->toBe(550_000);
    expect($result['today']['output'])->toBe(110_000);
    // Flash: 500_000×30/1M + 100_000×250/1M = 15 + 25 = 40 cents
    // Sonnet: 50_000×300/1M + 10_000×1500/1M = 15 + 15 = 30 cents
    expect($result['today']['cents'])->toBe(70);

    CarbonImmutable::setTestNow();
});
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=SummarizeCoachUsageTest`
Expected: 4 passed.

- [ ] **Step 4: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Coach/SummarizeCoachUsage.php tests/Unit/Actions/Coach/SummarizeCoachUsageTest.php
git commit -m "feat: add SummarizeCoachUsage action for today/MTD token + cost rollups"
```

---

## Task 12: Coach settings page rewrite

**Files:**
- Modify: `resources/views/pages/settings/⚡coach.blade.php`
- Modify: `tests/Feature/Settings/CoachSettingsTest.php` (or create if missing)

- [ ] **Step 1: Rewrite the settings page**

Replace `resources/views/pages/settings/⚡coach.blade.php`:

```blade
<?php

use App\Actions\Coach\SummarizeCoachUsage;
use App\Models\AppSetting;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Coach settings')] class extends Component {
    #[Validate('required|in:gemini,anthropic,ollama')]
    public string $provider = 'gemini';

    #[Validate('nullable|string|max:64')]
    public string $coachModel = '';

    #[Validate('nullable|string|max:255')]
    public string $geminiApiKey = '';

    #[Validate('nullable|string|max:255')]
    public string $anthropicApiKey = '';

    #[Validate('nullable|url|max:255')]
    public string $ollamaBaseUrl = '';

    #[Validate('nullable|string|max:64')]
    public string $ollamaModel = '';

    public bool $useTools = false;

    public bool $showWipeBanner = false;

    public function mount(): void
    {
        $setting = AppSetting::current();
        $this->provider = (string) ($setting->coach_provider ?? 'gemini');
        $this->coachModel = (string) ($setting->coach_model ?? '');
        $this->geminiApiKey = (string) ($setting->gemini_api_key ?? '');
        $this->anthropicApiKey = (string) ($setting->anthropic_api_key ?? '');
        $this->ollamaBaseUrl = (string) ($setting->ollama_base_url ?? '');
        $this->ollamaModel = (string) ($setting->ollama_model ?? '');
        $this->useTools = (bool) $setting->coach_use_tools;
    }

    public function save(): void
    {
        $this->validate();
        $previousProvider = (string) AppSetting::current()->coach_provider;

        AppSetting::current()->update([
            'coach_provider' => $this->provider,
            'coach_model' => $this->coachModel ?: null,
            'gemini_api_key' => $this->geminiApiKey ?: null,
            'anthropic_api_key' => $this->anthropicApiKey ?: null,
            'ollama_base_url' => $this->ollamaBaseUrl ?: null,
            'ollama_model' => $this->ollamaModel ?: null,
            'coach_use_tools' => $this->useTools,
        ]);

        if ($previousProvider !== $this->provider) {
            $this->showWipeBanner = true;
        }

        $this->dispatch('coach-saved');
    }

    public function wipeHistory(): void
    {
        \DB::table('chat_messages')->delete();
        \DB::table('chat_threads')->delete();
        $this->showWipeBanner = false;
    }

    public function dismissBanner(): void
    {
        $this->showWipeBanner = false;
    }

    #[Computed]
    public function modelOptions(): array
    {
        return match ($this->provider) {
            'gemini' => [
                ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash (default, cheapest)'],
                ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro'],
            ],
            'anthropic' => [
                ['id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5'],
                ['id' => 'claude-sonnet-4-6', 'name' => 'Claude Sonnet 4.6 (default)'],
                ['id' => 'claude-opus-4-7', 'name' => 'Claude Opus 4.7'],
            ],
            'ollama' => [],
            default => [],
        };
    }

    #[Computed]
    public function usage(): array
    {
        return (new SummarizeCoachUsage(new \App\Actions\Coach\EstimateCost))();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Coach')" :subheading="__('Choose a provider and configure access')">
        <x-form wire:submit="save" class="space-y-4">
            <x-radio label="Provider" :options="[
                ['id' => 'gemini', 'name' => 'Google Gemini'],
                ['id' => 'anthropic', 'name' => 'Anthropic Claude'],
                ['id' => 'ollama', 'name' => 'Ollama (local)'],
            ]" wire:model.live="provider" />

            @if ($provider !== 'ollama')
                <x-select label="Model" :options="$this->modelOptions" option-label="name" option-value="id" wire:model="coachModel" placeholder="(use default)" />
            @endif

            @if ($provider === 'gemini')
                <x-input label="Gemini API key" type="password" wire:model="geminiApiKey" autocomplete="off" hint="Encrypted at rest. Get one at aistudio.google.com." />
            @endif

            @if ($provider === 'anthropic')
                <x-input label="Anthropic API key" type="password" wire:model="anthropicApiKey" autocomplete="off" hint="Encrypted at rest. Get one at console.anthropic.com." />
            @endif

            @if ($provider === 'ollama')
                <x-input label="Ollama base URL" wire:model="ollamaBaseUrl" placeholder="http://homelab.local:11434" />
                <x-input label="Ollama model" wire:model="ollamaModel" placeholder="llama3.1:8b" />
            @endif

            <x-checkbox label="Enable tool calling" wire:model="useTools" hint="When ON, the coach can call analytics tools (top movers, anomalies, budget variance, etc.)." />

            <div class="flex gap-2">
                <x-button label="Save" type="submit" class="btn-primary" />
            </div>
        </x-form>

        @if ($showWipeBanner)
            <x-alert class="alert-warning mt-4">
                <span>Switching providers. Wipe existing chat history?</span>
                <x-slot:actions>
                    <x-button label="Yes, wipe" class="btn-error btn-sm" wire:click="wipeHistory" />
                    <x-button label="Keep" class="btn-ghost btn-sm" wire:click="dismissBanner" />
                </x-slot:actions>
            </x-alert>
        @endif

        <x-card class="border border-base-300 mt-6">
            <h2 class="text-sm font-semibold mb-3">{{ __('Usage') }}</h2>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="opacity-60">Today</div>
                    <div class="font-mono">{{ number_format($this->usage['today']['input']) }} in / {{ number_format($this->usage['today']['output']) }} out</div>
                    <div class="text-lg font-mono">${{ number_format($this->usage['today']['cents'] / 100, 2) }}</div>
                </div>
                <div>
                    <div class="opacity-60">Month to date</div>
                    <div class="font-mono">{{ number_format($this->usage['month']['input']) }} in / {{ number_format($this->usage['month']['output']) }} out</div>
                    <div class="text-lg font-mono">${{ number_format($this->usage['month']['cents'] / 100, 2) }}</div>
                </div>
            </div>
        </x-card>
    </x-pages::settings.layout>
</section>
```

- [ ] **Step 2: Update or create the settings page test**

If `tests/Feature/Settings/CoachSettingsTest.php` exists, replace it. Otherwise create it:

```php
<?php

use App\Models\AppSetting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders with defaults', function () {
    Livewire::test('pages::settings.coach')
        ->assertSet('provider', 'gemini')
        ->assertSee('Google Gemini');
});

it('saves provider and Gemini key', function () {
    Livewire::test('pages::settings.coach')
        ->set('provider', 'gemini')
        ->set('geminiApiKey', 'AIza-key')
        ->set('coachModel', 'gemini-2.5-pro')
        ->call('save');

    $setting = AppSetting::current()->fresh();
    expect($setting->coach_provider)->toBe('gemini');
    expect($setting->gemini_api_key)->toBe('AIza-key');
    expect($setting->coach_model)->toBe('gemini-2.5-pro');
});

it('shows wipe banner when provider changes', function () {
    AppSetting::current()->update(['coach_provider' => 'gemini']);

    Livewire::test('pages::settings.coach')
        ->set('provider', 'anthropic')
        ->set('anthropicApiKey', 'sk-ant-x')
        ->call('save')
        ->assertSet('showWipeBanner', true);
});

it('does not show wipe banner when provider unchanged', function () {
    AppSetting::current()->update(['coach_provider' => 'gemini']);

    Livewire::test('pages::settings.coach')
        ->set('geminiApiKey', 'AIza-new')
        ->call('save')
        ->assertSet('showWipeBanner', false);
});

it('switches model dropdown options when provider changes', function () {
    Livewire::test('pages::settings.coach')
        ->set('provider', 'anthropic')
        ->assertSee('Claude Sonnet')
        ->set('provider', 'gemini')
        ->assertSee('Gemini 2.5 Flash');
});
```

- [ ] **Step 3: Run tests**

Run: `php artisan test --compact --filter=CoachSettingsTest`
Expected: 5 passed.

- [ ] **Step 4: Run pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passed.

- [ ] **Step 5: Commit**

```bash
git add resources/views/pages/settings/⚡coach.blade.php tests/Feature/Settings/CoachSettingsTest.php
git commit -m "feat: multi-provider Coach settings UI with provider switch and usage rollups"
```

---

## Task 13: Wipe migration for Ollama-era threads

**Files:**
- Create: `database/migrations/2026_06_26_020000_wipe_chat_threads_for_provider_switch.php`

- [ ] **Step 1: Create the migration**

```bash
php artisan make:migration wipe_chat_threads_for_provider_switch
```

Replace the body:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chat_messages')) {
            DB::table('chat_messages')->delete();
        }
        if (Schema::hasTable('chat_threads')) {
            DB::table('chat_threads')->delete();
        }
    }

    public function down(): void
    {
        // No-op: wiped data cannot be restored.
    }
};
```

- [ ] **Step 2: Verify the migration runs cleanly against the in-memory test DB**

Run: `php artisan test --compact`
Expected: full suite still passing — the migration runs as part of RefreshDatabase and finds empty tables on a fresh DB.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_26_020000_wipe_chat_threads_for_provider_switch.php
git commit -m "chore: wipe existing chat threads on multi-provider deploy"
```

---

## Task 14: Provider-agnostic system prompt

**Files:**
- Modify: `resources/prompts/coach.md`

- [ ] **Step 1: Read the current prompt**

Read `resources/prompts/coach.md` to see if it has Ollama-specific or model-specific language. If it references "Ollama" or specific model behaviors that are no longer accurate, generalize.

The minimum required edit: replace any reference to Ollama with provider-neutral language, e.g. "the LLM" or "the coach model".

- [ ] **Step 2: Run full test suite to confirm nothing references the prompt strings**

Run: `php artisan test --compact`
Expected: full suite passing.

- [ ] **Step 3: Commit (only if the prompt was actually changed)**

```bash
git add resources/prompts/coach.md
git commit -m "docs: provider-neutral phrasing in coach system prompt"
```

If no changes were needed, skip the commit and move on.

---

## Task 15: Final integration check

- [ ] **Step 1: Run pint on everything**

Run: `vendor/bin/pint --format agent`
Expected: passed.

- [ ] **Step 2: Run the full test suite**

Run: `php artisan test --compact`
Expected: all tests passing.

- [ ] **Step 3: Manually smoke-test in the browser**

Start dev server if not running: `npm run dev` and `php artisan serve` (or `composer run dev`).

In the app:
1. Visit `/settings/coach`
2. Select Gemini, paste a valid Gemini API key from aistudio.google.com, save
3. Visit `/coach`, send a message ("How am I doing this month?")
4. Verify response streams character-by-character
5. Return to `/settings/coach`, confirm usage today shows non-zero tokens and a sub-cent cost
6. Change provider to Anthropic, paste a key, save, confirm the wipe banner appears
7. Click "Yes, wipe" and confirm threads disappear from `/coach`

- [ ] **Step 4: Final commit if any smoke-test fixes were needed**

If smoke-testing surfaced a bug, fix it with a small commit. Otherwise skip.

- [ ] **Step 5: Done. Hand off to finishing-a-development-branch.**

---

## Spec coverage summary

Mapping spec requirements to tasks (sanity-check before starting):

- **`CoachDriver` interface** → Task 3
- **`StreamChunk` value object** → Task 3
- **`GeminiDriver`** → Task 6
- **`AnthropicDriver`** → Task 7
- **`OllamaDriver` (refactor)** → Task 4
- **`CoachConfig` `provider/model/apiKey/driver`** → Task 8
- **`ChatLoop` consumes StreamChunk, persists tokens** → Task 5
- **`ChatMessage` `input_tokens`/`output_tokens`/`provider`/`model`** → Task 2 (`model` already exists)
- **`AppSetting` `coach_provider`/`coach_model`/`gemini_api_key`/`anthropic_api_key`** → Task 1
- **Migrations 1 + 2** → Tasks 1, 2
- **Migration 3 (wipe)** → Task 13
- **`EstimateCost`** → Task 10
- **Settings page** → Task 12
- **`useTools()` stays driver-agnostic** → Task 8 (kept in CoachConfig)
- **HTTP-mocked tests, no live calls** → All driver tests
- **Provider-switch banner with confirm** → Task 12

All spec requirements are covered.
