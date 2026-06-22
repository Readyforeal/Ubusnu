# Pay-Timing Optimizer & Calendar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a deterministic balance-projection engine, a per-bill pay-day recommender, and a clean monthly calendar that surfaces both due dates and recommended pay dates.

**Architecture:** Five pure-function forecast actions under `app/Actions/Finance/Forecast/` compose into a daily projected-balance curve. `RecommendPayDates` walks that curve to pick an earliest-safe pay day per bill. A new full-page Livewire SFC at `/calendar` runs the chain on mount. New `IncomeSource` model mirrors `Bill` and feeds income into the projection.

**Tech Stack:** Laravel 13, Livewire 4 SFC, MaryUI, daisyUI 5, Pest 4, SQLite, Carbon.

**Reference spec:** `docs/superpowers/specs/2026-06-22-pay-timing-optimizer-design.md`

---

## File Structure

**Database**
- Create: `database/migrations/{ts}_create_income_sources_table.php`
- Create: `database/migrations/{ts}_add_minimum_balance_to_accounts.php`
- Create: `database/migrations/{ts}_add_forecast_lookback_to_app_settings.php`
- Create: `database/migrations/{ts}_add_income_source_id_to_transactions.php`

**Models & factories**
- Create: `app/Models/IncomeSource.php`
- Create: `database/factories/IncomeSourceFactory.php`
- Modify: `app/Models/Account.php` (add `minimum_balance_cents` to Fillable + cast)
- Modify: `app/Models/AppSetting.php` (add `forecast_lookback_weeks`)
- Modify: `app/Models/Transaction.php` (add `income_source_id` to Fillable + relation)

**Forecast actions**
- Create: `app/Actions/Finance/Forecast/ProjectIncomeDeposits.php`
- Create: `app/Actions/Finance/Forecast/ProjectBillCharges.php`
- Create: `app/Actions/Finance/Forecast/ForecastVariableSpend.php`
- Create: `app/Actions/Finance/Forecast/ComputeProjectedBalance.php`
- Create: `app/Actions/Finance/Forecast/RecommendPayDates.php`

**Income actions & matcher**
- Create: `app/Actions/Finance/Income/CreateIncomeSource.php`
- Create: `app/Actions/Finance/Income/UpdateIncomeSource.php`
- Create: `app/Actions/Finance/Income/DeleteIncomeSource.php`
- Create: `app/Actions/Finance/Income/AdvanceIncomeAnchor.php`
- Create: `app/Support/IncomeMatcher.php`
- Modify: `app/Actions/Finance/Imports/ParseCsvForPreview.php` (run IncomeMatcher alongside BillMatcher)
- Modify: `app/Actions/Finance/Imports/ImportTransactions.php` (persist `income_source_id`; call `AdvanceIncomeAnchor` for matched sources)

**Views**
- Create: `resources/views/pages/income/⚡index.blade.php`
- Create: `resources/views/pages/income/⚡form.blade.php`
- Create: `resources/views/pages/income/⚡show.blade.php`
- Create: `resources/views/pages/calendar/⚡index.blade.php`
- Modify: `resources/views/pages/accounts/⚡form.blade.php` (add minimum balance input)
- Modify: `resources/views/layouts/app/sidebar.blade.php` (add Calendar + Income links)

**Routes**
- Modify: `routes/web.php`

**Tests**
- Create: `tests/Unit/Actions/Finance/Forecast/ProjectIncomeDepositsTest.php`
- Create: `tests/Unit/Actions/Finance/Forecast/ProjectBillChargesTest.php`
- Create: `tests/Unit/Actions/Finance/Forecast/ForecastVariableSpendTest.php`
- Create: `tests/Unit/Actions/Finance/Forecast/ComputeProjectedBalanceTest.php`
- Create: `tests/Unit/Actions/Finance/Forecast/RecommendPayDatesTest.php`
- Create: `tests/Unit/Actions/Finance/Income/CreateIncomeSourceTest.php`
- Create: `tests/Unit/Actions/Finance/Income/UpdateIncomeSourceTest.php`
- Create: `tests/Unit/Actions/Finance/Income/DeleteIncomeSourceTest.php`
- Create: `tests/Unit/Actions/Finance/Income/AdvanceIncomeAnchorTest.php`
- Create: `tests/Feature/Pages/Calendar/IndexTest.php`
- Create: `tests/Feature/Pages/Income/IndexTest.php`
- Create: `tests/Feature/Pages/Income/FormTest.php`
- Create: `tests/Feature/Pages/Income/ShowTest.php`
- Modify: `tests/Feature/Pages/Imports/WizardTest.php` (add income-match test case)

---

## Task 1: Schema migrations

**Files:**
- Create: `database/migrations/{ts}_create_income_sources_table.php`
- Create: `database/migrations/{ts}_add_minimum_balance_to_accounts.php`
- Create: `database/migrations/{ts}_add_forecast_lookback_to_app_settings.php`
- Create: `database/migrations/{ts}_add_income_source_id_to_transactions.php`

- [ ] **Step 1: Generate migrations**

```bash
php artisan make:migration create_income_sources_table --no-interaction
php artisan make:migration add_minimum_balance_to_accounts --no-interaction --table=accounts
php artisan make:migration add_forecast_lookback_to_app_settings --no-interaction --table=app_settings
php artisan make:migration add_income_source_id_to_transactions --no-interaction --table=transactions
```

- [ ] **Step 2: Fill `create_income_sources_table`**

Replace the generated file body:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('income_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('cadence', 16); // weekly | biweekly | semi_monthly | monthly
            $table->date('next_expected_on');
            $table->smallInteger('secondary_day_of_month')->nullable();
            $table->bigInteger('expected_amount_cents');
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_description', 255)->nullable();
            $table->string('color', 16)->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('income_sources');
    }
};
```

- [ ] **Step 3: Fill `add_minimum_balance_to_accounts`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->bigInteger('minimum_balance_cents')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('minimum_balance_cents');
        });
    }
};
```

- [ ] **Step 4: Fill `add_forecast_lookback_to_app_settings`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->smallInteger('forecast_lookback_weeks')->default(12);
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('forecast_lookback_weeks');
        });
    }
};
```

- [ ] **Step 5: Fill `add_income_source_id_to_transactions`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('income_source_id')->nullable()->after('bill_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('income_source_id');
        });
    }
};
```

- [ ] **Step 6: Run migrations**

```bash
php artisan migrate
```

Expected output: 4 migrations run, no errors.

- [ ] **Step 7: Commit**

```bash
git add database/migrations
git commit -m "Add schema for income sources, account floor, forecast lookback"
```

---

## Task 2: IncomeSource model + factory

**Files:**
- Create: `app/Models/IncomeSource.php`
- Create: `database/factories/IncomeSourceFactory.php`
- Modify: `app/Models/Account.php`
- Modify: `app/Models/AppSetting.php`
- Modify: `app/Models/Transaction.php`

- [ ] **Step 1: Generate model + factory**

```bash
php artisan make:model IncomeSource --factory --no-interaction
```

- [ ] **Step 2: Replace `app/Models/IncomeSource.php`**

```php
<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IncomeSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'cadence', 'next_expected_on', 'secondary_day_of_month',
    'expected_amount_cents', 'account_id', 'category_id',
    'match_description', 'color', 'notes', 'sort_order',
])]
class IncomeSource extends Model
{
    /** @use HasFactory<IncomeSourceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'next_expected_on' => 'immutable_date',
            'secondary_day_of_month' => 'integer',
            'expected_amount_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function advanceAnchor(): CarbonImmutable
    {
        $current = $this->next_expected_on;

        return match ($this->cadence) {
            'weekly' => $current->addWeek(),
            'biweekly' => $current->addWeeks(2),
            'monthly' => $this->safeDay($current->addMonth(), $current->day),
            'semi_monthly' => $this->advanceSemiMonthly($current),
            default => $current->addMonth(),
        };
    }

    private function advanceSemiMonthly(CarbonImmutable $current): CarbonImmutable
    {
        $secondary = (int) ($this->secondary_day_of_month ?? 15);
        $primary = $current->day;

        // If we're currently on the primary day, advance to secondary day in same month.
        // If we're on secondary day, advance to primary day of next month.
        if ($current->day < $secondary) {
            return $this->safeDay($current, $secondary);
        }

        return $this->safeDay($current->addMonth()->startOfMonth(), $primary);
    }

    private function safeDay(CarbonImmutable $month, int $day): CarbonImmutable
    {
        $daysInMonth = $month->daysInMonth;

        return $month->setDay(min($day, $daysInMonth));
    }
}
```

- [ ] **Step 3: Replace `database/factories/IncomeSourceFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\IncomeSource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeSource>
 */
class IncomeSourceFactory extends Factory
{
    protected $model = IncomeSource::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'cadence' => 'monthly',
            'next_expected_on' => CarbonImmutable::today()->addDays(7),
            'secondary_day_of_month' => null,
            'expected_amount_cents' => $this->faker->numberBetween(100000, 500000),
            'account_id' => null,
            'category_id' => null,
            'match_description' => null,
            'color' => null,
            'notes' => null,
            'sort_order' => 0,
        ];
    }

    public function biweekly(): static
    {
        return $this->state(['cadence' => 'biweekly']);
    }

    public function weekly(): static
    {
        return $this->state(['cadence' => 'weekly']);
    }

    public function semiMonthly(): static
    {
        return $this->state([
            'cadence' => 'semi_monthly',
            'secondary_day_of_month' => 15,
        ]);
    }
}
```

- [ ] **Step 4: Update `app/Models/Account.php`**

Add `minimum_balance_cents` to the `Fillable` array and the `casts()` method. The existing `#[Fillable(...)]` attribute lists the fields — append `'minimum_balance_cents'`, and add `'minimum_balance_cents' => 'integer'` inside `casts()`.

- [ ] **Step 5: Update `app/Models/AppSetting.php`**

Add `'forecast_lookback_weeks'` to the `Fillable` array, `'forecast_lookback_weeks' => 'integer'` to casts, and update the `current()` method's insert to set `'forecast_lookback_weeks' => 12`.

- [ ] **Step 6: Update `app/Models/Transaction.php`**

Add `'income_source_id'` to Fillable, add this relation method:

