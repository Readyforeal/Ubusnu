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

        $useTools = $this->config->useTools();
        $systemPrompt = $this->loadSystemPrompt($useTools);
        $tools = $useTools ? $this->registry->toOllamaToolsArray() : [];

        $maxRounds = 5;
        $toolCallsRecord = [];
        $assistantBuffer = '';

        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($thread->messages()->get() as $m) {
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        try {
            for ($round = 0; $round < $maxRounds; $round++) {
                $stream = $this->ollama->stream($messages, $tools);

                $roundContent = '';
                $roundToolCalls = [];

                foreach ($stream as $chunk) {
                    $msg = $chunk['message'] ?? [];
                    if (isset($msg['content']) && $msg['content'] !== '') {
                        $roundContent .= $msg['content'];
                        // Also accumulate cumulatively so the catch handler can
                        // persist whatever streamed before an error.
                        $assistantBuffer .= $msg['content'];
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
                    // Defensive: small models often emit tool-call-shaped JSON as
                    // content text instead of using the proper tool_calls field.
                    // Detect and rewrite to a friendly message rather than show raw JSON.
                    if ($this->looksLikeToolCallJson($assistantBuffer)) {
                        $assistantBuffer = $this->toolCallFallbackMessage();
                    }

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
                    $messages[] = ['role' => 'tool', 'content' => (string) $resultJson];
                }
            }

            // Hit max rounds without a clean final assistant message.
            ChatMessage::create([
                'chat_thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $assistantBuffer !== '' ? $assistantBuffer : '(no response — max rounds reached)',
                'tool_calls' => $toolCallsRecord === [] ? null : $toolCallsRecord,
                'model' => $this->config->model(),
            ]);
            $thread->touchLastMessage();
        } catch (\Throwable $e) {
            // Persist whatever streamed before the failure so the user
            // doesn't lose the partial response, and tell the client.
            $errorSuffix = "\n\n_(error: ".mb_substr($e->getMessage(), 0, 200).')_';
            $persistedContent = $assistantBuffer !== ''
                ? $assistantBuffer.$errorSuffix
                : trim($errorSuffix);

            ChatMessage::create([
                'chat_thread_id' => $thread->id,
                'role' => 'assistant',
                'content' => $persistedContent,
                'tool_calls' => $toolCallsRecord === [] ? null : $toolCallsRecord,
                'model' => $this->config->model(),
            ]);
            $thread->touchLastMessage();

            yield ['type' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function loadSystemPrompt(bool $useTools): string
    {
        if (! $useTools) {
            return 'You are a personal finance coach for the Ubusnu app. Reply to the user conversationally in plain English. Do NOT emit JSON. Do NOT try to call any tool — there are no tools available to you this turn. Answer general questions, offer guidance, and ask clarifying questions when the user is vague. Be concise.';
        }

        $path = resource_path('prompts/coach.md');

        return is_file($path) ? (string) file_get_contents($path) : 'You are a helpful financial coach.';
    }

    /**
     * True when the entire content is a single JSON object that looks like a
     * tool-call attempt — e.g. {"name": "...", "parameters": {...}}.
     */
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
        return "I tried to call an internal tool but couldn't produce a real answer. This usually means the model is too small for tool calling. Try a larger model (llama3.1:8b or bigger), or turn off tool calling in /settings/coach for general conversation.";
    }
}
