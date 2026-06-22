# Budgets & Buckets Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add user-defined budget buckets (50/30/20 style, configurable) with target percentages of a singleton monthly income figure. Each spending category lives in one bucket; a dashboard widget shows actual-vs-target per bucket plus income actual-vs-expected each month.

**Architecture:** Three schema additions (`buckets` table, `app_settings` singleton table, `categories.bucket_id` + `categories.kind` columns), replacing the existing `categories.excluded_from_totals` boolean with a three-valued `kind` enum (`spending` | `income` | `transfer`). One read-side action computes the monthly status; standard CRUD actions handle bucket management. New `/buckets` Livewire SFC page; existing `/categories` form gains Kind + Bucket fields; new dashboard widget rendered above the balance chart.

**Tech Stack:** Laravel 13, Livewire 4 SFC, MaryUI, Pest 4, SQLite.

**Spec:** `docs/superpowers/specs/2026-06-22-budgets-buckets-design.md`

---

## File Structure

### New files
```
app/
  Actions/Finance/Budgets/
    ComputeMonthlyBudgetStatus.php
    CreateBucket.php
    DeleteBucket.php
    UpdateBucket.php
  Models/
    AppSetting.php
    Bucket.php

database/
  factories/
    AppSettingFactory.php
    BucketFactory.php
  migrations/
    2026_06_22_000001_create_app_settings_table.php
    2026_06_22_000002_create_buckets_table.php
    2026_06_22_000003_add_kind_and_bucket_to_categories.php

resources/views/pages/
  buckets/
    ⚡form.blade.php
    ⚡index.blade.php
  dashboard/
    ⚡budget-status.blade.php

tests/
  Feature/Pages/
    Buckets/
      FormTest.php
      IndexTest.php
    Dashboard/
      BudgetStatusTest.php
  Unit/
    Actions/Finance/Budgets/
      ComputeMonthlyBudgetStatusTest.php
      CreateBucketTest.php
      DeleteBucketTest.php
      UpdateBucketTest.php
    Models/
      AppSettingTest.php
      BucketTest.php
```

### Modified files
- `app/Models/Category.php` — drop `excluded_from_totals`, add `kind`, `bucket_id`, `belongsTo(Bucket)`
- `database/factories/CategoryFactory.php` — drop `excludedFromTotals()` state, add `incomeKind()`, `transferKind()`, `inBucket()`
- `database/seeders/TransferCategorySeeder.php` — set `kind='transfer'` instead of `excluded_from_totals=true`
- `tests/Unit/Models/CategoryTest.php` — replace the `excluded_from_totals` boolean test with a `kind` test
- `resources/views/pages/categories/⚡form.blade.php` — add Kind radio + Bucket select; remove `Excluded from totals` checkbox
- `resources/views/pages/categories/⚡index.blade.php` — replace the Excluded column with Kind + Bucket columns
- `tests/Feature/Pages/Categories/FormTest.php` — add tests for Kind + Bucket fields; remove `excludedFromTotals` references
- `resources/views/dashboard.blade.php` — embed the new budget-status widget above the chart
- `routes/web.php` — add `/buckets` route
- `resources/views/layouts/app/sidebar.blade.php` — add "Budget" menu item

---

## Conventions

- **Each task ends with:** `vendor/bin/pint --dirty --format agent` then a commit with the exact message specified.
- **Pest tests:** `it()` / `expect()` style; filter via `php artisan test --compact --filter=<TestName>`.
- **Migrations:** filename-stamped to enforce order. Run with `php artisan migrate --no-interaction`.
- **Money everywhere:** integer cents, signed. Display layer converts to dollars via `App\Support\Money::format()` and `Money::toCents()`.
- **Reserved Livewire names:** avoid method names that collide with `$wire.*` magic actions (`commit`, `set`, `get`, `dispatch`, `upload`, `entangle`, `parent`, `js`, `wire`). Use semantic names like `saveBucket`, `applyTarget`.

---

## Task 1: AppSetting singleton

**Files:**
- Create: `database/migrations/2026_06_22_000001_create_app_settings_table.php`
- Create: `app/Models/AppSetting.php`
- Create: `database/factories/AppSettingFactory.php`
- Create: `tests/Unit/Models/AppSettingTest.php`

- [ ] **Step 1: Generate migration**

Run: `php artisan make:migration create_app_settings_table --no-interaction`

If the timestamp differs from `2026_06_22_000001`, rename to `2026_06_22_000001_create_app_settings_table.php`.

- [ ] **Step 2: Write migration body**

Replace contents:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('monthly_income_target_cents')->default(0);
            $table->timestamps();
        });

        DB::table('app_settings')->insert([
            'id' => 1,
            'monthly_income_target_cents' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
```

- [ ] **Step 3: Write the failing tests**

Write to `tests/Unit/Models/AppSettingTest.php`:

```php
<?php

use App\Models\AppSetting;

it('current() returns the singleton row', function () {
    $setting = AppSetting::current();

    expect($setting->id)->toBe(1);
    expect($setting->monthly_income_target_cents)->toBe(0);
});

it('current() returns the same row across calls', function () {
    $a = AppSetting::current();
    $b = AppSetting::current();

    expect($a->id)->toBe($b->id);
});

it('current() creates the singleton if missing', function () {
    AppSetting::query()->delete();

    $setting = AppSetting::current();

    expect($setting->id)->toBe(1);
    expect(AppSetting::count())->toBe(1);
});

it('persists monthly_income_target_cents updates', function () {
    $setting = AppSetting::current();
    $setting->update(['monthly_income_target_cents' => 500000]);

    expect(AppSetting::current()->monthly_income_target_cents)->toBe(500000);
});
```

- [ ] **Step 4: Generate model + factory**

Run: `php artisan make:model AppSetting --factory --no-interaction`

- [ ] **Step 5: Write the model**

Replace `app/Models/AppSetting.php`:

```php
<?php

namespace App\Models;

use Database\Factories\AppSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['monthly_income_target_cents'])]
class AppSetting extends Model
{
    /** @use HasFactory<AppSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'monthly_income_target_cents' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            ['monthly_income_target_cents' => 0]
        );
    }
}
```

- [ ] **Step 6: Write the factory**

Replace `database/factories/AppSettingFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppSetting>
 */
class AppSettingFactory extends Factory
{
    protected $model = AppSetting::class;

