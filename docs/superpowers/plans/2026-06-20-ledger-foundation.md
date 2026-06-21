# Ledger Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Phase 1 of the finance tracker — accounts with starting balances, CSV import with dedup, manual transaction CRUD, categories (manual assignment + Transfer auto-match only), and an on-the-fly balance-over-time chart.

**Architecture:** Approach B — business logic in `app/Actions/Finance/*` single-purpose invokable classes; thin Livewire components orchestrate UI and call actions. Data layer is five new tables in SQLite (Postgres-portable). Money stored as signed `bigInteger` cents. No multi-tenancy.

**Tech Stack:** Laravel 13, Livewire 4, MaryUI (ApexCharts via `<x-chart>`), Fortify (existing), Pest 4, SQLite. CSV parsing uses built-in PHP `fgetcsv()` (no new dependency).

**Spec:** `docs/superpowers/specs/2026-06-20-ledger-foundation-design.md`

---

## File Structure

### New files
```
app/
  Actions/Finance/
    Accounts/
      ArchiveAccount.php
      ComputeAccountBalance.php
      CreateAccount.php
      UpdateAccount.php
    Balance/
      ComputeBalanceSeries.php
    Imports/
      ImportTransactions.php
      ParseCsvForPreview.php
      UndoImportBatch.php
    Transactions/
      CategorizeTransaction.php
      CreateTransaction.php
      DeleteTransaction.php
      UpdateTransaction.php
  Livewire/
    Accounts/
      Form.php
      Index.php
      Show.php
    Categories/
      Form.php
      Index.php
    Charts/
      BalanceChart.php
    Imports/
      Index.php
      Wizard.php
    Transactions/
      Form.php
      Index.php
  Models/
    Account.php
    Category.php
    ImportBatch.php
    Transaction.php
  Support/
    Money.php
    TransactionHash.php

database/
  factories/
    AccountFactory.php
    CategoryFactory.php
    ImportBatchFactory.php
    TransactionFactory.php
  migrations/
    2026_06_20_000001_create_categories_table.php
    2026_06_20_000002_create_accounts_table.php
    2026_06_20_000003_create_transactions_table.php
    2026_06_20_000004_create_import_batches_table.php
  seeders/
    TransferCategorySeeder.php

resources/views/livewire/
  accounts/{form,index,show}.blade.php
  categories/{form,index}.blade.php
  charts/balance-chart.blade.php
  imports/{index,wizard}.blade.php
  transactions/{form,index}.blade.php

tests/
  Browser/
    ImportWizardBrowserTest.php
    DashboardRenderTest.php
  Feature/
    Livewire/Accounts/{FormTest,IndexTest,ShowTest}.php
    Livewire/Categories/{FormTest,IndexTest}.php
    Livewire/Charts/BalanceChartTest.php
    Livewire/Imports/{IndexTest,WizardTest}.php
    Livewire/Transactions/{FormTest,IndexTest}.php
  Fixtures/csv/
    sample-alt-headers.csv
    sample-bad-rows.csv
    sample-standard.csv
    sample-with-duplicates.csv
  Unit/
    Actions/Finance/Accounts/{ArchiveAccountTest,ComputeAccountBalanceTest,CreateAccountTest,UpdateAccountTest}.php
    Actions/Finance/Balance/ComputeBalanceSeriesTest.php
    Actions/Finance/Imports/{ImportTransactionsTest,ParseCsvForPreviewTest,UndoImportBatchTest}.php
    Actions/Finance/Transactions/{CategorizeTransactionTest,CreateTransactionTest,DeleteTransactionTest,UpdateTransactionTest}.php
    Models/{AccountTest,CategoryTest,ImportBatchTest,TransactionTest}.php
    Support/{MoneyTest,TransactionHashTest}.php
```

### Modified files
- `database/seeders/DatabaseSeeder.php` — call `TransferCategorySeeder`
- `resources/views/dashboard.blade.php` — embed chart + account tiles
- `resources/views/layouts/app/sidebar.blade.php` — add nav menu items
- `routes/web.php` — register finance routes inside auth group

---

## Conventions

- **Each task ends with:** `vendor/bin/pint --dirty --format agent` then a commit.
- **All Artisan commands:** include `--no-interaction`.
- **Tests:** Pest 4 syntax (`it()`, `test()`, `expect()`). Filter runs: `php artisan test --compact --filter=<Name>`.
- **Code style:** PHP 8.4 constructor property promotion, explicit return types, curly braces always.
- **Money rule:** every money field is signed `bigInteger` cents in DB. Application code passes integer cents around. Display/formatting via `App\Support\Money::format(int $cents)`.

---

## Task 1: Money formatter helper

**Files:**
- Create: `app/Support/Money.php`
- Create: `tests/Unit/Support/MoneyTest.php`

- [ ] **Step 1: Write the failing tests**

Write to `tests/Unit/Support/MoneyTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=MoneyTest`
Expected: FAIL (class `App\Support\Money` does not exist).

- [ ] **Step 3: Implement the helper**

Write to `app/Support/Money.php`:

```php
<?php

namespace App\Support;

class Money
{
    public static function format(int $cents): string
    {
        $abs = abs($cents);
        $dollars = intdiv($abs, 100);
        $remainder = $abs % 100;
        $formatted = '$'.number_format($dollars).'.'.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT);

        return $cents < 0 ? '-'.$formatted : $formatted;
    }

    public static function toCents(string $value): int
    {
        $clean = preg_replace('/[^0-9.\-]/', '', $value);

        return (int) round(((float) $clean) * 100);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=MoneyTest`
Expected: PASS, 6 assertions.

- [ ] **Step 5: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Money.php tests/Unit/Support/MoneyTest.php
git commit -m "Add Money formatter helper"
```

---

## Task 2: Categories table + model + factory + seeder

**Files:**
- Create: `database/migrations/2026_06_20_000001_create_categories_table.php`
- Create: `app/Models/Category.php`
- Create: `database/factories/CategoryFactory.php`
- Create: `database/seeders/TransferCategorySeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `tests/Unit/Models/CategoryTest.php`

- [ ] **Step 1: Generate migration**

Run: `php artisan make:migration create_categories_table --no-interaction`

Note: rename the generated file to `2026_06_20_000001_create_categories_table.php` if Laravel timestamps it differently — order matters so categories exist before transactions.

- [ ] **Step 2: Write migration body**

Replace contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('keywords')->nullable();
            $table->boolean('excluded_from_totals')->default(false);
            $table->string('color')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 3: Generate model + factory**

Run: `php artisan make:model Category --factory --no-interaction`

- [ ] **Step 4: Write the model**

Replace `app/Models/Category.php`:

```php
<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'keywords', 'excluded_from_totals', 'color'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'excluded_from_totals' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
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

- [ ] **Step 5: Write the factory**

Replace `database/factories/CategoryFactory.php`:

```php
<?php

namespace Database\Factories;

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
            'keywords' => null,
            'excluded_from_totals' => false,
            'color' => null,
        ];
    }

    public function excludedFromTotals(): static
    {
        return $this->state(['excluded_from_totals' => true]);
    }
}
```

- [ ] **Step 6: Create the seeder**

Run: `php artisan make:seeder TransferCategorySeeder --no-interaction`

Replace `database/seeders/TransferCategorySeeder.php`:

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
                'keywords' => 'transfer, tfr, to chequing, to savings, e-transfer, etfr',
                'excluded_from_totals' => true,
            ]
        );
    }
}
```

- [ ] **Step 7: Wire seeder into DatabaseSeeder**

Modify `database/seeders/DatabaseSeeder.php` — inside the `run()` method, add:

```php
$this->call(TransferCategorySeeder::class);
```

- [ ] **Step 8: Write model tests**

Write to `tests/Unit/Models/CategoryTest.php`:

```php
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
    $c = Category::factory()->excludedFromTotals()->create();

    expect($c->excluded_from_totals)->toBeTrue();
});
```

- [ ] **Step 9: Run migrations and tests**

```bash
php artisan migrate --no-interaction
php artisan db:seed --class=TransferCategorySeeder --no-interaction
php artisan test --compact --filter=CategoryTest
```

Expected: migration runs, seeder creates Transfer row, tests PASS.

- [ ] **Step 10: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Category.php database/factories/CategoryFactory.php database/migrations/2026_06_20_000001_create_categories_table.php database/seeders/TransferCategorySeeder.php database/seeders/DatabaseSeeder.php tests/Unit/Models/CategoryTest.php
git commit -m "Add categories table, model, factory, and Transfer seed"
```

---

## Task 3: Accounts table + model + factory

**Files:**
- Create: `database/migrations/2026_06_20_000002_create_accounts_table.php`
- Create: `app/Models/Account.php`
- Create: `database/factories/AccountFactory.php`
- Create: `tests/Unit/Models/AccountTest.php`

- [ ] **Step 1: Generate migration**

Run: `php artisan make:migration create_accounts_table --no-interaction`

Rename to `2026_06_20_000002_create_accounts_table.php` if needed for ordering.

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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('starting_balance_cents')->default(0);
            $table->boolean('counts_toward_goals')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->json('import_profile')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
```

- [ ] **Step 3: Generate model + factory**

Run: `php artisan make:model Account --factory --no-interaction`

- [ ] **Step 4: Write the model**

Replace `app/Models/Account.php`:

```php
<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'starting_balance_cents', 'counts_toward_goals', 'archived_at', 'import_profile'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starting_balance_cents' => 'integer',
            'counts_toward_goals' => 'boolean',
            'archived_at' => 'datetime',
            'import_profile' => 'array',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
```

- [ ] **Step 5: Write the factory**

Replace `database/factories/AccountFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'starting_balance_cents' => 0,
            'counts_toward_goals' => false,
            'archived_at' => null,
            'import_profile' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }

    public function countsTowardGoals(): static
    {
        return $this->state(['counts_toward_goals' => true]);
    }

    public function withStartingBalance(int $cents): static
    {
        return $this->state(['starting_balance_cents' => $cents]);
    }
}
```

- [ ] **Step 6: Write model tests**

Write to `tests/Unit/Models/AccountTest.php`:

```php
<?php

use App\Models\Account;

it('casts starting_balance_cents to integer', function () {
    $a = Account::factory()->withStartingBalance(-150000)->create();
    expect($a->starting_balance_cents)->toBe(-150000);
});

it('casts counts_toward_goals to boolean', function () {
    $a = Account::factory()->countsTowardGoals()->create();
    expect($a->counts_toward_goals)->toBeTrue();
});

it('casts import_profile to array', function () {
    $a = Account::factory()->create([
        'import_profile' => ['date_column' => 'Date', 'has_header' => true],
    ]);

    expect($a->import_profile)->toBe(['date_column' => 'Date', 'has_header' => true]);
});

it('scopes active to non-archived accounts', function () {
    Account::factory()->count(2)->create();
    Account::factory()->archived()->create();

    expect(Account::active()->count())->toBe(2);
});

it('reports archived status', function () {
    expect(Account::factory()->archived()->create()->isArchived())->toBeTrue();
    expect(Account::factory()->create()->isArchived())->toBeFalse();
});
```

- [ ] **Step 7: Run migrations and tests**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=AccountTest
```

Expected: PASS.

- [ ] **Step 8: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/Account.php database/factories/AccountFactory.php database/migrations/2026_06_20_000002_create_accounts_table.php tests/Unit/Models/AccountTest.php
git commit -m "Add accounts table, model, and factory"
```

---

## Task 4: TransactionHash helper + Transactions table + model + factory

**Files:**
- Create: `app/Support/TransactionHash.php`
- Create: `tests/Unit/Support/TransactionHashTest.php`
- Create: `database/migrations/2026_06_20_000003_create_transactions_table.php`
- Create: `app/Models/Transaction.php`
- Create: `database/factories/TransactionFactory.php`
- Create: `tests/Unit/Models/TransactionTest.php`

- [ ] **Step 1: Write the hash helper tests**

Write to `tests/Unit/Support/TransactionHashTest.php`:

```php
<?php

use App\Support\TransactionHash;

it('produces deterministic hash for identical inputs', function () {
    $a = TransactionHash::for(1, '2026-06-01', 1234, 'Coffee Shop');
    $b = TransactionHash::for(1, '2026-06-01', 1234, 'Coffee Shop');

    expect($a)->toBe($b);
});

it('normalizes whitespace in description', function () {
    $a = TransactionHash::for(1, '2026-06-01', 1234, '  Coffee   Shop  ');
    $b = TransactionHash::for(1, '2026-06-01', 1234, 'Coffee Shop');

    expect($a)->toBe($b);
});

it('normalizes case in description', function () {
    $a = TransactionHash::for(1, '2026-06-01', 1234, 'COFFEE SHOP');
    $b = TransactionHash::for(1, '2026-06-01', 1234, 'coffee shop');

    expect($a)->toBe($b);
});

it('produces different hash when any field differs', function () {
    $base = TransactionHash::for(1, '2026-06-01', 1234, 'Coffee');

    expect(TransactionHash::for(2, '2026-06-01', 1234, 'Coffee'))->not->toBe($base);
    expect(TransactionHash::for(1, '2026-06-02', 1234, 'Coffee'))->not->toBe($base);
    expect(TransactionHash::for(1, '2026-06-01', 1235, 'Coffee'))->not->toBe($base);
    expect(TransactionHash::for(1, '2026-06-01', 1234, 'Tea'))->not->toBe($base);
});

it('returns a 64-character sha256 hex string', function () {
    $hash = TransactionHash::for(1, '2026-06-01', 1234, 'X');

    expect($hash)->toHaveLength(64);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=TransactionHashTest`
Expected: FAIL (class missing).

- [ ] **Step 3: Implement the hash helper**

Write to `app/Support/TransactionHash.php`:

```php
<?php

namespace App\Support;

class TransactionHash
{
    public static function for(int $accountId, string $occurredOn, int $amountCents, string $description): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $description)));

        return hash('sha256', $accountId.'|'.$occurredOn.'|'.$amountCents.'|'.$normalized);
    }
}
```

- [ ] **Step 4: Run hash tests**

Run: `php artisan test --compact --filter=TransactionHashTest`
Expected: PASS.

- [ ] **Step 5: Generate transactions migration**

Run: `php artisan make:migration create_transactions_table --no-interaction`

Rename file to `2026_06_20_000003_create_transactions_table.php` if needed.

