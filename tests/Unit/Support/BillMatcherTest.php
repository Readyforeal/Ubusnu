<?php

use App\Models\Bill;
use App\Support\BillMatcher;

it('matches a transaction description against a bills match_description (case-insensitive substring)', function () {
    $bill = Bill::factory()->create(['match_description' => 'US BANK HOME MTG']);

    $matcher = new BillMatcher;

    expect($matcher->match('US BANK HOME MTG MTG PYMT 123456'))->toBe($bill->id);
    expect($matcher->match('us bank home mtg mtg pymt 123456'))->toBe($bill->id);
});

it('returns null when zero bills match', function () {
    Bill::factory()->create(['match_description' => 'US BANK HOME MTG']);

    $matcher = new BillMatcher;

    expect($matcher->match('STARBUCKS COFFEE #1234'))->toBeNull();
});

it('returns null when more than one bill matches (ambiguous)', function () {
    Bill::factory()->create(['match_description' => 'PAYMENT']);
    Bill::factory()->create(['match_description' => 'CARD']);

    $matcher = new BillMatcher;

    expect($matcher->match('CARD PAYMENT THANK YOU'))->toBeNull();
});

it('ignores bills with null match_description', function () {
    Bill::factory()->create(['match_description' => null]);
    $other = Bill::factory()->create(['match_description' => 'COMCAST']);

    $matcher = new BillMatcher;

    expect($matcher->match('COMCAST XFINITY 8001234'))->toBe($other->id);
});

it('ignores bills with whitespace-only match_description', function () {
    Bill::factory()->create(['match_description' => '   ']);
    $other = Bill::factory()->create(['match_description' => 'COMCAST']);

    $matcher = new BillMatcher;

    expect($matcher->match('COMCAST'))->toBe($other->id);
});

it('returns null when no bills have match_description set at all', function () {
    Bill::factory()->create(['match_description' => null]);

    $matcher = new BillMatcher;

    expect($matcher->match('ANYTHING'))->toBeNull();
});