    public function definition(): array
    {
        return [
            'monthly_income_target_cents' => 0,
        ];
    }
}
```

- [ ] **Step 7: Run migration + tests**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=AppSettingTest
```

Expected: migration runs cleanly; 4 tests pass.

- [ ] **Step 8: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/AppSetting.php database/factories/AppSettingFactory.php database/migrations/2026_06_22_000001_create_app_settings_table.php tests/Unit/Models/AppSettingTest.php
git commit -m "Add AppSetting singleton for monthly income target"
```

---

## Task 2: Bucket table + model + factory + tests

**Files:**
- Create: `database/migrations/2026_06_22_000002_create_buckets_table.php`
- Create: `app/Models/Bucket.php`
- Create: `database/factories/BucketFactory.php`
- Create: `tests/Unit/Models/BucketTest.php`

- [ ] **Step 1: Generate migration**

Run: `php artisan make:migration create_buckets_table --no-interaction`

Rename to `2026_06_22_000002_create_buckets_table.php` if needed.

- [ ] **Step 2: Write migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buckets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->smallInteger('target_percentage');
            $table->string('color', 16)->nullable();
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buckets');
    }
};
```

- [ ] **Step 3: Write the failing tests**

Write to `tests/Unit/Models/BucketTest.php`:

```php
<?php

use App\Models\Bucket;
use App\Models\Category;

it('persists bucket attributes', function () {
    $bucket = Bucket::factory()->create([
        'name' => 'Essentials',
        'target_percentage' => 50,
        'color' => '#22c55e',
        'sort_order' => 1,
    ]);

    expect($bucket->name)->toBe('Essentials');
    expect($bucket->target_percentage)->toBe(50);
    expect($bucket->color)->toBe('#22c55e');
    expect($bucket->sort_order)->toBe(1);
});

it('targetCents computes percentage of an income basis', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 50]);

    expect($bucket->targetCents(500000))->toBe(250000);
    expect($bucket->targetCents(123456))->toBe(61728);
});

it('targetCents returns 0 when income is 0', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 50]);

    expect($bucket->targetCents(0))->toBe(0);
});

it('targetCents returns 0 when percentage is 0', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 0]);

    expect($bucket->targetCents(500000))->toBe(0);
});

it('has many categories', function () {
    $bucket = Bucket::factory()->create();

    expect($bucket->categories())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    expect($bucket->categories)->toHaveCount(0);
});
```

- [ ] **Step 4: Generate model + factory**

Run: `php artisan make:model Bucket --factory --no-interaction`

- [ ] **Step 5: Write the model**

Replace `app/Models/Bucket.php`:

```php
<?php

namespace App\Models;

use Database\Factories\BucketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'target_percentage', 'color', 'sort_order'])]
class Bucket extends Model
{
    /** @use HasFactory<BucketFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'target_percentage' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function targetCents(int $incomeTargetCents): int
    {
        return intdiv($incomeTargetCents * $this->target_percentage, 100);
    }
}
```

- [ ] **Step 6: Write the factory**

Replace `database/factories/BucketFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Bucket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bucket>
 */
class BucketFactory extends Factory
{
    protected $model = Bucket::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'target_percentage' => 25,
            'color' => null,
            'sort_order' => 0,
        ];
    }
}
```

- [ ] **Step 7: Run migration + tests**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=BucketTest
```

Expected: PASS, 5 tests.

- [ ] **Step 8: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Bucket.php database/factories/BucketFactory.php database/migrations/2026_06_22_000002_create_buckets_table.php tests/Unit/Models/BucketTest.php
git commit -m "Add Bucket table, model, and factory"
```

---

## Task 3: Categories migration — kind enum + bucket_id, drop excluded_from_totals

**Files:**
- Create: `database/migrations/2026_06_22_000003_add_kind_and_bucket_to_categories.php`
- Modify: `app/Models/Category.php`
- Modify: `database/factories/CategoryFactory.php`
- Modify: `database/seeders/TransferCategorySeeder.php`
- Modify: `tests/Unit/Models/CategoryTest.php`

- [ ] **Step 1: Generate migration**

Run: `php artisan make:migration add_kind_and_bucket_to_categories --no-interaction`

Rename if needed to `2026_06_22_000003_add_kind_and_bucket_to_categories.php`.

- [ ] **Step 2: Write migration body**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('kind', 16)->default('spending')->after('name');
            $table->foreignId('bucket_id')->nullable()->after('kind')->constrained()->nullOnDelete();
        });

        DB::table('categories')
            ->where('excluded_from_totals', true)
            ->update(['kind' => 'transfer']);

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('excluded_from_totals');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('excluded_from_totals')->default(false);
        });

        DB::table('categories')
            ->where('kind', 'transfer')
            ->update(['excluded_from_totals' => true]);

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['bucket_id']);
            $table->dropColumn(['kind', 'bucket_id']);
        });
    }
};
```

- [ ] **Step 3: Update `app/Models/Category.php`**

Replace the file contents entirely:

```php
<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'kind', 'bucket_id', 'keywords', 'color'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => 'string',
            'bucket_id' => 'integer',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    /**
     * @return array<int, string>
     */
    public function keywordList(): array
    {
        if (! $this->keywords) {
            return [];
        }

        return collect(explode(',', $this->keywords))
            ->map(fn (string $k) => trim(mb_strtolower($k)))
            ->filter()
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Update `database/factories/CategoryFactory.php`**

Replace the file contents entirely:

```php
<?php

namespace Database\Factories;

use App\Models\Bucket;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'kind' => 'spending',
            'bucket_id' => null,
            'keywords' => null,
            'color' => null,
        ];
    }

    public function incomeKind(): static
    {
        return $this->state(['kind' => 'income']);
    }

    public function transferKind(): static
    {
        return $this->state(['kind' => 'transfer']);
    }

    public function inBucket(Bucket $bucket): static
    {
        return $this->state([
            'kind' => 'spending',
            'bucket_id' => $bucket->id,
        ]);
    }
}
```

- [ ] **Step 5: Update `database/seeders/TransferCategorySeeder.php`**

Replace the file contents entirely:

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class TransferCategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::updateOrCreate(
            ['name' => 'Transfer'],
            [
                'kind' => 'transfer',
                'keywords' => 'transfer, tfr, to chequing, to savings, e-transfer, etfr',
            ]
        );
    }
}
```

- [ ] **Step 6: Update `tests/Unit/Models/CategoryTest.php`**

Open the file. Find this test block:

```php
it('exposes excluded_from_totals as a boolean', function () {
    $c = Category::factory()->excludedFromTotals()->create();

    expect($c->excluded_from_totals)->toBeTrue();
});
```

Replace it with:

```php
it('persists kind values (spending/income/transfer)', function () {
    expect(Category::factory()->create()->kind)->toBe('spending');
    expect(Category::factory()->incomeKind()->create()->kind)->toBe('income');
    expect(Category::factory()->transferKind()->create()->kind)->toBe('transfer');
});

