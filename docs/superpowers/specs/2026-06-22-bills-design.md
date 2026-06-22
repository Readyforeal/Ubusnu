# Bills & Auto-match (Phase 5a)

## Overview

Phase 5a of the finance tracker — bills as a first-class concept. Each bill has a name, cadence (monthly or annual), expected amount, due day, optional `match_description`, and an optional account/category. On CSV import, a description-based matcher links incoming transactions to bills (similar pattern to Phase 2 category keywords) via a new `transactions.bill_id` foreign key. Paid status per period is computed from linked transactions, with a small manual-override hatch for the rare case where no matching transaction exists.

Phase 5b (the pay-timing optimizer that learns from balance trends) is intentionally deferred to its own spec/plan/build cycle.

## Decisions

- **Cadences:** `monthly` and `annual`. Schema stores `cadence`, `due_day_of_month` (1–31, always required), and `due_month_of_year` (1–12, required only when `cadence = 'annual'`).
- **Day-31 in shorter months:** when `due_day_of_month > days_in_month(current_month)`, the next due date clamps to the last day of that month (Feb 28 / 30-day months treat day 31 as the last day).
- **Auto-match:** `match_description` on the bill is a case-insensitive substring searched against the transaction description on every CSV import. Exactly one bill match → `transaction.bill_id` is set. Zero or ≥2 matches → `bill_id` stays null. (Same disambiguation rule as Phase 2 category matching.)
- **Manual override path A (preferred):** edit a specific transaction, set its `bill_id` via a new dropdown on the transaction form.
- **Manual override path B (escape hatch):** on `/bills`, click "Mark paid this period" — appends the current period token (e.g. `"2026-06"`) to a comma-separated `manually_marked_paid_periods` string on the bill row. Reversible via an "Unmark" action.
- **"Paid this period?":** any matched transaction's `occurred_on` falls within the period AND is not soft-deleted, OR the current period token appears in `manually_marked_paid_periods`.
- **Bill amount sign convention:** `expected_amount_cents` is stored positive. Display layer prefixes a `−` when shown as an outflow. Transactions remain signed as they have been (positive = inflow, negative = outflow); auto-match doesn't care about sign — it matches by description only.
- **Refund or reversal of a bill payment:** if a positive-amount transaction matches, it still gets `bill_id` set. "Paid this period" remains true (we don't try to detect refund-net-zero). User can manually unlink the refund transaction if desired.

## Data Model

### New: `bills` table
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, pk | |
| `name` | string(120) | unique per app (single-tenant) |
| `cadence` | string(16) | `'monthly'` \| `'annual'` |
| `due_day_of_month` | smallInteger | 1–31, always required |
| `due_month_of_year` | smallInteger, nullable | 1–12; required when `cadence = 'annual'` |
| `expected_amount_cents` | bigInteger | positive |
| `account_id` | foreignId, nullable, `nullOnDelete` | usual paying account |
| `category_id` | foreignId, nullable, `nullOnDelete` | category for budget attribution |
| `match_description` | string(255), nullable | substring to match against transaction descriptions |
| `manually_marked_paid_periods` | text, nullable | comma-separated period tokens: `2026-06,2026-07` or `2026,2027` |
| `color` | string(16), nullable | UI |
| `notes` | text, nullable | |
| `sort_order` | smallInteger, default 0 | |
| `created_at`, `updated_at` | timestamps | |

### Modify: `transactions` table
- Add `bill_id` foreignId, nullable, constrained on `bills`, `nullOnDelete`. Indexed.

No other schema changes.

## Components

### Models

- **`App\Models\Bill`**
  - Fillable: `name`, `cadence`, `due_day_of_month`, `due_month_of_year`, `expected_amount_cents`, `account_id`, `category_id`, `match_description`, `manually_marked_paid_periods`, `color`, `notes`, `sort_order`
  - Casts: integer columns to `integer`
  - `belongsTo(Account::class)`, `belongsTo(Category::class)`, `hasMany(Transaction::class)`
  - Helper methods:
    - `manuallyMarkedPeriods(): array` — splits `manually_marked_paid_periods` on commas, trims, filters empty
    - `addManuallyMarkedPeriod(string $period): void` — appends + saves
    - `removeManuallyMarkedPeriod(string $period): void` — removes + saves
    - `currentPeriodToken(): string` — `'Y-m'` for monthly, `'Y'` for annual
    - `nextDueDate(): CarbonImmutable` — computes next occurrence based on cadence + due fields, clamping to month-end for day-31 cases
- **`App\Models\Transaction`** — add `bill()` BelongsTo relation, add `bill_id` to fillable + cast

### Support

- **`App\Support\BillMatcher`** (single-purpose value object, mirrors `KeywordMatcher` from Phase 2)
  - Constructor: loads `Bill::whereNotNull('match_description')->get()` once
  - `match(string $description): ?int` — returns the single matching bill id, or null if zero or multiple bills match. Substring is case-insensitive (lowercase both sides before comparing).

### Actions (`app/Actions/Finance/Bills/`)

- **`ComputeBillsStatus`** — invokable, returns the array shape used by `/bills` and the dashboard widget (one row per bill with paid status, next due date, days_until_due, payment_source, last_paid_transaction_id). Two SQL queries: one for bills, one for current-period matched transactions grouped by `bill_id`. No N+1.
- **`CreateBill`, `UpdateBill`, `DeleteBill`** — standard single-purpose actions following Phase 3 Bucket pattern.
- **`MarkBillPaidThisPeriod`** — appends the current period token to the bill's `manually_marked_paid_periods` (no-op if already there).
- **`UnmarkBillPaidThisPeriod`** — removes the current period token.
- **`RematchUnlinkedBills`** — bulk action: iterate transactions where `bill_id IS NULL`, run `BillMatcher`, set `bill_id` for unambiguous matches. Returns `['updated' => int, 'still_unlinked' => int]`. Mirrors `RecategorizeUncategorized` from Phase 2.

### Modify existing import

- **`App\Actions\Finance\Imports\ParseCsvForPreview`** — also instantiate a `BillMatcher` once and stamp `bill_id` onto each preview row (alongside the existing `category_id` from the `KeywordMatcher`).
- **`App\Actions\Finance\Imports\ImportTransactions`** — persist `bill_id` from the preview row when inserting.

### Livewire SFC pages

- **`/bills` index** (`resources/views/pages/bills/⚡index.blade.php`)
  - Card grid of bills sorted by next due date (overdue first, then upcoming soonest, then later)
  - Each card: name, cadence badge, next due date with "Due in N days" or "Overdue by N days", expected amount, paid-this-period badge, "Mark paid"/"Unmark" button (changes based on state)
  - Top right: "New bill" + "Rematch unlinked transactions" (the bulk action with toast result)

- **`/bills/{bill}` show** (`resources/views/pages/bills/⚡show.blade.php`)
  - Bill metadata
  - Linked transactions table (paginated, most recent first) with date, description, amount, account, category
  - Manually-marked periods list with remove buttons
  - Edit + Delete actions

- **`/bills/⚡form.blade.php`** — create/edit child SFC (modal-style card)
  - Fields: name, cadence (radio), due day (1–31), due month (conditional — only when cadence='annual'), expected amount ($), account (select), category (select), match_description, color, notes

### Modify Livewire forms

- **`/transactions/⚡form.blade.php`** — add a "Bill" select (populated from `Bill::orderBy('name')->get()`) bound to `bill_id`. Optional; nullable.

### Dashboard widget

- **`resources/views/pages/dashboard/⚡upcoming-bills.blade.php`**
  - Lists bills with `next_due_date` within the next 14 days OR overdue
  - Per bill: name, due date label, expected amount, paid/unpaid status indicator
  - Hidden entirely when no bills are due in the window

### Routes + sidebar

- `Route::livewire('bills', 'pages::bills.index')->name('bills.index')`
- `Route::livewire('bills/{bill}', 'pages::bills.show')->name('bills.show')`
- Sidebar menu item "Bills" between "Goals" and "Imports", icon `lucide.calendar-clock`

### Dashboard embed

`resources/views/dashboard.blade.php` adds the upcoming-bills widget between the goal-progress widget and the balance chart.

## Validation

| Field | Rule |
|---|---|
| `name` | required, string, max 120 |
| `cadence` | required, in:`monthly,annual` |
| `due_day_of_month` | required, integer, 1–31 |
| `due_month_of_year` | required-if `cadence=annual`, integer, 1–12, otherwise nullable |
| `expected_amount_cents` | required, integer, ≥ 1 (form takes dollars, converts via `Money::toCents`) |
| `account_id` | nullable, exists:accounts,id |
| `category_id` | nullable, exists:categories,id |
| `match_description` | nullable, string, max 255 |
| `color` | nullable, string, max 16 |
| `notes` | nullable, string |

## Error Handling

| Scenario | Behavior |
|---|---|
| No bills defined | `/bills` shows empty-state CTA; dashboard widget hidden; CSV import skips bill-matching step silently. |
| `match_description` matches more than one bill on import | Both bills lose the match — transaction `bill_id` left null (ambiguous rule). User refines match strings. |
| Day-31 monthly bill in a 30-day month | `nextDueDate()` clamps to the 30th. Same for Feb (clamps to 28 or 29). |
| Annual bill with `cadence=annual` but `due_month_of_year` somehow null | Validation prevents save, but if present in DB, `nextDueDate()` defaults to January for safety; UI shows a warning. |
| Bill deleted while linked transactions still reference it | `nullOnDelete` cascades — transactions keep their data, lose the bill_id. |
| Multiple transactions match the same bill in the same period | All get `bill_id` set; `is_paid_this_period` is true; bill show page lists all. |
| Bill is auto-paid (e.g., by autopay), no CSV imported yet | Stays "unpaid" until import. User can use Manual Override path B if needed. |

## Testing

### `BillMatcher` unit tests
- Matches a single bill by substring (case-insensitive)
- Ambiguous match (≥2 bills) returns null
- No match returns null
- Bills with null `match_description` are never matched
- Bills with whitespace-only `match_description` are never matched

### `ComputeBillsStatus` action tests
- Empty bills → returns empty array, zero totals
- Bill with matched transaction in current period → `is_paid_this_period=true`, `payment_source='transaction'`
- Bill with no transactions but period in `manually_marked_paid_periods` → `is_paid_this_period=true`, `payment_source='manual'`
- Bill with neither → `is_paid_this_period=false`, `payment_source=null`
- Soft-deleted transactions excluded from the "paid" check
- `next_due_date` computed correctly for monthly bills (current month if not yet, else next)
- `next_due_date` clamps to last day of month when `due_day_of_month=31` in a shorter month
- `next_due_date` computed correctly for annual bills (this year if not yet, else next)
- `days_until_due` is negative when overdue
- `total_upcoming_cents` excludes bills already paid this period

### `MarkBillPaidThisPeriod` / `UnmarkBillPaidThisPeriod` tests
- Mark appends to the comma-separated list (no duplicate periods)
- Unmark removes the current period token
- Mark/unmark on a fresh bill works

### `RematchUnlinkedBills` action tests
- Touches only transactions with `bill_id IS NULL`
- Sets `bill_id` for unambiguous matches
- Leaves `bill_id` null when match is ambiguous
- Returns counts `['updated' => n, 'still_unlinked' => m]`
- Skips soft-deleted transactions

### `ParseCsvForPreview` integration test (added)
- A bill with `match_description='STARBUCKS'` set → row whose description contains 'STARBUCKS' has `bill_id` populated in the preview output
- Ambiguous bill match → `bill_id` is null

### `ImportTransactions` integration test (added)
- Preview rows with `bill_id` set persist that value to the created transactions

### Bill model tests
- `manuallyMarkedPeriods()` parses comma-separated list correctly (trims, filters empty)
- `addManuallyMarkedPeriod()` appends without duplicates
- `removeManuallyMarkedPeriod()` removes without affecting other entries
- `nextDueDate()` for monthly and annual cadences in various months
- Relationships: account, category, transactions

### Livewire feature tests
- `/bills` index — list, create, edit, delete, mark/unmark paid
- `/bills/⚡form` — required fields, conditional due_month_of_year visibility, dispatches `bill-saved`
- `/bills/{bill}` show — bill metadata, linked transactions list, manually-marked periods
- Transaction form — Bill select renders, persisting `bill_id`
- Dashboard widget — hidden when no upcoming bills, shows upcoming bills otherwise

## Out of Scope (Phase 5b and later)

- **Pay-timing optimizer** — "best day to pay this bill so the account doesn't drain" — Phase 5b
- Bill amount variance alerts ("Electric is $30 higher than usual this month")
- Notifications/email when a bill is approaching due
- Forecasting next month's total bill load against expected income
- Skip-this-month / one-time exception toggles per bill
- Auto-suggested `match_description` based on past transactions in the same category
- Bill templates / starter set
- Bills that must be paid from a specific account (we suggest, don't enforce)
- Reordering bills via drag-and-drop UI (the `sort_order` column exists for later)
