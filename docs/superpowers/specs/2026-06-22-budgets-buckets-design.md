# Budgets & Buckets (Phase 3)

## Overview

Phase 3 of the finance tracker. Lets the user define named buckets (e.g., "Essentials", "Lifestyle", "Savings") with target percentages of a user-set monthly income figure. Each spending category is assigned to one bucket. The dashboard shows actual-vs-target per bucket each month plus an income-actual-vs-expected comparison.

This is the 50/30/20 rule generalized — the user picks the split (50/30/20, 50/20/20/10, anything that adds up to 100, or even doesn't if they want slack).

## Decisions

- **Budget basis:** user-set monthly income target. Stored as cents in a singleton `app_settings` table row. Buckets are percentages of that target.
- **Mapping:** one bucket per category. Implemented as a nullable `bucket_id` foreign key on `categories`. No pivot table.
- **Rollover:** hard monthly reset. Each month is independent. No carry-forward, no cumulative variance.
- **Category kind:** new `kind` enum on categories — `spending` (default) | `income` | `transfer`. Replaces the existing `excluded_from_totals` boolean. Spending categories contribute to bucket totals; income categories feed the income comparison; transfer categories are excluded from everything.
- **Bucket percentages:** target_percentage range 0–100. The SUM across all buckets MAY equal 100 but isn't enforced (a warning is shown if not — the user might intentionally leave slack).
- **Unassigned spending:** spending-kind categories with `bucket_id IS NULL` appear in an implicit "Unassigned" pseudo-row on the dashboard. No target, just shows actual spend so the user knows they have un-bucketed categories.
- **Refunds:** positive amounts in a spending category reduce that bucket's net spend (signed SUM). They do NOT count as income.
- **Period:** monthly only. The current calendar month by default; the action accepts any year-month for historical views.

## Data Model

Three changes.

### New: `buckets` table
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, pk | |
| `name` | string(80), unique | "Essentials", "Lifestyle", "Savings" |
| `target_percentage` | smallInteger | 0–100 inclusive |
| `color` | string(16), nullable | hex for UI |
| `sort_order` | smallInteger, default 0 | display order |
| `created_at`, `updated_at` | timestamps | |

### Modified: `categories` table
Two columns added, one removed.

Add:
- `kind` enum-style string, default `'spending'`. Allowed values: `'spending'`, `'income'`, `'transfer'`.
- `bucket_id` `foreignId`, nullable, `constrained('buckets')->nullOnDelete()`.

Migrate:
- Copy `excluded_from_totals=true` rows → `kind='transfer'` BEFORE dropping the column.
- Drop `excluded_from_totals` boolean column.

Seeder update:
- `TransferCategorySeeder` sets `kind='transfer'`. No longer touches `excluded_from_totals` (column no longer exists).

### New: `app_settings` table (singleton)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, pk | always 1 |
| `monthly_income_target_cents` | bigInteger, default 0 | basis for bucket % math |
| `created_at`, `updated_at` | timestamps | |

The migration inserts a single row with id=1, target=0 so the app can always read it. There is exactly one row forever; the `update` semantic in code is "update row 1".

## Components

### Models
- **`App\Models\Bucket`**
  - Fillable: `name`, `target_percentage`, `color`, `sort_order`
  - `hasMany(Category::class, 'bucket_id')`
  - Accessor `targetCents(int $incomeTargetCents): int` returning `intdiv($incomeTargetCents * $this->target_percentage, 100)`
- **`App\Models\Category`** (existing — extend)
  - Add `bucket_id` to fillable
  - Add `kind` to fillable, cast `'string'` (PHP-level enum optional; string is fine)
  - Remove `excluded_from_totals` from fillable and cast
  - Add `belongsTo(Bucket::class)`
  - Drop the existing `excludedFromTotals` factory state; add `incomeKind()`, `transferKind()`, `inBucket(Bucket $b)` states
- **`App\Models\AppSetting`** (new)
  - Fillable: `monthly_income_target_cents`
  - Static helper: `AppSetting::current(): self` returning the singleton row (creates if missing)

### Actions (`app/Actions/Finance/Budgets/`)
- **`ComputeMonthlyBudgetStatus($yearMonth)`** — invokable
  - Takes a string like `'2026-06'` or a `CarbonImmutable`; default = current month
  - Returns:
    ```php
    [
      'period' => '2026-06',
      'income_target_cents' => 500000,
      'income_actual_cents' => 475000,    // signed: + = income received this month
      'buckets' => [
        [
          'id' => 1,
          'name' => 'Essentials',
          'color' => '#22c55e',
          'target_percentage' => 50,
          'target_cents' => 250000,
          'actual_cents' => 182000,         // signed: + = net spent, - = net refund
          'over_target' => false,
        ],
        // ...
      ],
      'unassigned_actual_cents' => 4500,    // same sign convention as buckets[].actual_cents
    ]
    ```
  - **Sign convention:** `actual_cents = -SUM(t.amount_cents)` for spending buckets. The raw SUM is negative (net spend) → `actual_cents` is positive. If refunds exceed spending in a bucket (rare but possible), the raw SUM is positive → `actual_cents` is negative, signaling a net refund. The display layer renders negative values as "+$X.XX refund net" and clamps the progress bar to 0.
  - `over_target` is `actual_cents > target_cents` (only meaningful when `target_cents > 0`).
  - One SQL query for bucket actuals (joined transactions ↔ categories WHERE `kind='spending'`, grouped by `bucket_id`).
  - One SQL query for income actuals (SUM in `kind='income'` categories — also signed; negative would indicate a paycheck reversal or similar, displayed as "−$X.XX").
  - Both wrapped in the action. No N+1.

- **`CreateBucket`, `UpdateBucket`, `DeleteBucket`** — standard single-purpose invokables following the Phase 1 Account-action pattern.

### Livewire SFC pages
- **`/buckets`** (new page) — `resources/views/pages/buckets/⚡index.blade.php`
  - Top section: monthly income target with inline edit (using a small `BucketsForm` child or inline editing flag — keep simple by inlining)
  - Sum-of-percentages indicator (warning class if ≠100, just a hint, no block)
  - Card list of buckets with name, %, target $, member-category-count, color swatch, edit/delete actions
  - "New bucket" button → opens form child
- **`/buckets/⚡form.blade.php`** (child SFC) — bucket create/edit modal
  - Fields: name, target_percentage (slider 0-100 + numeric input), color (small picker)
- **`/categories/⚡form.blade.php`** (existing — extend)
  - Add **Kind** radio group (Spending / Income / Transfer)
  - Add **Bucket** select shown only when kind=Spending (Alpine `x-show` or Livewire conditional)
  - On save: if kind != spending, force bucket_id = null
  - Update the existing `excludedFromTotals` checkbox handling — that field is gone; transfer is now represented by kind=Transfer
- **`/categories/⚡index.blade.php`** (existing — extend display only)
  - Replace the `Excluded` column with a `Kind` badge column
  - Add a `Bucket` column showing the assigned bucket name (or "—")

### Dashboard widget
- New Livewire SFC: `resources/views/pages/dashboard/⚡budget-status.blade.php`
  - Calls `ComputeMonthlyBudgetStatus` on render
  - Renders a small panel: "**Income**: $4,750 / $5,000 expected" then a list of horizontal bars per bucket
  - Each bar uses `<progress>` (daisyUI styles) or a custom div with width %
  - Bar color: bucket's own color when ≤80% used; warning at 80-100%; error >100%
  - "Unassigned" row at the bottom only shown when `unassigned_actual_cents > 0`
- Embed in `resources/views/dashboard.blade.php` ABOVE the balance chart

### Routes
- `Route::livewire('buckets', 'pages::buckets.index')->name('buckets.index')` — inside the existing auth/verified group
- Sidebar: new menu item between "Transactions" and "Imports" — "Budget" linking to `/buckets`

## Validation

- Bucket `name` unique (DB-level).
- `target_percentage` in 0–100 (Livewire Validate attribute + DB check optional).
- Total percentages MAY equal 100 — show inline warning otherwise; don't block save.
- Category kind=income or transfer + non-null bucket_id rejected by the Category form (clears bucket_id on kind change).
- Deleting a bucket sets `bucket_id` to null on member categories via `nullOnDelete`. UI shows a confirmation that mentions how many categories will be unassigned.

## Error Handling

| Scenario | Behavior |
|---|---|
| No buckets defined yet | `/buckets` shows empty state with "Create your first bucket" CTA. Dashboard widget shows just income + unassigned. |
| Monthly income target = 0 | Bucket bars show "$0.00 / $0.00 target". Unhelpful but not broken; UX prompts user to set the target. |
| Bucket percentages sum > 100 | Inline warning ("Allocated 110% — over your income"). No block. |
| Bucket percentages sum < 100 | Inline note ("Allocated 90% — 10% unallocated"). No block. |
| Income category exists but no transactions | `income_actual_cents = 0`. Comparison shows 0 / target. |
| No categories assigned to a bucket | Bucket shows `actual = 0`. Still rendered. |
| Spending category without bucket | Aggregated into "Unassigned" row on dashboard. |

## Testing

### Action tests
- **`ComputeMonthlyBudgetStatusTest`**
  - Returns correct shape (income_target, income_actual, buckets array, unassigned)
  - Filters by year-month boundaries (transactions in adjacent months excluded)
  - Includes refunds correctly (positive in spending → reduces net spend)
  - Excludes `kind='transfer'` everywhere
  - Income actual = sum of positive amounts in `kind='income'`
  - Unassigned = sum of spending categories with `bucket_id IS NULL`
  - Soft-deleted transactions excluded
- **`CreateBucket` / `UpdateBucket` / `DeleteBucket`** — standard CRUD coverage; delete-with-categories nulls out `bucket_id`

### Model tests
- **`BucketTest`** — relationships, `targetCents` accessor with various percentages and income values
- **`CategoryTest`** — Kind enum cast, `bucket_id` relationship, factory states (`incomeKind`, `transferKind`, `inBucket`)
- **`AppSettingTest`** — `current()` creates a row if missing, returns the singleton

### Livewire feature tests
- **`/buckets` index** — list, create, edit, delete; income target inline edit
- **`/buckets/⚡form`** — validation rules, save dispatches `bucket-saved` event
- **`/categories/⚡form`** — kind=Spending shows bucket select; kind=Income hides it and clears bucket_id; kind=Transfer same
- **Dashboard widget** — renders the income line + bucket bars + unassigned only when applicable

### Migration test
- Existing `excluded_from_totals=true` rows convert to `kind='transfer'` before the column is dropped. Verified via a test that creates a category with `excluded_from_totals=true`, runs the migration, asserts `kind='transfer'`.

## Out of Scope (Later)

- Multi-month historical bucket charts (sparklines / trend lines per bucket)
- Income smoothing across months (e.g., bonus months)
- Bucket templates / presets ("Use 50/30/20 starter")
- Drag-and-drop bucket reordering UI (DB column exists; UI can come later)
- Rollover / cumulative variance tracking
- Multi-currency, weekly/biweekly periods
- Notifications when a bucket exceeds target
- Bucket-level historical reports
- Linking buckets to savings goals (overlap with Phase 4 — kept separate)