it('belongs to a bucket when one is assigned', function () {
    $bucket = \App\Models\Bucket::factory()->create();
    $category = Category::factory()->inBucket($bucket)->create();

    expect($category->bucket->id)->toBe($bucket->id);
});

it('has a null bucket relation when bucket_id is null', function () {
    $category = Category::factory()->create();

    expect($category->bucket)->toBeNull();
});
```

- [ ] **Step 7: Run migration + Category tests + TransferCategorySeeder check**

```bash
php artisan migrate --no-interaction
php artisan db:seed --class=TransferCategorySeeder --no-interaction
php artisan test --compact --filter=CategoryTest
```

Expected: migration runs (existing `excluded_from_totals=true` rows convert to `kind='transfer'` before the column is dropped); seeder creates/updates Transfer with `kind='transfer'`; Category tests pass.

- [ ] **Step 8: Run full suite to confirm no regressions**

Run: `php artisan test --compact`

Expected: all previously passing tests still pass. If something references `excluded_from_totals`, it fails — fix by switching the reference to `kind`.

- [ ] **Step 9: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Category.php database/factories/CategoryFactory.php database/migrations/2026_06_22_000003_add_kind_and_bucket_to_categories.php database/seeders/TransferCategorySeeder.php tests/Unit/Models/CategoryTest.php
git commit -m "Replace excluded_from_totals with kind enum + bucket_id on categories"
```

---

## Task 4: ComputeMonthlyBudgetStatus action

**Files:**
- Create: `app/Actions/Finance/Budgets/ComputeMonthlyBudgetStatus.php`
- Create: `tests/Unit/Actions/Finance/Budgets/ComputeMonthlyBudgetStatusTest.php`

- [ ] **Step 1: Write the failing tests**

Write to `tests/Unit/Actions/Finance/Budgets/ComputeMonthlyBudgetStatusTest.php`:

```php
<?php

use App\Actions\Finance\Budgets\ComputeMonthlyBudgetStatus;
use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    AppSetting::current()->update(['monthly_income_target_cents' => 500000]);
});

it('returns the period and income target', function () {
    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['period'])->toBe('2026-06');
    expect($result['income_target_cents'])->toBe(500000);
});

it('computes income_actual_cents as the SUM of transactions in income-kind categories within the period', function () {
    $income = Category::factory()->incomeKind()->create();
    Transaction::factory()->create([
        'category_id' => $income->id,
        'amount_cents' => 250000,
        'occurred_on' => '2026-06-15',
    ]);
    Transaction::factory()->create([
        'category_id' => $income->id,
        'amount_cents' => 220000,
        'occurred_on' => '2026-06-30',
    ]);
    Transaction::factory()->create([
        'category_id' => $income->id,
        'amount_cents' => 100000,
        'occurred_on' => '2026-07-01',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['income_actual_cents'])->toBe(470000);
});

it('reports each bucket with target_cents and actual_cents (signed: + = net spent)', function () {
    $essentials = Bucket::factory()->create(['name' => 'Essentials', 'target_percentage' => 50, 'color' => '#22c55e']);
    $cat = Category::factory()->inBucket($essentials)->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -182000,
        'occurred_on' => '2026-06-10',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['buckets'])->toHaveCount(1);
    expect($result['buckets'][0]['id'])->toBe($essentials->id);
    expect($result['buckets'][0]['name'])->toBe('Essentials');
    expect($result['buckets'][0]['color'])->toBe('#22c55e');
    expect($result['buckets'][0]['target_percentage'])->toBe(50);
    expect($result['buckets'][0]['target_cents'])->toBe(250000);
    expect($result['buckets'][0]['actual_cents'])->toBe(182000);
    expect($result['buckets'][0]['over_target'])->toBeFalse();
});

it('marks a bucket as over_target when actual exceeds target', function () {
    $tiny = Bucket::factory()->create(['target_percentage' => 10]);
    $cat = Category::factory()->inBucket($tiny)->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -200000,
        'occurred_on' => '2026-06-05',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['buckets'][0]['target_cents'])->toBe(50000);
    expect($result['buckets'][0]['actual_cents'])->toBe(200000);
    expect($result['buckets'][0]['over_target'])->toBeTrue();
});

it('nets refunds against spending (positive amount in a spending category reduces actual_cents)', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 20]);
    $cat = Category::factory()->inBucket($bucket)->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -5000,
        'occurred_on' => '2026-06-10',
    ]);
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => 8000,
        'occurred_on' => '2026-06-15',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['buckets'][0]['actual_cents'])->toBe(-3000);
});

it('excludes transfer-kind categories from buckets and income', function () {
    $transfer = Category::factory()->transferKind()->create();
    Transaction::factory()->create([
        'category_id' => $transfer->id,
        'amount_cents' => 100000,
        'occurred_on' => '2026-06-10',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['income_actual_cents'])->toBe(0);
    expect($result['buckets'])->toBeEmpty();
    expect($result['unassigned_actual_cents'])->toBe(0);
});

it('reports unassigned spending in unassigned_actual_cents', function () {
    $cat = Category::factory()->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -4500,
        'occurred_on' => '2026-06-10',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['unassigned_actual_cents'])->toBe(4500);
    expect($result['buckets'])->toBeEmpty();
});

it('excludes soft-deleted transactions', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 50]);
    $cat = Category::factory()->inBucket($bucket)->create();
    $tx = Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -10000,
        'occurred_on' => '2026-06-10',
    ]);
    $tx->delete();

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['buckets'])->toHaveCount(1);
    expect($result['buckets'][0]['actual_cents'])->toBe(0);
});

it('excludes transactions outside the requested period', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 50]);
    $cat = Category::factory()->inBucket($bucket)->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -10000,
        'occurred_on' => '2026-05-31',
    ]);
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -10000,
        'occurred_on' => '2026-07-01',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['buckets'][0]['actual_cents'])->toBe(0);
});

it('defaults to current month when no period is supplied', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 50]);
    $cat = Category::factory()->inBucket($bucket)->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -5000,
        'occurred_on' => CarbonImmutable::today()->toDateString(),
    ]);

    $result = (new ComputeMonthlyBudgetStatus)();

    expect($result['period'])->toBe(CarbonImmutable::today()->format('Y-m'));
    expect($result['buckets'][0]['actual_cents'])->toBe(5000);
});
```

- [ ] **Step 2: Run — expect FAIL**

Run: `php artisan test --compact --filter=ComputeMonthlyBudgetStatusTest`

- [ ] **Step 3: Implement the action**

Write to `app/Actions/Finance/Budgets/ComputeMonthlyBudgetStatus.php`:

