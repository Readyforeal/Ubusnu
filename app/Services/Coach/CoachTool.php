<?php

namespace App\Services\Coach;

use Closure;

final class CoachTool
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
        public string $kind,
        public bool $requiresConfirmation,
        public Closure $handler,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toOllamaToolSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ],
        ];
    }
}
