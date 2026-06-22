# Goals (Phase 4)

## Overview

Phase 4 of the finance tracker. Lets the user define savings goals (down payment, camera, emergency fund, debt payoff) each with a target dollar amount and a priority percentage. The "savings pool" — sum of current balances across all accounts flagged `counts_toward_goals` (set up in Phase 1) — is virtually subdivided across the goals by their percentages. Each goal shows its allocation, target, and funded% (capped at 100). Excess allocation from fully-funded goals overflows back into an "unallocated" bucket.

This unlocks the user's original ask: watching multiple savings goals fund simultaneously while the money sits in one or more real accounts.

## Decisions

- **Pool definition:** sum of `ComputeAccountBalance(account)` for every account where `counts_toward_goals = true` and `archived_at IS NULL`. No schema change — the flag already exists from Phase 1.
- **Per-goal math:** `raw_allocation = pool × priority% / 100`; `capped_allocation = min(target, raw_allocation)`; `funded% = capped_allocation / target × 100` (clamped at 100). `overflow = raw_allocation − capped_allocation` (≥ 0).
- **Overflow handling:** overflow from fully-funded goals returns to the unallocated pool, not to other goals. No waterfall/cascading allocation. Math is intentionally independent per goal — each goal sees only its own slice.
- **Unallocated:** `pool − sum(capped_allocation)`. Includes both the user-unallocated portion (priority %s sum to <100%) AND overflow from fully-funded goals.
- **Priority sum:** allowed to be any value 0–N. UI warns when the sum is not exactly 100% but never blocks save. Sum > 100% is over-committing (allocations exceed pool); sum < 100% leaves slack.
- **Target required:** every goal must have `target_cents ≥ 1`. No nullable target. Users can set a high placeholder if they don't have a real target yet.
- **No transaction-level linking:** Phase 4 does not connect specific transactions to goals. The pool is a single number computed from account balances.
- **No archive/complete flag:** when a user wants to retire a goal, they delete it. Archive can come later.
- **Sign convention:** target and allocations are positive integers in cents. Negative numbers don't occur in this model (you can't have a "negative goal").

## Data Model

One new table. No changes elsewhere.

### `goals`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, pk | |
| `name` | string(120) | "Down payment", "Camera", "Emergency fund" |
| `target_cents` | bigInteger | required, ≥ 1 |
| `priority_percentage` | smallInteger | required, 0–100 |
| `color` | string(16), nullable | hex for UI |
| `notes` | text, nullable | optional context |
| `sort_order` | smallInteger, default 0 | display order |
| `created_at`, `updated_at` | timestamps | |

## Components

### Models

- **`App\Models\Goal`**
  - Fillable: `name`, `target_cents`, `priority_percentage`, `color`, `notes`, `sort_order`
  - Casts: `target_cents`/`priority_percentage`/`sort_order` → integer
  - No special methods on the model itself; the math lives in the action

### Actions (`app/Actions/Finance/Goals/`)

- **`ComputeGoalsStatus`** — invokable, no args.
  - Computes the savings pool: `SUM(starting_balance + transaction net)` joined on accounts where `counts_toward_goals = true` AND `archived_at IS NULL`. Uses the same join pattern as the existing balance computations.
  - Loads all goals ordered by `sort_order` then `id`.
  - For each goal: compute `raw_allocation`, `capped_allocation`, `funded_percentage`, `overflow_cents`, `is_fully_funded`.
  - Returns:
    ```php
    [
      'pool_cents' => 1000000,
      'goals' => [
        [
          'id' => 1,
          'name' => 'Debt',
          'color' => '#ef4444',
          'priority_percentage' => 30,
          'target_cents' => 300000,
          'raw_allocation_cents' => 360000,
          'capped_allocation_cents' => 300000,
          'funded_percentage' => 100,
          'overflow_cents' => 60000,
          'is_fully_funded' => true,
        ],
        // ...
      ],
      'total_allocated_cents' => 600000,
      'unallocated_cents' => 400000,
      'total_priority_percentage' => 60,
    ]
    ```

- **`CreateGoal`, `UpdateGoal`, `DeleteGoal`** — standard single-purpose invokables, same shape as `CreateBucket` from Phase 3.

### Livewire SFC pages