```php
public function incomeSource(): BelongsTo
{
    return $this->belongsTo(IncomeSource::class);
}
```

- [ ] **Step 7: Run existing tests**

```bash
php artisan test --compact
```

Expected: all currently-passing tests still pass (no regressions from model additions).

- [ ] **Step 8: Commit**

```bash
git add app/Models database/factories/IncomeSourceFactory.php
git commit -m "Add IncomeSource model + factory; wire minimum_balance and forecast_lookback"
```

---

## Task 3: ProjectIncomeDeposits action

**Files:**
- Create: `app/Actions/Finance/Forecast/ProjectIncomeDeposits.php`
- Test: `tests/Unit/Actions/Finance/Forecast/ProjectIncomeDepositsTest.php`

- [ ] **Step 1: Generate test file**

```bash
php artisan make:test --pest --unit Actions/Finance/Forecast/ProjectIncomeDepositsTest --no-interaction
```

- [ ] **Step 2: Write the failing test**

Replace `tests/Unit/Actions/Finance/Forecast/ProjectIncomeDepositsTest.php`:

```php
<?php

use App\Actions\Finance\Forecast\ProjectIncomeDeposits;
use App\Models\Account;
use App\Models\IncomeSource;
use Carbon\CarbonImmutable;

it('emits one deposit per occurrence within range for monthly cadence', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'next_expected_on' => '2026-07-01',
        'expected_amount_cents' => 250000,
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-09-30'));

    expect($result)->toHaveCount(3);
    expect($result[0])->toMatchArray(['date' => '2026-07-01', 'account_id' => $account->id, 'cents' => 250000, 'income_source_id' => $source->id]);
    expect($result[1]['date'])->toBe('2026-08-01');
    expect($result[2]['date'])->toBe('2026-09-01');
});

it('emits biweekly deposits', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->biweekly()->create([
        'account_id' => $account->id,
        'next_expected_on' => '2026-07-03',
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-03'), CarbonImmutable::parse('2026-08-14'));

    expect(array_column($result, 'date'))->toBe(['2026-07-03', '2026-07-17', '2026-07-31', '2026-08-14']);
});

it('emits weekly deposits', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->weekly()->create([
        'account_id' => $account->id,
        'next_expected_on' => '2026-07-03',
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-03'), CarbonImmutable::parse('2026-07-24'));

    expect(array_column($result, 'date'))->toBe(['2026-07-03', '2026-07-10', '2026-07-17', '2026-07-24']);
});

it('emits semi-monthly deposits on primary and secondary days', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->semiMonthly()->create([
        'account_id' => $account->id,
        'next_expected_on' => '2026-07-01',
        'secondary_day_of_month' => 15,
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-08-15'));

    expect(array_column($result, 'date'))->toBe(['2026-07-01', '2026-07-15', '2026-08-01', '2026-08-15']);
});

it('clamps monthly day to last-of-month for short months', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'next_expected_on' => '2026-01-31',
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-01-31'), CarbonImmutable::parse('2026-03-31'));

    expect(array_column($result, 'date'))->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

it('returns empty when anchor is past range end', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->create([
        'account_id' => $account->id,
        'next_expected_on' => '2027-01-01',
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-09-30'));

    expect($result)->toBe([]);
});
```

- [ ] **Step 3: Run test, expect failure**

```bash
php artisan test --compact --filter=ProjectIncomeDepositsTest
```

Expected: FAIL — class `App\Actions\Finance\Forecast\ProjectIncomeDeposits` not found.

- [ ] **Step 4: Implement action**

Create `app/Actions/Finance/Forecast/ProjectIncomeDeposits.php`:

```php
<?php

namespace App\Actions\Finance\Forecast;

use App\Models\IncomeSource;
use Carbon\CarbonImmutable;

class ProjectIncomeDeposits
{
    /**
     * @param  array<int, IncomeSource>  $sources
     * @return array<int, array{date: string, account_id: int, cents: int, income_source_id: int}>
     */
    public function __invoke(array $sources, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $out = [];

        foreach ($sources as $source) {
            $cursor = $source->next_expected_on instanceof CarbonImmutable
                ? $source->next_expected_on
                : CarbonImmutable::parse($source->next_expected_on);

            $primaryDay = $cursor->day;
            $secondaryDay = (int) ($source->secondary_day_of_month ?? 0);

            while ($cursor->lte($end)) {
                if ($cursor->gte($start)) {
                    $out[] = [
                        'date' => $cursor->toDateString(),
                        'account_id' => (int) $source->account_id,
                        'cents' => (int) $source->expected_amount_cents,
                        'income_source_id' => (int) $source->id,
                    ];
                }
                $cursor = $this->advance($cursor, $source->cadence, $primaryDay, $secondaryDay);
            }
        }

        usort($out, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $out;
    }

    private function advance(CarbonImmutable $cursor, string $cadence, int $primaryDay, int $secondaryDay): CarbonImmutable
    {
        return match ($cadence) {
            'weekly' => $cursor->addWeek(),
            'biweekly' => $cursor->addWeeks(2),
            'semi_monthly' => $this->advanceSemiMonthly($cursor, $primaryDay, $secondaryDay),
            default => $this->safeDay($cursor->addMonth()->startOfMonth(), $primaryDay),
        };
    }

    private function advanceSemiMonthly(CarbonImmutable $cursor, int $primaryDay, int $secondaryDay): CarbonImmutable
    {
        if ($cursor->day === $primaryDay) {
            return $this->safeDay($cursor, $secondaryDay);
        }

        return $this->safeDay($cursor->addMonth()->startOfMonth(), $primaryDay);
    }

    private function safeDay(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->setDay(min($day, $month->daysInMonth));
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=ProjectIncomeDepositsTest
```

Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Forecast/ProjectIncomeDeposits.php tests/Unit/Actions/Finance/Forecast/ProjectIncomeDepositsTest.php
git commit -m "Add ProjectIncomeDeposits forecast action"
```

---

## Task 4: ProjectBillCharges action

**Files:**
- Create: `app/Actions/Finance/Forecast/ProjectBillCharges.php`
- Test: `tests/Unit/Actions/Finance/Forecast/ProjectBillChargesTest.php`

- [ ] **Step 1: Generate test file**

```bash
php artisan make:test --pest --unit Actions/Finance/Forecast/ProjectBillChargesTest --no-interaction
```

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Actions\Finance\Forecast\ProjectBillCharges;
use App\Models\Account;
use App\Models\Bill;
use Carbon\CarbonImmutable;

it('emits monthly bills on their due day across range', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'due_day_of_month' => 15,
        'expected_amount_cents' => 150000,
    ]);

    $result = (new ProjectBillCharges)([$bill], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-09-30'));

    expect(array_column($result, 'date'))->toBe(['2026-07-15', '2026-08-15', '2026-09-15']);
    expect($result[0])->toMatchArray(['cents' => 150000, 'account_id' => $account->id, 'bill_id' => $bill->id]);
});

it('emits annual bills only in their month', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->annual()->create([
        'account_id' => $account->id,
        'due_day_of_month' => 10,
        'due_month_of_year' => 8,
    ]);

    $result = (new ProjectBillCharges)([$bill], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2027-09-30'));

    expect(array_column($result, 'date'))->toBe(['2026-08-10', '2027-08-10']);
});

it('clamps day-of-month for short months', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'due_day_of_month' => 31,
    ]);

    $result = (new ProjectBillCharges)([$bill], CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-04-30'));

    expect(array_column($result, 'date'))->toBe(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30']);
});

it('includes all bills regardless of paid status', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'due_day_of_month' => 5,
        'manually_marked_paid_periods' => CarbonImmutable::today()->format('Y-m'),
    ]);

    $result = (new ProjectBillCharges)([$bill], CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfMonth());

    expect($result)->toHaveCount(1); // paid status does NOT remove the bill from the projection
});
```

- [ ] **Step 3: Run test, expect failure**

```bash
php artisan test --compact --filter=ProjectBillChargesTest
```

Expected: FAIL — class not found.

- [ ] **Step 4: Implement action**

Create `app/Actions/Finance/Forecast/ProjectBillCharges.php`:

```php
<?php

namespace App\Actions\Finance\Forecast;

use App\Models\Bill;
use Carbon\CarbonImmutable;

class ProjectBillCharges
{
    /**
     * @param  array<int, Bill>  $bills
     * @return array<int, array{date: string, account_id: int, cents: int, bill_id: int}>
     */
    public function __invoke(array $bills, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $out = [];

        foreach ($bills as $bill) {
            if ($bill->account_id === null) {
                continue;
            }

            if ($bill->cadence === 'annual') {
                $this->emitAnnual($bill, $start, $end, $out);
            } else {
                $this->emitMonthly($bill, $start, $end, $out);
            }
        }

        usort($out, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $out;
    }

    /**
     * @param  array<int, array{date: string, account_id: int, cents: int, bill_id: int}>  $out
     */
    private function emitMonthly(Bill $bill, CarbonImmutable $start, CarbonImmutable $end, array &$out): void
    {
        $cursor = $this->safeDay($start->startOfMonth(), (int) $bill->due_day_of_month);

        while ($cursor->lte($end)) {
            if ($cursor->gte($start)) {
                $out[] = $this->row($bill, $cursor);
            }
            $cursor = $this->safeDay($cursor->addMonth()->startOfMonth(), (int) $bill->due_day_of_month);
        }
    }

    /**
     * @param  array<int, array{date: string, account_id: int, cents: int, bill_id: int}>  $out
     */
    private function emitAnnual(Bill $bill, CarbonImmutable $start, CarbonImmutable $end, array &$out): void
    {
        $month = (int) ($bill->due_month_of_year ?? 1);
        $day = (int) $bill->due_day_of_month;

        $year = $start->year;
        while (true) {
            $candidate = $this->safeDay(CarbonImmutable::create($year, $month, 1), $day);
            if ($candidate->gt($end)) {
                break;
            }
            if ($candidate->gte($start)) {
                $out[] = $this->row($bill, $candidate);
            }
            $year++;
        }
    }

    /**
     * @return array{date: string, account_id: int, cents: int, bill_id: int}
     */
    private function row(Bill $bill, CarbonImmutable $date): array
    {
        return [
            'date' => $date->toDateString(),
            'account_id' => (int) $bill->account_id,
            'cents' => (int) $bill->expected_amount_cents,
            'bill_id' => (int) $bill->id,
        ];
    }

    private function safeDay(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->setDay(min($day, $month->daysInMonth));
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=ProjectBillChargesTest
```

Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Forecast/ProjectBillCharges.php tests/Unit/Actions/Finance/Forecast/ProjectBillChargesTest.php
git commit -m "Add ProjectBillCharges forecast action"
```

---

## Task 5: ForecastVariableSpend action

**Files:**
- Create: `app/Actions/Finance/Forecast/ForecastVariableSpend.php`
- Test: `tests/Unit/Actions/Finance/Forecast/ForecastVariableSpendTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Forecast/ForecastVariableSpendTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Forecast\ForecastVariableSpend;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    AppSetting::current()->update(['forecast_lookback_weeks' => 12]);
});

