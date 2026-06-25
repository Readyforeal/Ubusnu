<?php

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('persists all bill attributes', function () {
    $bill = Bill::factory()->create([
        'name' => 'US Bank Mortgage',
        'cadence' => 'monthly',
        'due_day_of_month' => 1,
        'expected_amount_cents' => 230000,
        'match_description' => 'US BANK HOME MTG',
    ]);

    expect($bill->name)->toBe('US Bank Mortgage');
    expect($bill->cadence)->toBe('monthly');
    expect($bill->due_day_of_month)->toBe(1);
    expect($bill->expected_amount_cents)->toBe(230000);
    expect($bill->match_description)->toBe('US BANK HOME MTG');
});

it('casts integer columns to int', function () {
    $bill = Bill::factory()->create([
        'due_day_of_month' => 15,
        'expected_amount_cents' => 50000,
        'sort_order' => 3,
    ]);

    expect($bill->due_day_of_month)->toBeInt();
    expect($bill->expected_amount_cents)->toBeInt();
    expect($bill->sort_order)->toBeInt();
});

it('allows null account, category, and match_description', function () {
    $bill = Bill::factory()->create([
        'account_id' => null,
        'category_id' => null,
        'match_description' => null,
    ]);

    expect($bill->account_id)->toBeNull();
    expect($bill->category_id)->toBeNull();
    expect($bill->match_description)->toBeNull();
});

it('belongsTo an account when set', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->create(['account_id' => $account->id]);

    expect($bill->account->id)->toBe($account->id);
});

it('belongsTo a category when set', function () {
    $category = Category::factory()->create();
    $bill = Bill::factory()->create(['category_id' => $category->id]);

    expect($bill->category->id)->toBe($category->id);
});

it('hasMany transactions', function () {
    $bill = Bill::factory()->create();
    Transaction::factory()->count(2)->create(['bill_id' => $bill->id]);

    expect($bill->transactions)->toHaveCount(2);
});

it('manuallyMarkedPeriods parses the comma-separated list', function () {
    $bill = Bill::factory()->create([
        'manually_marked_paid_periods' => '2026-06, 2026-07,, 2026-08',
    ]);

    expect($bill->manuallyMarkedPeriods())->toBe(['2026-06', '2026-07', '2026-08']);
});

it('manuallyMarkedPeriods returns empty array when null', function () {
    $bill = Bill::factory()->create(['manually_marked_paid_periods' => null]);

    expect($bill->manuallyMarkedPeriods())->toBe([]);
});

it('currentPeriodToken returns Y-m for monthly bills', function () {
    CarbonImmutable::setTestNow('2026-06-15');
    $bill = Bill::factory()->create(['cadence' => 'monthly']);

    expect($bill->currentPeriodToken())->toBe('2026-06');

    CarbonImmutable::setTestNow();
});

it('currentPeriodToken returns Y for annual bills', function () {
    CarbonImmutable::setTestNow('2026-06-15');
    $bill = Bill::factory()->annual()->create();

    expect($bill->currentPeriodToken())->toBe('2026');

    CarbonImmutable::setTestNow();
});

it('addManuallyMarkedPeriod appends without duplicating', function () {
    $bill = Bill::factory()->create(['manually_marked_paid_periods' => '2026-06']);

    $bill->addManuallyMarkedPeriod('2026-07');
    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026-06', '2026-07']);

    $bill->addManuallyMarkedPeriod('2026-06');
    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026-06', '2026-07']);
});

it('removeManuallyMarkedPeriod removes the entry leaving others', function () {
    $bill = Bill::factory()->create(['manually_marked_paid_periods' => '2026-06,2026-07,2026-08']);

    $bill->removeManuallyMarkedPeriod('2026-07');

    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026-06', '2026-08']);
});

it('removeManuallyMarkedPeriod is a no-op when the period is not present', function () {
    $bill = Bill::factory()->create(['manually_marked_paid_periods' => '2026-06']);

    $bill->removeManuallyMarkedPeriod('2026-12');

    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026-06']);
});

it('nextDueDate for monthly bill returns this month if day is in the future', function () {
    CarbonImmutable::setTestNow('2026-06-10');
    $bill = Bill::factory()->create(['cadence' => 'monthly', 'due_day_of_month' => 15]);

    expect($bill->nextDueDate()->toDateString())->toBe('2026-06-15');

    CarbonImmutable::setTestNow();
});

it('nextDueDate for monthly bill returns next month if day has passed', function () {
    CarbonImmutable::setTestNow('2026-06-20');
    $bill = Bill::factory()->create(['cadence' => 'monthly', 'due_day_of_month' => 15]);

    expect($bill->nextDueDate()->toDateString())->toBe('2026-07-15');

    CarbonImmutable::setTestNow();
});

it('nextDueDate for monthly bill returns today if due_day is today', function () {
    CarbonImmutable::setTestNow('2026-06-15');
    $bill = Bill::factory()->create(['cadence' => 'monthly', 'due_day_of_month' => 15]);

    expect($bill->nextDueDate()->toDateString())->toBe('2026-06-15');

    CarbonImmutable::setTestNow();
});

it('nextDueDate clamps to last day of month for day-31 bills in shorter months', function () {
    CarbonImmutable::setTestNow('2026-02-15');
    $bill = Bill::factory()->create(['cadence' => 'monthly', 'due_day_of_month' => 31]);

    expect($bill->nextDueDate()->toDateString())->toBe('2026-02-28');

    CarbonImmutable::setTestNow();
});

it('nextDueDate for annual bill returns this year if date is in the future', function () {
    CarbonImmutable::setTestNow('2026-06-15');
    $bill = Bill::factory()->annual()->create(['due_month_of_year' => 11, 'due_day_of_month' => 1]);

    expect($bill->nextDueDate()->toDateString())->toBe('2026-11-01');

    CarbonImmutable::setTestNow();
});

it('nextDueDate for annual bill returns next year if date has passed', function () {
    CarbonImmutable::setTestNow('2026-12-15');
    $bill = Bill::factory()->annual()->create(['due_month_of_year' => 11, 'due_day_of_month' => 1]);

    expect($bill->nextDueDate()->toDateString())->toBe('2027-11-01');

    CarbonImmutable::setTestNow();
});

it('stores payment_url and username in plain text', function () {
    $bill = Bill::factory()->create([
        'payment_url' => 'https://example.com/login',
        'username' => 'jamie@example.com',
    ]);

    expect($bill->fresh()->payment_url)->toBe('https://example.com/login');
    expect($bill->fresh()->username)->toBe('jamie@example.com');
});

it('encrypts password at rest and decrypts via the model accessor', function () {
    $plain = 'super-secret-12345!';
    $bill = Bill::factory()->create(['password' => $plain]);

    // Accessor decrypts.
    expect($bill->fresh()->password)->toBe($plain);

    // Raw DB value does not contain plaintext.
    $raw = DB::table('bills')->where('id', $bill->id)->value('password');
    expect($raw)->not->toBeNull();
    expect($raw)->not->toContain($plain);
    expect(strlen($raw))->toBeGreaterThan(strlen($plain));
});

it('allows null credentials', function () {
    $bill = Bill::factory()->create([
        'payment_url' => null,
        'username' => null,
        'password' => null,
    ]);

    expect($bill->fresh()->payment_url)->toBeNull();
    expect($bill->fresh()->username)->toBeNull();
    expect($bill->fresh()->password)->toBeNull();
});