```php
<?php

namespace App\Actions\Finance\Budgets;

use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class ComputeMonthlyBudgetStatus
{
    /**
     * @return array{
     *     period: string,
     *     income_target_cents: int,
     *     income_actual_cents: int,
     *     buckets: array<int, array{id: int, name: string, color: ?string, target_percentage: int, target_cents: int, actual_cents: int, over_target: bool}>,
     *     unassigned_actual_cents: int
     * }
     */
    public function __invoke(?string $yearMonth = null): array
    {
        $period = $yearMonth ?? CarbonImmutable::today()->format('Y-m');
        $start = CarbonImmutable::parse($period.'-01');
        $end = $start->endOfMonth();

        $incomeTargetCents = AppSetting::current()->monthly_income_target_cents;

        $incomeActualCents = (int) Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.kind', 'income')
            ->whereBetween('transactions.occurred_on', [$start->toDateString(), $end->toDateString()])
            ->sum('transactions.amount_cents');

        $bucketSums = Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.kind', 'spending')
            ->whereBetween('transactions.occurred_on', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('categories.bucket_id, SUM(transactions.amount_cents) AS net_cents')
            ->groupBy('categories.bucket_id')
            ->pluck('net_cents', 'bucket_id');

        $unassignedActual = (int) (-1 * ($bucketSums[null] ?? 0));

        $buckets = Bucket::query()->orderBy('sort_order')->orderBy('id')->get()->map(function (Bucket $bucket) use ($bucketSums, $incomeTargetCents) {
            $target = $bucket->targetCents($incomeTargetCents);
            $actual = (int) (-1 * ($bucketSums[$bucket->id] ?? 0));

            return [
                'id' => $bucket->id,
                'name' => $bucket->name,
                'color' => $bucket->color,
                'target_percentage' => $bucket->target_percentage,
                'target_cents' => $target,
                'actual_cents' => $actual,
                'over_target' => $target > 0 && $actual > $target,
            ];
        })->all();

        return [
            'period' => $period,
            'income_target_cents' => $incomeTargetCents,
            'income_actual_cents' => $incomeActualCents,
            'buckets' => $buckets,
            'unassigned_actual_cents' => $unassignedActual,
        ];
    }
}
```

- [ ] **Step 4: Run — expect PASS (10 tests)**

Run: `php artisan test --compact --filter=ComputeMonthlyBudgetStatusTest`

- [ ] **Step 5: Run full suite**

Run: `php artisan test --compact`

Expected: full suite green except the 2 skipped browser tests.

- [ ] **Step 6: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Budgets/ComputeMonthlyBudgetStatus.php tests/Unit/Actions/Finance/Budgets/ComputeMonthlyBudgetStatusTest.php
git commit -m "Add ComputeMonthlyBudgetStatus action"
```

---

## Task 5: Bucket CRUD actions

**Files:**
- Create: `app/Actions/Finance/Budgets/CreateBucket.php`
- Create: `app/Actions/Finance/Budgets/UpdateBucket.php`
- Create: `app/Actions/Finance/Budgets/DeleteBucket.php`
- Create: `tests/Unit/Actions/Finance/Budgets/CreateBucketTest.php`
- Create: `tests/Unit/Actions/Finance/Budgets/UpdateBucketTest.php`
- Create: `tests/Unit/Actions/Finance/Budgets/DeleteBucketTest.php`

- [ ] **Step 1: Write CreateBucket test**

Write to `tests/Unit/Actions/Finance/Budgets/CreateBucketTest.php`:

```php
<?php

use App\Actions\Finance\Budgets\CreateBucket;
use App\Models\Bucket;

it('creates a bucket with the given fields', function () {
    $bucket = (new CreateBucket)('Essentials', 50, '#22c55e');

    expect($bucket)->toBeInstanceOf(Bucket::class);
    expect($bucket->name)->toBe('Essentials');
    expect($bucket->target_percentage)->toBe(50);
    expect($bucket->color)->toBe('#22c55e');
});

it('accepts null color', function () {
    $bucket = (new CreateBucket)('Lifestyle', 30, null);

    expect($bucket->color)->toBeNull();
});
```

- [ ] **Step 2: Implement CreateBucket**

Write to `app/Actions/Finance/Budgets/CreateBucket.php`:

```php
<?php

namespace App\Actions\Finance\Budgets;

use App\Models\Bucket;

class CreateBucket
{
    public function __invoke(string $name, int $targetPercentage, ?string $color = null): Bucket
    {
        return Bucket::create([
            'name' => $name,
            'target_percentage' => $targetPercentage,
            'color' => $color,
        ]);
    }
}
```

- [ ] **Step 3: Run CreateBucket tests**

Run: `php artisan test --compact --filter=CreateBucketTest`
Expected: PASS, 2 tests.

- [ ] **Step 4: Write UpdateBucket test**

Write to `tests/Unit/Actions/Finance/Budgets/UpdateBucketTest.php`:

```php
<?php

use App\Actions\Finance\Budgets\UpdateBucket;
use App\Models\Bucket;

it('updates allowed attributes', function () {
    $bucket = Bucket::factory()->create(['name' => 'Old', 'target_percentage' => 10]);

    (new UpdateBucket)($bucket, [
        'name' => 'New',
        'target_percentage' => 25,
        'color' => '#abcdef',
    ]);

    $bucket->refresh();
    expect($bucket->name)->toBe('New');
    expect($bucket->target_percentage)->toBe(25);
    expect($bucket->color)->toBe('#abcdef');
});

it('ignores attributes not in the allowed list', function () {
    $bucket = Bucket::factory()->create();

    (new UpdateBucket)($bucket, ['id' => 999, 'name' => 'Renamed']);

    $bucket->refresh();
    expect($bucket->name)->toBe('Renamed');
    expect($bucket->id)->not->toBe(999);
});
```

- [ ] **Step 5: Implement UpdateBucket**

Write to `app/Actions/Finance/Budgets/UpdateBucket.php`:

```php
<?php

namespace App\Actions\Finance\Budgets;

use App\Models\Bucket;

class UpdateBucket
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Bucket $bucket, array $attributes): Bucket
    {
        $allowed = ['name', 'target_percentage', 'color', 'sort_order'];

        $bucket->update(collect($attributes)->only($allowed)->all());

        return $bucket->fresh();
    }
}
```

- [ ] **Step 6: Write DeleteBucket test**

Write to `tests/Unit/Actions/Finance/Budgets/DeleteBucketTest.php`:

```php
<?php

use App\Actions\Finance\Budgets\DeleteBucket;
use App\Models\Bucket;
use App\Models\Category;

