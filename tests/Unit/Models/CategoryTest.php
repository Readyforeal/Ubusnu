<?php

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

it('exposes excluded_from_totals as a boolean', function () {
    $c = Category::factory()->excludedFromTotals()->make();

    expect($c->excluded_from_totals)->toBeTrue();
});
