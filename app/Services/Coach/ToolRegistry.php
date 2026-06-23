<?php

namespace App\Services\Coach;

class ToolRegistry
{
    /** @var array<string, CoachTool> */
    private array $tools = [];

    public function register(CoachTool $tool): void
    {
        $this->tools[$tool->name] = $tool;
    }

    /**
     * @return array<int, CoachTool>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function find(string $name): ?CoachTool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toOllamaToolsArray(): array
    {
        return array_map(fn (CoachTool $t) => $t->toOllamaToolSchema(), $this->all());
    }
}
