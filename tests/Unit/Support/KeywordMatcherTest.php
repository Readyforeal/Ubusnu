<?php

use App\Models\Category;
use App\Support\KeywordMatcher;

it('matches a description against a single category by keyword (case-insensitive)', function () {
    $cat = Category::factory()->create(['name' => 'Gas', 'keywords' => 'shell, exxon']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('SHELL GAS STATION 123'))->toBe($cat->id);
    expect($matcher->match('shell gas station 123'))->toBe($cat->id);
});

it('matches with word-boundary semantics (positive cases)', function () {
    $cat = Category::factory()->create(['keywords' => 'gas']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('GAS STATION'))->toBe($cat->id);
    expect($matcher->match('Gas-N-Go'))->toBe($cat->id);
    expect($matcher->match('123 GAS'))->toBe($cat->id);
});

it('does not match across word boundaries (negative cases)', function () {
    Category::factory()->create(['keywords' => 'gas']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('LAS VEGAS CASINO'))->toBeNull();
    expect($matcher->match('OREGAS DINER'))->toBeNull();
    expect($matcher->match('MEGASTORE'))->toBeNull();
});

it('returns null when description matches keywords from more than one category (ambiguous)', function () {
    Category::factory()->create(['name' => 'Shopping', 'keywords' => 'target']);
    Category::factory()->create(['name' => 'Groceries', 'keywords' => 'groceries']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('PAYMENT TO TARGET STORE - GROCERIES'))->toBeNull();
});

it('resolves to one category when multiple keywords from THAT category match', function () {
    $coffee = Category::factory()->create(['name' => 'Coffee', 'keywords' => 'starbucks, coffee, latte']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('STARBUCKS COFFEE #1234'))->toBe($coffee->id);
});

it('returns null when no keyword matches', function () {
    Category::factory()->create(['keywords' => 'shell, exxon']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('MYSTERY MERCHANT 9876'))->toBeNull();
});

it('treats keyword metacharacters literally (preg_quote)', function () {
    $cat = Category::factory()->create(['keywords' => 'a.b']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('SHOP a.b LLC'))->toBe($cat->id);
    expect($matcher->match('SHOP aXb LLC'))->toBeNull();
});

it('ignores categories with empty/whitespace-only keywords', function () {
    Category::factory()->create(['name' => 'Empty', 'keywords' => '   ,  , ']);
    $cat = Category::factory()->create(['name' => 'Real', 'keywords' => 'starbucks']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('STARBUCKS COFFEE'))->toBe($cat->id);
});

it('returns null when no categories have keywords', function () {
    Category::factory()->create(['keywords' => null]);

    $matcher = new KeywordMatcher;

    expect($matcher->match('ANYTHING'))->toBeNull();
});