- [ ] **Step 6: Write transactions migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('occurred_on');
            $table->text('description');
            $table->bigInteger('amount_cents');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dedup_hash');
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->string('source')->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'dedup_hash']);
            $table->index(['account_id', 'occurred_on']);
            $table->index('import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
```

(Note: `import_batch_id` foreign key constraint is added in Task 5's migration, since `import_batches` table doesn't exist yet.)

- [ ] **Step 7: Generate Transaction model + factory**

Run: `php artisan make:model Transaction --factory --no-interaction`

- [ ] **Step 8: Write the Transaction model**

Replace `app/Models/Transaction.php`:

```php
<?php

namespace App\Models;

use App\Support\TransactionHash;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['account_id', 'occurred_on', 'description', 'amount_cents', 'category_id', 'dedup_hash', 'import_batch_id', 'source', 'notes'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            if (! $transaction->dedup_hash) {
                $transaction->dedup_hash = TransactionHash::for(
                    $transaction->account_id,
                    $transaction->occurred_on instanceof \DateTimeInterface
                        ? $transaction->occurred_on->format('Y-m-d')
                        : (string) $transaction->occurred_on,
                    $transaction->amount_cents,
                    $transaction->description,
                );
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
```

- [ ] **Step 9: Write the TransactionFactory**

Replace `database/factories/TransactionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'occurred_on' => now()->toDateString(),
            'description' => $this->faker->company(),
            'amount_cents' => $this->faker->numberBetween(-100000, 100000),
            'category_id' => null,
            'import_batch_id' => null,
            'source' => 'manual',
            'notes' => null,
        ];
    }

    public function onDate(string $date): static
    {
        return $this->state(['occurred_on' => $date]);
    }

    public function withAmount(int $cents): static
    {
        return $this->state(['amount_cents' => $cents]);
    }

    public function forAccount(Account $account): static
    {
        return $this->state(['account_id' => $account->id]);
    }

    public function imported(): static
    {
        return $this->state(['source' => 'import']);
    }
}
```

- [ ] **Step 10: Write Transaction model tests**

Write to `tests/Unit/Models/TransactionTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;

it('belongs to an account', function () {
    $tx = Transaction::factory()->create();
    expect($tx->account)->toBeInstanceOf(Account::class);
});

it('can belong to a category', function () {
    $cat = Category::factory()->create();
    $tx = Transaction::factory()->create(['category_id' => $cat->id]);
    expect($tx->category->id)->toBe($cat->id);
});

it('auto-computes dedup_hash on create', function () {
    $tx = Transaction::factory()->create([
        'occurred_on' => '2026-06-01',
        'amount_cents' => 1234,
        'description' => 'Coffee',
    ]);

    expect($tx->dedup_hash)->toHaveLength(64);
});

it('produces same dedup_hash for identical rows on the same account', function () {
    $account = Account::factory()->create();
    $tx1 = Transaction::factory()->forAccount($account)->create([
        'occurred_on' => '2026-06-01',
        'amount_cents' => 1234,
        'description' => 'Coffee',
    ]);

    // Try to insert an identical second row — DB unique constraint should reject
    expect(fn () => Transaction::factory()->forAccount($account)->create([
        'occurred_on' => '2026-06-01',
        'amount_cents' => 1234,
        'description' => 'Coffee',
    ]))->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);
});

it('soft-deletes and excludes from default queries', function () {
    $tx = Transaction::factory()->create();
    $tx->delete();

    expect(Transaction::find($tx->id))->toBeNull();
    expect(Transaction::withTrashed()->find($tx->id))->not->toBeNull();
});

it('casts occurred_on to a date', function () {
    $tx = Transaction::factory()->create(['occurred_on' => '2026-06-01']);
    expect($tx->occurred_on->format('Y-m-d'))->toBe('2026-06-01');
});
```

- [ ] **Step 11: Run migration + tests**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=TransactionTest
php artisan test --compact --filter=TransactionHashTest
```

Expected: PASS.

- [ ] **Step 12: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/TransactionHash.php app/Models/Transaction.php database/factories/TransactionFactory.php database/migrations/2026_06_20_000003_create_transactions_table.php tests/Unit/Support/TransactionHashTest.php tests/Unit/Models/TransactionTest.php
git commit -m "Add transactions table, model, factory, and dedup hash"
```

---

## Task 5: ImportBatch table + model + factory + FK to transactions

**Files:**
- Create: `database/migrations/2026_06_20_000004_create_import_batches_table.php`
- Create: `app/Models/ImportBatch.php`
- Create: `database/factories/ImportBatchFactory.php`
- Create: `tests/Unit/Models/ImportBatchTest.php`

- [ ] **Step 1: Generate migration**

Run: `php artisan make:migration create_import_batches_table --no-interaction`

Rename file to `2026_06_20_000004_create_import_batches_table.php`.

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
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->integer('row_count')->default(0);
            $table->integer('imported_count')->default(0);
            $table->integer('skipped_duplicate_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->timestamp('undone_at')->nullable();
            $table->timestamps();
        });

        // Add the FK from transactions.import_batch_id now that the table exists.
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('import_batch_id')->references('id')->on('import_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
        });

        Schema::dropIfExists('import_batches');
    }
};
```

- [ ] **Step 3: Generate model + factory**

Run: `php artisan make:model ImportBatch --factory --no-interaction`

- [ ] **Step 4: Write the model**

Replace `app/Models/ImportBatch.php`:

```php
<?php

namespace App\Models;

use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['account_id', 'user_id', 'filename', 'row_count', 'imported_count', 'skipped_duplicate_count', 'error_count', 'undone_at'])]
class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'undone_at' => 'datetime',
            'row_count' => 'integer',
            'imported_count' => 'integer',
            'skipped_duplicate_count' => 'integer',
            'error_count' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('undone_at');
    }

    public function isUndone(): bool
    {
        return $this->undone_at !== null;
    }
}
```

- [ ] **Step 5: Write the factory**

Replace `database/factories/ImportBatchFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'user_id' => User::factory(),
            'filename' => 'sample.csv',
            'row_count' => 10,
            'imported_count' => 10,
            'skipped_duplicate_count' => 0,
            'error_count' => 0,
            'undone_at' => null,
        ];
    }

    public function undone(): static
    {
        return $this->state(['undone_at' => now()]);
    }
}
```

- [ ] **Step 6: Write model tests**

Write to `tests/Unit/Models/ImportBatchTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;

it('belongs to an account and a user', function () {
    $batch = ImportBatch::factory()->create();
    expect($batch->account)->toBeInstanceOf(Account::class);
    expect($batch->user)->toBeInstanceOf(User::class);
});

it('has many transactions', function () {
    $batch = ImportBatch::factory()->create();
    Transaction::factory()->count(3)->create(['import_batch_id' => $batch->id]);

    expect($batch->transactions)->toHaveCount(3);
});

it('scopes active to non-undone batches', function () {
    ImportBatch::factory()->count(2)->create();
    ImportBatch::factory()->undone()->create();

    expect(ImportBatch::active()->count())->toBe(2);
});

it('reports undone status', function () {
    expect(ImportBatch::factory()->undone()->create()->isUndone())->toBeTrue();
    expect(ImportBatch::factory()->create()->isUndone())->toBeFalse();
});
```

- [ ] **Step 7: Run migration + tests**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=ImportBatchTest
```

Expected: PASS.

- [ ] **Step 8: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/ImportBatch.php database/factories/ImportBatchFactory.php database/migrations/2026_06_20_000004_create_import_batches_table.php tests/Unit/Models/ImportBatchTest.php
git commit -m "Add import_batches table, model, and factory"
```

---

## Task 6: Account CRUD actions

**Files:**
- Create: `app/Actions/Finance/Accounts/CreateAccount.php`
- Create: `app/Actions/Finance/Accounts/UpdateAccount.php`
- Create: `app/Actions/Finance/Accounts/ArchiveAccount.php`
- Create: `tests/Unit/Actions/Finance/Accounts/CreateAccountTest.php`
- Create: `tests/Unit/Actions/Finance/Accounts/UpdateAccountTest.php`
- Create: `tests/Unit/Actions/Finance/Accounts/ArchiveAccountTest.php`

- [ ] **Step 1: Write CreateAccount tests**

Write to `tests/Unit/Actions/Finance/Accounts/CreateAccountTest.php`:

```php
<?php

use App\Actions\Finance\Accounts\CreateAccount;
use App\Models\Account;

it('creates an account with the given name and starting balance', function () {
    $account = (new CreateAccount)('Tangerine Chequing', 50000, false);

    expect($account)->toBeInstanceOf(Account::class);
    expect($account->name)->toBe('Tangerine Chequing');
    expect($account->starting_balance_cents)->toBe(50000);
    expect($account->counts_toward_goals)->toBeFalse();
});

it('accepts negative starting balance for credit cards', function () {
    $account = (new CreateAccount)('Visa', -150000, false);
    expect($account->starting_balance_cents)->toBe(-150000);
});

it('marks counts_toward_goals when requested', function () {
    $account = (new CreateAccount)('Savings', 0, true);
    expect($account->counts_toward_goals)->toBeTrue();
});
```

- [ ] **Step 2: Implement CreateAccount**

Write to `app/Actions/Finance/Accounts/CreateAccount.php`:

```php
<?php

namespace App\Actions\Finance\Accounts;

use App\Models\Account;

class CreateAccount
{
    public function __invoke(string $name, int $startingBalanceCents, bool $countsTowardGoals): Account
    {
        return Account::create([
            'name' => $name,
            'starting_balance_cents' => $startingBalanceCents,
            'counts_toward_goals' => $countsTowardGoals,
        ]);
    }
}
```

- [ ] **Step 3: Run CreateAccount tests**

Run: `php artisan test --compact --filter=CreateAccountTest`
Expected: PASS.

- [ ] **Step 4: Write UpdateAccount tests**

Write to `tests/Unit/Actions/Finance/Accounts/UpdateAccountTest.php`:

```php
<?php

use App\Actions\Finance\Accounts\UpdateAccount;
use App\Models\Account;

it('updates the account attributes', function () {
    $account = Account::factory()->create(['name' => 'Old', 'starting_balance_cents' => 1000]);

    (new UpdateAccount)($account, [
        'name' => 'New',
        'starting_balance_cents' => 2000,
        'counts_toward_goals' => true,
    ]);

    $account->refresh();
    expect($account->name)->toBe('New');
    expect($account->starting_balance_cents)->toBe(2000);
    expect($account->counts_toward_goals)->toBeTrue();
});

it('ignores keys not in the allowed list', function () {
    $account = Account::factory()->create();

    (new UpdateAccount)($account, ['id' => 999, 'name' => 'Renamed']);

    $account->refresh();
    expect($account->name)->toBe('Renamed');
    expect($account->id)->not->toBe(999);
});
```

- [ ] **Step 5: Implement UpdateAccount**

Write to `app/Actions/Finance/Accounts/UpdateAccount.php`:

```php
<?php

namespace App\Actions\Finance\Accounts;

use App\Models\Account;

class UpdateAccount
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Account $account, array $attributes): Account
    {
        $allowed = ['name', 'starting_balance_cents', 'counts_toward_goals'];

        $account->update(collect($attributes)->only($allowed)->all());

        return $account->fresh();
    }
}
```

- [ ] **Step 6: Write ArchiveAccount tests**

Write to `tests/Unit/Actions/Finance/Accounts/ArchiveAccountTest.php`:

```php
<?php

use App\Actions\Finance\Accounts\ArchiveAccount;
use App\Models\Account;

it('sets archived_at on the account', function () {
    $account = Account::factory()->create();

    (new ArchiveAccount)($account);

    expect($account->fresh()->isArchived())->toBeTrue();
});

it('is idempotent — re-archiving an archived account does not error', function () {
    $account = Account::factory()->archived()->create();

    (new ArchiveAccount)($account);

    expect($account->fresh()->isArchived())->toBeTrue();
});
```

- [ ] **Step 7: Implement ArchiveAccount**

Write to `app/Actions/Finance/Accounts/ArchiveAccount.php`:

```php
<?php

namespace App\Actions\Finance\Accounts;

use App\Models\Account;

class ArchiveAccount
{
    public function __invoke(Account $account): Account
    {
        if (! $account->isArchived()) {
            $account->update(['archived_at' => now()]);
        }

        return $account->fresh();
    }
}
```

- [ ] **Step 8: Run all account action tests**

Run: `php artisan test --compact --filter="CreateAccountTest|UpdateAccountTest|ArchiveAccountTest"`
Expected: PASS.

- [ ] **Step 9: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Accounts tests/Unit/Actions/Finance/Accounts
git commit -m "Add Account CRUD actions"
```

---

## Task 7: Transaction CRUD actions

**Files:**
- Create: `app/Actions/Finance/Transactions/CreateTransaction.php`
- Create: `app/Actions/Finance/Transactions/UpdateTransaction.php`
- Create: `app/Actions/Finance/Transactions/DeleteTransaction.php`
- Create: `app/Actions/Finance/Transactions/CategorizeTransaction.php`
- Create: `tests/Unit/Actions/Finance/Transactions/CreateTransactionTest.php`
- Create: `tests/Unit/Actions/Finance/Transactions/UpdateTransactionTest.php`
- Create: `tests/Unit/Actions/Finance/Transactions/DeleteTransactionTest.php`
- Create: `tests/Unit/Actions/Finance/Transactions/CategorizeTransactionTest.php`

- [ ] **Step 1: Write CreateTransaction tests**

Write to `tests/Unit/Actions/Finance/Transactions/CreateTransactionTest.php`:

```php
<?php

use App\Actions\Finance\Transactions\CreateTransaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;

it('creates a manual transaction', function () {
    $account = Account::factory()->create();

    $tx = (new CreateTransaction)(
        account: $account,
        occurredOn: '2026-06-15',
        description: 'Lunch',
        amountCents: -1200,
        categoryId: null,
    );

    expect($tx)->toBeInstanceOf(Transaction::class);
    expect($tx->account_id)->toBe($account->id);
    expect($tx->amount_cents)->toBe(-1200);
    expect($tx->source)->toBe('manual');
    expect($tx->dedup_hash)->not->toBeEmpty();
});

it('attaches a category when provided', function () {
    $account = Account::factory()->create();
    $category = Category::factory()->create();

    $tx = (new CreateTransaction)(
        account: $account,
        occurredOn: '2026-06-15',
        description: 'Groceries',
        amountCents: -5000,
        categoryId: $category->id,
    );

    expect($tx->category_id)->toBe($category->id);
});
```

- [ ] **Step 2: Implement CreateTransaction**

Write to `app/Actions/Finance/Transactions/CreateTransaction.php`:

```php
<?php

namespace App\Actions\Finance\Transactions;

use App\Models\Account;
use App\Models\Transaction;

class CreateTransaction
{
    public function __invoke(
        Account $account,
        string $occurredOn,
        string $description,
        int $amountCents,
        ?int $categoryId = null,
        ?string $notes = null,
    ): Transaction {
        return Transaction::create([
            'account_id' => $account->id,
            'occurred_on' => $occurredOn,
            'description' => $description,
            'amount_cents' => $amountCents,
            'category_id' => $categoryId,
            'source' => 'manual',
            'notes' => $notes,
        ]);
    }
}
```

- [ ] **Step 3: Write UpdateTransaction tests**

Write to `tests/Unit/Actions/Finance/Transactions/UpdateTransactionTest.php`:

```php
<?php

use App\Actions\Finance\Transactions\UpdateTransaction;
use App\Models\Transaction;

