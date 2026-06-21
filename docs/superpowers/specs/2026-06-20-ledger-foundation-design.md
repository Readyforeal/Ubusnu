# Ledger Foundation (Phase 1)

## Overview

Phase 1 of a personal finance tracking application built on Laravel 13 / Livewire 4 / MaryUI. Establishes the data substrate every later phase depends on: multiple accounts with starting balances, CSV transaction import with per-account column mapping and duplicate detection, manual transaction entry/edit/delete, a category table (manual assignment only — auto-classification ships in Phase 2 except for a minimal Transfer match), and an on-the-fly balance-over-time chart.

This is the first of seven planned phases. Future phases build on the data model defined here:

1. **Ledger Foundation** ← this spec
2. Categories & auto-categorization (full keyword engine)
3. Budgets & buckets (configurable rule like 50/30/20)
4. Goals (virtual subdivision of savings-pool accounts by priority %)
5. Bills & pay-timing optimization (learn best pay-day from balance trends)
6. Ollama chat (LLM with tool calls into the Actions layer)
7. Docker + Ubuntu homelab deployment

## Decisions

- **Architecture:** Approach B — business logic lives in `app/Actions/Finance/*` single-purpose classes; Livewire components are thin orchestration. Sets up Phase 6 (Ollama tool calls via `laravel/mcp`) to reuse the same code that drives the UI.
- **Database:** SQLite for development. Schema is Postgres-portable for later migration.
- **Tenancy:** None. Single shared app for the user + partner. No `households` or scoping middleware.
- **Money:** Stored as signed `bigInteger` cents. Custom `Money` cast handles dollars↔cents at the model edge. Display layer formats `en_US` (`$1,234.56`).
- **CSV strategy:** Per-account import profile. First import walks a column-mapping wizard; mapping saved to `account.import_profile` and auto-applied on subsequent imports.
- **Transfers:** Handled via a seeded `Transfer` category with `excluded_from_totals=true`. Keywords auto-match transfer transactions on import (minimal Phase 1 categorization for Transfer only).
- **Account types:** Freeform `name` + boolean `counts_toward_goals`. Credit cards are just accounts that can hold a negative balance.
- **Dedup:** Hash of `account_id | occurred_on | amount_cents | normalized(description)`. App-level check during import preview, DB-level unique constraint on `(account_id, dedup_hash)` as safety net.
- **Balance computation:** On-the-fly from `starting_balance + sum(transactions)`. No snapshot table. Sub-100ms in SQLite at expected data volumes; easy to add caching later if ever needed.
- **Chart:** MaryUI `<x-chart>` (ApexCharts wrapper) — area style with no smoothing. Default 30-day range; presets for 90d, YTD, all-time + custom range picker. Per-account view OR sum-of-all-accounts toggle.
- **Containerization:** Phase 7. Laravel apps are 12-factor friendly out of the box; idiomatic code now means containerization later is a 1–2 day exercise.

## Data Model

Five tables. All money in signed `bigInteger` cents.

### `accounts`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, pk | |
| `name` | string | Freeform, e.g. "Tangerine Chequing" |
| `starting_balance_cents` | bigInteger, signed | Negative allowed (credit cards) |
| `counts_toward_goals` | bool, default false | Phase 4 reads this |
| `archived_at` | timestamp, nullable | Soft-archive closed accounts |
| `import_profile` | json, nullable | Column mapping for CSV imports |
| `created_at`, `updated_at` | timestamps | |

`import_profile` JSON shape:
```json
{
  "delimiter": ",",
  "has_header": true,
  "date_column": "Transaction Date",
  "date_format": "m/d/Y",
  "description_column": "Description",
  "amount_column": "Amount"
}
```

### `categories`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, pk | |
| `name` | string, unique | |
| `keywords` | text, nullable | Comma-separated; Phase 2 reads, Phase 1 uses for Transfer auto-match only |
| `excluded_from_totals` | bool, default false | True for `Transfer` |
| `color` | string, nullable | Hex color for UI |
| `created_at`, `updated_at` | timestamps | |

