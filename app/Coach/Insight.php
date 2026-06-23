<?php

namespace App\Coach;

final class Insight
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $severity,           // 'critical' | 'warning' | 'info' | 'positive'
        public string $headline,
        public string $detail,
        public ?string $suggestedPrompt,
        public string $sourceTool,
        public array $metadata = [],
    ) {}
}