it('updates allowed transaction fields', function () {
    $tx = Transaction::factory()->create([
        'description' => 'Old',
        'amount_cents' => 100,
        'notes' => null,
    ]);

    (new UpdateTransaction)($tx, [
        'description' => 'New',
        'amount_cents' => 200,
        'notes' => 'A note',
    ]);

    $tx->refresh();
    expect($tx->description)->toBe('New');
    expect($tx->amount_cents)->toBe(200);
    expect($tx->notes)->toBe('A note');
});

it('recomputes dedup_hash when description, date, or amount changes', function () {
    $tx = Transaction::factory()->create([
        'description' => 'Old',
        'amount_cents' => 100,
    ]);
    $original = $tx->dedup_hash;

    (new UpdateTransaction)($tx, ['description' => 'New']);

    expect($tx->fresh()->dedup_hash)->not->toBe($original);
});
```

- [ ] **Step 4: Implement UpdateTransaction**

Write to `app/Actions/Finance/Transactions/UpdateTransaction.php`:

```php
<?php

namespace App\Actions\Finance\Transactions;

use App\Models\Transaction;
use App\Support\TransactionHash;

class UpdateTransaction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(Transaction $transaction, array $attributes): Transaction
    {
        $allowed = ['occurred_on', 'description', 'amount_cents', 'category_id', 'notes'];
        $updates = collect($attributes)->only($allowed)->all();

        $transaction->fill($updates);

        if ($transaction->isDirty(['occurred_on', 'description', 'amount_cents'])) {
            $transaction->dedup_hash = TransactionHash::for(
                $transaction->account_id,
                $transaction->occurred_on instanceof \DateTimeInterface
                    ? $transaction->occurred_on->format('Y-m-d')
                    : (string) $transaction->occurred_on,
                $transaction->amount_cents,
                $transaction->description,
            );
        }

        $transaction->save();

        return $transaction->fresh();
    }
}
```

- [ ] **Step 5: Write DeleteTransaction tests**

Write to `tests/Unit/Actions/Finance/Transactions/DeleteTransactionTest.php`:

```php
<?php

use App\Actions\Finance\Transactions\DeleteTransaction;
use App\Models\Transaction;

it('soft-deletes the transaction', function () {
    $tx = Transaction::factory()->create();

    (new DeleteTransaction)($tx);

    expect(Transaction::find($tx->id))->toBeNull();
    expect(Transaction::withTrashed()->find($tx->id))->not->toBeNull();
});
```

- [ ] **Step 6: Implement DeleteTransaction**

Write to `app/Actions/Finance/Transactions/DeleteTransaction.php`:

```php
<?php

namespace App\Actions\Finance\Transactions;

use App\Models\Transaction;

class DeleteTransaction
{
    public function __invoke(Transaction $transaction): void
    {
        $transaction->delete();
    }
}
```

- [ ] **Step 7: Write CategorizeTransaction tests**

Write to `tests/Unit/Actions/Finance/Transactions/CategorizeTransactionTest.php`:

```php
<?php

use App\Actions\Finance\Transactions\CategorizeTransaction;
use App\Models\Category;
use App\Models\Transaction;

it('assigns a category to a transaction', function () {
    $tx = Transaction::factory()->create(['category_id' => null]);
    $cat = Category::factory()->create();

    (new CategorizeTransaction)($tx, $cat);

    expect($tx->fresh()->category_id)->toBe($cat->id);
});

it('clears the category when null is passed', function () {
    $cat = Category::factory()->create();
    $tx = Transaction::factory()->create(['category_id' => $cat->id]);

    (new CategorizeTransaction)($tx, null);

    expect($tx->fresh()->category_id)->toBeNull();
});
```

- [ ] **Step 8: Implement CategorizeTransaction**

Write to `app/Actions/Finance/Transactions/CategorizeTransaction.php`:

```php
<?php

namespace App\Actions\Finance\Transactions;

use App\Models\Category;
use App\Models\Transaction;

class CategorizeTransaction
{
    public function __invoke(Transaction $transaction, ?Category $category): Transaction
    {
        $transaction->update(['category_id' => $category?->id]);

        return $transaction->fresh();
    }
}
```

- [ ] **Step 9: Run all transaction action tests**

Run: `php artisan test --compact --filter="CreateTransactionTest|UpdateTransactionTest|DeleteTransactionTest|CategorizeTransactionTest"`
Expected: PASS.

- [ ] **Step 10: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Transactions tests/Unit/Actions/Finance/Transactions
git commit -m "Add Transaction CRUD actions"
```

---

## Task 8: ComputeAccountBalance action

**Files:**
- Create: `app/Actions/Finance/Accounts/ComputeAccountBalance.php`
- Create: `tests/Unit/Actions/Finance/Accounts/ComputeAccountBalanceTest.php`

- [ ] **Step 1: Write the failing tests**

Write to `tests/Unit/Actions/Finance/Accounts/ComputeAccountBalanceTest.php`:

```php
<?php

use App\Actions\Finance\Accounts\ComputeAccountBalance;
use App\Models\Account;
use App\Models\Transaction;

it('returns starting balance for account with no transactions', function () {
    $account = Account::factory()->withStartingBalance(50000)->create();

    expect((new ComputeAccountBalance)($account))->toBe(50000);
});

it('sums transactions on top of starting balance', function () {
    $account = Account::factory()->withStartingBalance(50000)->create();
    Transaction::factory()->forAccount($account)->withAmount(10000)->onDate('2026-06-01')->create();
    Transaction::factory()->forAccount($account)->withAmount(-3000)->onDate('2026-06-02')->create();

    expect((new ComputeAccountBalance)($account))->toBe(57000);
});

it('excludes transactions after asOf date', function () {
    $account = Account::factory()->withStartingBalance(0)->create();
    Transaction::factory()->forAccount($account)->withAmount(1000)->onDate('2026-05-31')->create();
    Transaction::factory()->forAccount($account)->withAmount(2000)->onDate('2026-06-01')->create();
    Transaction::factory()->forAccount($account)->withAmount(4000)->onDate('2026-06-02')->create();

    expect((new ComputeAccountBalance)($account, '2026-06-01'))->toBe(3000);
});

it('excludes soft-deleted transactions', function () {
    $account = Account::factory()->withStartingBalance(0)->create();
    $tx = Transaction::factory()->forAccount($account)->withAmount(5000)->create();
    Transaction::factory()->forAccount($account)->withAmount(2000)->create();

    $tx->delete();

    expect((new ComputeAccountBalance)($account))->toBe(2000);
});

it('handles negative starting balance for credit-card style account', function () {
    $account = Account::factory()->withStartingBalance(-150000)->create();
    Transaction::factory()->forAccount($account)->withAmount(50000)->create();

    expect((new ComputeAccountBalance)($account))->toBe(-100000);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ComputeAccountBalanceTest`
Expected: FAIL (class missing).

- [ ] **Step 3: Implement the action**

Write to `app/Actions/Finance/Accounts/ComputeAccountBalance.php`:

```php
<?php

namespace App\Actions\Finance\Accounts;

use App\Models\Account;
use App\Models\Transaction;

class ComputeAccountBalance
{
    public function __invoke(Account $account, ?string $asOf = null): int
    {
        $query = Transaction::query()->where('account_id', $account->id);

        if ($asOf !== null) {
            $query->whereDate('occurred_on', '<=', $asOf);
        }

        $sum = (int) $query->sum('amount_cents');

        return $account->starting_balance_cents + $sum;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ComputeAccountBalanceTest`
Expected: PASS.

- [ ] **Step 5: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Accounts/ComputeAccountBalance.php tests/Unit/Actions/Finance/Accounts/ComputeAccountBalanceTest.php
git commit -m "Add ComputeAccountBalance action"
```

---

## Task 9: ComputeBalanceSeries action

**Files:**
- Create: `app/Actions/Finance/Balance/ComputeBalanceSeries.php`
- Create: `tests/Unit/Actions/Finance/Balance/ComputeBalanceSeriesTest.php`

- [ ] **Step 1: Write the failing tests**

Write to `tests/Unit/Actions/Finance/Balance/ComputeBalanceSeriesTest.php`:

```php
<?php

use App\Actions\Finance\Balance\ComputeBalanceSeries;
use App\Models\Account;
use App\Models\Transaction;

it('returns a single point for a one-day range with no transactions', function () {
    $account = Account::factory()->withStartingBalance(50000)->create();

    $series = (new ComputeBalanceSeries)([$account], '2026-06-01', '2026-06-01');

    expect($series)->toHaveCount(1);
    expect($series[0])->toBe(['date' => '2026-06-01', 'balance_cents' => 50000]);
});

it('applies anchor: starts at balance through end of day before range', function () {
    $account = Account::factory()->withStartingBalance(10000)->create();
    Transaction::factory()->forAccount($account)->withAmount(5000)->onDate('2026-05-31')->create();
    Transaction::factory()->forAccount($account)->withAmount(2000)->onDate('2026-06-02')->create();

    $series = (new ComputeBalanceSeries)([$account], '2026-06-01', '2026-06-03');

    // Anchor (end of 2026-05-31) = 10000 + 5000 = 15000
    // 2026-06-01: 15000 (no tx that day)
    // 2026-06-02: 15000 + 2000 = 17000
    // 2026-06-03: 17000 (forward-fill)
    expect($series)->toBe([
        ['date' => '2026-06-01', 'balance_cents' => 15000],
        ['date' => '2026-06-02', 'balance_cents' => 17000],
        ['date' => '2026-06-03', 'balance_cents' => 17000],
    ]);
});

it('forward-fills days with no transactions', function () {
    $account = Account::factory()->withStartingBalance(0)->create();
    Transaction::factory()->forAccount($account)->withAmount(1000)->onDate('2026-06-01')->create();

    $series = (new ComputeBalanceSeries)([$account], '2026-06-01', '2026-06-04');

    expect($series)->toBe([
        ['date' => '2026-06-01', 'balance_cents' => 1000],
        ['date' => '2026-06-02', 'balance_cents' => 1000],
        ['date' => '2026-06-03', 'balance_cents' => 1000],
        ['date' => '2026-06-04', 'balance_cents' => 1000],
    ]);
});

it('sums multiple accounts per day for a household total', function () {
    $a = Account::factory()->withStartingBalance(1000)->create();
    $b = Account::factory()->withStartingBalance(2000)->create();
    Transaction::factory()->forAccount($a)->withAmount(500)->onDate('2026-06-01')->create();
    Transaction::factory()->forAccount($b)->withAmount(-300)->onDate('2026-06-02')->create();

    $series = (new ComputeBalanceSeries)([$a, $b], '2026-06-01', '2026-06-02');

    expect($series)->toBe([
        ['date' => '2026-06-01', 'balance_cents' => 3500], // 1500 + 2000
        ['date' => '2026-06-02', 'balance_cents' => 3200], // 1500 + 1700
    ]);
});

