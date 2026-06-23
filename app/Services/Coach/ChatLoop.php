<?php

namespace App\Services\Coach;

use App\Models\ChatMessage;
use App\Models\ChatThread;

class ChatLoop
{
    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly ToolRegistry $registry,
        private readonly CoachConfig $config,
    ) {}

    /**
     * Run one full chat turn (user message → potentially multiple tool calls → final assistant message).
     *
     * @return \Generator<array{type: string, content?: string, tool_name?: string, summary?: string}>
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

        $systemPrompt = $this->loadSystemPrompt();
        $maxRounds = 5;
        $toolCallsRecord = [];
        $assistantBuffer = '';

        for ($round = 0; $round < $maxRounds; $round++) {
            $messages = $this->buildMessages($thread, $systemPrompt);
            $stream = $this->ollama->stream($messages, $this->registry->toOllamaToolsArray());

            $roundContent = '';
            $roundToolCalls = [];

            foreach ($stream as $chunk) {
                $msg = $chunk['message'] ?? [];
                if (isset($msg['content']) && $msg['content'] !== '') {
                    $roundContent .= $msg['content'];
                    yield ['type' => 'token', 'content' => $msg['content']];
                }
                if (! empty($msg['tool_calls'])) {
                    foreach ($msg['tool_calls'] as $tc) {
                        $roundToolCalls[] = $tc;
                    }
                }
                if ($chunk['done'] ?? false) {
                    break;
                }
            }

            if ($roundToolCalls === []) {
                $assistantBuffer .= $roundContent;
                ChatMessage::create([
                    'chat_thread_id' => $thread->id,
                    'role' => 'assistant',
                    'content' => $assistantBuffer,
                    'tool_calls' => $toolCallsRecord === [] ? null : $toolCallsRecord,
                    'model' => $this->config->model(),
                ]);
                $thread->touchLastMessage();

                return;
            }

            foreach ($roundToolCalls as $tc) {
                $name = $tc['function']['name'] ?? '';
                $args = $tc['function']['arguments'] ?? [];
                $tool = $this->registry->find($name);

                if (! $tool) {
                    $resultJson = json_encode(['error' => "unknown tool: $name"]);
                } elseif ($tool->kind === 'write') {
                    $resultJson = json_encode(['error' => 'write tools are not enabled in v1']);
                } else {
                    try {
                        $result = ($tool->handler)(is_array($args) ? $args : []);
                        $resultJson = json_encode($result);
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
            }
        }

        ChatMessage::create([
            'chat_thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $assistantBuffer !== '' ? $assistantBuffer : '(no response — max rounds reached)',
            'tool_calls' => $toolCallsRecord === [] ? null : $toolCallsRecord,
            'model' => $this->config->model(),
        ]);
        $thread->touchLastMessage();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(ChatThread $thread, string $systemPrompt): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($thread->messages()->get() as $m) {
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        return $messages;
    }

    private function loadSystemPrompt(): string
    {
        $path = resource_path('prompts/coach.md');

        return is_file($path) ? (string) file_get_contents($path) : 'You are a helpful financial coach.';
    }
}