it('deletes the bucket', function () {
    $bucket = Bucket::factory()->create();

    (new DeleteBucket)($bucket);

    expect(Bucket::find($bucket->id))->toBeNull();
});

it('unassigns categories that referenced the bucket (nullOnDelete)', function () {
    $bucket = Bucket::factory()->create();
    $category = Category::factory()->inBucket($bucket)->create();

    (new DeleteBucket)($bucket);

    expect($category->fresh()->bucket_id)->toBeNull();
    expect($category->fresh()->kind)->toBe('spending');
});
```

- [ ] **Step 7: Implement DeleteBucket**

Write to `app/Actions/Finance/Budgets/DeleteBucket.php`:

```php
<?php

namespace App\Actions\Finance\Budgets;

use App\Models\Bucket;

class DeleteBucket
{
    public function __invoke(Bucket $bucket): void
    {
        $bucket->delete();
    }
}
```

- [ ] **Step 8: Run all three test files**

Run: `php artisan test --compact --filter="CreateBucketTest|UpdateBucketTest|DeleteBucketTest"`
Expected: PASS, 6 tests.

- [ ] **Step 9: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Budgets/CreateBucket.php app/Actions/Finance/Budgets/UpdateBucket.php app/Actions/Finance/Budgets/DeleteBucket.php tests/Unit/Actions/Finance/Budgets
git commit -m "Add Bucket CRUD actions"
```

---

## Task 6: /buckets Livewire SFC (index + form) + route + nav

**Files:**
- Create: `resources/views/pages/buckets/⚡index.blade.php`
- Create: `resources/views/pages/buckets/⚡form.blade.php`
- Create: `tests/Feature/Pages/Buckets/IndexTest.php`
- Create: `tests/Feature/Pages/Buckets/FormTest.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app/sidebar.blade.php`

- [ ] **Step 1: Add the route**

Open `routes/web.php`. Inside the existing `Route::middleware(['auth', 'verified'])->group(function () {` block, ADD the line after the transactions route:

```php
    Route::livewire('buckets', 'pages::buckets.index')->name('buckets.index');
```

- [ ] **Step 2: Add sidebar menu item**

Open `resources/views/layouts/app/sidebar.blade.php`. Find the existing line for the Transactions menu item:

```blade
<x-menu-item title="{{ __('Transactions') }}" icon="lucide.list" link="{{ route('transactions.index') }}" wire:navigate />
```

ADD this line directly below it:

```blade
<x-menu-item title="{{ __('Budget') }}" icon="lucide.wallet-cards" link="{{ route('buckets.index') }}" wire:navigate />
```

- [ ] **Step 3: Write FormTest**

Write to `tests/Feature/Pages/Buckets/FormTest.php`:

```php
<?php

use App\Models\Bucket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a new bucket', function () {
    Livewire::test('pages::buckets.form', ['bucketId' => 0])
        ->set('name', 'Essentials')
        ->set('targetPercentage', 50)
        ->set('color', '#22c55e')
        ->call('saveBucket')
        ->assertHasNoErrors();

    expect(Bucket::where('name', 'Essentials')->exists())->toBeTrue();
});

it('updates an existing bucket', function () {
    $bucket = Bucket::factory()->create(['name' => 'Old']);

    Livewire::test('pages::buckets.form', ['bucketId' => $bucket->id])
        ->set('name', 'New')
        ->call('saveBucket')
        ->assertHasNoErrors();

    expect($bucket->fresh()->name)->toBe('New');
});

it('requires name and target_percentage within range', function () {
    Livewire::test('pages::buckets.form', ['bucketId' => 0])
        ->set('name', '')
        ->set('targetPercentage', 150)
        ->call('saveBucket')
        ->assertHasErrors(['name', 'targetPercentage']);
});

it('dispatches bucket-saved on success', function () {
    Livewire::test('pages::buckets.form', ['bucketId' => 0])
        ->set('name', 'Lifestyle')
        ->set('targetPercentage', 30)
        ->call('saveBucket')
        ->assertDispatched('bucket-saved');
});
```

- [ ] **Step 4: Create the form SFC**

Write to `resources/views/pages/buckets/⚡form.blade.php`:

```blade
<?php

use App\Actions\Finance\Budgets\CreateBucket;
use App\Actions\Finance\Budgets\UpdateBucket;
use App\Models\Bucket;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public int $bucketId = 0;

    #[Validate('required|string|max:80')]
    public string $name = '';

    #[Validate('required|integer|min:0|max:100')]
    public int $targetPercentage = 0;

    public ?string $color = null;

    public function mount(int $bucketId): void
    {
        $this->bucketId = $bucketId;
        if ($bucketId > 0) {
            $bucket = Bucket::findOrFail($bucketId);
            $this->name = $bucket->name;
            $this->targetPercentage = $bucket->target_percentage;
            $this->color = $bucket->color;
        }
    }

    public function saveBucket(): void
    {
        $this->validate();

        if ($this->bucketId > 0) {
            $bucket = Bucket::findOrFail($this->bucketId);
            (new UpdateBucket)($bucket, [
                'name' => $this->name,
                'target_percentage' => $this->targetPercentage,
                'color' => $this->color,
            ]);
        } else {
            (new CreateBucket)($this->name, $this->targetPercentage, $this->color);
        }

        $this->dispatch('bucket-saved');
    }

    public function cancel(): void
    {
        $this->dispatch('bucket-cancelled');
    }
}; ?>

<x-card class="border border-base-300 mb-4">
    <div class="space-y-3">
        <x-input label="Name" wire:model="name" placeholder="Essentials" />
        <x-input type="number" label="Target percentage" wire:model="targetPercentage" min="0" max="100" hint="0-100, percentage of monthly income" />
        <x-input label="Color (hex)" wire:model="color" placeholder="#22c55e" />
        <div class="flex gap-2 justify-end">
            <x-button label="Cancel" class="btn-ghost" wire:click="cancel" />
            <x-button label="Save" class="btn-primary" wire:click="saveBucket" />
        </div>
    </div>
</x-card>
```

- [ ] **Step 5: Write IndexTest**

Write to `tests/Feature/Pages/Buckets/IndexTest.php`:

```php
<?php

use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists existing buckets', function () {
    Bucket::factory()->create(['name' => 'Essentials']);
    Bucket::factory()->create(['name' => 'Lifestyle']);

    Livewire::test('pages::buckets.index')
        ->assertOk()
        ->assertSee('Essentials')
        ->assertSee('Lifestyle');
});

it('shows the monthly income target', function () {
    AppSetting::current()->update(['monthly_income_target_cents' => 500000]);

    Livewire::test('pages::buckets.index')
        ->assertSee('$5,000.00');
});

it('saves a new monthly income target', function () {
    Livewire::test('pages::buckets.index')
        ->set('incomeTargetDollars', '5000')
        ->call('applyIncomeTarget')
        ->assertHasNoErrors();

    expect(AppSetting::current()->monthly_income_target_cents)->toBe(500000);
});

it('opens the form via startEdit and closes on bucket-saved event', function () {
    $bucket = Bucket::factory()->create();

    Livewire::test('pages::buckets.index')
        ->call('startEdit', $bucket->id)
        ->assertSet('editingId', $bucket->id)
        ->call('closeForm')
        ->assertSet('editingId', null);
});

it('deletes a bucket and unassigns its categories', function () {
    $bucket = Bucket::factory()->create();
    $category = \App\Models\Category::factory()->inBucket($bucket)->create();

    Livewire::test('pages::buckets.index')
        ->call('deleteBucket', $bucket->id);

    expect(Bucket::find($bucket->id))->toBeNull();
    expect($category->fresh()->bucket_id)->toBeNull();
});

it('requires authentication', function () {
    auth()->logout();
    $this->get(route('buckets.index'))->assertRedirect(route('login'));
});
```

- [ ] **Step 6: Create the index SFC**

Write to `resources/views/pages/buckets/⚡index.blade.php`:

```blade
<?php

use App\Actions\Finance\Budgets\DeleteBucket;
use App\Models\AppSetting;
use App\Models\Bucket;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Budget')] class extends Component {
    public ?int $editingId = null;

    public string $incomeTargetDollars = '0';

    public function mount(): void
    {
        $cents = AppSetting::current()->monthly_income_target_cents;
        $this->incomeTargetDollars = number_format($cents / 100, 2, '.', '');
    }

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
    }

    public function deleteBucket(int $id): void
    {
        $bucket = Bucket::findOrFail($id);
        (new DeleteBucket)($bucket);
    }

    public function applyIncomeTarget(): void
    {
        $cents = Money::toCents($this->incomeTargetDollars);
        AppSetting::current()->update(['monthly_income_target_cents' => $cents]);
    }

    #[On('bucket-saved')]
    #[On('bucket-cancelled')]
    public function closeForm(): void
    {
        $this->editingId = null;
    }

    #[Computed]
    public function buckets(): Collection
    {
        return Bucket::query()->withCount('categories')->orderBy('sort_order')->orderBy('id')->get();
    }

    #[Computed]
    public function totalPercentage(): int
    {
        return (int) Bucket::query()->sum('target_percentage');
    }

    #[Computed]
    public function incomeTargetCents(): int
    {
        return AppSetting::current()->monthly_income_target_cents;
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-2xl font-semibold">{{ __('Budget') }}</h1>
        <x-button label="New bucket" icon="lucide.plus" class="btn-primary" wire:click="startEdit(0)" />
    </div>

    <x-card class="border border-base-300">
        <div class="flex items-end gap-3">
            <x-input label="Monthly income target ($)" wire:model="incomeTargetDollars" />
            <x-button label="Save target" class="btn-primary" wire:click="applyIncomeTarget" />
        </div>
        <div class="text-sm mt-3 opacity-70">
            Allocated: {{ $this->totalPercentage }}% of {{ \App\Support\Money::format($this->incomeTargetCents) }}
            @if ($this->totalPercentage !== 100)
                <span class="text-warning">— buckets sum to {{ $this->totalPercentage }}%, not 100%</span>
            @endif
        </div>
    </x-card>

    @if ($editingId !== null)
        <livewire:pages::buckets.form :bucket-id="$editingId" :key="'bucket-form-'.$editingId" />
    @endif

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->buckets as $bucket)
            <x-card class="border border-base-300" :style="$bucket->color ? 'border-left:4px solid '.$bucket->color : ''">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-semibold">{{ $bucket->name }}</div>
                        <div class="text-2xl mt-1 font-mono">{{ \App\Support\Money::format($bucket->targetCents($this->incomeTargetCents)) }}</div>
                        <div class="text-xs opacity-60">{{ $bucket->target_percentage }}% · {{ $bucket->categories_count }} categor{{ $bucket->categories_count === 1 ? 'y' : 'ies' }}</div>
                    </div>
                    <div class="flex gap-1">
                        <x-button icon="lucide.pencil" class="btn-ghost btn-sm" wire:click="startEdit({{ $bucket->id }})" />
                        <x-button icon="lucide.trash-2" class="btn-ghost btn-sm text-error" wire:click="deleteBucket({{ $bucket->id }})" wire:confirm="Delete this bucket? Categories using it will be unassigned." />
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>
</div>
```

- [ ] **Step 7: Run tests**

```bash
php artisan view:clear
php artisan test --compact --filter="Pages.Buckets"
```

Expected: 10 tests pass (6 Index + 4 Form).

- [ ] **Step 8: Full suite check**

Run: `php artisan test --compact`
Expected: all green except 2 skipped browser.

- [ ] **Step 9: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/pages/buckets routes/web.php resources/views/layouts/app/sidebar.blade.php tests/Feature/Pages/Buckets
git commit -m "Add Buckets Livewire pages, route, and sidebar entry"
```

---

## Task 7: Categories form — add Kind + Bucket fields

**Files:**
- Modify: `resources/views/pages/categories/⚡form.blade.php`
- Modify: `tests/Feature/Pages/Categories/FormTest.php`

- [ ] **Step 1: Append tests for Kind + Bucket fields**

Open `tests/Feature/Pages/Categories/FormTest.php`. APPEND the following tests at the end of the file (after the existing tests, before closing whitespace):

```php
it('creates a category with kind=spending and a bucket', function () {
    $bucket = \App\Models\Bucket::factory()->create();

    Livewire::test('pages::categories.form', ['categoryId' => 0])
        ->set('name', 'Groceries')
        ->set('kind', 'spending')
        ->set('bucketId', $bucket->id)
        ->call('save')
        ->assertHasNoErrors();

    $cat = Category::where('name', 'Groceries')->first();
    expect($cat->kind)->toBe('spending');
    expect($cat->bucket_id)->toBe($bucket->id);
});

it('clears bucket_id when kind switches to income or transfer', function () {
    $bucket = \App\Models\Bucket::factory()->create();
    $cat = Category::factory()->inBucket($bucket)->create();

    Livewire::test('pages::categories.form', ['categoryId' => $cat->id])
        ->set('kind', 'income')
        ->call('save')
        ->assertHasNoErrors();

    expect($cat->fresh()->kind)->toBe('income');
    expect($cat->fresh()->bucket_id)->toBeNull();
});