it('forecasts daily spend as weekly median divided by seven', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    // 12 weeks of $70 weekly spend → median weekly = 70, per-day = 10
    for ($w = 0; $w < 12; $w++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'occurred_on' => CarbonImmutable::today()->subWeeks($w + 1)->toDateString(),
            'amount_cents' => -7000,
        ]);
    }

    $start = CarbonImmutable::today();
    $end = $start->addDays(2);
    $result = (new ForecastVariableSpend)($start, $end);

    $byDate = [];
    foreach ($result as $row) {
        $byDate[$row['date']] = ($byDate[$row['date']] ?? 0) + $row['cents'];
    }

    expect($byDate[$start->toDateString()])->toBe(1000);
    expect($byDate[$start->addDay()->toDateString()])->toBe(1000);
    expect($byDate[$end->toDateString()])->toBe(1000);
});

it('uses median not mean (outliers do not dominate)', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    // 11 weeks of $70, 1 week of $5000. Mean is high; median should still be 70.
    for ($w = 0; $w < 11; $w++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'occurred_on' => CarbonImmutable::today()->subWeeks($w + 1)->toDateString(),
            'amount_cents' => -7000,
        ]);
    }
    Transaction::factory()->create([
        'account_id' => $account->id,
        'category_id' => $cat->id,
        'occurred_on' => CarbonImmutable::today()->subWeek()->toDateString(),
        'amount_cents' => -500000,
    ]);

    $start = CarbonImmutable::today();
    $end = $start->addDay();
    $result = (new ForecastVariableSpend)($start, $end);

    $byDate = [];
    foreach ($result as $row) {
        $byDate[$row['date']] = ($byDate[$row['date']] ?? 0) + $row['cents'];
    }

    // Median weekly = 7000, /7 = 1000/day (well below the mean which would be ~14k/day)
    expect($byDate[$start->toDateString()])->toBe(1000);
});

it('excludes categories that are tied to a bill', function () {
    $account = Account::factory()->create();
    $billCat = Category::factory()->create(['kind' => 'spending', 'name' => 'Mortgage Cat']);
    Bill::factory()->create(['category_id' => $billCat->id, 'account_id' => $account->id]);

    for ($w = 0; $w < 12; $w++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $billCat->id,
            'occurred_on' => CarbonImmutable::today()->subWeeks($w + 1)->toDateString(),
            'amount_cents' => -150000,
        ]);
    }

    $result = (new ForecastVariableSpend)(CarbonImmutable::today(), CarbonImmutable::today()->addDay());

    expect($result)->toBe([]);
});

it('returns no forecast for categories with fewer than four weeks of data', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    for ($w = 0; $w < 3; $w++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'occurred_on' => CarbonImmutable::today()->subWeeks($w + 1)->toDateString(),
            'amount_cents' => -10000,
        ]);
    }

    $result = (new ForecastVariableSpend)(CarbonImmutable::today(), CarbonImmutable::today()->addDay());

    expect($result)->toBe([]);
});

it('respects forecast_lookback_weeks setting', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    // Spend in the past 4 weeks only
    for ($w = 0; $w < 4; $w++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'occurred_on' => CarbonImmutable::today()->subWeeks($w + 1)->toDateString(),
            'amount_cents' => -7000,
        ]);
    }

    AppSetting::current()->update(['forecast_lookback_weeks' => 4]);

    $result = (new ForecastVariableSpend)(CarbonImmutable::today(), CarbonImmutable::today());

    expect($result)->not->toBe([]);

    AppSetting::current()->update(['forecast_lookback_weeks' => 2]);

    $result = (new ForecastVariableSpend)(CarbonImmutable::today(), CarbonImmutable::today());

    // Only 2 weeks of data falls within the 2-week lookback → below the 4-week threshold → empty
    expect($result)->toBe([]);
});
```

- [ ] **Step 3: Run test, expect failure**

```bash
php artisan test --compact --filter=ForecastVariableSpendTest
```

Expected: FAIL — class not found.

- [ ] **Step 4: Implement action**

```php
<?php

namespace App\Actions\Finance\Forecast;

use App\Models\AppSetting;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class ForecastVariableSpend
{
    /**
     * @return array<int, array{date: string, category_id: int, cents: int}>
     */
    public function __invoke(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $lookbackWeeks = (int) AppSetting::current()->forecast_lookback_weeks;
        $lookbackStart = CarbonImmutable::today()->subWeeks($lookbackWeeks)->startOfWeek();
        $lookbackEnd = CarbonImmutable::today()->subDay()->endOfDay();

        $billCategoryIds = Bill::query()->whereNotNull('category_id')->pluck('category_id')->all();

        $categories = Category::query()
            ->where('kind', 'spending')
            ->whereNotIn('id', $billCategoryIds)
            ->get();

        $out = [];

        foreach ($categories as $category) {
            $weeklyMedian = $this->weeklyMedianSpend($category->id, $lookbackStart, $lookbackEnd);
            if ($weeklyMedian === null) {
                continue;
            }

            $perDay = (int) round($weeklyMedian / 7);
            if ($perDay <= 0) {
                continue;
            }

            $cursor = $start;
            while ($cursor->lte($end)) {
                $out[] = [
                    'date' => $cursor->toDateString(),
                    'category_id' => (int) $category->id,
                    'cents' => $perDay,
                ];
                $cursor = $cursor->addDay();
            }
        }

        return $out;
    }

    private function weeklyMedianSpend(int $categoryId, CarbonImmutable $start, CarbonImmutable $end): ?int
    {
        $rows = Transaction::query()
            ->where('category_id', $categoryId)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->where('amount_cents', '<', 0)
            ->whereNull('deleted_at')
            ->get(['occurred_on', 'amount_cents']);

        if ($rows->isEmpty()) {
            return null;
        }

        $byWeek = [];
        foreach ($rows as $row) {
            $weekKey = CarbonImmutable::parse($row->occurred_on)->startOfWeek()->toDateString();
            $byWeek[$weekKey] = ($byWeek[$weekKey] ?? 0) + abs((int) $row->amount_cents);
        }

        if (count($byWeek) < 4) {
            return null;
        }

        $values = array_values($byWeek);
        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);

        return $count % 2 === 1
            ? $values[$mid]
            : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=ForecastVariableSpendTest
```

Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Forecast/ForecastVariableSpend.php tests/Unit/Actions/Finance/Forecast/ForecastVariableSpendTest.php
git commit -m "Add ForecastVariableSpend action with per-category median forecasting"
```

---

## Task 6: ComputeProjectedBalance orchestrator

**Files:**
- Create: `app/Actions/Finance/Forecast/ComputeProjectedBalance.php`
- Test: `tests/Unit/Actions/Finance/Forecast/ComputeProjectedBalanceTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Forecast/ComputeProjectedBalanceTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Forecast\ComputeProjectedBalance;
use App\Models\Account;
use App\Models\Bill;
use App\Models\IncomeSource;
use Carbon\CarbonImmutable;

it('projects starting balance plus income minus bills minus variable per day', function () {
    $today = CarbonImmutable::today();
    $account = Account::factory()->withStartingBalance(500000)->create();

    IncomeSource::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'next_expected_on' => $today->addDays(2)->toDateString(),
        'expected_amount_cents' => 300000,
    ]);

    Bill::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(4)->day,
        'expected_amount_cents' => 100000,
    ]);

    $result = (new ComputeProjectedBalance)([$account->fresh()], $today, $today->addDays(5));

    $byKey = [];
    foreach ($result as $row) {
        $byKey[$row['account_id'].'|'.$row['date']] = $row['balance_cents'];
    }

    // Day 0: starting balance only
    expect($byKey[$account->id.'|'.$today->toDateString()])->toBe(500000);
    // Day 2: + income
    expect($byKey[$account->id.'|'.$today->addDays(2)->toDateString()])->toBe(800000);
    // Day 4: + income, - bill
    expect($byKey[$account->id.'|'.$today->addDays(4)->toDateString()])->toBe(700000);
});

it('projects independently per account', function () {
    $today = CarbonImmutable::today();
    $a = Account::factory()->withStartingBalance(100000)->create();
    $b = Account::factory()->withStartingBalance(50000)->create();

    IncomeSource::factory()->create([
        'account_id' => $a->id,
        'cadence' => 'monthly',
        'next_expected_on' => $today->addDay()->toDateString(),
        'expected_amount_cents' => 50000,
    ]);

    $result = (new ComputeProjectedBalance)([$a->fresh(), $b->fresh()], $today, $today->addDay());

    $byKey = [];
    foreach ($result as $row) {
        $byKey[$row['account_id'].'|'.$row['date']] = $row['balance_cents'];
    }

    expect($byKey[$a->id.'|'.$today->addDay()->toDateString()])->toBe(150000);
    expect($byKey[$b->id.'|'.$today->addDay()->toDateString()])->toBe(50000);
});
```

- [ ] **Step 3: Run test, expect failure**

```bash
php artisan test --compact --filter=ComputeProjectedBalanceTest
```

Expected: FAIL — class not found.

- [ ] **Step 4: Implement action**

```php
<?php

namespace App\Actions\Finance\Forecast;

use App\Actions\Finance\Balance\ComputeBalanceSeries;
use App\Models\Account;
use App\Models\Bill;
use App\Models\IncomeSource;
use Carbon\CarbonImmutable;

class ComputeProjectedBalance
{
    /**
     * @param  array<int, Account>  $accounts
     * @return array<int, array{account_id: int, date: string, balance_cents: int}>
     */
    public function __invoke(array $accounts, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $incomeRows = (new ProjectIncomeDeposits)(IncomeSource::all()->all(), $start, $end);
        $billRows = (new ProjectBillCharges)(Bill::all()->all(), $start, $end);
        $variableRows = (new ForecastVariableSpend)($start, $end);

        $perAccountDeltas = [];

        foreach ($incomeRows as $row) {
            $perAccountDeltas[$row['account_id']][$row['date']] = ($perAccountDeltas[$row['account_id']][$row['date']] ?? 0) + $row['cents'];
        }

        foreach ($billRows as $row) {
            $perAccountDeltas[$row['account_id']][$row['date']] = ($perAccountDeltas[$row['account_id']][$row['date']] ?? 0) - $row['cents'];
        }

        // Variable spend is account-agnostic; subtract proportionally from each account.
        // Convention for v1: subtract entirely from the first account in the list (the user's primary).
        // This is acceptable because variable spend is a forecast, not a precise per-account ledger.
        $primaryAccountId = isset($accounts[0]) ? (int) $accounts[0]->id : null;
        if ($primaryAccountId !== null) {
            foreach ($variableRows as $row) {
                $perAccountDeltas[$primaryAccountId][$row['date']] = ($perAccountDeltas[$primaryAccountId][$row['date']] ?? 0) - $row['cents'];
            }
        }

        $out = [];
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        foreach ($accounts as $account) {
            // Seed from existing balance series at $start
            $seed = (new ComputeBalanceSeries)([$account], $startDate, $startDate);
            $running = $seed[0]['balance_cents'] ?? 0;

            $cursor = $start;
            while ($cursor->lte($end)) {
                $key = $cursor->toDateString();
                $running += $perAccountDeltas[$account->id][$key] ?? 0;
                $out[] = [
                    'account_id' => (int) $account->id,
                    'date' => $key,
                    'balance_cents' => $running,
                ];
                $cursor = $cursor->addDay();
            }
        }

        return $out;
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=ComputeProjectedBalanceTest
```

Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Forecast/ComputeProjectedBalance.php tests/Unit/Actions/Finance/Forecast/ComputeProjectedBalanceTest.php
git commit -m "Add ComputeProjectedBalance orchestrator"
```

---

## Task 7: RecommendPayDates action

**Files:**
- Create: `app/Actions/Finance/Forecast/RecommendPayDates.php`
- Test: `tests/Unit/Actions/Finance/Forecast/RecommendPayDatesTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Forecast/RecommendPayDatesTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Forecast\RecommendPayDates;
use App\Models\Account;
use App\Models\Bill;
use Carbon\CarbonImmutable;

function curve(int $accountId, CarbonImmutable $start, int $days, callable $balanceForDay): array
{
    $out = [];
    for ($i = 0; $i <= $days; $i++) {
        $date = $start->addDays($i)->toDateString();
        $out[] = ['account_id' => $accountId, 'date' => $date, 'balance_cents' => $balanceForDay($i)];
    }

    return $out;
}

it('recommends today when balance stays safe through due date', function () {
    $today = CarbonImmutable::today();
    $acct = Account::factory()->create(['minimum_balance_cents' => 50000]);
    $bill = Bill::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(10)->day,
        'expected_amount_cents' => 100000,
    ]);

    $projection = curve($acct->id, $today, 10, fn ($d) => 500000);

    $result = (new RecommendPayDates)([$bill], $projection, $today, $today->addDays(10));

    expect($result[0]['recommended_date'])->toBe($today->toDateString());
    expect($result[0]['warning'])->toBeFalse();
});

