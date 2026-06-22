# Pay-Timing Optimizer & Calendar Design

**Phase:** 5b (split off from Phase 5 — Bills, after 5a shipped)

**Goal:** Recommend when to pay each upcoming bill — early when comfortable, latest-safe when tight — using a deterministic balance projection that accounts for income, scheduled bills, and forecast variable spending. Surface those recommendations on a clean monthly calendar.

## Foundational Decisions

| Decision | Choice |
|---|---|
| Trend / forecast brain | **Math only.** Deterministic SQL/PHP. Ollama is deferred to Phase 6, where it becomes the natural-language explainer over the same actions. |
| Calendar role | **Viewer with recommendation overlay.** Click-through to drawer for details. No drag-to-reschedule. |
| Paid pill treatment | Faded (`opacity-50`), `✓` prefix, shows actual transaction amount (not the expected amount). |
| Safety floor | **Per-account** — new column `accounts.minimum_balance_cents`, default 0. |
| Income projection | **`IncomeSource` model** that mirrors `Bill`. No auto-detection in this phase (defer to Phase 6 if useful). |
| Recommendation philosophy | **Earliest safe day in `[today, due_date]`** — pay early when projected balance after payment stays ≥ floor through the due date; otherwise push later until safe. |
| Variable spending in projection | **Yes — per-category median over the last 12 weeks.** Categories tied to a bill are excluded (would double-count). |
| Persistence of recommendations | **None.** Computed on demand at calendar load. |
| Ollama / AI features | **Not in this phase.** Phase 6 adds analytics actions + MCP server + chat UI on top of this engine. |

## Architecture

Three layers, cleanly separated:

1. **Forecast engine** — pure-function actions under `app/Actions/Finance/Forecast/`. No DB writes, no side effects. Each is independently testable with factories.
2. **Pay-timing optimizer** — one action that consumes the projection plus the unpaid-bill list and returns a recommended pay date per bill.
3. **Calendar page** — full-page Livewire SFC at `/calendar`. Composes the forecast actions on mount, renders month grid + side rail.

No background jobs. Everything is on-demand at request time. The math is fast (a single day-by-day walk over a ~60-day window).

## Data Model

### New table `income_sources`

| column | type | notes |
|---|---|---|
| `id` | bigInt PK | |
| `name` | string | e.g., "Paycheck — Jamie" |
| `cadence` | string | `weekly` / `biweekly` / `semi_monthly` / `monthly` |
| `next_expected_on` | date | anchor for cadence math; advances on auto-match |
| `secondary_day_of_month` | smallInt nullable | only used when cadence is `semi_monthly` (the second monthly day; primary is implied by `next_expected_on`) |
| `expected_amount_cents` | bigInt | signed positive |
| `account_id` | FK → `accounts` | which account receives the deposit |
| `category_id` | FK → `categories` nullable | income-kind category for matching |
| `match_description` | string nullable | exact-ish substring like `PAYROLL ALLAN MICHAEL` |
| `color` | string nullable | for calendar/pill rendering |
| `notes` | text nullable | |
| `sort_order` | integer default 0 | |
| `created_at` / `updated_at` | timestamps | |

### Migration: add `accounts.minimum_balance_cents`

```php
Schema::table('accounts', function (Blueprint $table) {
    $table->bigInteger('minimum_balance_cents')->default(0);
});
```

Backfills 0 for existing rows; the form on `/accounts/{account}` exposes the field.

### No change to `bills`

Phase 5a's `transaction_id` and `manually_marked_paid_periods` still drive paid-state detection.

## Forecast Engine

Five single-purpose invokable actions in `app/Actions/Finance/Forecast/`:

### `ProjectIncomeDeposits`

**Signature:** `(IncomeSource[] $sources, CarbonImmutable $start, CarbonImmutable $end): array<{date: string, account_id: int, cents: int, income_source_id: int}>`

Walks each source's cadence forward from `next_expected_on` until `end`, emitting one deposit per occurrence. For `semi_monthly`, emits two per month (primary day + `secondary_day_of_month`). Day-of-month clamping for short months (e.g., 31 → last day of February).

### `ProjectBillCharges`

**Signature:** `(Bill[] $bills, CarbonImmutable $start, CarbonImmutable $end): array<{date: string, account_id: int, cents: int, bill_id: int}>`

Same idea for bills. Includes **all** upcoming bills in the window, including ones already paid this period. Paid status is layered on later by the UI / optimizer; the forecast engine does not branch on it.

### `ForecastVariableSpend`

**Signature:** `(CarbonImmutable $start, CarbonImmutable $end): array<{date: string, category_id: int, cents: int}>`

For each spending-kind category, compute the median weekly spend over the trailing window (default 12 weeks), divide by 7 to get a per-day amount, and emit one entry per (date, category) across `[$start, $end]`.