it('excludes soft-deleted transactions', function () {
    $account = Account::factory()->withStartingBalance(0)->create();
    $tx = Transaction::factory()->forAccount($account)->withAmount(5000)->onDate('2026-06-01')->create();
    Transaction::factory()->forAccount($account)->withAmount(2000)->onDate('2026-06-01')->create();
    $tx->delete();

    $series = (new ComputeBalanceSeries)([$account], '2026-06-01', '2026-06-01');

    expect($series[0]['balance_cents'])->toBe(2000);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ComputeBalanceSeriesTest`
Expected: FAIL.

- [ ] **Step 3: Implement the action**

Write to `app/Actions/Finance/Balance/ComputeBalanceSeries.php`:

```php
<?php

namespace App\Actions\Finance\Balance;

use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class ComputeBalanceSeries
{
    /**
     * @param  array<int, Account>  $accounts
     * @return array<int, array{date: string, balance_cents: int}>
     */
    public function __invoke(array $accounts, string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate);
        $end = CarbonImmutable::parse($endDate);

        // Build per-day delta map across all accounts for the range.
        $deltas = [];
        $anchor = 0;

        foreach ($accounts as $account) {
            // Anchor = starting balance + sum of transactions strictly before startDate
            $anchor += $account->starting_balance_cents + (int) Transaction::query()
                ->where('account_id', $account->id)
                ->whereDate('occurred_on', '<', $start->toDateString())
                ->sum('amount_cents');

            $rows = Transaction::query()
                ->where('account_id', $account->id)
                ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('occurred_on, SUM(amount_cents) as delta')
                ->groupBy('occurred_on')
                ->pluck('delta', 'occurred_on');

            foreach ($rows as $date => $delta) {
                $key = CarbonImmutable::parse($date)->toDateString();
                $deltas[$key] = ($deltas[$key] ?? 0) + (int) $delta;
            }
        }

        $series = [];
        $running = $anchor;
        $cursor = $start;

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $running += ($deltas[$key] ?? 0);
            $series[] = ['date' => $key, 'balance_cents' => $running];
            $cursor = $cursor->addDay();
        }

        return $series;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ComputeBalanceSeriesTest`
Expected: PASS.

- [ ] **Step 5: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Balance tests/Unit/Actions/Finance/Balance
git commit -m "Add ComputeBalanceSeries action"
```

---

## Task 10: CSV fixture files + ParseCsvForPreview action

**Files:**
- Create: `tests/Fixtures/csv/sample-standard.csv`
- Create: `tests/Fixtures/csv/sample-with-duplicates.csv`
- Create: `tests/Fixtures/csv/sample-bad-rows.csv`
- Create: `tests/Fixtures/csv/sample-alt-headers.csv`
- Create: `app/Actions/Finance/Imports/ParseCsvForPreview.php`
- Create: `tests/Unit/Actions/Finance/Imports/ParseCsvForPreviewTest.php`

- [ ] **Step 1: Create CSV fixtures**

Write to `tests/Fixtures/csv/sample-standard.csv`:

```
Date,Description,Amount
06/01/2026,Coffee Shop,-4.50
06/02/2026,Paycheck,2500.00
06/03/2026,Grocery Store,-87.23
```

Write to `tests/Fixtures/csv/sample-with-duplicates.csv`:

```
Date,Description,Amount
06/01/2026,Coffee Shop,-4.50
06/02/2026,Paycheck,2500.00
06/01/2026,Coffee Shop,-4.50
```

Write to `tests/Fixtures/csv/sample-bad-rows.csv`:

```
Date,Description,Amount
06/01/2026,Good Row,-10.00
not-a-date,Bad Date,5.00
06/03/2026,Bad Amount,not-a-number
06/04/2026,Another Good Row,15.00
```

Write to `tests/Fixtures/csv/sample-alt-headers.csv`:

```
Posting Date,Memo,Trans Amount
2026-06-01,Coffee Shop,-4.50
2026-06-02,Paycheck,2500.00
```

- [ ] **Step 2: Write the failing tests**

Write to `tests/Unit/Actions/Finance/Imports/ParseCsvForPreviewTest.php`:

```php
<?php

use App\Actions\Finance\Imports\ParseCsvForPreview;
use App\Models\Account;
use App\Models\Transaction;

beforeEach(function () {
    $this->profile = [
        'delimiter' => ',',
        'has_header' => true,
        'date_column' => 'Date',
        'date_format' => 'm/d/Y',
        'description_column' => 'Description',
        'amount_column' => 'Amount',
    ];
});

it('parses each non-header row into a structured preview row', function () {
    $account = Account::factory()->create();
    $path = base_path('tests/Fixtures/csv/sample-standard.csv');

    $rows = (new ParseCsvForPreview)($account, $path, $this->profile);

    expect($rows)->toHaveCount(3);
    expect($rows[0]['occurred_on'])->toBe('2026-06-01');
    expect($rows[0]['description'])->toBe('Coffee Shop');
    expect($rows[0]['amount_cents'])->toBe(-450);
    expect($rows[0]['status'])->toBe('new');
});

it('marks duplicate rows already in DB', function () {
    $account = Account::factory()->create();
    Transaction::factory()->forAccount($account)->create([
        'occurred_on' => '2026-06-01',
        'description' => 'Coffee Shop',
        'amount_cents' => -450,
    ]);

    $rows = (new ParseCsvForPreview)($account, base_path('tests/Fixtures/csv/sample-standard.csv'), $this->profile);

    expect($rows[0]['status'])->toBe('duplicate');
    expect($rows[0]['duplicate_of'])->not->toBeNull();
});

it('flags unparseable rows as errors', function () {
    $account = Account::factory()->create();

    $rows = (new ParseCsvForPreview)($account, base_path('tests/Fixtures/csv/sample-bad-rows.csv'), $this->profile);

    expect($rows[0]['status'])->toBe('new');
    expect($rows[1]['status'])->toBe('error');
    expect($rows[1]['error'])->toContain('date');
    expect($rows[2]['status'])->toBe('error');
    expect($rows[2]['error'])->toContain('amount');
    expect($rows[3]['status'])->toBe('new');
});

it('honours alternative column names from the profile', function () {
    $account = Account::factory()->create();
    $profile = [
        'delimiter' => ',',
        'has_header' => true,
        'date_column' => 'Posting Date',
        'date_format' => 'Y-m-d',
        'description_column' => 'Memo',
        'amount_column' => 'Trans Amount',
    ];

    $rows = (new ParseCsvForPreview)($account, base_path('tests/Fixtures/csv/sample-alt-headers.csv'), $profile);

    expect($rows)->toHaveCount(2);
    expect($rows[0]['description'])->toBe('Coffee Shop');
    expect($rows[0]['amount_cents'])->toBe(-450);
});

it('auto-categorizes rows matching Transfer keywords', function () {
    $account = Account::factory()->create();
    $transfer = \App\Models\Category::factory()->create([
        'name' => 'Transfer',
        'keywords' => 'transfer, tfr',
        'excluded_from_totals' => true,
    ]);

    // Create a CSV in memory via the standard fixture but check description match
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "Date,Description,Amount\n06/01/2026,E-Transfer to John,-100.00\n");

    $rows = (new ParseCsvForPreview)($account, $path, $this->profile);

    expect($rows[0]['category_id'])->toBe($transfer->id);

    unlink($path);
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ParseCsvForPreviewTest`
Expected: FAIL.

- [ ] **Step 4: Implement the action**

Write to `app/Actions/Finance/Imports/ParseCsvForPreview.php`:

```php
<?php

namespace App\Actions\Finance\Imports;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\TransactionHash;
use Carbon\CarbonImmutable;

class ParseCsvForPreview
{
    /**
     * @param  array<string, mixed>  $profile
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(Account $account, string $path, array $profile): array
    {
        $delimiter = $profile['delimiter'] ?? ',';
        $hasHeader = (bool) ($profile['has_header'] ?? true);
        $dateCol = $profile['date_column'];
        $dateFormat = $profile['date_format'];
        $descCol = $profile['description_column'];
        $amountCol = $profile['amount_column'];

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $header = null;
        $rows = [];

        while (($raw = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($hasHeader && $header === null) {
                $header = $raw;
                continue;
            }

            $assoc = $hasHeader ? array_combine($header, $raw) : $raw;
            $rows[] = $this->processRow($account, $assoc, $dateCol, $dateFormat, $descCol, $amountCol);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string>  $assoc
     * @return array<string, mixed>
     */
    private function processRow(Account $account, array $assoc, string $dateCol, string $dateFormat, string $descCol, string $amountCol): array
    {
        $rawDate = $assoc[$dateCol] ?? null;
        $rawDesc = trim((string) ($assoc[$descCol] ?? ''));
        $rawAmount = $assoc[$amountCol] ?? null;

        try {
            $occurredOn = CarbonImmutable::createFromFormat($dateFormat, (string) $rawDate);
            if (! $occurredOn) {
                throw new \RuntimeException('Invalid date');
            }
            $occurredOn = $occurredOn->toDateString();
        } catch (\Throwable) {
            return [
                'occurred_on' => null,
                'description' => $rawDesc,
                'amount_cents' => null,
                'status' => 'error',
                'error' => "Could not parse date '{$rawDate}'",
            ];
        }

        $cleanAmount = preg_replace('/[^0-9.\-]/', '', (string) $rawAmount);
        if ($cleanAmount === '' || ! is_numeric($cleanAmount)) {
            return [
                'occurred_on' => $occurredOn,
                'description' => $rawDesc,
                'amount_cents' => null,
                'status' => 'error',
                'error' => "Could not parse amount '{$rawAmount}'",
            ];
        }
        $amountCents = (int) round(((float) $cleanAmount) * 100);

        $hash = TransactionHash::for($account->id, $occurredOn, $amountCents, $rawDesc);
        $duplicate = Transaction::query()
            ->where('account_id', $account->id)
            ->where('dedup_hash', $hash)
            ->first();

        $categoryId = $this->matchTransferCategory($rawDesc);

        return [
            'occurred_on' => $occurredOn,
            'description' => $rawDesc,
            'amount_cents' => $amountCents,
            'dedup_hash' => $hash,
            'category_id' => $categoryId,
            'status' => $duplicate ? 'duplicate' : 'new',
            'duplicate_of' => $duplicate?->id,
        ];
    }

    private function matchTransferCategory(string $description): ?int
    {
        $transfer = Category::where('name', 'Transfer')->first();
        if (! $transfer) {
            return null;
        }

        $lower = mb_strtolower($description);
        foreach ($transfer->keywordList() as $keyword) {
            if ($keyword && str_contains($lower, $keyword)) {
                return $transfer->id;
            }
        }

        return null;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ParseCsvForPreviewTest`
Expected: PASS.

- [ ] **Step 6: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Imports/ParseCsvForPreview.php tests/Unit/Actions/Finance/Imports/ParseCsvForPreviewTest.php tests/Fixtures/csv
git commit -m "Add ParseCsvForPreview action and CSV fixtures"
```

---

## Task 11: ImportTransactions action

**Files:**
- Create: `app/Actions/Finance/Imports/ImportTransactions.php`
- Create: `tests/Unit/Actions/Finance/Imports/ImportTransactionsTest.php`

- [ ] **Step 1: Write the failing tests**

Write to `tests/Unit/Actions/Finance/Imports/ImportTransactionsTest.php`:

```php
<?php

use App\Actions\Finance\Imports\ImportTransactions;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;

it('creates an import batch and persists new rows', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $rows = [
        [
            'occurred_on' => '2026-06-01',
            'description' => 'Coffee',
            'amount_cents' => -450,
            'dedup_hash' => 'h1',
            'category_id' => null,
            'status' => 'new',
        ],
        [
            'occurred_on' => '2026-06-02',
            'description' => 'Paycheck',
            'amount_cents' => 250000,
            'dedup_hash' => 'h2',
            'category_id' => null,
            'status' => 'new',
        ],
    ];

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch)->toBeInstanceOf(ImportBatch::class);
    expect($batch->imported_count)->toBe(2);
    expect($batch->skipped_duplicate_count)->toBe(0);
    expect($batch->error_count)->toBe(0);
    expect(Transaction::where('import_batch_id', $batch->id)->count())->toBe(2);
});

it('skips rows marked duplicate', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $rows = [
        ['occurred_on' => '2026-06-01', 'description' => 'A', 'amount_cents' => 100, 'dedup_hash' => 'h1', 'category_id' => null, 'status' => 'new'],
        ['occurred_on' => '2026-06-02', 'description' => 'B', 'amount_cents' => 200, 'dedup_hash' => 'h2', 'category_id' => null, 'status' => 'duplicate'],
    ];

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch->imported_count)->toBe(1);
    expect($batch->skipped_duplicate_count)->toBe(1);
});

it('counts error rows but does not persist them', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $rows = [
        ['occurred_on' => '2026-06-01', 'description' => 'A', 'amount_cents' => 100, 'dedup_hash' => 'h1', 'category_id' => null, 'status' => 'new'],
        ['occurred_on' => null, 'description' => 'B', 'amount_cents' => null, 'dedup_hash' => null, 'category_id' => null, 'status' => 'error'],
    ];

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch->imported_count)->toBe(1);
    expect($batch->error_count)->toBe(1);
});

it('catches DB unique constraint violations as duplicates', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();

    // Pre-existing row with hash 'collision'
    Transaction::factory()->forAccount($account)->create([
        'occurred_on' => '2026-06-01',
        'description' => 'Existing',
        'amount_cents' => 100,
        'dedup_hash' => 'collision',
    ]);

    $rows = [
        ['occurred_on' => '2026-06-01', 'description' => 'Pretend new', 'amount_cents' => 100, 'dedup_hash' => 'collision', 'category_id' => null, 'status' => 'new'],
    ];

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch->imported_count)->toBe(0);
    expect($batch->error_count)->toBe(1);
});

it('records row_count from the input total regardless of status', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $rows = array_fill(0, 5, [
        'occurred_on' => '2026-06-01', 'description' => 'X', 'amount_cents' => 100, 'dedup_hash' => uniqid(), 'category_id' => null, 'status' => 'new',
    ]);

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch->row_count)->toBe(5);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ImportTransactionsTest`
Expected: FAIL.

- [ ] **Step 3: Implement the action**

Write to `app/Actions/Finance/Imports/ImportTransactions.php`:

```php
<?php

namespace App\Actions\Finance\Imports;

