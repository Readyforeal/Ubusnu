<?php

use App\Models\Bucket;
use App\Models\Category;

it('parses comma-separated keywords into a normalized list', function () {
    $c = Category::factory()->make([
        'keywords' => '  Transfer , TFR,e-Transfer ,, ',
    ]);

    expect($c->keywordList())->toBe(['transfer', 'tfr', 'e-transfer']);
});

it('returns empty list when no keywords set', function () {
    expect(Category::factory()->make(['keywords' => null])->keywordList())->toBe([]);
});

it('persists kind values (spending/income/transfer)', function () {
    expect(Category::factory()->create()->kind)->toBe('spending');
    expect(Category::factory()->incomeKind()->create()->kind)->toBe('income');
    expect(Category::factory()->transferKind()->create()->kind)->toBe('transfer');
});

it('belongs to a bucket when one is assigned', function () {
    $bucket = Bucket::factory()->create();
    $category = Category::factory()->inBucket($bucket)->create();

    expect($category->bucket->id)->toBe($bucket->id);
});

it('has a null bucket relation when bucket_id is null', function () {
    $category = Category::factory()->create();

    expect($category->bucket)->toBeNull();
});