it('pushes recommendation to after a paycheck if today is unsafe', function () {
    $today = CarbonImmutable::today();
    $acct = Account::factory()->create(['minimum_balance_cents' => 50000]);
    $bill = Bill::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(10)->day,
        'expected_amount_cents' => 100000,
    ]);

    // Balance is too low until day 5, then jumps up
    $projection = curve($acct->id, $today, 10, fn ($d) => $d < 5 ? 120000 : 500000);

    $result = (new RecommendPayDates)([$bill], $projection, $today, $today->addDays(10));

    expect($result[0]['recommended_date'])->toBe($today->addDays(5)->toDateString());
    expect($result[0]['warning'])->toBeFalse();
});

it('returns due_date with warning when no safe day exists', function () {
    $today = CarbonImmutable::today();
    $acct = Account::factory()->create(['minimum_balance_cents' => 50000]);
    $due = $today->addDays(5);
    $bill = Bill::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $due->day,
        'expected_amount_cents' => 100000,
    ]);

    // Balance stays at 80000 throughout; subtracting 100000 makes it negative
    $projection = curve($acct->id, $today, 5, fn ($d) => 80000);

    $result = (new RecommendPayDates)([$bill], $projection, $today, $today->addDays(5));

    expect($result[0]['recommended_date'])->toBe($due->toDateString());
    expect($result[0]['warning'])->toBeTrue();
});

it('skips bills that have already been paid this period', function () {
    $today = CarbonImmutable::today();
    $acct = Account::factory()->create();
    $bill = Bill::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(5)->day,
        'manually_marked_paid_periods' => $today->format('Y-m'),
    ]);

    $projection = curve($acct->id, $today, 5, fn ($d) => 500000);

    $result = (new RecommendPayDates)([$bill], $projection, $today, $today->addDays(5));

    expect($result)->toBe([]);
});

it('honors per-account minimum balance', function () {
    $today = CarbonImmutable::today();
    $low = Account::factory()->create(['minimum_balance_cents' => 0]);
    $high = Account::factory()->create(['minimum_balance_cents' => 200000]);

    $billLow = Bill::factory()->create([
        'account_id' => $low->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(5)->day,
        'expected_amount_cents' => 100000,
    ]);
    $billHigh = Bill::factory()->create([
        'account_id' => $high->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(5)->day,
        'expected_amount_cents' => 100000,
    ]);

    // Both accounts at 250000 throughout
    $projection = array_merge(
        curve($low->id, $today, 5, fn ($d) => 250000),
        curve($high->id, $today, 5, fn ($d) => 250000),
    );

    $result = (new RecommendPayDates)([$billLow, $billHigh], $projection, $today, $today->addDays(5));

    $byBill = [];
    foreach ($result as $row) {
        $byBill[$row['bill_id']] = $row;
    }

    // Low floor: today is safe (250k - 100k = 150k, ≥ 0)
    expect($byBill[$billLow->id]['recommended_date'])->toBe($today->toDateString());
    expect($byBill[$billLow->id]['warning'])->toBeFalse();

    // High floor: today not safe (250k - 100k = 150k, < 200k floor) → warning
    expect($byBill[$billHigh->id]['warning'])->toBeTrue();
});
```

- [ ] **Step 3: Run test, expect failure**

```bash
php artisan test --compact --filter=RecommendPayDatesTest
```

Expected: FAIL — class not found.

- [ ] **Step 4: Implement action**

```php
<?php

namespace App\Actions\Finance\Forecast;

use App\Models\Account;
use App\Models\Bill;
use Carbon\CarbonImmutable;