- **`resources/views/pages/goals/⚡index.blade.php`** — list page
  - Top card: "Savings pool: $10,000.00" with a small line listing which accounts contribute
  - Allocation summary: "60% allocated · 40% unallocated ($4,000.00)" with a yellow note if total priority > 100%
  - Card list of goals: each card shows name, priority %, target $, current allocation $, funded% progress bar (color from `goal.color`), "fully funded" badge when `is_fully_funded`
  - Edit + delete per goal
  - "New goal" button → form child SFC

- **`resources/views/pages/goals/⚡form.blade.php`** — child SFC (create/edit), modal-style card matching the `/buckets/⚡form` pattern
  - Fields: name, target ($), priority %, color, notes (textarea)

- **`resources/views/pages/dashboard/⚡goal-progress.blade.php`** — dashboard widget embedded below `budget-status`
  - Compact view: top line shows "Savings pool: $X / Total goal targets: $Y"
  - One row per goal with name + funded% progress bar + dollar allocation

### Routes + sidebar

- `Route::livewire('goals', 'pages::goals.index')->name('goals.index')` inside the existing `auth + verified` group
- Sidebar menu item "Goals" between "Budget" and "Imports", icon `lucide.target`

## Validation

| Field | Rule |
|---|---|
| `name` | required, string, max 120 |
| `target_cents` | required, integer, ≥ 1 (Livewire form takes dollars, converts via `Money::toCents`) |
| `priority_percentage` | required, integer, 0–100 |
| `color` | nullable, string, max 16 |
| `notes` | nullable, string |

- Sum of all goal priority %s: soft warning shown when ≠ 100; never blocks save.

## Error Handling

| Scenario | Behavior |
|---|---|
| No accounts flagged `counts_toward_goals` | `pool_cents = 0`. All goals show 0% funded. UI prompts user to mark a savings account. |
| Goals defined but sum < 100% | All math works. UI shows "unallocated" portion. |
| Goals defined but sum > 100% | All math still works (each goal allocates from pool independently). UI warns "Total priority is X% — over-committed by Y%". |
| Goal with target_cents = 1 | Always 100% funded with any pool > 0. Math still works. (User sets a tiny target as a placeholder — visual only, no edge case.) |
| Deleted goal | Removed from `/goals` page; allocation returns to unallocated pool. |
| `counts_toward_goals` account becomes archived | Excluded from pool computation. Goals re-evaluate next render. |

## Testing

### `ComputeGoalsStatusTest`
- Returns correct pool when one or many `counts_toward_goals` accounts contribute (sum of starting balance + transactions)
- Excludes archived savings accounts
- Excludes accounts not flagged for goals
- Excludes soft-deleted transactions
- Per-goal: raw and capped allocations match the formula
- Over-funded goal: funded% capped at 100, overflow_cents = raw − target
- Under-funded goal: funded% = capped/target × 100 (integer-rounded)
- `is_fully_funded` = true when `capped_allocation == target`
- Unallocated = pool − sum(capped); accounts for both slack and overflow
- `total_priority_percentage` = sum of all goal priority %s
- Empty goals list → returns empty `goals`, `unallocated_cents = pool`
- Empty pool + goals exist → all goals show 0% funded

### `CreateGoalTest` / `UpdateGoalTest` / `DeleteGoalTest`
- Standard CRUD coverage (same shape as Phase 3 bucket CRUD)

### `Goal` model test
- Factory + persistence; relationships are not needed (goals don't FK to anything in Phase 4)

### Livewire feature tests
- **`/goals` index** — list rendering, savings-pool display, create/edit/delete flow, form-open/close events
- **`/goals/⚡form`** — required fields, conversion of dollars to cents, dispatches `goal-saved`
- **Dashboard goal-progress widget** — renders goals when they exist; hides when no goals defined

## Out of Scope (later)

- Linking specific transactions to specific goals ("this $500 deposit goes toward the down payment")
- Auto-archive when a goal hits 100% funded
- Recurring goals / templates ("Annual emergency fund top-up")
- Time-targeted goals ("$5k by July 2026 → at current savings rate, ETA Oct 2026")
- Goal categories or grouping
- Reordering goals via drag-and-drop UI (the `sort_order` column exists for later)
- Notifications when a goal hits 100%
- Multiple savings pools (currently one global pool)
- Integration with Phase 3 bucket math (savings bucket → goals pool is conceptually overlapping but kept independent here)