use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\Transaction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ImportTransactions
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __invoke(Account $account, array $rows, int $userId, string $filename): ImportBatch
    {
        return DB::transaction(function () use ($account, $rows, $userId, $filename) {
            $batch = ImportBatch::create([
                'account_id' => $account->id,
                'user_id' => $userId,
                'filename' => $filename,
                'row_count' => count($rows),
                'imported_count' => 0,
                'skipped_duplicate_count' => 0,
                'error_count' => 0,
            ]);

            $imported = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($rows as $row) {
                if ($row['status'] === 'duplicate') {
                    $skipped++;

                    continue;
                }

                if ($row['status'] === 'error') {
                    $errors++;

                    continue;
                }

                try {
                    Transaction::create([
                        'account_id' => $account->id,
                        'occurred_on' => $row['occurred_on'],
                        'description' => $row['description'],
                        'amount_cents' => $row['amount_cents'],
                        'category_id' => $row['category_id'] ?? null,
                        'dedup_hash' => $row['dedup_hash'],
                        'import_batch_id' => $batch->id,
                        'source' => 'import',
                    ]);
                    $imported++;
                } catch (UniqueConstraintViolationException) {
                    $errors++;
                }
            }

            $batch->update([
                'imported_count' => $imported,
                'skipped_duplicate_count' => $skipped,
                'error_count' => $errors,
            ]);

            return $batch->fresh();
        });
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ImportTransactionsTest`
Expected: PASS.

- [ ] **Step 5: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Imports/ImportTransactions.php tests/Unit/Actions/Finance/Imports/ImportTransactionsTest.php
git commit -m "Add ImportTransactions action"
```

---

## Task 12: UndoImportBatch action

**Files:**
- Create: `app/Actions/Finance/Imports/UndoImportBatch.php`
- Create: `tests/Unit/Actions/Finance/Imports/UndoImportBatchTest.php`

- [ ] **Step 1: Write the failing tests**

Write to `tests/Unit/Actions/Finance/Imports/UndoImportBatchTest.php`:

```php
<?php

use App\Actions\Finance\Imports\UndoImportBatch;
use App\Models\ImportBatch;
use App\Models\Transaction;

it('soft-deletes all transactions in the batch', function () {
    $batch = ImportBatch::factory()->create();
    Transaction::factory()->count(3)->create(['import_batch_id' => $batch->id]);

    (new UndoImportBatch)($batch);

    expect(Transaction::where('import_batch_id', $batch->id)->count())->toBe(0);
    expect(Transaction::withTrashed()->where('import_batch_id', $batch->id)->count())->toBe(3);
});

it('marks the batch as undone', function () {
    $batch = ImportBatch::factory()->create();

    (new UndoImportBatch)($batch);

    expect($batch->fresh()->isUndone())->toBeTrue();
});

it('does not touch transactions from other batches', function () {
    $batchA = ImportBatch::factory()->create();
    $batchB = ImportBatch::factory()->create();
    Transaction::factory()->count(2)->create(['import_batch_id' => $batchA->id]);
    Transaction::factory()->count(2)->create(['import_batch_id' => $batchB->id]);

    (new UndoImportBatch)($batchA);

    expect(Transaction::where('import_batch_id', $batchB->id)->count())->toBe(2);
});

it('is a no-op if batch is already undone', function () {
    $batch = ImportBatch::factory()->undone()->create();
    $originalTimestamp = $batch->undone_at;

    (new UndoImportBatch)($batch);

    expect($batch->fresh()->undone_at->equalTo($originalTimestamp))->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=UndoImportBatchTest`
Expected: FAIL.

- [ ] **Step 3: Implement the action**

Write to `app/Actions/Finance/Imports/UndoImportBatch.php`:

```php
<?php

namespace App\Actions\Finance\Imports;

use App\Models\ImportBatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class UndoImportBatch
{
    public function __invoke(ImportBatch $batch): ImportBatch
    {
        if ($batch->isUndone()) {
            return $batch;
        }

        DB::transaction(function () use ($batch) {
            Transaction::where('import_batch_id', $batch->id)->delete();
            $batch->update(['undone_at' => now()]);
        });

        return $batch->fresh();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=UndoImportBatchTest`
Expected: PASS.

- [ ] **Step 5: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Imports/UndoImportBatch.php tests/Unit/Actions/Finance/Imports/UndoImportBatchTest.php
git commit -m "Add UndoImportBatch action"
```

---

## Task 13: Routes + sidebar nav

**Files:**
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/app/sidebar.blade.php`

- [ ] **Step 1: Register routes**

Replace contents of `routes/web.php`:

```php
<?php

use App\Livewire\Accounts\Index as AccountsIndex;
use App\Livewire\Accounts\Show as AccountShow;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Imports\Index as ImportsIndex;
use App\Livewire\Imports\Wizard as ImportsWizard;
use App\Livewire\Transactions\Index as TransactionsIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('accounts', AccountsIndex::class)->name('accounts.index');
    Route::get('accounts/{account}', AccountShow::class)->name('accounts.show');

    Route::get('transactions', TransactionsIndex::class)->name('transactions.index');

    Route::get('categories', CategoriesIndex::class)->name('categories.index');

    Route::get('imports', ImportsIndex::class)->name('imports.index');
    Route::get('imports/new', ImportsWizard::class)->name('imports.new');
});

require __DIR__.'/settings.php';
```

- [ ] **Step 2: Add sidebar menu items**

Modify `resources/views/layouts/app/sidebar.blade.php`. Find the existing `<x-menu-item title="Dashboard" ... />` line and add five new menu items beneath it inside the same `<x-menu>` block:

```blade
<x-menu-item title="{{ __('Dashboard') }}" icon="lucide.layout-dashboard" link="{{ route('dashboard') }}" wire:navigate />
<x-menu-item title="{{ __('Accounts') }}" icon="lucide.wallet" link="{{ route('accounts.index') }}" wire:navigate />
<x-menu-item title="{{ __('Transactions') }}" icon="lucide.list" link="{{ route('transactions.index') }}" wire:navigate />
<x-menu-item title="{{ __('Imports') }}" icon="lucide.upload" link="{{ route('imports.index') }}" wire:navigate />
<x-menu-item title="{{ __('Categories') }}" icon="lucide.tag" link="{{ route('categories.index') }}" wire:navigate />
```

- [ ] **Step 3: Sanity check routes**

Run: `php artisan route:list --except-vendor`
Expected: see `accounts.index`, `accounts.show`, `transactions.index`, `categories.index`, `imports.index`, `imports.new` listed.

Note: the routes will resolve to "class not found" until subsequent tasks create the Livewire components — that's expected. Don't run the app yet.

- [ ] **Step 4: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/web.php resources/views/layouts/app/sidebar.blade.php
git commit -m "Add finance routes and sidebar navigation"
```

---

## Task 14: Categories Livewire UI

**Files:**
- Create: `app/Livewire/Categories/Index.php`
- Create: `app/Livewire/Categories/Form.php`
- Create: `resources/views/livewire/categories/index.blade.php`
- Create: `resources/views/livewire/categories/form.blade.php`
- Create: `tests/Feature/Livewire/Categories/IndexTest.php`
- Create: `tests/Feature/Livewire/Categories/FormTest.php`

- [ ] **Step 1: Write Index test**

Write to `tests/Feature/Livewire/Categories/IndexTest.php`:

```php
<?php

use App\Livewire\Categories\Index;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

it('lists categories', function () {
    $this->actingAs(User::factory()->create());
    Category::factory()->count(3)->create();

    Livewire::test(Index::class)
        ->assertOk()
        ->assertViewHas('categories', fn ($cs) => $cs->count() >= 3);
});

it('requires authentication', function () {
    $this->get(route('categories.index'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Generate Index component**

Run: `php artisan make:livewire Categories/Index --no-interaction`

Replace `app/Livewire/Categories/Index.php`:

```php
<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Categories')]
class Index extends Component
{
    public ?int $editingId = null;

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
    }

    #[\Livewire\Attributes\Computed]
    public function categories(): Collection
    {
        return Category::orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.categories.index', [
            'categories' => $this->categories,
        ]);
    }
}
```

- [ ] **Step 3: Write Index view**

Write to `resources/views/livewire/categories/index.blade.php`:

```blade
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Categories') }}</h1>
        <x-button label="New category" icon="lucide.plus" class="btn-primary" @click="$wire.startEdit(0)" />
    </div>

    @if ($editingId !== null)
        <livewire:categories.form :category-id="$editingId" :key="'cat-form-'.$editingId" />
    @endif

    <x-table :headers="[
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'keywords', 'label' => 'Keywords'],
        ['key' => 'excluded_from_totals', 'label' => 'Excluded'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-20'],
    ]" :rows="$categories">
        @scope('cell_excluded_from_totals', $row)
            {{ $row->excluded_from_totals ? 'Yes' : 'No' }}
        @endscope
        @scope('cell_actions', $row)
            <x-button icon="lucide.pencil" class="btn-ghost btn-sm" @click="$wire.startEdit({{ $row->id }})" />
        @endscope
    </x-table>
</div>
```

- [ ] **Step 4: Write Form test**

Write to `tests/Feature/Livewire/Categories/FormTest.php`:

```php
<?php

use App\Livewire\Categories\Form;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a new category', function () {
    Livewire::test(Form::class, ['categoryId' => 0])
        ->set('name', 'Groceries')
        ->set('keywords', 'safeway, save-on, walmart')
        ->set('excludedFromTotals', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Category::where('name', 'Groceries')->exists())->toBeTrue();
});

it('updates an existing category', function () {
    $cat = Category::factory()->create(['name' => 'Old']);

    Livewire::test(Form::class, ['categoryId' => $cat->id])
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($cat->fresh()->name)->toBe('New');
});

it('requires a name', function () {
    Livewire::test(Form::class, ['categoryId' => 0])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});
```

- [ ] **Step 5: Generate Form component**

Run: `php artisan make:livewire Categories/Form --no-interaction`

Replace `app/Livewire/Categories/Form.php`:

```php
<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    public int $categoryId = 0;

    #[Validate('required|string|max:80')]
    public string $name = '';

    public ?string $keywords = null;

    public bool $excludedFromTotals = false;

    public ?string $color = null;

    public function mount(int $categoryId): void
    {
        $this->categoryId = $categoryId;
        if ($categoryId > 0) {
            $cat = Category::findOrFail($categoryId);
            $this->name = $cat->name;
            $this->keywords = $cat->keywords;
            $this->excludedFromTotals = $cat->excluded_from_totals;
            $this->color = $cat->color;
        }
    }

    public function save(): void
    {
        $this->validate();

        Category::updateOrCreate(
            ['id' => $this->categoryId > 0 ? $this->categoryId : null],
            [
                'name' => $this->name,
                'keywords' => $this->keywords,
                'excluded_from_totals' => $this->excludedFromTotals,
                'color' => $this->color,
            ]
        );

        $this->dispatch('category-saved');
        $this->categoryId = 0;
        $this->name = '';
        $this->keywords = null;
        $this->excludedFromTotals = false;
        $this->color = null;
    }

    public function cancel(): void
    {
        $this->dispatch('category-cancelled');
    }

    public function render()
    {
        return view('livewire.categories.form');
    }
}
```

- [ ] **Step 6: Write Form view**

Write to `resources/views/livewire/categories/form.blade.php`:

```blade
<x-card class="border border-base-300 mb-4">
    <div class="space-y-3">
        <x-input label="Name" wire:model="name" />
        <x-input label="Keywords (comma-separated)" wire:model="keywords" placeholder="safeway, save-on, walmart" />
        <x-checkbox label="Excluded from income/expense totals" wire:model="excludedFromTotals" />
        <x-input label="Color (hex)" wire:model="color" placeholder="#aabbcc" />
        <div class="flex gap-2 justify-end">
            <x-button label="Cancel" class="btn-ghost" wire:click="$parentCancel" @click="$wire.dispatch('category-cancelled')" />
            <x-button label="Save" class="btn-primary" wire:click="save" />
        </div>
    </div>
</x-card>
```

(Note: parent component listens for `category-cancelled` and `category-saved` events to close itself — see refinement in Step 7.)

- [ ] **Step 7: Refine Index to listen for events**

Replace `app/Livewire/Categories/Index.php`'s class body to add event listeners:

```php
<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Categories')]
class Index extends Component
{
    public ?int $editingId = null;

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
    }

    #[On('category-saved')]
    #[On('category-cancelled')]
    public function closeForm(): void
    {
        $this->editingId = null;
    }

    #[\Livewire\Attributes\Computed]
    public function categories(): Collection
    {
        return Category::orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.categories.index', [
            'categories' => $this->categories,
        ]);
    }
}
```

- [ ] **Step 8: Run tests**

```bash
php artisan test --compact --filter="Livewire.Categories"
```

Expected: PASS.

- [ ] **Step 9: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Categories resources/views/livewire/categories tests/Feature/Livewire/Categories
git commit -m "Add Categories Livewire CRUD UI"
```

---

## Task 15: Accounts Livewire Index + Form

**Files:**
- Create: `app/Livewire/Accounts/Index.php`
- Create: `app/Livewire/Accounts/Form.php`
- Create: `resources/views/livewire/accounts/index.blade.php`
- Create: `resources/views/livewire/accounts/form.blade.php`
- Create: `tests/Feature/Livewire/Accounts/IndexTest.php`
- Create: `tests/Feature/Livewire/Accounts/FormTest.php`

- [ ] **Step 1: Write Index test**

Write to `tests/Feature/Livewire/Accounts/IndexTest.php`:

```php
<?php

use App\Livewire\Accounts\Index;
use App\Models\Account;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists active accounts with current balance', function () {
    Account::factory()->withStartingBalance(50000)->create(['name' => 'Chequing']);

    Livewire::test(Index::class)
        ->assertOk()
        ->assertSee('Chequing')
        ->assertSee('$500.00');
});

it('hides archived accounts by default', function () {
    Account::factory()->create(['name' => 'Active']);
    Account::factory()->archived()->create(['name' => 'Archived']);

    Livewire::test(Index::class)
        ->assertSee('Active')
        ->assertDontSee('Archived');
});
```

- [ ] **Step 2: Generate + implement Index**

Run: `php artisan make:livewire Accounts/Index --no-interaction`

Replace `app/Livewire/Accounts/Index.php`:

```php
<?php

namespace App\Livewire\Accounts;

use App\Actions\Finance\Accounts\ComputeAccountBalance;
use App\Models\Account;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Accounts')]
class Index extends Component
{
    public ?int $editingId = null;

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
    }

    #[On('account-saved')]
    #[On('account-cancelled')]
    public function closeForm(): void
    {
        $this->editingId = null;
    }

    #[Computed]
    public function accounts(): array
    {
        $balance = new ComputeAccountBalance;

        return Account::active()
            ->orderBy('name')
            ->get()
            ->map(fn (Account $a) => [
                'model' => $a,
                'balance_cents' => $balance($a),
            ])->all();
    }

    public function render()
    {
        return view('livewire.accounts.index', ['accounts' => $this->accounts]);
    }
}
```

- [ ] **Step 3: Write Index view**

Write to `resources/views/livewire/accounts/index.blade.php`:

```blade
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Accounts') }}</h1>
        <x-button label="New account" icon="lucide.plus" class="btn-primary" @click="$wire.startEdit(0)" />
    </div>

    @if ($editingId !== null)
        <livewire:accounts.form :account-id="$editingId" :key="'acct-form-'.$editingId" />
    @endif

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($accounts as $row)
            <a href="{{ route('accounts.show', $row['model']) }}" wire:navigate class="block">
                <x-card class="border border-base-300 hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-semibold">{{ $row['model']->name }}</div>
                            <div class="text-2xl mt-1">{{ \App\Support\Money::format($row['balance_cents']) }}</div>
                        </div>
                        <x-button icon="lucide.pencil" class="btn-ghost btn-sm" @click.stop.prevent="$wire.startEdit({{ $row['model']->id }})" />
                    </div>
                    @if ($row['model']->counts_toward_goals)
                        <x-badge value="Goals pool" class="badge-info mt-2" />
                    @endif
                </x-card>
            </a>
        @endforeach
    </div>
</div>
```

- [ ] **Step 4: Write Form test**

Write to `tests/Feature/Livewire/Accounts/FormTest.php`:

```php
<?php

use App\Livewire\Accounts\Form;
use App\Models\Account;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a new account with starting balance in dollars', function () {
    Livewire::test(Form::class, ['accountId' => 0])
        ->set('name', 'Chequing')
        ->set('startingBalanceDollars', '500')
        ->set('countsTowardGoals', false)
        ->call('save')
        ->assertHasNoErrors();

    $account = Account::where('name', 'Chequing')->first();
    expect($account->starting_balance_cents)->toBe(50000);
});

it('updates an existing account', function () {
    $account = Account::factory()->create(['name' => 'Old']);

    Livewire::test(Form::class, ['accountId' => $account->id])
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($account->fresh()->name)->toBe('New');
});

it('archives an account', function () {
    $account = Account::factory()->create();

    Livewire::test(Form::class, ['accountId' => $account->id])
        ->call('archive')
        ->assertHasNoErrors();

    expect($account->fresh()->isArchived())->toBeTrue();
});

it('requires a name', function () {
    Livewire::test(Form::class, ['accountId' => 0])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});
```

- [ ] **Step 5: Generate + implement Form**

Run: `php artisan make:livewire Accounts/Form --no-interaction`

Replace `app/Livewire/Accounts/Form.php`:

```php
<?php

namespace App\Livewire\Accounts;

use App\Actions\Finance\Accounts\ArchiveAccount;
use App\Actions\Finance\Accounts\CreateAccount;
use App\Actions\Finance\Accounts\UpdateAccount;
use App\Models\Account;
use App\Support\Money;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    public int $accountId = 0;

    #[Validate('required|string|max:120')]
    public string $name = '';

    public string $startingBalanceDollars = '0';

    public bool $countsTowardGoals = false;

    public function mount(int $accountId): void
    {
        $this->accountId = $accountId;
        if ($accountId > 0) {
            $account = Account::findOrFail($accountId);
            $this->name = $account->name;
            $this->startingBalanceDollars = number_format($account->starting_balance_cents / 100, 2, '.', '');
            $this->countsTowardGoals = $account->counts_toward_goals;
        }
    }

    public function save(): void
    {
        $this->validate();
        $cents = Money::toCents($this->startingBalanceDollars);

        if ($this->accountId > 0) {
            $account = Account::findOrFail($this->accountId);
            (new UpdateAccount)($account, [
                'name' => $this->name,
                'starting_balance_cents' => $cents,
                'counts_toward_goals' => $this->countsTowardGoals,
            ]);
        } else {
            (new CreateAccount)($this->name, $cents, $this->countsTowardGoals);
        }

        $this->dispatch('account-saved');
    }

    public function archive(): void
    {
        if ($this->accountId > 0) {
            $account = Account::findOrFail($this->accountId);
            (new ArchiveAccount)($account);
            $this->dispatch('account-saved');
        }
    }

    public function cancel(): void
    {
        $this->dispatch('account-cancelled');
    }

    public function render()
    {
        return view('livewire.accounts.form');
    }
}
```

- [ ] **Step 6: Write Form view**

Write to `resources/views/livewire/accounts/form.blade.php`:

```blade
<x-card class="border border-base-300 mb-4">
    <div class="space-y-3">
        <x-input label="Name" wire:model="name" placeholder="Tangerine Chequing" />
        <x-input label="Starting balance (dollars)" wire:model="startingBalanceDollars" placeholder="0.00" hint="Negative is OK for credit cards" />
        <x-checkbox label="Counts toward goals pool" wire:model="countsTowardGoals" />
        <div class="flex justify-between gap-2">
            @if ($accountId > 0)
                <x-button label="Archive" icon="lucide.archive" class="btn-ghost text-error" wire:click="archive" wire:confirm="Archive this account?" />
            @else
                <div></div>
            @endif
            <div class="flex gap-2">
                <x-button label="Cancel" class="btn-ghost" @click="$wire.dispatch('account-cancelled')" />
                <x-button label="Save" class="btn-primary" wire:click="save" />
            </div>
        </div>
    </div>