**Seed:** one row at install — `Transfer` with `excluded_from_totals=true` and default keywords (`transfer, tfr, to chequing, to savings, e-transfer, etfr`).

### `transactions`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, pk | |
| `account_id` | fk → accounts | |
| `occurred_on` | date | |
| `description` | text | |
| `amount_cents` | bigInteger, signed | + = money in, − = money out |
| `category_id` | fk → categories, nullable | |
| `dedup_hash` | string | sha256 of normalized row identity |
| `import_batch_id` | fk → import_batches, nullable | Null = manual entry |
| `source` | enum: `import`, `manual` | |
| `notes` | text, nullable | User-added comments |
| `created_at`, `updated_at` | timestamps | |
| `deleted_at` | timestamp, nullable | Soft delete |

**Indexes:**
- Composite unique `(account_id, dedup_hash)` — DB-level dedup safety net.
- Index on `(account_id, occurred_on)` for balance queries.
- Index on `import_batch_id` for batch undo.

`dedup_hash` construction:
```
sha256( account_id + "|" + occurred_on + "|" + amount_cents + "|" + normalized_description )
```
`normalized_description` = trim, collapse whitespace, lowercase.

### `import_batches`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint, pk | |
| `account_id` | fk → accounts | |
| `user_id` | fk → users | Who triggered the import |
| `filename` | string | Original uploaded filename |
| `row_count` | int | Total rows in source CSV |
| `imported_count` | int | Rows actually persisted |
| `skipped_duplicate_count` | int | |
| `error_count` | int | Unparseable rows |
| `undone_at` | timestamp, nullable | Set when user undoes the batch |
| `created_at` | timestamp | |

### `users` (existing — no changes)
Phase 1 uses the existing Fortify-driven users table as-is. No new columns.

## Components

### Models (`app/Models/`)
- `Account` — `hasMany(Transaction)`, `hasMany(ImportBatch)`
- `Transaction` — `belongsTo(Account)`, `belongsTo(Category)`, `belongsTo(ImportBatch)`. Soft-deletes.
- `Category` — `hasMany(Transaction)`
- `ImportBatch` — `belongsTo(Account)`, `belongsTo(User)`, `hasMany(Transaction)`

### Actions (`app/Actions/Finance/`)
Each is a single-purpose invokable class. Reusable by Livewire components, Artisan commands, and (Phase 6) Ollama MCP tools.

**Account actions**
- `CreateAccount(name, startingBalanceCents, countsTowardGoals)`
- `UpdateAccount(account, attrs)`
- `ArchiveAccount(account)`
- `ComputeAccountBalance(account, asOf = null)` → cents

**Transaction actions**
- `CreateTransaction(account, occurredOn, description, amountCents, categoryId?)`
- `UpdateTransaction(transaction, attrs)`
- `DeleteTransaction(transaction)` (soft)
- `CategorizeTransaction(transaction, category)`

**Import actions**
- `ParseCsvForPreview(account, file, profile)` → array of parsed rows + dedup status per row (does NOT persist)
- `ImportTransactions(account, parsedRows, userId, filename)` → creates `ImportBatch` + transactions, wrapped in DB transaction
- `UndoImportBatch(batch)` → soft-deletes batch's transactions, marks `undone_at`

**Balance actions**
- `ComputeBalanceSeries(accounts, startDate, endDate)` → `[{date, balance_cents}, …]`

### Livewire components (`app/Livewire/`)
| Component | Route | Purpose |
|---|---|---|
| `Accounts/Index` | `/accounts` | List + current balances |
| `Accounts/Show` | `/accounts/{account}` | Transactions + chart for one account |
| `Accounts/Form` | (modal) | Create/edit |
| `Transactions/Index` | `/transactions` | Global filterable table |
| `Transactions/Form` | (modal) | Manual entry/edit |
| `Transactions/Table` | (partial) | Reusable transaction list |
| `Imports/Wizard` | `/imports/new` | Multi-step wizard (upload → map → preview → confirm) |
| `Imports/Index` | `/imports` | Batch history + undo |
| `Categories/Index` | `/categories` | List + inline edit |
| `Categories/Form` | (modal) | Create/edit |
| `Charts/BalanceChart` | (embedded) | Balance time-series chart |

