<?php

namespace App\Actions\Coach;

class EstimateCost
{
    /**
     * Cents per million tokens.
     *
     * @var array<string, array<string, array{input: int, output: int}>>
     */
    private const PRICING = [
        'gemini' => [
            'gemini-2.5-flash' => ['input' => 30, 'output' => 250],
            'gemini-2.5-pro' => ['input' => 125, 'output' => 1000],
        ],
        'anthropic' => [
            'claude-haiku-4-5-20251001' => ['input' => 100, 'output' => 500],
            'claude-sonnet-4-6' => ['input' => 300, 'output' => 1500],
            'claude-opus-4-7' => ['input' => 1500, 'output' => 7500],
        ],
    ];

    /**
     * Returns the dollar cost in cents.
     */
    public function __invoke(string $provider, string $model, int $inputTokens, int $outputTokens): int
    {
        $rates = self::PRICING[$provider][$model] ?? null;
        if (! $rates) {
            return 0;
        }

        $inputCents = ($inputTokens * $rates['input']) / 1_000_000;
        $outputCents = ($outputTokens * $rates['output']) / 1_000_000;

        return (int) round($inputCents + $outputCents);
    }
}
