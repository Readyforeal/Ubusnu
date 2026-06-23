<?php

use App\Support\Stats;

it('computes the median of an odd-count list', function () {
    expect(Stats::median([1, 5, 3, 9, 2]))->toBe(3.0);
});

it('computes the median of an even-count list as the average of the two middle values', function () {
    expect(Stats::median([1, 2, 3, 4]))->toBe(2.5);
});

it('returns null for an empty median', function () {
    expect(Stats::median([]))->toBeNull();
});

it('computes the mean', function () {
    expect(Stats::mean([2, 4, 6, 8]))->toBe(5.0);
});

it('returns null for empty mean', function () {
    expect(Stats::mean([]))->toBeNull();
});

it('computes the standard deviation', function () {
    $values = [2, 4, 4, 4, 5, 5, 7, 9];
    expect(round(Stats::stdDev($values), 2))->toBe(2.14);
});

it('returns null stddev for fewer than 2 values', function () {
    expect(Stats::stdDev([1]))->toBeNull();
});
