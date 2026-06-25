<?php

use App\Actions\Coach\EstimateCost;

it('returns zero for unknown provider/model', function () {
    expect((new EstimateCost)('unknown', 'unknown', 1000, 1000))->toBe(0);
    expect((new EstimateCost)('gemini', 'made-up', 1000, 1000))->toBe(0);
});

it('estimates Gemini Flash cost', function () {
    // 1M input × $0.30 = $0.30 = 30 cents; 1M output × $2.50 = $2.50 = 250 cents
    expect((new EstimateCost)('gemini', 'gemini-2.5-flash', 1_000_000, 1_000_000))->toBe(280);
});

it('estimates Sonnet cost', function () {
    // 1M input × $3.00 = 300; 1M output × $15.00 = 1500
    expect((new EstimateCost)('anthropic', 'claude-sonnet-4-6', 1_000_000, 1_000_000))->toBe(1800);
});

it('rounds tiny token counts to zero cents', function () {
    expect((new EstimateCost)('gemini', 'gemini-2.5-flash', 100, 50))->toBe(0);
});

it('estimates a realistic chat turn cost', function () {
    // 5,000 input tokens + 1,500 output for Flash
    // input: 5,000 × 30 / 1M = 0.15 cents
    // output: 1,500 × 250 / 1M = 0.375 cents
    // total ≈ 0.525 cents, rounded to 1 cent
    expect((new EstimateCost)('gemini', 'gemini-2.5-flash', 5_000, 1_500))->toBe(1);
});

it('returns zero for ollama (no pricing)', function () {
    expect((new EstimateCost)('ollama', 'llama3.1:8b', 50_000, 10_000))->toBe(0);
});