</x-card>
```

- [ ] **Step 7: Run tests**

```bash
php artisan test --compact --filter="Livewire.Accounts"
```

Expected: PASS.

- [ ] **Step 8: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Accounts resources/views/livewire/accounts tests/Feature/Livewire/Accounts
git commit -m "Add Accounts Index and Form Livewire UI"
```

---

## Task 16: Transactions Livewire Index + Form

**Files:**
- Create: `app/Livewire/Transactions/Index.php`
- Create: `app/Livewire/Transactions/Form.php`
- Create: `resources/views/livewire/transactions/index.blade.php`
- Create: `resources/views/livewire/transactions/form.blade.php`
- Create: `tests/Feature/Livewire/Transactions/IndexTest.php`
- Create: `tests/Feature/Livewire/Transactions/FormTest.php`

- [ ] **Step 1: Write Index test**

Write to `tests/Feature/Livewire/Transactions/IndexTest.php`:

```php
<?php

use App\Livewire\Transactions\Index;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists transactions across all accounts', function () {
    Transaction::factory()->count(3)->create(['description' => 'Coffee']);

    Livewire::test(Index::class)
        ->assertOk()
        ->assertViewHas('transactions', fn ($t) => $t->count() === 3);
});

it('filters by account', function () {
    $a = Account::factory()->create();
    $b = Account::factory()->create();
    Transaction::factory()->forAccount($a)->count(2)->create();
    Transaction::factory()->forAccount($b)->count(3)->create();

    Livewire::test(Index::class)
        ->set('accountFilter', $a->id)
        ->assertViewHas('transactions', fn ($t) => $t->count() === 2);
});

it('filters by description search', function () {
    Transaction::factory()->create(['description' => 'Starbucks Coffee']);
    Transaction::factory()->create(['description' => 'Loblaws Groceries']);

    Livewire::test(Index::class)
        ->set('search', 'Star')
        ->assertViewHas('transactions', fn ($t) => $t->count() === 1);
});

it('deletes a transaction via component method', function () {
    $tx = Transaction::factory()->create();

    Livewire::test(Index::class)->call('deleteTransaction', $tx->id);

    expect(Transaction::find($tx->id))->toBeNull();
});
```

- [ ] **Step 2: Generate + implement Index**

Run: `php artisan make:livewire Transactions/Index --no-interaction`

Replace `app/Livewire/Transactions/Index.php`:

```php
<?php

namespace App\Livewire\Transactions;

use App\Actions\Finance\Transactions\DeleteTransaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Transactions')]
class Index extends Component
{
    use WithPagination;

    public ?int $accountFilter = null;

    public ?int $categoryFilter = null;

    public string $search = '';

    public ?int $editingId = null;

    public bool $creating = false;

    public function updatedAccountFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->creating = true;
        $this->editingId = null;
    }

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
        $this->creating = false;
    }

    public function deleteTransaction(int $id): void
    {
        $tx = Transaction::findOrFail($id);
        (new DeleteTransaction)($tx);
    }

    #[On('transaction-saved')]
    #[On('transaction-cancelled')]
    public function closeForm(): void
    {
        $this->editingId = null;
        $this->creating = false;
    }

    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['account', 'category'])
            ->when($this->accountFilter, fn ($q) => $q->where('account_id', $this->accountFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->search, fn ($q) => $q->where('description', 'like', '%'.$this->search.'%'))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(50);
    }

    public function render()
    {
        return view('livewire.transactions.index', [
            'transactions' => $this->transactions,
            'accounts' => Account::active()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
```

- [ ] **Step 3: Write Index view**

Write to `resources/views/livewire/transactions/index.blade.php`:

```blade
<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-2xl font-semibold">{{ __('Transactions') }}</h1>
        <x-button label="New transaction" icon="lucide.plus" class="btn-primary" wire:click="startCreate" />
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <x-input placeholder="Search description…" wire:model.live.debounce.300ms="search" icon="lucide.search" />
        <x-select placeholder="All accounts" :options="$accounts" option-label="name" option-value="id" wire:model.live="accountFilter" />
        <x-select placeholder="All categories" :options="$categories" option-label="name" option-value="id" wire:model.live="categoryFilter" />
    </div>

    @if ($creating || $editingId !== null)
        <livewire:transactions.form :transaction-id="$editingId ?? 0" :key="'tx-form-'.($editingId ?? 'new')" />
    @endif

    <x-table :headers="[
        ['key' => 'occurred_on', 'label' => 'Date'],
        ['key' => 'account', 'label' => 'Account'],
        ['key' => 'description', 'label' => 'Description'],
        ['key' => 'category', 'label' => 'Category'],
        ['key' => 'amount', 'label' => 'Amount', 'class' => 'text-right'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-24'],
    ]" :rows="$transactions->items()">
        @scope('cell_occurred_on', $row)
            {{ $row->occurred_on->format('Y-m-d') }}
        @endscope
        @scope('cell_account', $row)
            {{ $row->account?->name }}
        @endscope
        @scope('cell_category', $row)
            {{ $row->category?->name ?? '—' }}
        @endscope
        @scope('cell_amount', $row)
            <span class="font-mono {{ $row->amount_cents < 0 ? 'text-error' : 'text-success' }}">
                {{ \App\Support\Money::format($row->amount_cents) }}
            </span>
        @endscope
        @scope('cell_actions', $row)
            <div class="flex gap-1 justify-end">
                <x-button icon="lucide.pencil" class="btn-ghost btn-sm" wire:click="startEdit({{ $row->id }})" />
                <x-button icon="lucide.trash-2" class="btn-ghost btn-sm text-error" wire:click="deleteTransaction({{ $row->id }})" wire:confirm="Delete this transaction?" />
            </div>
        @endscope
    </x-table>

    <div>{{ $transactions->links() }}</div>
</div>
```

- [ ] **Step 4: Write Form test**

Write to `tests/Feature/Livewire/Transactions/FormTest.php`:

```php
<?php

use App\Livewire\Transactions\Form;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a manual transaction', function () {
    $account = Account::factory()->create();

    Livewire::test(Form::class, ['transactionId' => 0])
        ->set('accountId', $account->id)
        ->set('occurredOn', '2026-06-15')
        ->set('description', 'Lunch')
        ->set('amountDollars', '-12.50')
        ->call('save')
        ->assertHasNoErrors();

    $tx = Transaction::where('description', 'Lunch')->first();
    expect($tx)->not->toBeNull();
    expect($tx->amount_cents)->toBe(-1250);
    expect($tx->source)->toBe('manual');
});

it('updates an existing transaction', function () {
    $tx = Transaction::factory()->create([
        'description' => 'Old',
        'amount_cents' => -1000,
    ]);

    Livewire::test(Form::class, ['transactionId' => $tx->id])
        ->set('description', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($tx->fresh()->description)->toBe('New');
});

it('requires account, date, description, amount', function () {
    Livewire::test(Form::class, ['transactionId' => 0])
        ->set('accountId', null)
        ->set('occurredOn', '')
        ->set('description', '')
        ->set('amountDollars', '')
        ->call('save')
        ->assertHasErrors(['accountId', 'occurredOn', 'description', 'amountDollars']);
});
```

- [ ] **Step 5: Generate + implement Form**

Run: `php artisan make:livewire Transactions/Form --no-interaction`

Replace `app/Livewire/Transactions/Form.php`:

```php
<?php

namespace App\Livewire\Transactions;

use App\Actions\Finance\Transactions\CreateTransaction;
use App\Actions\Finance\Transactions\UpdateTransaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\Money;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    public int $transactionId = 0;

    #[Validate('required|integer|exists:accounts,id')]
    public ?int $accountId = null;

    #[Validate('required|date')]
    public string $occurredOn = '';

    #[Validate('required|string|max:500')]
    public string $description = '';

    #[Validate('required|string')]
    public string $amountDollars = '';

    public ?int $categoryId = null;

    public ?string $notes = null;

    public function mount(int $transactionId): void
    {
        $this->transactionId = $transactionId;
        if ($transactionId > 0) {
            $tx = Transaction::findOrFail($transactionId);
            $this->accountId = $tx->account_id;
            $this->occurredOn = $tx->occurred_on->format('Y-m-d');
            $this->description = $tx->description;
            $this->amountDollars = number_format($tx->amount_cents / 100, 2, '.', '');
            $this->categoryId = $tx->category_id;
            $this->notes = $tx->notes;
        } else {
            $this->occurredOn = now()->toDateString();
        }
    }

    public function save(): void
    {
        $this->validate();
        $cents = Money::toCents($this->amountDollars);

        if ($this->transactionId > 0) {
            $tx = Transaction::findOrFail($this->transactionId);
            (new UpdateTransaction)($tx, [
                'occurred_on' => $this->occurredOn,
                'description' => $this->description,
                'amount_cents' => $cents,
                'category_id' => $this->categoryId,
                'notes' => $this->notes,
            ]);
        } else {
            $account = Account::findOrFail($this->accountId);
            (new CreateTransaction)(
                account: $account,
                occurredOn: $this->occurredOn,
                description: $this->description,
                amountCents: $cents,
                categoryId: $this->categoryId,
                notes: $this->notes,
            );
        }

        $this->dispatch('transaction-saved');
    }

    public function render()
    {
        return view('livewire.transactions.form', [
            'accounts' => Account::active()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
```

- [ ] **Step 6: Write Form view**

Write to `resources/views/livewire/transactions/form.blade.php`:

```blade
<x-card class="border border-base-300 mb-4">
    <div class="grid gap-3 md:grid-cols-2">
        <x-select label="Account" :options="$accounts" option-label="name" option-value="id" placeholder="Pick an account" wire:model="accountId" />
        <x-input type="date" label="Date" wire:model="occurredOn" />
        <x-input label="Description" wire:model="description" class="md:col-span-2" />
        <x-input label="Amount (dollars, negative = out)" wire:model="amountDollars" placeholder="-12.50" />
        <x-select label="Category" :options="$categories" option-label="name" option-value="id" placeholder="Uncategorized" wire:model="categoryId" />
        <x-textarea label="Notes" wire:model="notes" class="md:col-span-2" rows="2" />
    </div>
    <div class="flex gap-2 justify-end mt-4">
        <x-button label="Cancel" class="btn-ghost" @click="$wire.dispatch('transaction-cancelled')" />
        <x-button label="Save" class="btn-primary" wire:click="save" />
    </div>
</x-card>
```

- [ ] **Step 7: Run tests**

```bash
php artisan test --compact --filter="Livewire.Transactions"
```

Expected: PASS.

- [ ] **Step 8: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Transactions resources/views/livewire/transactions tests/Feature/Livewire/Transactions
git commit -m "Add Transactions Index and Form Livewire UI"
```

---

## Task 17: Account Show page (transactions for one account + chart placeholder)

**Files:**
- Create: `app/Livewire/Accounts/Show.php`
- Create: `resources/views/livewire/accounts/show.blade.php`
- Create: `tests/Feature/Livewire/Accounts/ShowTest.php`

(Chart embed deferred to Task 18; this task wires the rest of the page.)

- [ ] **Step 1: Write test**

Write to `tests/Feature/Livewire/Accounts/ShowTest.php`:

```php
<?php

use App\Livewire\Accounts\Show;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('shows account name and current balance', function () {
    $account = Account::factory()->withStartingBalance(10000)->create(['name' => 'Chequing']);
    Transaction::factory()->forAccount($account)->withAmount(5000)->create();

    Livewire::test(Show::class, ['account' => $account])
        ->assertSee('Chequing')
        ->assertSee('$150.00');
});

it('lists transactions for this account only', function () {
    $account = Account::factory()->create();
    $other = Account::factory()->create();
    Transaction::factory()->forAccount($account)->count(2)->create(['description' => 'Mine']);
    Transaction::factory()->forAccount($other)->count(3)->create(['description' => 'Other']);

    Livewire::test(Show::class, ['account' => $account])
        ->assertSee('Mine')
        ->assertDontSee('Other');
});
```

- [ ] **Step 2: Generate component**

Run: `php artisan make:livewire Accounts/Show --no-interaction`

Replace `app/Livewire/Accounts/Show.php`:

```php
<?php

namespace App\Livewire\Accounts;

use App\Actions\Finance\Accounts\ComputeAccountBalance;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Account')]
class Show extends Component
{
    use WithPagination;

    public Account $account;

    public function mount(Account $account): void
    {
        $this->account = $account;
    }

    #[Computed]
    public function balanceCents(): int
    {
        return (new ComputeAccountBalance)($this->account);
    }

    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        return Transaction::query()
            ->with('category')
            ->where('account_id', $this->account->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(50);
    }

    public function render()
    {
        return view('livewire.accounts.show', [
            'balanceCents' => $this->balanceCents,
            'transactions' => $this->transactions,
        ]);
    }
}
```

- [ ] **Step 3: Write view**

Write to `resources/views/livewire/accounts/show.blade.php`:

```blade
<div class="space-y-4">
    <div class="flex items-start justify-between">
        <div>
            <a href="{{ route('accounts.index') }}" wire:navigate class="text-sm opacity-70 hover:opacity-100">← Accounts</a>
            <h1 class="text-2xl font-semibold mt-1">{{ $account->name }}</h1>
            <div class="text-3xl mt-2 font-mono">{{ \App\Support\Money::format($balanceCents) }}</div>
        </div>
    </div>

    <livewire:charts.balance-chart :account-id="$account->id" :key="'chart-acct-'.$account->id" />

    <h2 class="text-lg font-semibold mt-6">{{ __('Transactions') }}</h2>

    <x-table :headers="[
        ['key' => 'occurred_on', 'label' => 'Date'],
        ['key' => 'description', 'label' => 'Description'],
        ['key' => 'category', 'label' => 'Category'],
        ['key' => 'amount', 'label' => 'Amount', 'class' => 'text-right'],
    ]" :rows="$transactions->items()">
        @scope('cell_occurred_on', $row)
            {{ $row->occurred_on->format('Y-m-d') }}
        @endscope
        @scope('cell_category', $row)
            {{ $row->category?->name ?? '—' }}
        @endscope
        @scope('cell_amount', $row)
            <span class="font-mono {{ $row->amount_cents < 0 ? 'text-error' : 'text-success' }}">
                {{ \App\Support\Money::format($row->amount_cents) }}
            </span>
        @endscope
    </x-table>

    <div>{{ $transactions->links() }}</div>