it('persists kind=transfer with no bucket', function () {
    Livewire::test('pages::categories.form', ['categoryId' => 0])
        ->set('name', 'Internal Transfer')
        ->set('kind', 'transfer')
        ->call('save')
        ->assertHasNoErrors();

    $cat = Category::where('name', 'Internal Transfer')->first();
    expect($cat->kind)->toBe('transfer');
    expect($cat->bucket_id)->toBeNull();
});
```

If the existing FormTest contains any reference to `excludedFromTotals` (the old field name), REMOVE those lines/tests. Search for `excludedFromTotals` in the file and delete every usage; the new tests above replace that behavior.

- [ ] **Step 2: Rewrite the category form**

Replace `resources/views/pages/categories/⚡form.blade.php` entirely with:

```blade
<?php

use App\Models\Bucket;
use App\Models\Category;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public int $categoryId = 0;

    #[Validate('required|string|max:80')]
    public string $name = '';

    #[Validate('required|in:spending,income,transfer')]
    public string $kind = 'spending';

    public ?int $bucketId = null;

    public ?string $keywords = null;

    public ?string $color = null;

    public function mount(int $categoryId): void
    {
        $this->categoryId = $categoryId;
        if ($categoryId > 0) {
            $cat = Category::findOrFail($categoryId);
            $this->name = $cat->name;
            $this->kind = $cat->kind;
            $this->bucketId = $cat->bucket_id;
            $this->keywords = $cat->keywords;
            $this->color = $cat->color;
        }
    }

    public function updatedKind(string $value): void
    {
        if ($value !== 'spending') {
            $this->bucketId = null;
        }
    }

    public function save(): void
    {
        $this->validate();

        Category::updateOrCreate(
            ['id' => $this->categoryId > 0 ? $this->categoryId : null],
            [
                'name' => $this->name,
                'kind' => $this->kind,
                'bucket_id' => $this->kind === 'spending' ? $this->bucketId : null,
                'keywords' => $this->keywords,
                'color' => $this->color,
            ]
        );

        $this->dispatch('category-saved');
    }

    public function cancel(): void
    {
        $this->dispatch('category-cancelled');
    }

    public function with(): array
    {
        return [
            'buckets' => Bucket::orderBy('name')->get(),
        ];
    }
}; ?>

<x-card class="border border-base-300 mb-4">
    <div class="space-y-3">
        <x-input label="Name" wire:model="name" />

        <x-radio label="Kind" :options="[
            ['id' => 'spending', 'name' => 'Spending'],
            ['id' => 'income', 'name' => 'Income'],
            ['id' => 'transfer', 'name' => 'Transfer'],
        ]" wire:model.live="kind" />

        @if ($kind === 'spending')
            <x-select label="Bucket" :options="$buckets" option-label="name" option-value="id" placeholder="Unassigned" wire:model="bucketId" />
        @endif

        <x-input label="Keywords (comma-separated)" wire:model="keywords" placeholder="safeway, save-on, walmart" />
        <x-input label="Color (hex)" wire:model="color" placeholder="#aabbcc" />

        <div class="flex gap-2 justify-end">
            <x-button label="Cancel" class="btn-ghost" wire:click="cancel" />
            <x-button label="Save" class="btn-primary" wire:click="save" />
        </div>
    </div>
</x-card>
```

- [ ] **Step 3: Run tests**

```bash
php artisan view:clear
php artisan test --compact --filter="Pages.Categories"
```

Expected: all Categories tests pass, including the 3 newly appended ones.

- [ ] **Step 4: Full suite**

Run: `php artisan test --compact`
Expected: green except 2 skipped browser tests.

- [ ] **Step 5: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/pages/categories/⚡form.blade.php tests/Feature/Pages/Categories/FormTest.php
git commit -m "Add Kind and Bucket fields to Category form"
```

---

## Task 8: Categories index — replace Excluded column with Kind + Bucket

**Files:**
- Modify: `resources/views/pages/categories/⚡index.blade.php`

- [ ] **Step 1: Update the index view**

Open `resources/views/pages/categories/⚡index.blade.php`. Find the `<x-table :headers="[...]">` block. Replace its `:headers` array contents so the table column set becomes:

```blade
    <x-table :headers="[
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'kind', 'label' => 'Kind'],
        ['key' => 'bucket', 'label' => 'Bucket'],
        ['key' => 'keywords', 'label' => 'Keywords'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-20'],
    ]" :rows="$this->categories">
```

Then ADD `with('bucket')` to the categories query so the bucket name is eager-loaded. Find the existing `categories()` computed method:

```php
#[Computed]
public function categories(): Collection
{
    return Category::orderBy('name')->get();
}
```

Replace with:

```php
#[Computed]
public function categories(): Collection
{
    return Category::with('bucket')->orderBy('name')->get();
}
```

ADD `use App\Models\Bucket;` to the existing use list if it isn't already there.

REPLACE the old `@scope('cell_excluded_from_totals', $row) ... @endscope` block with these two new scoped slots, placed in the same area between the table opening and `</x-table>`:

```blade
        @scope('cell_kind', $row)
            @if ($row->kind === 'spending')
                <x-badge value="Spending" class="badge-info badge-sm" />
            @elseif ($row->kind === 'income')
                <x-badge value="Income" class="badge-success badge-sm" />
            @else
                <x-badge value="Transfer" class="badge-ghost badge-sm" />
            @endif
        @endscope
        @scope('cell_bucket', $row)
            {{ $row->bucket?->name ?? '—' }}
        @endscope
```

(Keep the `cell_actions` scope unchanged.)

- [ ] **Step 2: Run tests**

```bash
php artisan view:clear
php artisan test --compact --filter="Pages.Categories"
```

Expected: same number of passing tests as before — the index view change shouldn't break anything; we removed the `Excluded` column reference, which was only displayed (not asserted in tests beyond clear-text matches).

- [ ] **Step 3: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/pages/categories/⚡index.blade.php
git commit -m "Show Kind and Bucket columns on Categories index"
```

---

## Task 9: Dashboard budget-status widget

**Files:**
- Create: `resources/views/pages/dashboard/⚡budget-status.blade.php`
- Create: `tests/Feature/Pages/Dashboard/BudgetStatusTest.php`
- Modify: `resources/views/dashboard.blade.php`

- [ ] **Step 1: Write the test**

Write to `tests/Feature/Pages/Dashboard/BudgetStatusTest.php`:

```php
<?php

use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    AppSetting::current()->update(['monthly_income_target_cents' => 500000]);
});

it('shows the income line', function () {
    Livewire::test('pages::dashboard.budget-status')
        ->assertSee('Income')
        ->assertSee('$5,000.00');
});