**Exclusion rule:** skip any category that is referenced by at least one row in `bills.category_id`. Those categories' spend is already represented by `ProjectBillCharges`; including them would double-count.

Uses **median**, not mean, so a single $5k outlier doesn't dominate. Categories with fewer than 4 weeks of data return zero.

Window length comes from `AppSetting('forecast_lookback_weeks', 12)`.

### `ComputeProjectedBalance`

**Signature:** `(Account[] $accounts, CarbonImmutable $start, CarbonImmutable $end): array<{account_id: int, date: string, balance_cents: int}>`

Orchestrator. For each account, seeds the day-0 balance from existing `ComputeBalanceSeries(today)`. Then walks day-by-day to `end`, adding deposits (from `ProjectIncomeDeposits`), subtracting bills (from `ProjectBillCharges`), subtracting the variable-spend slice (from `ForecastVariableSpend`, summed across categories for that day).

Returns the same shape `ComputeBalanceSeries` produces, so the existing dashboard chart can render a "Projected" series alongside the historical line in the future.

### `RecommendPayDates`

**Signature:** `(Bill[] $unpaidBills, array $projection): array<{bill_id: int, recommended_date: string, warning: bool}>`

For each unpaid bill:

1. Walk `[today, bill.due_date]` day by day.
2. For each candidate day `d`, hypothetically subtract `bill.expected_amount_cents` from `projection[bill.account_id][d…due_date]`.
3. If every day in that subrange stays ≥ `account.minimum_balance_cents`, this is the earliest safe day — return it.
4. If no candidate day works, return `due_date` with `warning: true`.

The early-when-comfortable behavior falls out naturally: if today already passes the check, today is the recommendation.

## Pay-Timing Optimizer

`RecommendPayDates` IS the optimizer. Listed as its own concept because it's the only action that has knowledge of the recommendation policy. All other forecast actions are policy-free.

## Calendar Page

**Route:** `Route::livewire('/calendar', 'pages::calendar.index')->name('calendar')`. Sidebar link added between "Bills" and "Imports".

**File:** `resources/views/pages/calendar/⚡index.blade.php` (full-page SFC).

### Layout (option B confirmed)

```
┌─ Calendar — June 2026 ─────────────────────────── [<] [Today] [>] ─┐
│  ┌────────────────────────────────────┐  ┌─ This week ──────────┐ │
│  │  S   M   T   W   T   F   S         │  │ Spectrum  ⚠ low      │ │
│  │  …  1   2   3   4   5   6          │  │   due Fri · pay Thu  │ │
│  │      [Mtg]      [Spec rec] [Ins ✓] │  │ T-Mobile             │ │
│  │  7   8   9  10  11  12  13         │  │   due Mon · $78      │ │
│  │             [T-Mob]    [Spec]      │  └──────────────────────┘ │
│  │ 14  15  16  17  18  19  20         │  ┌─ Recommendation ─────┐ │
│  │     [Mtg rec]              [Wst]   │  │ Pay Spectrum on the  │ │
│  │ 21  22  23  24  25  26  27         │  │ 4th — balance dips   │ │
│  │     [Pwr]                          │  │ below $500 on the 9th│ │
│  └────────────────────────────────────┘  └──────────────────────┘ │
└────────────────────────────────────────────────────────────────────┘
```

### Pill states (Blade partial `partials.bill-pill`)

| State | Visual | Triggered by |
|---|---|---|
| Due, unpaid | solid `badge badge-primary` with bill name | bill in window, no matched txn for current period |
| Recommended pay day | `badge badge-outline border-dashed border-primary` | `recommended_date ≠ due_date` for unpaid bill |
| Paid | `badge badge-ghost opacity-50` prefixed with `✓`, showing actual `$amount` | bill has matched txn for current period |
| Unsafe (no safe day) | solid `badge badge-error` | `RecommendPayDates` returned `warning: true` |

### Interactions

- Click any pill → MaryUI `<x-drawer>` slides in from the right showing bill detail, "Mark paid this period" button, and "Go to bill" link.
- `[<] [Today] [>]` paginate months. URL state: `/calendar?month=2026-07`.
- Top-right month dropdown for jumping to arbitrary months.

### Side rail

- **This week** — bills due in next 7 days (paid or not). Each row links to the bill's drawer.
- **Recommendation** — single top item: the unsafe warning, or the earliest-shifted recommendation, or "All bills on track."

### Data flow on mount

```
window = [today - 1 day, today + 60 days]
incomeSources = IncomeSource::all()
bills = Bill::all()
accounts = Account::active()->get()

projection = ComputeProjectedBalance(accounts, window)
  ├─ ProjectIncomeDeposits(incomeSources, window)
  ├─ ProjectBillCharges(bills, window)
  └─ ForecastVariableSpend(window)

recommendations = RecommendPayDates(unpaidBills($bills), projection)

[render]
```

