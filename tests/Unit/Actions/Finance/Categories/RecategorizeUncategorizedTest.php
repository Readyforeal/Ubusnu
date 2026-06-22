<?php

use App\Actions\Finance\Categories\RecategorizeUncategorized;
use App\Models\Category;
use App\Models\Transaction;

it('categorizes only rows where category_id is null', function () {
    $coffee = Category::factory()->create(['keywords' => 'starbucks']);
    Transaction::factory()->create(['category_id' => null, 'description' => 'STARBUCKS #1']);
    Transaction::factory()->create(['category_id' => null, 'description' => 'SHELL OIL']);
    $alreadySet = Transaction::factory()->create(['category_id' => $coffee->id, 'description' => 'STARBUCKS #2']);

    $result = (new RecategorizeUncategorized)();

    expect($result)->toBe(['updated' => 1, 'still_uncategorized' => 1]);
    expect(Transaction::where('description', 'STARBUCKS #1')->first()->category_id)->toBe($coffee->id);
    expect(Transaction::where('description', 'SHELL OIL')->first()->category_id)->toBeNull();
});

it('never overrides an already-categorized transaction', function () {
    $coffee = Category::factory()->create(['keywords' => 'starbucks']);
    $other = Category::factory()->create();
    $manuallySet = Transaction::factory()->create([
        'category_id' => $other->id,
        'description' => 'STARBUCKS DOWNTOWN',
    ]);

    (new RecategorizeUncategorized)();

    expect($manuallySet->fresh()->category_id)->toBe($other->id);
});

it('skips soft-deleted transactions', function () {
    $coffee = Category::factory()->create(['keywords' => 'starbucks']);
    $tx = Transaction::factory()->create(['category_id' => null, 'description' => 'STARBUCKS']);
    $tx->delete();

    $result = (new RecategorizeUncategorized)();

    expect($result['updated'])->toBe(0);
});

it('is idempotent — running twice on unchanged data updates 0 the second time', function () {
    Category::factory()->create(['keywords' => 'starbucks']);
    Transaction::factory()->create(['category_id' => null, 'description' => 'STARBUCKS']);

    (new RecategorizeUncategorized)();
    $result = (new RecategorizeUncategorized)();

    expect($result['updated'])->toBe(0);
    expect($result['still_uncategorized'])->toBe(0);
});

it('returns counts when nothing matches', function () {
    Transaction::factory()->count(3)->create(['category_id' => null]);

    $result = (new RecategorizeUncategorized)();

    expect($result)->toBe(['updated' => 0, 'still_uncategorized' => 3]);
});
