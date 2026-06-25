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

        $processLine = function (string $line) use (&$inputTokens, &$outputTokens, &$pendingToolCall, &$pendingToolJson): \Generator {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, 'data:')) {
                return;
            }

            $json = trim(substr($line, 5));
            $event = json_decode($json, true);
            if (! is_array($event)) {
                return;
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
            }
        };

        while (! $stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($newlineAt = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlineAt);
                $buffer = substr($buffer, $newlineAt + 1);
                $stop = false;
                foreach ($processLine($line) as $chunk) {
                    yield $chunk;
                    if ($chunk->type === 'done') {
                        $stop = true;
                    }
                }
                if ($stop) {
                    return;
                }
            }
        }

        // Drain any remaining buffer content without a trailing newline.
        if ($buffer !== '') {
            $stop = false;
            foreach ($processLine($buffer) as $chunk) {
                yield $chunk;
                if ($chunk->type === 'done') {
                    $stop = true;
                }
            }
            if ($stop) {
                return;
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