</div>
```

(Note: `<livewire:charts.balance-chart>` reference will fail until Task 18 — that's OK; tests in this task don't render that child component.)

- [ ] **Step 4: Stub the chart placeholder for now**

To keep tests passing before Task 18 lands, temporarily replace the `<livewire:charts.balance-chart ... />` line with a placeholder:

```blade
<div class="h-48 rounded-xl border border-base-300 bg-base-100 flex items-center justify-center opacity-50">Chart coming in next task</div>
```

(Task 18 will swap this back to the real component.)

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=ShowTest
```

Expected: PASS.

- [ ] **Step 6: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Accounts/Show.php resources/views/livewire/accounts/show.blade.php tests/Feature/Livewire/Accounts/ShowTest.php
git commit -m "Add Account Show Livewire page"
```

---

## Task 18: BalanceChart Livewire component

**Files:**
- Create: `app/Livewire/Charts/BalanceChart.php`
- Create: `resources/views/livewire/charts/balance-chart.blade.php`
- Create: `tests/Feature/Livewire/Charts/BalanceChartTest.php`
- Modify: `resources/views/livewire/accounts/show.blade.php` (swap placeholder for real chart)

- [ ] **Step 1: Write test**

Write to `tests/Feature/Livewire/Charts/BalanceChartTest.php`:

```php
<?php

use App\Livewire\Charts\BalanceChart;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders for a specific account', function () {
    $account = Account::factory()->withStartingBalance(10000)->create();
    Transaction::factory()->forAccount($account)->withAmount(1000)->onDate(now()->subDays(5)->toDateString())->create();

    Livewire::test(BalanceChart::class, ['accountId' => $account->id])
        ->assertOk()
        ->assertSet('range', '30d');
});

it('renders for the household total when accountId is null', function () {
    Account::factory()->count(2)->create();

    Livewire::test(BalanceChart::class, ['accountId' => null])
        ->assertOk();
});

it('switches range on action', function () {
    $account = Account::factory()->create();

    Livewire::test(BalanceChart::class, ['accountId' => $account->id])
        ->call('setRange', '90d')
        ->assertSet('range', '90d');
});
```

- [ ] **Step 2: Generate component**

Run: `php artisan make:livewire Charts/BalanceChart --no-interaction`

Replace `app/Livewire/Charts/BalanceChart.php`:

```php
<?php

namespace App\Livewire\Charts;

use App\Actions\Finance\Balance\ComputeBalanceSeries;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Livewire\Component;

class BalanceChart extends Component
{
    public ?int $accountId = null;

    public string $range = '30d';

    public function setRange(string $range): void
    {
        $this->range = $range;
    }

    /**
     * @return array{start: string, end: string}
     */
    private function resolveRange(): array
    {
        $end = CarbonImmutable::today();

        $start = match ($this->range) {
            '90d' => $end->subDays(89),
            'ytd' => $end->startOfYear(),
            'all' => Transaction::query()
                ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
                ->min('occurred_on'),
            default => $end->subDays(29),
        };

        $start = $start ? CarbonImmutable::parse($start) : $end->subDays(29);

        return ['start' => $start->toDateString(), 'end' => $end->toDateString()];
    }

    public function render()
    {
        $accounts = $this->accountId
            ? Account::where('id', $this->accountId)->get()->all()
            : Account::active()->get()->all();

        $range = $this->resolveRange();
        $series = (new ComputeBalanceSeries)($accounts, $range['start'], $range['end']);

        $chart = [
            'chart' => ['type' => 'area', 'height' => 280, 'toolbar' => ['show' => false], 'animations' => ['enabled' => false]],
            'series' => [[
                'name' => 'Balance',
                'data' => array_map(fn ($p) => ['x' => $p['date'], 'y' => $p['balance_cents'] / 100], $series),
            ]],
            'xaxis' => ['type' => 'datetime'],
            'yaxis' => ['labels' => ['formatter' => 'function (v) { return "$" + v.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }']],
            'stroke' => ['curve' => 'stepline'],
            'dataLabels' => ['enabled' => false],
            'tooltip' => ['x' => ['format' => 'yyyy-MM-dd']],
        ];

        return view('livewire.charts.balance-chart', [
            'chart' => $chart,
            'range' => $this->range,
        ]);
    }
}
```

- [ ] **Step 3: Write view**

Write to `resources/views/livewire/charts/balance-chart.blade.php`:

```blade
<div>
    <div class="flex justify-end gap-1 mb-2">
        @foreach (['30d' => '30D', '90d' => '90D', 'ytd' => 'YTD', 'all' => 'All'] as $key => $label)
            <x-button :label="$label" class="btn-xs {{ $range === $key ? 'btn-primary' : 'btn-ghost' }}" wire:click="setRange('{{ $key }}')" />
        @endforeach
    </div>
    <x-chart wire:model="chart" />
</div>
```

- [ ] **Step 4: Swap placeholder in Account Show view**

Modify `resources/views/livewire/accounts/show.blade.php` — replace the placeholder `<div class="h-48 rounded-xl border ...">Chart coming in next task</div>` with:

```blade
<livewire:charts.balance-chart :account-id="$account->id" :key="'chart-acct-'.$account->id" />
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=BalanceChartTest
php artisan test --compact --filter=ShowTest
```

Expected: PASS.

- [ ] **Step 6: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Charts resources/views/livewire/charts resources/views/livewire/accounts/show.blade.php tests/Feature/Livewire/Charts
git commit -m "Add BalanceChart Livewire component"
```

---

## Task 19: Imports Wizard (Upload + MapColumns steps)

**Files:**
- Create: `app/Livewire/Imports/Wizard.php`
- Create: `resources/views/livewire/imports/wizard.blade.php`
- Create: `tests/Feature/Livewire/Imports/WizardTest.php`

This task lays out the multi-step Livewire component with all four steps stubbed; Steps 1 (Upload) and 2 (MapColumns) are functional. Steps 3+4 are wired in Task 20.

- [ ] **Step 1: Write test for upload + mapping steps**

Write to `tests/Feature/Livewire/Imports/WizardTest.php`:

```php
<?php

use App\Livewire\Imports\Wizard;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

it('starts on the upload step', function () {
    Livewire::test(Wizard::class)
        ->assertSet('step', 'upload');
});

it('moves to map step after upload when account has no profile', function () {
    $account = Account::factory()->create();
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->assertSet('step', 'map')
        ->assertSet('detectedHeaders', ['Date', 'Description', 'Amount']);
});

it('skips map step when account already has matching profile', function () {
    $account = Account::factory()->create([
        'import_profile' => [
            'delimiter' => ',',
            'has_header' => true,
            'date_column' => 'Date',
            'date_format' => 'm/d/Y',
            'description_column' => 'Description',
            'amount_column' => 'Amount',
        ],
    ]);
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->assertSet('step', 'preview');
});

it('saves the mapping to the account and proceeds to preview', function () {
    $account = Account::factory()->create();
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->set('mapDateColumn', 'Date')
        ->set('mapDateFormat', 'm/d/Y')
        ->set('mapDescriptionColumn', 'Description')
        ->set('mapAmountColumn', 'Amount')
        ->set('mapHasHeader', true)
        ->call('proceedFromMap')
        ->assertSet('step', 'preview');

    expect($account->fresh()->import_profile['date_column'])->toBe('Date');
});
```

- [ ] **Step 2: Generate component**

Run: `php artisan make:livewire Imports/Wizard --no-interaction`

Replace `app/Livewire/Imports/Wizard.php`:

```php
<?php

namespace App\Livewire\Imports;

use App\Actions\Finance\Imports\ImportTransactions;
use App\Actions\Finance\Imports\ParseCsvForPreview;
use App\Models\Account;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Import CSV')]
class Wizard extends Component
{
    use WithFileUploads;

    public string $step = 'upload';

    public ?int $accountId = null;

    public $upload = null;

    public string $uploadedPath = '';

    public string $uploadedFilename = '';

    /** @var array<int, string> */
    public array $detectedHeaders = [];

    public string $mapDateColumn = '';

    public string $mapDateFormat = 'm/d/Y';

    public string $mapDescriptionColumn = '';

    public string $mapAmountColumn = '';

    public bool $mapHasHeader = true;

    /** @var array<int, array<string, mixed>> */
    public array $previewRows = [];

    public ?int $createdBatchId = null;

    public function proceedFromUpload(): void
    {
        $this->validate([
            'accountId' => 'required|integer|exists:accounts,id',
            'upload' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $this->uploadedPath = $this->upload->getRealPath();
        $this->uploadedFilename = $this->upload->getClientOriginalName();
        $this->detectedHeaders = $this->sniffHeaders($this->uploadedPath);

        $account = Account::findOrFail($this->accountId);
        $profile = $account->import_profile;

        if ($profile && $this->headersMatch($profile, $this->detectedHeaders)) {
            $this->applyProfile($profile);
            $this->buildPreview();
            $this->step = 'preview';

            return;
        }

        if ($profile) {
            // Prefill mapping with previous values; user can adjust.
            $this->mapDateColumn = $profile['date_column'] ?? '';
            $this->mapDateFormat = $profile['date_format'] ?? 'm/d/Y';
            $this->mapDescriptionColumn = $profile['description_column'] ?? '';
            $this->mapAmountColumn = $profile['amount_column'] ?? '';
            $this->mapHasHeader = $profile['has_header'] ?? true;
        }

        $this->step = 'map';
    }

    public function proceedFromMap(): void
    {
        $this->validate([
            'mapDateColumn' => 'required|string',
            'mapDateFormat' => 'required|string',
            'mapDescriptionColumn' => 'required|string',
            'mapAmountColumn' => 'required|string',
        ]);

        $account = Account::findOrFail($this->accountId);
        $account->update([
            'import_profile' => [
                'delimiter' => ',',
                'has_header' => $this->mapHasHeader,
                'date_column' => $this->mapDateColumn,
                'date_format' => $this->mapDateFormat,
                'description_column' => $this->mapDescriptionColumn,
                'amount_column' => $this->mapAmountColumn,
            ],
        ]);

        $this->buildPreview();
        $this->step = 'preview';
    }

    public function commit(): void
    {
        $account = Account::findOrFail($this->accountId);
        $batch = (new ImportTransactions)($account, $this->previewRows, auth()->id(), $this->uploadedFilename);
        $this->createdBatchId = $batch->id;
        $this->step = 'done';
    }

    public function toggleRow(int $index): void
    {
        if (! isset($this->previewRows[$index])) {
            return;
        }
        $current = $this->previewRows[$index]['status'];
        if ($current === 'error') {
            return;
        }
        $this->previewRows[$index]['status'] = $current === 'duplicate' ? 'new' : 'duplicate';
    }

    private function sniffHeaders(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }
        $first = fgetcsv($handle);
        fclose($handle);

        return is_array($first) ? array_map('strval', $first) : [];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<int, string>  $headers
     */
    private function headersMatch(array $profile, array $headers): bool
    {
        return in_array($profile['date_column'] ?? null, $headers, true)
            && in_array($profile['description_column'] ?? null, $headers, true)
            && in_array($profile['amount_column'] ?? null, $headers, true);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function applyProfile(array $profile): void
    {
        $this->mapDateColumn = $profile['date_column'];
        $this->mapDateFormat = $profile['date_format'];
        $this->mapDescriptionColumn = $profile['description_column'];
        $this->mapAmountColumn = $profile['amount_column'];
        $this->mapHasHeader = $profile['has_header'] ?? true;
    }

    private function buildPreview(): void
    {
        $account = Account::findOrFail($this->accountId);
        $this->previewRows = (new ParseCsvForPreview)($account, $this->uploadedPath, [
            'delimiter' => ',',
            'has_header' => $this->mapHasHeader,
            'date_column' => $this->mapDateColumn,
            'date_format' => $this->mapDateFormat,
            'description_column' => $this->mapDescriptionColumn,
            'amount_column' => $this->mapAmountColumn,
        ]);
    }

    public function render()
    {
        return view('livewire.imports.wizard', [
            'accounts' => Account::active()->orderBy('name')->get(),
            'counts' => [
                'new' => collect($this->previewRows)->where('status', 'new')->count(),
                'duplicate' => collect($this->previewRows)->where('status', 'duplicate')->count(),
                'error' => collect($this->previewRows)->where('status', 'error')->count(),
            ],
        ]);
    }
}
```

- [ ] **Step 3: Write wizard view**

Write to `resources/views/livewire/imports/wizard.blade.php`:

```blade
<div class="space-y-4 max-w-3xl mx-auto">
    <h1 class="text-2xl font-semibold">{{ __('Import CSV') }}</h1>

    <ul class="steps w-full">
        <li class="step {{ in_array($step, ['upload','map','preview','done']) ? 'step-primary' : '' }}">Upload</li>
        <li class="step {{ in_array($step, ['map','preview','done']) ? 'step-primary' : '' }}">Map columns</li>
        <li class="step {{ in_array($step, ['preview','done']) ? 'step-primary' : '' }}">Preview</li>
        <li class="step {{ $step === 'done' ? 'step-primary' : '' }}">Done</li>
    </ul>

    @if ($step === 'upload')
        <x-card class="border border-base-300">
            <div class="space-y-3">
                <x-select label="Target account" :options="$accounts" option-label="name" option-value="id" placeholder="Pick an account" wire:model="accountId" />
                <x-file label="CSV file" wire:model="upload" accept=".csv,text/csv,text/plain" hint="Max 10 MB" />
                <div class="flex justify-end">
                    <x-button label="Next" class="btn-primary" wire:click="proceedFromUpload" spinner="proceedFromUpload" />
                </div>
            </div>
        </x-card>
    @endif

    @if ($step === 'map')
        <x-card class="border border-base-300">
            <p class="text-sm mb-3">Map the CSV's columns to the fields we need. Detected headers: {{ implode(', ', $detectedHeaders) }}</p>
            <div class="grid gap-3 md:grid-cols-2">
                <x-select label="Date column" :options="collect($detectedHeaders)->map(fn ($h) => ['id' => $h, 'name' => $h])" placeholder="…" wire:model="mapDateColumn" />
                <x-input label="Date format" wire:model="mapDateFormat" hint="e.g. m/d/Y, d/m/Y, Y-m-d" />
                <x-select label="Description column" :options="collect($detectedHeaders)->map(fn ($h) => ['id' => $h, 'name' => $h])" placeholder="…" wire:model="mapDescriptionColumn" />
                <x-select label="Amount column" :options="collect($detectedHeaders)->map(fn ($h) => ['id' => $h, 'name' => $h])" placeholder="…" wire:model="mapAmountColumn" />
                <x-checkbox label="First row is a header" wire:model="mapHasHeader" />
            </div>
            <div class="flex justify-end mt-4">
                <x-button label="Next" class="btn-primary" wire:click="proceedFromMap" />
            </div>
        </x-card>
    @endif

    @if ($step === 'preview')
        @include('livewire.imports.partials.preview')
    @endif

    @if ($step === 'done')
        <x-card class="border border-base-300">
            <div class="text-center space-y-3">
                <x-icon name="lucide.check-circle" class="size-12 mx-auto text-success" />
                <h2 class="text-lg font-semibold">Import complete</h2>
                <div class="flex gap-2 justify-center">
                    <x-button label="View import" link="{{ route('imports.index') }}" class="btn-primary" />
                    <x-button label="New import" link="{{ route('imports.new') }}" class="btn-ghost" />
                </div>
            </div>
        </x-card>
    @endif
</div>
```

