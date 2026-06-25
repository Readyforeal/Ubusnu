<?php

use App\Actions\Coach\EstimateCost;
use App\Actions\Coach\SummarizeCoachUsage;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use Carbon\CarbonImmutable;

it('returns zero totals when no messages exist', function () {
    $result = (new SummarizeCoachUsage(new EstimateCost))();

    expect($result['today']['input'])->toBe(0);
    expect($result['today']['cents'])->toBe(0);
    expect($result['month']['input'])->toBe(0);
});

it('sums tokens and estimates cost for today', function () {
    CarbonImmutable::setTestNow('2026-06-25 12:00:00');

    $thread = ChatThread::factory()->create();
    ChatMessage::create([
        'chat_thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => 'a',
        'provider' => 'gemini',
        'model' => 'gemini-2.5-flash',
        'input_tokens' => 1_000_000,
        'output_tokens' => 1_000_000,
        'created_at' => CarbonImmutable::now(),
    ]);

    $result = (new SummarizeCoachUsage(new EstimateCost))();
    expect($result['today']['input'])->toBe(1_000_000);
    expect($result['today']['output'])->toBe(1_000_000);
    expect($result['today']['cents'])->toBe(280);

    CarbonImmutable::setTestNow();
});

it('excludes messages without a provider', function () {
    $thread = ChatThread::factory()->create();
    ChatMessage::create([
        'chat_thread_id' => $thread->id,
        'role' => 'user',
        'content' => 'hi',
    ]);

    $result = (new SummarizeCoachUsage(new EstimateCost))();
    expect($result['today']['input'])->toBe(0);
});

it('aggregates across multiple providers and models', function () {
    CarbonImmutable::setTestNow('2026-06-25 09:00:00');

    $thread = ChatThread::factory()->create();
    ChatMessage::create([
        'chat_thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => 'a',
        'provider' => 'gemini',
        'model' => 'gemini-2.5-flash',
        'input_tokens' => 500_000,
        'output_tokens' => 100_000,
        'created_at' => CarbonImmutable::now(),
    ]);
    ChatMessage::create([
        'chat_thread_id' => $thread->id,
        'role' => 'assistant',
        'content' => 'b',
        'provider' => 'anthropic',
        'model' => 'claude-sonnet-4-6',
        'input_tokens' => 50_000,
        'output_tokens' => 10_000,
        'created_at' => CarbonImmutable::now(),
    ]);

    $result = (new SummarizeCoachUsage(new EstimateCost))();
    expect($result['today']['input'])->toBe(550_000);
    expect($result['today']['output'])->toBe(110_000);
    // Flash: 500_000×30/1M + 100_000×250/1M = 15 + 25 = 40 cents
    // Sonnet: 50_000×300/1M + 10_000×1500/1M = 15 + 15 = 30 cents
    expect($result['today']['cents'])->toBe(70);

    CarbonImmutable::setTestNow();
});
