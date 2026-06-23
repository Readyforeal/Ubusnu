<?php

namespace App\Services\Coach;

use App\Exceptions\CoachNotConfiguredException;
use Illuminate\Support\Facades\Http;

class OllamaClient
{
    public function __construct(private readonly CoachConfig $config) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function dryRun(array $messages, array $tools): array
    {
        if (! $this->config->isConfigured()) {
            throw new CoachNotConfiguredException;
        }

        $body = [
            'model' => $this->config->model(),
            'messages' => $messages,
            'stream' => false,
        ];
        if ($tools !== []) {
            $body['tools'] = $tools;
        }

        return Http::timeout(60)
            ->post($this->config->baseUrl().'/api/chat', $body)
            ->throw()
            ->json();
    }

    /**
     * Streaming generator that yields parsed NDJSON chunks from Ollama.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return \Generator<array<string, mixed>>
     */
    public function stream(array $messages, array $tools): \Generator
    {
        if (! $this->config->isConfigured()) {
            throw new CoachNotConfiguredException;
        }

        $body = [
            'model' => $this->config->model(),
            'messages' => $messages,
            'stream' => true,
        ];
        if ($tools !== []) {
            $body['tools'] = $tools;
        }

        $url = $this->config->baseUrl().'/api/chat';

        $response = Http::timeout(120)
            ->withOptions(['stream' => true])
            ->post($url, $body);

        if ($response->status() >= 400) {
            $errorBody = (string) $response->toPsrResponse()->getBody();
            throw new \RuntimeException("Ollama returned HTTP {$response->status()}: ".mb_substr($errorBody, 0, 200));
        }

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        while (! $stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($newlineAt = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlineAt);
                $buffer = substr($buffer, $newlineAt + 1);
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    yield $decoded;
                }
            }
        }
    }
}