Dashboard (existing `/dashboard`) updated to show account tiles + balance chart.

### Sidebar nav (in existing layout)
Add links: Dashboard · Accounts · Transactions · Imports · Categories.

## CSV Import Flow

### Step 1 — Upload
- User picks target account; uploads CSV.
- Validated: MIME `text/csv`/`text/plain`, max 10 MB.
- File streamed to `storage/app/imports/{uuid}.csv` (temp).
- App reads first 5 rows for header sniffing.

### Step 2 — Map columns
- **If profile saved AND CSV headers match:** skip to Step 3.
- **If profile saved but headers differ:** warn "this CSV looks different from previous imports for this account"; show mapping UI prefilled with previous values.
- **If no profile yet (first import):**
  - Show first 5 parsed rows as preview.
  - User picks columns for **Date**, **Description**, **Amount** from dropdowns.
  - User picks **date format**: `MM/DD/YYYY`, `DD/MM/YYYY`, `YYYY-MM-DD`, `M/D/YY`, etc.
  - "Has header row" checkbox (auto-checked when first row looks header-y).
  - On submit: profile saved to `account.import_profile`.

### Step 3 — Preview & dedup
- Parse entire CSV with active mapping.
- For each row: compute `dedup_hash`, query existing transactions in this account for matches.
- Display table with columns: `☐ Include | Date | Description | Amount | Status`.
- Status badges: `New`, `Duplicate of #1234` (linked), `Error: <reason>`.
- Duplicates pre-unchecked (user can force-include).
- Error rows have checkbox disabled.
- Apply minimal Transfer auto-match: if `description` matches a Transfer keyword, set `category_id` to the Transfer row.
- Header strip: `47 new · 3 duplicates · 1 error`.

### Step 4 — Confirm
- "Import N rows" button.
- `ImportTransactions` action runs inside a DB transaction:
  1. Create `ImportBatch` with counts.
  2. Bulk-insert selected transactions.
  3. DB unique constraint `(account_id, dedup_hash)` catches anything app-level missed; rejected rows increment `error_count`, batch still commits.
- On success: redirect to `/imports/{batch}` showing summary + "Undo this import".
- Temp CSV deleted.

### Undo
- `/imports` lists all batches (active first, undone collapsed).
- "Undo" → confirmation modal → soft-deletes every transaction with that `import_batch_id`, sets `undone_at` on batch.

### Error Handling
| Scenario | Behavior |
|---|---|
| Empty CSV / wrong delimiter / unreadable | Error at Step 1, prompt for re-upload. |
| Mapping produces zero parseable dates | Error in Step 2 with "Re-map" link. |
| Row-level errors (bad date / non-numeric amount) | Shown in red in Step 3, user proceeds without them; counted in `error_count`. |
| DB error mid-commit | Transaction rolled back; temp file kept for retry. |
| File >10 MB | Rejected at upload (10 MB ≈ 100k rows). |

## Balance Calculation & Chart

### `ComputeAccountBalance(account, asOf = null)`
```
balance = account.starting_balance_cents
        + sum(transactions.amount_cents
              where account_id = X
              and occurred_on <= asOf
              and deleted_at is null)
```
Single SQL aggregate. `asOf = null` means today. `excluded_from_totals` does **not** affect balance — transfers move real money and affect each account's balance. The flag only excludes from income/expense rollups (Phase 3).

### `ComputeBalanceSeries(accounts, startDate, endDate)`
Returns `[{date, balance_cents}, …]` — the chart's input.