it('shows a row per bucket with name and target', function () {
    Bucket::factory()->create(['name' => 'Essentials', 'target_percentage' => 50]);
    Bucket::factory()->create(['name' => 'Lifestyle', 'target_percentage' => 30]);

    Livewire::test('pages::dashboard.budget-status')
        ->assertSee('Essentials')
        ->assertSee('$2,500.00')
        ->assertSee('Lifestyle')
        ->assertSee('$1,500.00');
});

it('hides the Unassigned row when there is no unassigned spending', function () {
    Livewire::test('pages::dashboard.budget-status')
        ->assertDontSee('Unassigned');
});

it('shows the Unassigned row when spending categories without a bucket have transactions', function () {
    $cat = Category::factory()->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -4500,
        'occurred_on' => now()->toDateString(),
    ]);

    Livewire::test('pages::dashboard.budget-status')
        ->assertSee('Unassigned')
        ->assertSee('$45.00');
});
```

- [ ] **Step 2: Create the widget SFC**

Write to `resources/views/pages/dashboard/⚡budget-status.blade.php`:

```blade
<?php

use App\Actions\Finance\Budgets\ComputeMonthlyBudgetStatus;
use App\Support\Money;
use Livewire\Component;

new class extends Component {
    /** @var array<string, mixed> */
    public array $status = [];

    public function mount(): void
    {
        $this->status = (new ComputeMonthlyBudgetStatus)();
    }
}; ?>

<x-card class="border border-base-300">
    <div class="flex justify-between items-center mb-3">
        <h2 class="text-lg font-semibold">{{ __('This month') }}</h2>
        <div class="text-sm">
            <span class="opacity-60">{{ __('Income') }}:</span>
            <span class="font-mono">{{ \App\Support\Money::format($status['income_actual_cents']) }}</span>
            <span class="opacity-60">/ {{ \App\Support\Money::format($status['income_target_cents']) }}</span>
        </div>
    </div>

    <div class="space-y-3">
        @foreach ($status['buckets'] as $b)
            @php
                $pct = $b['target_cents'] > 0 ? max(0, min(150, (int) round($b['actual_cents'] / $b['target_cents'] * 100))) : 0;
                $barClass = $b['over_target'] ? 'progress-error' : ($pct >= 80 ? 'progress-warning' : 'progress-primary');
                $barWidth = $pct > 100 ? 100 : $pct;
            @endphp
            <div>
                <div class="flex justify-between text-sm">
                    <span>{{ $b['name'] }}</span>
                    <span class="font-mono">
                        {{ \App\Support\Money::format(max(0, $b['actual_cents'])) }}
                        <span class="opacity-50">/ {{ \App\Support\Money::format($b['target_cents']) }}</span>
                        <span class="opacity-50">({{ $pct }}%)</span>
                    </span>
                </div>
                <progress class="progress {{ $barClass }} w-full h-2" value="{{ $barWidth }}" max="100"></progress>
            </div>
        @endforeach

        @if ($status['unassigned_actual_cents'] > 0)
            <div>
                <div class="flex justify-between text-sm">
                    <span class="opacity-70">{{ __('Unassigned') }}</span>
                    <span class="font-mono opacity-70">{{ \App\Support\Money::format($status['unassigned_actual_cents']) }}</span>
                </div>
                <progress class="progress progress-ghost w-full h-2" value="0" max="100"></progress>
            </div>
        @endif
    </div>
</x-card>
```

- [ ] **Step 3: Embed in dashboard**

Open `resources/views/dashboard.blade.php`. Find the existing content and replace it ENTIRELY with:

```blade
<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <livewire:pages::dashboard.budget-status key="budget-status" />

        <div>
            <livewire:pages::charts.balance-chart :account-id="null" key="chart-household" />
        </div>

        <livewire:pages::accounts.index key="dashboard-accounts" />
    </div>
</x-layouts::app>
```

- [ ] **Step 4: Run tests**

```bash
php artisan view:clear
php artisan test --compact --filter="Pages.Dashboard.BudgetStatus"
```

Expected: 4 tests pass.

- [ ] **Step 5: Full suite**

Run: `php artisan test --compact`

Expected: all green except 2 skipped browser tests.

- [ ] **Step 6: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/pages/dashboard resources/views/dashboard.blade.php tests/Feature/Pages/Dashboard
git commit -m "Add Budget Status dashboard widget"
```

---

## Self-Review Summary

Plan checked against the spec:

| Spec requirement | Covered by |
|---|---|
| `app_settings` singleton, `monthly_income_target_cents` | Task 1 |
| `buckets` table with name, target_percentage, color, sort_order | Task 2 |
| `Bucket::targetCents($income)` accessor | Task 2 (`targetCents` method + tests) |
| `categories.kind` enum (`spending`/`income`/`transfer`) | Task 3 |
| `categories.bucket_id` FK with `nullOnDelete` | Task 3 |
| Migration converts existing `excluded_from_totals=true` to `kind='transfer'` | Task 3 (DB::table update before drop) |
| TransferCategorySeeder updated | Task 3 |
| `ComputeMonthlyBudgetStatus` with full return shape | Task 4 |
| Signed `actual_cents` (positive = spent, negative = refund net) | Task 4 (`(-1 *)` flip + test for refund case) |
| Income actual sums `kind='income'` transactions | Task 4 |
| Transfer categories excluded from both buckets and income | Task 4 |
| Unassigned spending categories aggregated | Task 4 |
| Soft-deleted transactions excluded | Task 4 |
| Period defaults to current month | Task 4 |
| `Create`/`Update`/`DeleteBucket` actions | Task 5 |
| `DeleteBucket` unassigns categories via `nullOnDelete` | Task 5 (test + cascade) |
| `/buckets` Livewire SFC + route + sidebar | Task 6 |
| Inline income-target editor on `/buckets` | Task 6 |
| Bucket form SFC | Task 6 |
| Category form gains Kind radio + Bucket select | Task 7 |
| Switching kind away from spending clears bucket_id | Task 7 (`updatedKind` hook + test) |
| Categories index shows Kind + Bucket columns | Task 8 |
| Dashboard widget with income line + bar per bucket + unassigned | Task 9 |
| Bar color logic (primary/warning/error) | Task 9 (`$barClass`) |

No placeholders. No TODO/TBD. Method names (`saveBucket`, `applyIncomeTarget`, `recategorize`, `targetCents`, `current`) consistent across tasks. Property names (`bucketId`, `kind`, `incomeTargetDollars`) consistent. Reserved-Livewire-magic check: no `commit`, `set`, `get`, `dispatch`, etc. used as user method names.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-22-budgets-buckets.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration. 9 tasks total.

**2. Inline Execution** — I execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