Switching months re-runs the chain. No caching — recomputing 60 days is sub-50ms locally.

## Income CRUD

Mirror of Bills.

### Routes

- `/income` — index
- `/income/create` — form
- `/income/{income}` — show + edit combined

### Files

- `resources/views/pages/income/⚡index.blade.php`
- `resources/views/pages/income/⚡form.blade.php`
- `resources/views/pages/income/⚡show.blade.php`

### Actions in `app/Actions/Finance/Income/`

- `CreateIncomeSource(array $data): IncomeSource`
- `UpdateIncomeSource(IncomeSource $source, array $data): IncomeSource`
- `DeleteIncomeSource(IncomeSource $source): void`
- `AdvanceIncomeAnchor(IncomeSource $source): void` — adds one cadence interval to `next_expected_on`. For `semi_monthly`, alternates between primary day and `secondary_day_of_month`.
- `MatchIncomeToTransactions(array $transactions): void` — mirror of `BillMatcher`. Called from the import pipeline.

### Auto-match hook

In the import pipeline (where `BillMatcher` runs today), call `MatchIncomeToTransactions` next. Match logic:

- Filter to deposit-positive transactions in income-kind categories.
- For each, case-insensitive substring match on `match_description` of any `IncomeSource`.
- On match: call `AdvanceIncomeAnchor($source)`.

### Sidebar

Insert "Income" between "Goals" and "Bills" — top-to-bottom cash flow reads income → bills → goals.

### `semi_monthly` form UX

When `cadence === 'semi_monthly'`:
- Show `next_expected_on` as the primary day input (rendered as a date).
- Show an additional `secondary_day_of_month` input (1–31).
- Below both: a helper line — "Income lands on the 1st and 15th" — computed live from the inputs.

When cadence is any other value, the secondary input is hidden and saved as `null`.

## Testing Strategy

Pest feature tests under `tests/Feature/`. No HTTP layer required for forecast actions.

### Forecast actions (≈ 25 tests)

Under `tests/Unit/Actions/Finance/Forecast/`:

- `ProjectIncomeDepositsTest` — one test per cadence (weekly / biweekly / semi_monthly / monthly), short-month day clamping (cadence advance from Jan 31 → Feb 28 or 29), anchor-past-end-of-range (returns empty), and account-id propagation.
- `ProjectBillChargesTest` — monthly + annual cadence, day clamping for short months, inclusion-of-paid (paid bills still appear in the projection).
- `ForecastVariableSpendTest` — median-not-mean (one $5k outlier doesn't blow up the result), excludes categories referenced by any bill, returns zero for categories with < 4 weeks of data, respects `forecast_lookback_weeks` AppSetting.
- `ComputeProjectedBalanceTest` — end-to-end with seeded transactions + bills + income, asserts day-N balance matches a hand-computed value.
- `RecommendPayDatesTest` — paid bills excluded, bill that fits today, bill that has to wait for paycheck, bill with no safe day (`warning: true`), per-account floor enforcement, today-is-safe (recommendation == today).

### Income action tests

Under `tests/Unit/Actions/Finance/Income/`:

- `CreateIncomeSourceTest`, `UpdateIncomeSourceTest`, `DeleteIncomeSourceTest` — mirror the existing `Bills` action tests.
- `AdvanceIncomeAnchorTest` — covers all four cadences; semi_monthly test asserts it alternates between primary day and `secondary_day_of_month`.
- `MatchIncomeToTransactionsTest` — substring match advances anchor; non-matching transactions are ignored; multiple sources don't conflict.

### Page tests

Under `tests/Feature/Pages/`:

- `Calendar/IndexTest.php` — seed user, account, one paid bill, one unpaid bill with `recommended_date != due_date`. `get('/calendar')` and assert paid bill renders with `opacity-50` + `✓`, unpaid bill renders on both due-date and recommended-date cells, side rail "Recommendation" surfaces the shifted date.
- `Income/IndexTest.php`, `Income/FormTest.php`, `Income/ShowTest.php` — clone the shape of `Pages/Bills/*Test.php`. Cover list, create, edit, delete, semi_monthly secondary-day persistence.

### Import hook

Under `tests/Feature/Pages/Imports/` (or extend `WizardTest.php`):

- New test: import a CSV containing a `PAYROLL ALLAN MICHAEL` deposit; assert the matching `IncomeSource.next_expected_on` advanced by one cadence interval.

**Total new tests:** ~35.

## Out of Scope (deferred to Phase 6)

- Ollama chat UI
- MCP server exposing finance tools
- Analytics actions (month-over-month, top movers, anomaly detection, savings rate, etc.)
- An "Insights" panel that surfaces detected patterns proactively
- Auto-detection of income sources from history

These all build cleanly on the engine we're shipping in 5b. Phase 6 needs no schema changes from this spec.