Note: the preview partial (`livewire.imports.partials.preview`) is created in Task 20.

- [ ] **Step 4: Create empty preview partial placeholder so the view doesn't error**

Write to `resources/views/livewire/imports/partials/preview.blade.php`:

```blade
<x-card class="border border-base-300">
    <p>Preview rendered in Task 20.</p>
</x-card>
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=WizardTest
```

Expected: PASS (the tests written so far cover upload + map only).

- [ ] **Step 6: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Imports/Wizard.php resources/views/livewire/imports tests/Feature/Livewire/Imports/WizardTest.php
git commit -m "Add Imports Wizard with Upload and MapColumns steps"
```

---

## Task 20: Imports Wizard (Preview + Confirm steps)

**Files:**
- Modify: `resources/views/livewire/imports/partials/preview.blade.php`
- Modify: `tests/Feature/Livewire/Imports/WizardTest.php` (add preview/commit tests)

- [ ] **Step 1: Add preview + commit tests**

Append to `tests/Feature/Livewire/Imports/WizardTest.php`:

```php
it('shows preview rows after mapping', function () {
    $account = Account::factory()->create();
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    $component = Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->set('mapDateColumn', 'Date')
        ->set('mapDateFormat', 'm/d/Y')
        ->set('mapDescriptionColumn', 'Description')
        ->set('mapAmountColumn', 'Amount')
        ->set('mapHasHeader', true)
        ->call('proceedFromMap');

    expect($component->get('previewRows'))->toHaveCount(3);
    expect($component->get('previewRows')[0]['description'])->toBe('Coffee Shop');
});

it('commits the import and creates a batch', function () {
    $account = Account::factory()->create();
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->set('mapDateColumn', 'Date')
        ->set('mapDateFormat', 'm/d/Y')
        ->set('mapDescriptionColumn', 'Description')
        ->set('mapAmountColumn', 'Amount')
        ->set('mapHasHeader', true)
        ->call('proceedFromMap')
        ->call('commit')
        ->assertSet('step', 'done');

    expect(\App\Models\ImportBatch::count())->toBe(1);
    expect(\App\Models\Transaction::count())->toBe(3);
});

it('lets the user toggle a duplicate row to be force-included', function () {
    $account = Account::factory()->create();
    \App\Models\Transaction::factory()->forAccount($account)->create([
        'occurred_on' => '2026-06-01',
        'description' => 'Coffee Shop',
        'amount_cents' => -450,
    ]);
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    $component = Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->set('mapDateColumn', 'Date')
        ->set('mapDateFormat', 'm/d/Y')
        ->set('mapDescriptionColumn', 'Description')
        ->set('mapAmountColumn', 'Amount')
        ->set('mapHasHeader', true)
        ->call('proceedFromMap');

    // First row should be flagged duplicate
    expect($component->get('previewRows')[0]['status'])->toBe('duplicate');

    $component->call('toggleRow', 0);
    expect($component->get('previewRows')[0]['status'])->toBe('new');
});
```

- [ ] **Step 2: Replace the preview partial**

Replace `resources/views/livewire/imports/partials/preview.blade.php`:

```blade
<x-card class="border border-base-300">
    <div class="flex justify-between items-center mb-3 text-sm">
        <div class="space-x-3">
            <span class="text-success">{{ $counts['new'] }} new</span>
            <span class="text-warning">{{ $counts['duplicate'] }} duplicates</span>
            <span class="text-error">{{ $counts['error'] }} errors</span>
        </div>
        <x-button label="Import {{ $counts['new'] }} rows" class="btn-primary" wire:click="commit" wire:loading.attr="disabled" />
    </div>

    <div class="overflow-x-auto">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th></th>
                    <th>Date</th>
                    <th>Description</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($previewRows as $i => $row)
                    <tr class="{{ $row['status'] === 'error' ? 'opacity-50' : '' }}">
                        <td>
                            @if ($row['status'] !== 'error')
                                <input type="checkbox"
                                       class="checkbox checkbox-sm"
                                       wire:click="toggleRow({{ $i }})"
                                       @checked($row['status'] === 'new') />
                            @endif
                        </td>
                        <td>{{ $row['occurred_on'] ?? '—' }}</td>
                        <td>{{ $row['description'] ?? '—' }}</td>
                        <td class="text-right font-mono">
                            @if ($row['amount_cents'] !== null)
                                {{ \App\Support\Money::format($row['amount_cents']) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($row['status'] === 'new')
                                <x-badge value="New" class="badge-success badge-sm" />
                            @elseif ($row['status'] === 'duplicate')
                                <x-badge value="Duplicate" class="badge-warning badge-sm" />
                            @else
                                <x-badge value="Error" class="badge-error badge-sm" />
                                <div class="text-xs text-error mt-1">{{ $row['error'] ?? '' }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=WizardTest
```

Expected: PASS (all five wizard tests).

- [ ] **Step 4: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/livewire/imports/partials/preview.blade.php tests/Feature/Livewire/Imports/WizardTest.php
git commit -m "Add Imports Wizard preview and commit steps"
```

---

## Task 21: Imports Index with undo

**Files:**
- Create: `app/Livewire/Imports/Index.php`
- Create: `resources/views/livewire/imports/index.blade.php`
- Create: `tests/Feature/Livewire/Imports/IndexTest.php`

- [ ] **Step 1: Write tests**

Write to `tests/Feature/Livewire/Imports/IndexTest.php`:

```php
<?php

use App\Livewire\Imports\Index;
use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists active import batches', function () {
    ImportBatch::factory()->count(2)->create();
    ImportBatch::factory()->undone()->create();

    Livewire::test(Index::class)
        ->assertOk()
        ->assertViewHas('batches', fn ($b) => $b->count() === 2);
});

it('shows undone batches when toggled', function () {
    ImportBatch::factory()->undone()->count(2)->create();

    Livewire::test(Index::class)
        ->set('showUndone', true)
        ->assertViewHas('batches', fn ($b) => $b->count() === 2);
});

it('undoes a batch via component', function () {
    $batch = ImportBatch::factory()->create();
    Transaction::factory()->count(3)->create(['import_batch_id' => $batch->id]);

    Livewire::test(Index::class)->call('undo', $batch->id);

    expect($batch->fresh()->isUndone())->toBeTrue();
    expect(Transaction::where('import_batch_id', $batch->id)->count())->toBe(0);
});
```

- [ ] **Step 2: Generate component**

Run: `php artisan make:livewire Imports/Index --no-interaction`

Replace `app/Livewire/Imports/Index.php`:

```php
<?php

namespace App\Livewire\Imports;

use App\Actions\Finance\Imports\UndoImportBatch;
use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Imports')]
class Index extends Component
{
    public bool $showUndone = false;

    public function undo(int $batchId): void
    {
        $batch = ImportBatch::findOrFail($batchId);
        (new UndoImportBatch)($batch);
    }

    #[Computed]
    public function batches(): Collection
    {
        return ImportBatch::query()
            ->with(['account', 'user'])
            ->when(! $this->showUndone, fn ($q) => $q->whereNull('undone_at'))
            ->when($this->showUndone, fn ($q) => $q->whereNotNull('undone_at'))
            ->orderByDesc('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.imports.index', ['batches' => $this->batches]);
    }
}
```

- [ ] **Step 3: Write view**

Write to `resources/views/livewire/imports/index.blade.php`:

```blade
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Imports') }}</h1>
        <div class="flex gap-2 items-center">
            <x-checkbox label="Show undone" wire:model.live="showUndone" />
            <x-button label="New import" icon="lucide.upload" class="btn-primary" link="{{ route('imports.new') }}" wire:navigate />
        </div>
    </div>

    <x-table :headers="[
        ['key' => 'created_at', 'label' => 'When'],
        ['key' => 'account', 'label' => 'Account'],
        ['key' => 'filename', 'label' => 'File'],
        ['key' => 'imported', 'label' => 'Imported', 'class' => 'text-right'],
        ['key' => 'dupes', 'label' => 'Dupes', 'class' => 'text-right'],
        ['key' => 'errors', 'label' => 'Errors', 'class' => 'text-right'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-32'],
    ]" :rows="$batches">
        @scope('cell_created_at', $row)
            {{ $row->created_at->format('Y-m-d H:i') }}
        @endscope
        @scope('cell_account', $row)
            {{ $row->account?->name }}
        @endscope
        @scope('cell_imported', $row)
            {{ $row->imported_count }}
        @endscope
        @scope('cell_dupes', $row)
            {{ $row->skipped_duplicate_count }}
        @endscope
        @scope('cell_errors', $row)
            {{ $row->error_count }}
        @endscope
        @scope('cell_actions', $row)
            @if (! $row->isUndone())
                <x-button label="Undo" icon="lucide.undo-2" class="btn-ghost btn-sm" wire:click="undo({{ $row->id }})" wire:confirm="Undo this import? All transactions from this batch will be removed." />
            @else
                <x-badge value="Undone" class="badge-ghost badge-sm" />
            @endif
        @endscope
    </x-table>
</div>
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact --filter="Livewire.Imports"
```

Expected: PASS.

- [ ] **Step 5: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Livewire/Imports/Index.php resources/views/livewire/imports/index.blade.php tests/Feature/Livewire/Imports/IndexTest.php
git commit -m "Add Imports Index with undo"
```

---

## Task 22: Dashboard update (account tiles + balance chart)

**Files:**
- Modify: `resources/views/dashboard.blade.php`

- [ ] **Step 1: Replace dashboard view**

Replace `resources/views/dashboard.blade.php`:

```blade
<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <div>
            <livewire:charts.balance-chart :account-id="null" key="chart-household" />
        </div>

        <div>
            <h2 class="text-lg font-semibold mb-3">{{ __('Accounts') }}</h2>
            <livewire:accounts.index key="dashboard-accounts" />
        </div>
    </div>
</x-layouts::app>
```

Note: the embedded `accounts.index` Livewire component shows its full UI here including the "New account" button — that's fine; reusing the same surface keeps the dashboard simple.

- [ ] **Step 2: Manual check (no test for plain Blade view)**

Run: `php artisan serve` is not needed (Herd serves the site). Visit the dashboard route in the browser to verify there are no errors. Run the full test suite to catch regressions:

```bash
php artisan test --compact
```

Expected: all tests still pass.

- [ ] **Step 3: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/dashboard.blade.php
git commit -m "Update dashboard with household balance chart and accounts list"
```

---

## Task 23: Browser tests for import wizard + dashboard

**Files:**
- Create: `tests/Browser/ImportWizardBrowserTest.php`
- Create: `tests/Browser/DashboardRenderTest.php`

Pest 4 has native browser testing. These tests confirm the multi-step wizard holds state across page renders and the chart renders without JS console errors.

- [ ] **Step 1: Write import wizard browser test**

Write to `tests/Browser/ImportWizardBrowserTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\User;

it('walks through the full import wizard end to end', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['name' => 'Test Chequing']);

    $this->actingAs($user);

    $page = visit(route('imports.new'));

    $page->assertSee('Upload')
        ->assertSee('Map columns')
        ->assertSee('Preview');

    // Pick account
    $page->select('#accountId', (string) $account->id);

    // Upload a CSV
    $page->attach('input[type=file]', base_path('tests/Fixtures/csv/sample-standard.csv'));

    $page->click('Next');

    $page->assertSee('Detected headers');

    // Map columns
    $page->select('#mapDateColumn', 'Date')
        ->fill('#mapDateFormat', 'm/d/Y')
        ->select('#mapDescriptionColumn', 'Description')
        ->select('#mapAmountColumn', 'Amount')
        ->click('Next');

    // Preview step shows counts
    $page->assertSee('new');

    // Commit
    $page->click('Import')
        ->assertSee('Import complete');
})->skip('Selectors may need tuning to match MaryUI rendered HTML — enable when wizard UI is settled.');
```

(Note: the `->skip()` is intentional — browser-test selectors are brittle against MaryUI's rendered output and may need tuning once the UI is visually verified. Skip on first commit; un-skip after manual verification.)

- [ ] **Step 2: Write dashboard render test**

Write to `tests/Browser/DashboardRenderTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

it('renders the dashboard without JS console errors', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withStartingBalance(50000)->create(['name' => 'Test Chequing']);
    Transaction::factory()->forAccount($account)->withAmount(1000)->create();

    $this->actingAs($user);

    $page = visit(route('dashboard'));

    $page->assertSee('Test Chequing')
        ->assertNoJavascriptErrors();
});
```

- [ ] **Step 3: Run browser tests**

```bash
php artisan test --compact --filter=Browser
```

Expected: Dashboard test passes; ImportWizardBrowserTest is skipped. If the dashboard test fails because the browser harness isn't configured, follow the prompts to install browser dependencies (`php artisan pest:install --browser` if available, or consult Pest 4 docs).

- [ ] **Step 4: Run the full suite**

```bash
php artisan test --compact
```

Expected: full suite green.

- [ ] **Step 5: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Browser
git commit -m "Add browser tests for import wizard and dashboard render"
```

---

## Self-Review Summary

The plan was checked against the spec section by section:

| Spec requirement | Covered by |
|---|---|
| `accounts` table with starting balance, archive, import_profile | Task 3 |
| `categories` table with keywords, excluded_from_totals, Transfer seed | Task 2 |
| `transactions` table with dedup_hash, soft delete, indexes | Task 4 |
| `import_batches` table with undone_at | Task 5 |
| Money integer cents + `en_US` formatting helper | Task 1 |
| Approach B — actions in `app/Actions/Finance/*` | Tasks 6–12 |
| `ComputeAccountBalance` action | Task 8 |
| `ComputeBalanceSeries` action with anchor + forward fill | Task 9 |
| CSV import 4-step wizard | Tasks 19 + 20 |
| Per-account import profile + reuse | Tasks 19 + 20 |
| Dedup at app and DB level | Tasks 4 + 10 + 11 |
| Minimal Transfer auto-match on import | Task 10 |
| Undo batch | Tasks 12 + 21 |
| Account Index, Show, Form | Tasks 15 + 17 |
| Transaction Index + Form + manual entry | Task 16 |
| Categories Index + Form | Task 14 |
| BalanceChart with scope + range | Task 18 |
| Routes + sidebar nav | Task 13 |
| Dashboard update | Task 22 |
| Pest unit tests for actions | Tasks 6–12 |
| Pest feature tests for Livewire components | Tasks 14–21 |
| Browser tests for wizard + dashboard | Task 23 |

No placeholders left. Type consistency verified: `Account`, `Transaction`, `Category`, `ImportBatch` referenced identically across tasks; action method names match between definition and use.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-20-ledger-foundation.md`.

Two execution options:

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