class RecommendPayDates
{
    /**
     * @param  array<int, Bill>  $bills
     * @param  array<int, array{account_id: int, date: string, balance_cents: int}>  $projection
     * @return array<int, array{bill_id: int, recommended_date: string, warning: bool}>
     */
    public function __invoke(array $bills, array $projection, CarbonImmutable $today, CarbonImmutable $horizonEnd): array
    {
        $byAccount = [];
        foreach ($projection as $row) {
            $byAccount[$row['account_id']][$row['date']] = (int) $row['balance_cents'];
        }

        $accountFloors = Account::query()->pluck('minimum_balance_cents', 'id')->all();

        $out = [];

        foreach ($bills as $bill) {
            if ($bill->account_id === null) {
                continue;
            }
            if ($this->isAlreadyPaid($bill, $today)) {
                continue;
            }

            $dueDate = $bill->nextDueDate();
            if ($dueDate->gt($horizonEnd)) {
                continue;
            }

            $floor = (int) ($accountFloors[$bill->account_id] ?? 0);
            $amount = (int) $bill->expected_amount_cents;
            $accountId = (int) $bill->account_id;

            $recommended = $this->earliestSafeDay($byAccount[$accountId] ?? [], $today, $dueDate, $amount, $floor);

            $out[] = [
                'bill_id' => (int) $bill->id,
                'recommended_date' => ($recommended ?? $dueDate)->toDateString(),
                'warning' => $recommended === null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, int>  $balanceByDate
     */
    private function earliestSafeDay(array $balanceByDate, CarbonImmutable $today, CarbonImmutable $dueDate, int $amount, int $floor): ?CarbonImmutable
    {
        $cursor = $today;
        while ($cursor->lte($dueDate)) {
            if ($this->staysSafe($balanceByDate, $cursor, $dueDate, $amount, $floor)) {
                return $cursor;
            }
            $cursor = $cursor->addDay();
        }

        return null;
    }

    /**
     * @param  array<string, int>  $balanceByDate
     */
    private function staysSafe(array $balanceByDate, CarbonImmutable $payDay, CarbonImmutable $dueDate, int $amount, int $floor): bool
    {
        $cursor = $payDay;
        while ($cursor->lte($dueDate)) {
            $balance = ($balanceByDate[$cursor->toDateString()] ?? 0) - $amount;
            if ($balance < $floor) {
                return false;
            }
            $cursor = $cursor->addDay();
        }

        return true;
    }

    private function isAlreadyPaid(Bill $bill, CarbonImmutable $today): bool
    {
        $period = $bill->cadence === 'annual' ? $today->format('Y') : $today->format('Y-m');

        if (in_array($period, $bill->manuallyMarkedPeriods(), true)) {
            return true;
        }

        return $bill->transactions()
            ->whereYear('occurred_on', $today->year)
            ->when($bill->cadence !== 'annual', fn ($q) => $q->whereMonth('occurred_on', $today->month))
            ->exists();
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=RecommendPayDatesTest
```

Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Forecast/RecommendPayDates.php tests/Unit/Actions/Finance/Forecast/RecommendPayDatesTest.php
git commit -m "Add RecommendPayDates earliest-safe-day optimizer"
```

---

## Task 8: CreateIncomeSource action

**Files:**
- Create: `app/Actions/Finance/Income/CreateIncomeSource.php`
- Test: `tests/Unit/Actions/Finance/Income/CreateIncomeSourceTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Income/CreateIncomeSourceTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Income\CreateIncomeSource;
use App\Models\Account;
use App\Models\IncomeSource;

it('creates an income source with the given attributes', function () {
    $account = Account::factory()->create();

    $source = (new CreateIncomeSource)([
        'name' => 'Paycheck',
        'cadence' => 'biweekly',
        'next_expected_on' => '2026-07-10',
        'expected_amount_cents' => 250000,
        'account_id' => $account->id,
    ]);

    expect($source)->toBeInstanceOf(IncomeSource::class);
    expect($source->name)->toBe('Paycheck');
    expect($source->cadence)->toBe('biweekly');
});
```

- [ ] **Step 3: Run, expect failure**

```bash
php artisan test --compact --filter=CreateIncomeSourceTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Income;

use App\Models\IncomeSource;

class CreateIncomeSource
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): IncomeSource
    {
        return IncomeSource::create($attributes);
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=CreateIncomeSourceTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Income/CreateIncomeSource.php tests/Unit/Actions/Finance/Income/CreateIncomeSourceTest.php
git commit -m "Add CreateIncomeSource action"
```

---

## Task 9: UpdateIncomeSource action

**Files:**
- Create: `app/Actions/Finance/Income/UpdateIncomeSource.php`
- Test: `tests/Unit/Actions/Finance/Income/UpdateIncomeSourceTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Income/UpdateIncomeSourceTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Income\UpdateIncomeSource;
use App\Models\IncomeSource;

it('updates the given income source', function () {
    $source = IncomeSource::factory()->create(['name' => 'Old']);

    $updated = (new UpdateIncomeSource)($source, ['name' => 'New']);

    expect($updated->name)->toBe('New');
    expect($source->fresh()->name)->toBe('New');
});
```

- [ ] **Step 3: Run, expect failure**

```bash
php artisan test --compact --filter=UpdateIncomeSourceTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Income;

use App\Models\IncomeSource;

class UpdateIncomeSource
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(IncomeSource $source, array $attributes): IncomeSource
    {
        $source->update($attributes);

        return $source->fresh();
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=UpdateIncomeSourceTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Income/UpdateIncomeSource.php tests/Unit/Actions/Finance/Income/UpdateIncomeSourceTest.php
git commit -m "Add UpdateIncomeSource action"
```

---

## Task 10: DeleteIncomeSource action

**Files:**
- Create: `app/Actions/Finance/Income/DeleteIncomeSource.php`
- Test: `tests/Unit/Actions/Finance/Income/DeleteIncomeSourceTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Income/DeleteIncomeSourceTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Income\DeleteIncomeSource;
use App\Models\IncomeSource;

it('deletes the income source', function () {
    $source = IncomeSource::factory()->create();
    $id = $source->id;

    (new DeleteIncomeSource)($source);

    expect(IncomeSource::find($id))->toBeNull();
});
```

- [ ] **Step 3: Run, expect failure**

```bash
php artisan test --compact --filter=DeleteIncomeSourceTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Income;

use App\Models\IncomeSource;

class DeleteIncomeSource
{
    public function __invoke(IncomeSource $source): void
    {
        $source->delete();
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=DeleteIncomeSourceTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Income/DeleteIncomeSource.php tests/Unit/Actions/Finance/Income/DeleteIncomeSourceTest.php
git commit -m "Add DeleteIncomeSource action"
```

---

## Task 11: AdvanceIncomeAnchor action

**Files:**
- Create: `app/Actions/Finance/Income/AdvanceIncomeAnchor.php`
- Test: `tests/Unit/Actions/Finance/Income/AdvanceIncomeAnchorTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Income/AdvanceIncomeAnchorTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Income\AdvanceIncomeAnchor;
use App\Models\IncomeSource;

it('advances monthly cadence by one month', function () {
    $source = IncomeSource::factory()->create([
        'cadence' => 'monthly',
        'next_expected_on' => '2026-07-10',
    ]);

    (new AdvanceIncomeAnchor)($source);

    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-08-10');
});

it('advances weekly cadence by one week', function () {
    $source = IncomeSource::factory()->weekly()->create([
        'next_expected_on' => '2026-07-10',
    ]);

    (new AdvanceIncomeAnchor)($source);

    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-07-17');
});

it('advances biweekly cadence by two weeks', function () {
    $source = IncomeSource::factory()->biweekly()->create([
        'next_expected_on' => '2026-07-10',
    ]);

    (new AdvanceIncomeAnchor)($source);

    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-07-24');
});

it('alternates semi_monthly between primary and secondary days', function () {
    $source = IncomeSource::factory()->semiMonthly()->create([
        'next_expected_on' => '2026-07-01', // primary day
        'secondary_day_of_month' => 15,
    ]);

    (new AdvanceIncomeAnchor)($source);
    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-07-15');

    (new AdvanceIncomeAnchor)($source->fresh());
    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-08-01');
});

it('clamps to last-day-of-month when day exceeds month length', function () {
    $source = IncomeSource::factory()->create([
        'cadence' => 'monthly',
        'next_expected_on' => '2026-01-31',
    ]);

    (new AdvanceIncomeAnchor)($source);

    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-02-28');
});
```

- [ ] **Step 3: Run, expect failure**

```bash
php artisan test --compact --filter=AdvanceIncomeAnchorTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Income;

use App\Models\IncomeSource;

class AdvanceIncomeAnchor
{
    public function __invoke(IncomeSource $source): IncomeSource
    {
        $source->update(['next_expected_on' => $source->advanceAnchor()->toDateString()]);

        return $source->fresh();
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=AdvanceIncomeAnchorTest
```

Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Income/AdvanceIncomeAnchor.php tests/Unit/Actions/Finance/Income/AdvanceIncomeAnchorTest.php
git commit -m "Add AdvanceIncomeAnchor action"
```

---

## Task 12: IncomeMatcher support class

**Files:**
- Create: `app/Support/IncomeMatcher.php`
- Test: (covered indirectly by Task 13's import-hook test)

- [ ] **Step 1: Create the matcher**

Create `app/Support/IncomeMatcher.php` — exact mirror of `BillMatcher`, but for income sources:

```php
<?php

namespace App\Support;

use App\Models\IncomeSource;

class IncomeMatcher
{
    /** @var array<int, array{income_source_id: int, needle: string}> */
    private array $patterns = [];

    public function __construct()
    {
        $this->load();
    }

    private function load(): void
    {
        $sources = IncomeSource::query()->whereNotNull('match_description')->get();

        foreach ($sources as $source) {
            $needle = trim((string) $source->match_description);
            if ($needle === '') {
                continue;
            }
            $this->patterns[] = [
                'income_source_id' => $source->id,
                'needle' => mb_strtolower($needle),
            ];
        }
    }

    public function match(string $description): ?int
    {
        $hits = [];
        $haystack = mb_strtolower($description);

        foreach ($this->patterns as $p) {
            if (str_contains($haystack, $p['needle'])) {
                $hits[$p['income_source_id']] = true;
            }
        }

        return count($hits) === 1 ? (int) array_key_first($hits) : null;
    }
}
```

- [ ] **Step 2: Commit (no test on its own; covered by Task 13)**

```bash
git add app/Support/IncomeMatcher.php
git commit -m "Add IncomeMatcher (mirror of BillMatcher)"
```

---

## Task 13: Wire import hook for income matching

**Files:**
- Modify: `app/Actions/Finance/Imports/ParseCsvForPreview.php`
- Modify: `app/Actions/Finance/Imports/ImportTransactions.php`
- Modify: `tests/Feature/Pages/Imports/WizardTest.php`

- [ ] **Step 1: Write the failing test (extend WizardTest.php)**

Open `tests/Feature/Pages/Imports/WizardTest.php` and append:

```php
it('matches an income source on import and advances its anchor', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'biweekly',
        'next_expected_on' => '2026-07-10',
        'match_description' => 'PAYROLL ALLAN MICHAEL',
        'expected_amount_cents' => 250000,
    ]);

    $this->actingAs(User::factory()->create());

    $csv = "Date,Amount,Description\n2026-07-10,2500.00,DIRECT DEP PAYROLL ALLAN MICHAEL 12345\n";
    $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($path, $csv);

    Livewire::test('pages::imports.wizard')
        ->set('accountId', $account->id)
        ->set('file', new \Illuminate\Http\Testing\File('paycheck.csv', fopen($path, 'r')))
        ->call('parse')
        ->call('runImport');

    expect(Transaction::query()->where('income_source_id', $source->id)->count())->toBe(1);
    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-07-24');

    @unlink($path);
});
```

Verify the imports already present at the top of `WizardTest.php`. If `IncomeSource`, `Transaction`, or `User` are not imported there yet, add the `use` lines.

- [ ] **Step 2: Run, expect failure**

```bash
php artisan test --compact --filter='matches an income source on import and advances its anchor'
```

Expected: FAIL — `income_source_id` not set on the transaction, anchor unchanged.

- [ ] **Step 3: Modify `ParseCsvForPreview.php`**

Around the existing BillMatcher use, add IncomeMatcher in parallel:

```php
use App\Support\IncomeMatcher;
```

In the property block where `private ?BillMatcher $billMatcher = null;` is declared, add:

```php
private ?IncomeMatcher $incomeMatcher = null;
```

In the method where `$this->billMatcher = new BillMatcher;` is initialized, add:

```php
$this->incomeMatcher = new IncomeMatcher;
```

In the row-building section where `'bill_id' => $billId,` is set, compute and include `income_source_id`:

```php
$incomeSourceId = $row['amount_cents'] > 0
    ? $this->incomeMatcher?->match($row['description'])
    : null;
```

Add to the row array:

```php
'income_source_id' => $incomeSourceId,
```

- [ ] **Step 4: Modify `ImportTransactions.php`**

In the `Transaction::create([...])` block, add the field:

```php
'income_source_id' => $row['income_source_id'] ?? null,
```

After the loop completes (before `$batch->update(...)`), advance anchors for matched sources:

```php
$matchedIncomeSourceIds = array_values(array_unique(array_filter(array_column($rows, 'income_source_id'))));
foreach ($matchedIncomeSourceIds as $sourceId) {
    $source = \App\Models\IncomeSource::find($sourceId);
    if ($source) {
        (new \App\Actions\Finance\Income\AdvanceIncomeAnchor)($source);
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter='matches an income source on import and advances its anchor'
```

Expected: PASS.

Then full suite:

```bash
php artisan test --compact
```

Expected: all green.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Imports/ParseCsvForPreview.php app/Actions/Finance/Imports/ImportTransactions.php tests/Feature/Pages/Imports/WizardTest.php
git commit -m "Match income sources on import and advance anchors"
```

---

## Task 14: Account form — minimum balance input

**Files:**
- Modify: `resources/views/pages/accounts/⚡form.blade.php`
- Modify: `tests/Feature/Pages/Accounts/FormTest.php`

- [ ] **Step 1: Inspect the existing form**

```bash
grep -n "starting_balance\|name=" resources/views/pages/accounts/⚡form.blade.php | head
```

Note where the existing balance input is — the new field goes right after it.

- [ ] **Step 2: Add the input**

Right after the starting-balance input, add:

```blade
<x-input
    label="Minimum balance"
    wire:model="minimumBalance"
    prefix="$"
    hint="The optimizer will not recommend pay dates that project below this amount."
/>
```

In the component's `<?php ... ?>` block, add a public property:

```php
public string $minimumBalance = '0.00';
```

In `mount()`, hydrate it from the model: `$this->minimumBalance = number_format($account->minimum_balance_cents / 100, 2, '.', '');` (when editing).

In `save()` (or whatever the submit method is named — check the file), add `'minimum_balance_cents' => Money::toCents($this->minimumBalance)` to the data array.

- [ ] **Step 3: Add a test**

In `tests/Feature/Pages/Accounts/FormTest.php`, append:

```php
it('saves the minimum balance', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::accounts.index') // adapt to whatever route the form lives at
        ->call('create')
        ->set('name', 'Checking')
        ->set('startingBalance', '1000.00')
        ->set('minimumBalance', '500.00')
        ->call('save');

    expect(Account::where('name', 'Checking')->first()->minimum_balance_cents)->toBe(50000);
});
```

(Adapt the `Livewire::test(...)` target and method names to match the rest of `FormTest.php` — look at the existing "create" test for the exact shape.)

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact --filter=AccountsFormTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/pages/accounts/⚡form.blade.php tests/Feature/Pages/Accounts/FormTest.php
git commit -m "Expose minimum_balance_cents on the account form"
```

---

## Task 15: Income index page

**Files:**
- Create: `resources/views/pages/income/⚡index.blade.php`
- Create: `tests/Feature/Pages/Income/IndexTest.php`

- [ ] **Step 1: Inspect the existing Bills index as a template**

```bash
cat resources/views/pages/bills/⚡index.blade.php
```

- [ ] **Step 2: Generate the SFC**

```bash
php artisan make:livewire pages::income.index --no-interaction
```

- [ ] **Step 3: Write the failing index test**

Create `tests/Feature/Pages/Income/IndexTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\IncomeSource;
use App\Models\User;

it('renders income sources for the authenticated user', function () {
    $this->actingAs(User::factory()->create());
    $account = Account::factory()->create();
    IncomeSource::factory()->create(['name' => 'Paycheck', 'account_id' => $account->id]);

    $this->get('/income')
        ->assertOk()
        ->assertSee('Paycheck');
});
```

- [ ] **Step 4: Run, expect failure (route not defined)**

```bash
php artisan test --compact --filter=Pages\\Income\\IndexTest
```

- [ ] **Step 5: Add the route**

In `routes/web.php`, inside the `auth + verified` group, add (route + sidebar wiring also in Task 19; this line is the minimum to make the test pass):

```php
Route::livewire('income', 'pages::income.index')->name('income.index');
```

- [ ] **Step 6: Replace the SFC**

Replace `resources/views/pages/income/⚡index.blade.php` (closely follow Bills' index — same table shape, columns: name, cadence, next expected, account, amount, actions):

```blade
<?php

use App\Models\IncomeSource;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Income')] class extends Component {
    public function render(): mixed
    {
        return view('pages.income.index', [
            'sources' => IncomeSource::with(['account', 'category'])->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}; ?>

<x-layouts.app :title="$title">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Income</h1>
        <x-button label="New income" link="{{ route('income.create') }}" icon="lucide.plus" class="btn-primary" />
    </div>

    <x-card>
        @if ($sources->isEmpty())
            <div class="py-8 text-center opacity-60">No income sources yet.</div>
        @else
            <x-table :headers="[
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'cadence', 'label' => 'Cadence'],
                ['key' => 'next_expected_on', 'label' => 'Next expected'],
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'expected_amount_cents', 'label' => 'Amount'],
            ]" :rows="$sources" link="{{ route('income.show', ['income' => '[id]']) }}">
                @scope('cell_cadence', $source)
                    {{ str_replace('_', ' ', $source->cadence) }}
                @endscope
                @scope('cell_next_expected_on', $source)
                    {{ $source->next_expected_on?->format('M j, Y') }}
                @endscope
                @scope('cell_account', $source)
                    {{ $source->account?->name }}
                @endscope
                @scope('cell_expected_amount_cents', $source)
                    {{ \App\Support\Money::format($source->expected_amount_cents) }}
                @endscope
            </x-table>
        @endif
    </x-card>
</x-layouts.app>
```

Also register `income.create` and `income.show` routes in `routes/web.php` (next tasks reference them; you can stub them so this view renders without errors):

```php
Route::livewire('income/create', 'pages::income.form')->name('income.create');
Route::livewire('income/{income}', 'pages::income.show')->name('income.show');
```

- [ ] **Step 7: Run test**

```bash
php artisan test --compact --filter=Pages\\Income\\IndexTest
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add routes/web.php resources/views/pages/income/⚡index.blade.php tests/Feature/Pages/Income/IndexTest.php
git commit -m "Add Income index page"
```

---

## Task 16: Income form page

**Files:**
- Create: `resources/views/pages/income/⚡form.blade.php`
- Create: `tests/Feature/Pages/Income/FormTest.php`

- [ ] **Step 1: Inspect Bills form**

```bash
cat resources/views/pages/bills/⚡form.blade.php
```

Mirror its structure — fields are different (no `due_day_of_month`, instead `next_expected_on` + optional `secondary_day_of_month`).

- [ ] **Step 2: Generate SFC**

```bash
php artisan make:livewire pages::income.form --no-interaction
```

- [ ] **Step 3: Write failing test**

Create `tests/Feature/Pages/Income/FormTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\IncomeSource;
use App\Models\User;
use Livewire\Livewire;

it('creates an income source', function () {
    $this->actingAs(User::factory()->create());
    $account = Account::factory()->create();

    Livewire::test('pages::income.form')
        ->set('name', 'Paycheck')
        ->set('cadence', 'biweekly')
        ->set('nextExpectedOn', '2026-07-10')
        ->set('expectedAmount', '2500.00')
        ->set('accountId', $account->id)
        ->call('save')
        ->assertRedirect(route('income.index'));

    expect(IncomeSource::where('name', 'Paycheck')->first())->not->toBeNull();
});

it('persists secondary day for semi_monthly cadence', function () {
    $this->actingAs(User::factory()->create());
    $account = Account::factory()->create();

    Livewire::test('pages::income.form')
        ->set('name', 'Salary')
        ->set('cadence', 'semi_monthly')
        ->set('nextExpectedOn', '2026-07-01')
        ->set('secondaryDayOfMonth', '15')
        ->set('expectedAmount', '2500.00')
        ->set('accountId', $account->id)
        ->call('save');

    expect(IncomeSource::where('name', 'Salary')->first()->secondary_day_of_month)->toBe(15);
});
```

- [ ] **Step 4: Run, expect failure**

```bash
php artisan test --compact --filter=Pages\\Income\\FormTest
```

- [ ] **Step 5: Implement the SFC**

Replace `resources/views/pages/income/⚡form.blade.php`:

```blade
<?php

use App\Actions\Finance\Income\CreateIncomeSource;
use App\Actions\Finance\Income\UpdateIncomeSource;
use App\Models\Account;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Support\Money;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('New income')] class extends Component {
    public ?IncomeSource $source = null;
    public string $name = '';
    public string $cadence = 'biweekly';
    public string $nextExpectedOn = '';
    public string $secondaryDayOfMonth = '';
    public string $expectedAmount = '';
    public ?int $accountId = null;
    public ?int $categoryId = null;
    public string $matchDescription = '';
    public string $notes = '';

    public function mount(?IncomeSource $income = null): void
    {
        if ($income?->exists) {
            $this->source = $income;
            $this->name = $income->name;
            $this->cadence = $income->cadence;
            $this->nextExpectedOn = $income->next_expected_on->toDateString();
            $this->secondaryDayOfMonth = (string) ($income->secondary_day_of_month ?? '');
            $this->expectedAmount = number_format($income->expected_amount_cents / 100, 2, '.', '');
            $this->accountId = $income->account_id;
            $this->categoryId = $income->category_id;
            $this->matchDescription = (string) ($income->match_description ?? '');
            $this->notes = (string) ($income->notes ?? '');
        }
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:120',
            'cadence' => 'required|in:weekly,biweekly,semi_monthly,monthly',
            'nextExpectedOn' => 'required|date',
            'secondaryDayOfMonth' => 'nullable|integer|min:1|max:31',
            'expectedAmount' => 'required|numeric',
            'accountId' => 'required|exists:accounts,id',
            'categoryId' => 'nullable|exists:categories,id',
            'matchDescription' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $attrs = [
            'name' => $this->name,
            'cadence' => $this->cadence,
            'next_expected_on' => $this->nextExpectedOn,
            'secondary_day_of_month' => $this->cadence === 'semi_monthly' && $this->secondaryDayOfMonth !== ''
                ? (int) $this->secondaryDayOfMonth : null,
            'expected_amount_cents' => Money::toCents($this->expectedAmount),
            'account_id' => $this->accountId,
            'category_id' => $this->categoryId,
            'match_description' => $this->matchDescription ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->source) {
            (new UpdateIncomeSource)($this->source, $attrs);
        } else {
            (new CreateIncomeSource)($attrs);
        }

        $this->redirect(route('income.index'), navigate: true);
    }
}; ?>

<x-layouts.app :title="$title">
    <x-card title="{{ $source ? 'Edit income' : 'New income' }}">
        <x-form wire:submit="save">
            <x-input label="Name" wire:model="name" />
            <x-select label="Cadence" wire:model.live="cadence" :options="[
                ['id' => 'weekly', 'name' => 'Weekly'],
                ['id' => 'biweekly', 'name' => 'Biweekly'],
                ['id' => 'semi_monthly', 'name' => 'Semi-monthly'],
                ['id' => 'monthly', 'name' => 'Monthly'],
            ]" />
            <x-input type="date" label="Next expected on" wire:model="nextExpectedOn" />
            @if ($cadence === 'semi_monthly')
                <x-input type="number" label="Secondary day of month" wire:model="secondaryDayOfMonth" min="1" max="31" hint="The second monthly day (e.g., 15)." />
            @endif
            <x-input label="Expected amount" wire:model="expectedAmount" prefix="$" />
            <x-select label="Account" wire:model="accountId" :options="Account::all()->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->all()" />
            <x-select label="Category" wire:model="categoryId" :options="Category::where('kind', 'income')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all()" />
            <x-input label="Match description" wire:model="matchDescription" hint="Substring used to auto-match transactions on import." />
            <x-textarea label="Notes" wire:model="notes" />
            <x-slot:actions>
                <x-button label="Cancel" link="{{ route('income.index') }}" />
                <x-button label="Save" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</x-layouts.app>
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --compact --filter=Pages\\Income\\FormTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/views/pages/income/⚡form.blade.php tests/Feature/Pages/Income/FormTest.php
git commit -m "Add Income create/edit form"
```

---

## Task 17: Income show page

**Files:**
- Create: `resources/views/pages/income/⚡show.blade.php`
- Create: `tests/Feature/Pages/Income/ShowTest.php`

- [ ] **Step 1: Inspect Bills show**

```bash
cat resources/views/pages/bills/⚡show.blade.php
```

- [ ] **Step 2: Generate**

```bash
php artisan make:livewire pages::income.show --no-interaction
```

- [ ] **Step 3: Write failing test**

```php
<?php

use App\Models\IncomeSource;
use App\Models\User;
use Livewire\Livewire;

it('displays income source details and matched transactions', function () {
    $this->actingAs(User::factory()->create());
    $source = IncomeSource::factory()->create(['name' => 'Paycheck']);

    $this->get(route('income.show', ['income' => $source->id]))
        ->assertOk()
        ->assertSee('Paycheck');
});

it('deletes the income source', function () {
    $this->actingAs(User::factory()->create());
    $source = IncomeSource::factory()->create();
    $id = $source->id;

    Livewire::test('pages::income.show', ['income' => $source])
        ->call('delete')
        ->assertRedirect(route('income.index'));

    expect(IncomeSource::find($id))->toBeNull();
});
```

- [ ] **Step 4: Run, expect failure**

```bash
php artisan test --compact --filter=Pages\\Income\\ShowTest
```

- [ ] **Step 5: Implement SFC**

Replace `resources/views/pages/income/⚡show.blade.php`:

```blade
<?php

use App\Actions\Finance\Income\DeleteIncomeSource;
use App\Models\IncomeSource;
use App\Support\Money;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Income')] class extends Component {
    public IncomeSource $source;

