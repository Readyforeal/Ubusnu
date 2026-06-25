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

                foreach ($this->chunksFromEvent($decoded) as $chunk) {
                    yield $chunk;
                }

                if (($decoded['candidates'][0]['finishReason'] ?? null) !== null) {
                    yield StreamChunk::done();

                    return;
                }
            }
        }

        // Drain any remaining buffer content that didn't have a trailing newline.
        $line = trim($buffer);
        if ($line !== '' && str_starts_with($line, 'data:')) {
            $json = trim(substr($line, 5));
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                foreach ($this->chunksFromEvent($decoded) as $chunk) {
                    yield $chunk;
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
     * @param  array<string, mixed>  $decoded
     * @return \Generator<StreamChunk>
     */
    private function chunksFromEvent(array $decoded): \Generator
    {
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