Per account:
1. **Anchor:** `starting_balance + sum(amount_cents where occurred_on < startDate AND deleted_at is null)`.
2. Pull transactions in `[startDate, endDate]`, group by `occurred_on`, sum per day.
3. Walk day-by-day, applying daily net delta to running balance. Days with no transactions forward-fill the previous day's balance.

Household total: run per-account algorithm, sum per-day arrays at the end.

### Chart UI
- MaryUI `<x-chart>` → ApexCharts area type, smoothing off (step-style is honest for ledger balances).
- **Scope selector:** dropdown of accounts + "All accounts (total)". State in URL query param so refresh preserves view.
- **Range presets:** `30d` (default), `90d`, `YTD`, `All-time` + custom date-range picker.
- **Tooltip:** date + currency-formatted balance.
- **Y-axis:** currency-formatted ticks.
- **Empty state:** account exists but no transactions → flat line at `starting_balance` + "Import your first transactions" CTA.

## Testing Strategy

Pest 4. Project rule: every change tested.

### Actions — primary coverage
- `ComputeAccountBalanceTest` — empty account, single transaction, mixed signs, deleted transactions excluded, `asOf` past/present.
- `ImportTransactionsTest` — happy path, duplicates skipped, error rows counted, rollback on mid-import failure, undone batches excluded from active.
- `ParseCsvForPreviewTest` — applies saved profile, detects header mismatch, parses standard date formats, surfaces row-level errors.
- `ComputeBalanceSeriesTest` — anchor correctness, day-gap fill, household sum, deleted transactions excluded.
- `UndoImportBatchTest` — only that batch's rows soft-deleted; other transactions untouched.

### Models — light tests
- Relationships exist on each model.
- `Money` cast round-trips (`12.34 → 1234 → 12.34`); handles `$0`, negatives, fractional cents.
- `Transaction` soft-delete works (default scope excludes).
- `dedup_hash` is deterministic — same row, same hash; whitespace/case normalization stable.

### Livewire — feature tests
- `AccountsFormTest` — create/edit/archive happy path.
- `TransactionsFormTest` — manual entry, validation, edit, soft-delete.
- `ImportsWizardTest` — full wizard with a fixture CSV: upload → map → preview shows correct dedup state → commit creates batch + transactions.
- `ImportsIndexTest` — undo removes batch's transactions, marks `undone_at`.
- `BalanceChartTest` — emits correct series shape for scope + range.

### Browser (Pest 4 native) — selective
- Import wizard end-to-end with real CSV upload (multi-step state across renders).
- Dashboard renders chart without JS console errors.

### Fixtures (`tests/Fixtures/csv/`)
- `sample-standard.csv` — clean Date/Description/Amount, no duplicates.
- `sample-with-duplicates.csv` — overlapping date range, dedup test.
- `sample-bad-rows.csv` — bad dates and non-numeric amounts mixed with good rows.
- `sample-alt-headers.csv` — different column names, validates mapping wizard.

### Factories
- `AccountFactory` (states: `archived`, `countsTowardGoals`)
- `TransactionFactory` (states: `onDate($date)`, `withAmount(cents)`, `forAccount($account)`, `manual`, `fromImport($batch)`)
- `CategoryFactory` (state: `excludedFromTotals`)
- `ImportBatchFactory` (state: `undone`)

### Explicitly not tested
- Default Laravel/Fortify auth — already trusted.
- MaryUI components themselves.
- Money formatting against every locale quirk — one happy-path format test is enough.

## Out of Scope (deferred to later phases)

- Auto-categorization beyond Transfer keyword match → Phase 2
- Bucket grouping of categories / custom rule (50/30/20 etc.) → Phase 3
- Goals → Phase 4
- Bills, recurring transactions, pay-timing optimizer → Phase 5
- Ollama chat + MCP tool wrappers around the Actions layer → Phase 6
- Docker / homelab deployment → Phase 7
- Multi-currency, foreign exchange
- Investment tracking (positions, market data)
- Transfer pair-linking (we use category-level exclusion instead)
- Multi-user permissions / household tenancy