    public function mount(IncomeSource $income): void
    {
        $this->source = $income->load(['account', 'category', 'transactions' => fn ($q) => $q->orderByDesc('occurred_on')->limit(20)]);
    }

    public function delete(): void
    {
        (new DeleteIncomeSource)($this->source);
        $this->redirect(route('income.index'), navigate: true);
    }
}; ?>

<x-layouts.app :title="$title">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">{{ $source->name }}</h1>
        <div class="flex gap-2">
            <x-button label="Edit" link="{{ route('income.show', ['income' => $source->id]) }}/edit" />
            <x-button label="Delete" wire:click="delete" wire:confirm="Delete this income source?" class="btn-error btn-outline" />
        </div>
    </div>

    <x-card>
        <dl class="grid grid-cols-2 gap-y-2 gap-x-6">
            <dt class="opacity-60">Cadence</dt><dd>{{ str_replace('_', ' ', $source->cadence) }}</dd>
            <dt class="opacity-60">Next expected</dt><dd>{{ $source->next_expected_on?->format('M j, Y') }}</dd>
            <dt class="opacity-60">Amount</dt><dd>{{ Money::format($source->expected_amount_cents) }}</dd>
            <dt class="opacity-60">Account</dt><dd>{{ $source->account?->name }}</dd>
            @if ($source->cadence === 'semi_monthly')
                <dt class="opacity-60">Secondary day</dt><dd>{{ $source->secondary_day_of_month }}</dd>
            @endif
            @if ($source->match_description)
                <dt class="opacity-60">Match</dt><dd>{{ $source->match_description }}</dd>
            @endif
        </dl>
    </x-card>

    @if ($source->transactions->isNotEmpty())
        <x-card title="Recent matched transactions" class="mt-4">
            <ul class="divide-y">
                @foreach ($source->transactions as $tx)
                    <li class="py-2 flex justify-between">
                        <span>{{ $tx->occurred_on->format('M j, Y') }} — {{ $tx->description }}</span>
                        <span>{{ Money::format($tx->amount_cents) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
</x-layouts.app>
```

(The "Edit" button can be wired to a separate route or to a drawer — for now link to `/income/{id}/edit`. If your project uses a different pattern, mirror what Bills does.)

- [ ] **Step 6: Run tests**

```bash
php artisan test --compact --filter=Pages\\Income\\ShowTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/views/pages/income/⚡show.blade.php tests/Feature/Pages/Income/ShowTest.php
git commit -m "Add Income show page"
```

---

## Task 18: Calendar page

**Files:**
- Create: `resources/views/pages/calendar/⚡index.blade.php`
- Create: `tests/Feature/Pages/Calendar/IndexTest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Generate SFC**

```bash
php artisan make:livewire pages::calendar.index --no-interaction
```

- [ ] **Step 2: Write failing test**

Create `tests/Feature/Pages/Calendar/IndexTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Bill;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

it('renders bills on the calendar with correct paid/unpaid treatment', function () {
    $this->actingAs(User::factory()->create());
    $today = CarbonImmutable::today();

    $acct = Account::factory()->withStartingBalance(500000)->create(['minimum_balance_cents' => 0]);

    IncomeSource::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'next_expected_on' => $today->addDays(2)->toDateString(),
        'expected_amount_cents' => 300000,
    ]);

    $unpaidBill = Bill::factory()->create([
        'name' => 'Spectrum',
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(10)->day,
        'expected_amount_cents' => 12000,
    ]);

    $paidBill = Bill::factory()->create([
        'name' => 'Mortgage',
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->day,
        'expected_amount_cents' => 150000,
        'manually_marked_paid_periods' => $today->format('Y-m'),
    ]);

    Transaction::factory()->create([
        'account_id' => $acct->id,
        'bill_id' => $paidBill->id,
        'occurred_on' => $today->toDateString(),
        'amount_cents' => -150000,
        'description' => 'US BANK HOME MTG',
    ]);

    $response = $this->get('/calendar?month='.$today->format('Y-m'))
        ->assertOk()
        ->assertSee('Spectrum')
        ->assertSee('Mortgage');

    // Paid pill includes the ✓ + amount; unpaid pill is the badge-primary class
    expect($response->getContent())->toContain('opacity-50');
    expect($response->getContent())->toContain('✓');
});

it('shows "All bills on track" when nothing is recommended', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/calendar')
        ->assertOk()
        ->assertSee('All bills on track');
});
```

- [ ] **Step 3: Add route**

In `routes/web.php`, inside the auth group:

```php
Route::livewire('calendar', 'pages::calendar.index')->name('calendar.index');
```

- [ ] **Step 4: Run, expect failure**

```bash
php artisan test --compact --filter=Pages\\Calendar\\IndexTest
```

- [ ] **Step 5: Implement SFC**

Replace `resources/views/pages/calendar/⚡index.blade.php`:

```blade
<?php

use App\Actions\Finance\Forecast\ComputeProjectedBalance;
use App\Actions\Finance\Forecast\RecommendPayDates;
use App\Models\Account;
use App\Models\Bill;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Calendar')] class extends Component {
    #[Url(as: 'month')]
    public string $monthKey = '';

    /** @var array<int, array{day: int, in_month: bool, date: string, items: array<int, array>}> */
    public array $cells = [];

    /** @var array<int, array> */
    public array $thisWeek = [];

    public ?string $topRecommendation = null;

    public function mount(): void
    {
        if (! $this->monthKey) {
            $this->monthKey = CarbonImmutable::today()->format('Y-m');
        }
        $this->rebuild();
    }

    public function prevMonth(): void
    {
        $this->monthKey = CarbonImmutable::parse($this->monthKey.'-01')->subMonth()->format('Y-m');
        $this->rebuild();
    }

    public function nextMonth(): void
    {
        $this->monthKey = CarbonImmutable::parse($this->monthKey.'-01')->addMonth()->format('Y-m');
        $this->rebuild();
    }

    public function jumpToToday(): void
    {
        $this->monthKey = CarbonImmutable::today()->format('Y-m');
        $this->rebuild();
    }

    private function rebuild(): void
    {
        $monthStart = CarbonImmutable::parse($this->monthKey.'-01');
        $monthEnd = $monthStart->endOfMonth();

        // Calendar grid spans full weeks
        $gridStart = $monthStart->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $monthEnd->endOfWeek(CarbonImmutable::SATURDAY);

        // Projection horizon: today through 60 days out, plus the visible grid
        $today = CarbonImmutable::today();
        $horizonStart = $today->min($gridStart);
        $horizonEnd = $today->addDays(60)->max($gridEnd);

        $accounts = Account::active()->get()->all();
        $bills = Bill::all()->all();

        $projection = (new ComputeProjectedBalance)($accounts, $horizonStart, $horizonEnd);
        $recommendations = (new RecommendPayDates)($bills, $projection, $today, $horizonEnd);

        $recByBill = [];
        foreach ($recommendations as $rec) {
            $recByBill[$rec['bill_id']] = $rec;
        }

        $this->cells = $this->buildCells($gridStart, $gridEnd, $monthStart, $bills, $recByBill, $today);
        $this->thisWeek = $this->buildThisWeek($bills, $today, $recByBill);
        $this->topRecommendation = $this->buildTopRecommendation($bills, $recByBill);
    }

    /**
     * @param  array<int, Bill>  $bills
     * @param  array<int, array>  $recByBill
     * @return array<int, array{day: int, in_month: bool, date: string, items: array<int, array>}>
     */
    private function buildCells(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $monthStart, array $bills, array $recByBill, CarbonImmutable $today): array
    {
        $cells = [];
        $cursor = $start;
        while ($cursor->lte($end)) {
            $items = [];
            foreach ($bills as $bill) {
                $due = $this->billDueIn($bill, $cursor);
                if ($due) {
                    $items[] = $this->pillForDue($bill, $cursor, $today);
                }
                $rec = $recByBill[$bill->id] ?? null;
                if ($rec && $rec['recommended_date'] === $cursor->toDateString() && $rec['recommended_date'] !== $this->billDueDateInMonth($bill, $cursor)) {
                    $items[] = ['type' => 'rec', 'bill_id' => $bill->id, 'label' => $bill->name];
                }
            }
            $cells[] = [
                'day' => $cursor->day,
                'in_month' => $cursor->month === $monthStart->month,
                'date' => $cursor->toDateString(),
                'is_today' => $cursor->isSameDay($today),
                'items' => $items,
            ];
            $cursor = $cursor->addDay();
        }

        return $cells;
    }

    private function billDueIn(Bill $bill, CarbonImmutable $date): bool
    {
        if ($bill->cadence === 'annual') {
            return $bill->due_month_of_year === $date->month && $this->clampDay($bill, $date) === $date->day;
        }

        return $this->clampDay($bill, $date) === $date->day;
    }

    private function clampDay(Bill $bill, CarbonImmutable $date): int
    {
        return min((int) $bill->due_day_of_month, $date->daysInMonth);
    }

    private function billDueDateInMonth(Bill $bill, CarbonImmutable $monthCursor): string
    {
        if ($bill->cadence === 'annual' && $bill->due_month_of_year !== $monthCursor->month) {
            return '';
        }
        $day = $this->clampDay($bill, $monthCursor);

        return $monthCursor->setDay($day)->toDateString();
    }

    /**
     * @return array{type: string, bill_id: int, label: string}
     */
    private function pillForDue(Bill $bill, CarbonImmutable $date, CarbonImmutable $today): array
    {
        $period = $bill->cadence === 'annual' ? $date->format('Y') : $date->format('Y-m');
        $isPaid = in_array($period, $bill->manuallyMarkedPeriods(), true)
            || $bill->transactions()
                ->whereYear('occurred_on', $date->year)
                ->when($bill->cadence !== 'annual', fn ($q) => $q->whereMonth('occurred_on', $date->month))
                ->exists();

        $tx = null;
        if ($isPaid) {
            $tx = $bill->transactions()
                ->whereYear('occurred_on', $date->year)
                ->when($bill->cadence !== 'annual', fn ($q) => $q->whereMonth('occurred_on', $date->month))
                ->first();
        }

        return [
            'type' => $isPaid ? 'paid' : 'due',
            'bill_id' => $bill->id,
            'label' => $isPaid && $tx
                ? '✓ '.$bill->name.' '.Money::format(abs($tx->amount_cents))
                : $bill->name,
        ];
    }

    /**
     * @param  array<int, Bill>  $bills
     * @param  array<int, array>  $recByBill
     * @return array<int, array{bill: string, due: string, warning: bool}>
     */
    private function buildThisWeek(array $bills, CarbonImmutable $today, array $recByBill): array
    {
        $window = $today->addDays(7);
        $out = [];
        foreach ($bills as $bill) {
            $next = $bill->nextDueDate();
            if ($next->lt($today) || $next->gt($window)) {
                continue;
            }
            $rec = $recByBill[$bill->id] ?? null;
            $out[] = [
                'bill' => $bill->name,
                'due' => $next->format('D, M j'),
                'warning' => (bool) ($rec['warning'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, Bill>  $bills
     * @param  array<int, array>  $recByBill
     */
    private function buildTopRecommendation(array $bills, array $recByBill): ?string
    {
        foreach ($bills as $bill) {
            $rec = $recByBill[$bill->id] ?? null;
            if (! $rec) {
                continue;
            }
            $due = $bill->nextDueDate()->toDateString();
            if ($rec['warning']) {
                return 'Heads up — '.$bill->name.' may not be safe to pay by its due date ('.$bill->nextDueDate()->format('M j').').';
            }
            if ($rec['recommended_date'] !== $due) {
                $recDate = CarbonImmutable::parse($rec['recommended_date'])->format('M j');
                $dueDate = $bill->nextDueDate()->format('M j');

                return 'Pay '.$bill->name.' on '.$recDate.' instead of '.$dueDate.'.';
            }
        }

        return null;
    }
}; ?>

<x-layouts.app :title="$title">
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-4">
        <div>
            <div class="flex items-center justify-between mb-3">
                <h1 class="text-2xl font-bold">{{ \Carbon\CarbonImmutable::parse($monthKey.'-01')->format('F Y') }}</h1>
                <div class="join">
                    <x-button class="join-item btn-sm btn-ghost" wire:click="prevMonth" label="‹" />
                    <x-button class="join-item btn-sm btn-ghost" wire:click="jumpToToday" label="Today" />
                    <x-button class="join-item btn-sm btn-ghost" wire:click="nextMonth" label="›" />
                </div>
            </div>

            <div class="grid grid-cols-7 gap-1 text-xs opacity-60 mb-1">
                @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)
                    <div class="px-2">{{ $d }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-1">
                @foreach ($cells as $cell)
                    <div class="min-h-24 rounded p-1.5 {{ $cell['in_month'] ? 'bg-base-200/40' : 'bg-base-200/10 opacity-50' }} {{ $cell['is_today'] ? 'ring-2 ring-primary' : '' }}">
                        <div class="text-xs font-semibold opacity-60">{{ $cell['day'] }}</div>
                        <div class="flex flex-col gap-0.5 mt-1">
                            @foreach ($cell['items'] as $item)
                                @if ($item['type'] === 'paid')
                                    <span class="badge badge-ghost opacity-50 text-[10px] truncate">{{ $item['label'] }}</span>
                                @elseif ($item['type'] === 'rec')
                                    <span class="badge badge-outline border-dashed border-primary text-primary text-[10px] truncate">{{ $item['label'] }}</span>
                                @else
                                    <span class="badge badge-primary text-[10px] truncate">{{ $item['label'] }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <x-card title="This week">
                @if (empty($thisWeek))
                    <p class="text-sm opacity-60">Nothing due in the next 7 days.</p>
                @else
                    <ul class="text-sm space-y-1">
                        @foreach ($thisWeek as $row)
                            <li class="flex justify-between">
                                <span>{{ $row['bill'] }}</span>
                                <span class="opacity-60">{{ $row['due'] }} @if ($row['warning']) <span class="text-error">⚠</span> @endif</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
            <x-card title="Recommendation">
                <p class="text-sm">{{ $topRecommendation ?? 'All bills on track.' }}</p>
            </x-card>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --compact --filter=Pages\\Calendar\\IndexTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add routes/web.php resources/views/pages/calendar/⚡index.blade.php tests/Feature/Pages/Calendar/IndexTest.php
git commit -m "Add Calendar page with month grid, recommended pills, and side rail"
```

---

## Task 19: Sidebar links + final integration check

**Files:**
- Modify: `resources/views/layouts/app/sidebar.blade.php`

- [ ] **Step 1: Add the sidebar entries**

Open `resources/views/layouts/app/sidebar.blade.php`. Find the existing line:

```blade
<x-menu-item title="{{ __('Goals') }}" icon="lucide.target" link="{{ route('goals.index') }}" wire:navigate />
```

Insert two new entries — `Income` between Goals and Bills, then `Calendar` between Bills and Imports:

```blade
<x-menu-item title="{{ __('Goals') }}" icon="lucide.target" link="{{ route('goals.index') }}" wire:navigate />
<x-menu-item title="{{ __('Income') }}" icon="lucide.banknote" link="{{ route('income.index') }}" wire:navigate />
<x-menu-item title="{{ __('Bills') }}" icon="lucide.calendar-clock" link="{{ route('bills.index') }}" wire:navigate />
<x-menu-item title="{{ __('Calendar') }}" icon="lucide.calendar" link="{{ route('calendar.index') }}" wire:navigate />
<x-menu-item title="{{ __('Imports') }}" icon="lucide.upload" link="{{ route('imports.index') }}" wire:navigate />
```

- [ ] **Step 2: Run full suite**

```bash
php artisan test --compact
```

Expected: all green (including 315 prior + ~35 new).

- [ ] **Step 3: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

Expected: `{"tool":"pint","result":"passed"}`.

- [ ] **Step 4: Smoke-test in browser**

```bash
php artisan view:clear
```

Open `http://ubusnu.test/calendar` and `http://ubusnu.test/income`, click around. Confirm pills render, navigation works, and theme switching still keeps the chart on the dashboard happy.

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/app/sidebar.blade.php
git commit -m "Add Income + Calendar to the sidebar"
```

---

## Self-Review Notes

- **Spec coverage:** all foundational decisions in the spec map to a task — math-only (Tasks 3–7), per-account floor (Task 1 + 14), IncomeSource model (Tasks 1, 2), earliest-safe-day (Task 7), variable-spend forecast (Task 5), no persistence of recommendations (Task 18 computes on mount). Calendar UI option B (Task 18). Auto-match hook (Task 13).
- **Type consistency:** every action's return shape used downstream in Tasks 6, 7, 18 matches what its producing task declares.
- **No placeholders:** every step has code or an exact command. The only "adapt to your existing pattern" notes are in Task 14 (account form) and Task 17 (Bills show as a template), both of which are unavoidable because the existing files are the source of truth.
- **Test coverage:** ~32 new tests across forecast, income actions, income pages, calendar page, and the import-hook regression.
