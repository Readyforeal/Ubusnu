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
                    $toolCallId = $tc['id'] ?? null;
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
                    $messages[] = ['role' => 'tool', 'content' => (string) $resultJson, 'tool_use_id' => $toolCallId];
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
