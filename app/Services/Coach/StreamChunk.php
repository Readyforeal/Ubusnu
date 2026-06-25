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
