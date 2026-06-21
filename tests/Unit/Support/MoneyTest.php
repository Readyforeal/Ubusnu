<?php

use App\Support\Money;

it('formats positive cents as USD', function () {
    expect(Money::format(123456))->toBe('$1,234.56');
});

it('formats zero', function () {
    expect(Money::format(0))->toBe('$0.00');
});

it('formats negative cents with leading minus', function () {
    expect(Money::format(-150000))->toBe('-$1,500.00');
});

it('formats sub-dollar cents', function () {
    expect(Money::format(7))->toBe('$0.07');
});

it('converts dollar string to cents', function () {
    expect(Money::toCents('1234.56'))->toBe(123456);
    expect(Money::toCents('-1500'))->toBe(-150000);
    expect(Money::toCents('0'))->toBe(0);
    expect(Money::toCents('0.07'))->toBe(7);
});

it('strips currency formatting when parsing', function () {
    expect(Money::toCents('$1,234.56'))->toBe(123456);
});

it('formats negative sub-dollar amounts', function () {
    expect(Money::format(-7))->toBe('-$0.07');
    expect(Money::format(-99))->toBe('-$0.99');
});

it('returns 0 for unparseable input to toCents', function () {
    expect(Money::toCents(''))->toBe(0);
    expect(Money::toCents('-'))->toBe(0);
    expect(Money::toCents('abc'))->toBe(0);
});
