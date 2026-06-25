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

        // Process any remaining buffer content that had no trailing newline.
        $line = trim($buffer);
        if ($line !== '') {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
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
                }
            }
        }
    }
}
