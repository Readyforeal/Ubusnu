# Auto-Categorization (Phase 2)

## Overview

Phase 2 of the finance tracker. Generalizes the keyword-based categorization that already runs for the seeded "Transfer" category during CSV import — extending it to every category, and adding a one-shot "recategorize uncategorized transactions" action so newly-added keywords retroactively apply to existing transactions.

The user's intended workflow: import CSVs, let the matcher tag most rows automatically, manually fix the few that get missed or mis-categorized each week (e.g., a $5 Caseys swipe that's a Monster Energy, not gas).

## Decisions

- **Matching algorithm:** word-boundary regex (`/\bkeyword\b/iu`) against the lowercased description. Keywords pass through `preg_quote()` first so user input can't break the matcher.
- **Ambiguity rule:** if a description matches keywords on more than one category, the transaction stays uncategorized (`category_id = null`). User picks manually. No silent guessing.
- **Retroactive application:** manual button on `/categories` page only. Action operates on `category_id IS NULL` rows. Already-categorized transactions are never touched.
- **Manual edits stick forever:** because the matcher only fills `null` rows, anything the user manually categorizes (or anything the matcher previously categorized) is permanent until the user explicitly nulls it again. No "auto vs manual" flag needed.
- **No schema changes:** `categories.keywords` and `transactions.category_id` already exist from Phase 1.
- **Transfer category loses special treatment:** its keywords are matched alongside every other category's. The seeder still creates it with keywords, but `ParseCsvForPreview::matchTransferCategory()` (the hardcoded helper) goes away.

## Data Model

No changes. Phase 1's `categories` and `transactions` tables already carry everything we need.

## Components

### `App\Support\KeywordMatcher`

Small, single-purpose value object. Pulls all categories with non-empty keywords once on construction; exposes one method.

| Method | Returns | Behavior |
|---|---|---|
| `__construct()` | — | Loads `Category::whereNotNull('keywords')->get()`, builds an internal array of `[category_id, keyword, length]` rows per keyword. |
| `match(string $description): ?int` | category id, or null | Lowercases description, runs `preg_match('/\bquoted_kw\b/iu', ...)` for each keyword, collects matching category ids. Returns the single id only if exactly one distinct category matched; returns null for zero or for ambiguous (>1) matches. |

Roughly 40 lines including PHPDoc. Idempotent — constructing the same matcher against the same DB state always produces the same matches.

### `App\Actions\Finance\Categories\RecategorizeUncategorized`

Single-purpose invokable. Pseudo-shape:

```
public function __invoke(): array
{
    $matcher = new KeywordMatcher();
    $updated = 0;
    $still = 0;

    Transaction::whereNull('category_id')->chunkById(500, function ($rows) use ($matcher, &$updated, &$still) {
        foreach ($rows as $tx) {
            $cid = $matcher->match($tx->description);
            if ($cid !== null) {
                $tx->update(['category_id' => $cid]);
                $updated++;
            } else {
                $still++;
            }
        }
    });

    return ['updated' => $updated, 'still_uncategorized' => $still];
}
```

Returns counts for the toast message. No DB transaction wrapping needed — each row update is independent; partial completion on failure is recoverable by re-running.

### `ParseCsvForPreview` modification

Replace these lines (at top of `__invoke()`):

```php
$this->transferCategory = Category::where('name', 'Transfer')->first();
```

with:

```php
$this->matcher = new KeywordMatcher();
```

Replace the `matchTransferCategory()` private method with a one-line call: `return $this->matcher->match($description);` (removing the Transfer-specific helper entirely). Behavior generalizes: all categories' keywords get matched, not just Transfer.

## UI

One change on `/categories` (the existing `pages::categories.index` SFC).

Before:
```
[ Categories title ]                 [ + New category ]
```

After:
```
[ Categories title ]   [ Recategorize uncategorized ]  [ + New category ]
```

On click:
- Calls `RecategorizeUncategorized` action.
- Shows a MaryUI toast: `Recategorized {n} transactions. {m} still uncategorized.` (using the `Toast` trait the settings pages already use).
- No confirmation modal — the action only touches null rows, no destructive side effects.

## Error Handling

| Scenario | Behavior |
|---|---|
| User has zero categories with keywords | `KeywordMatcher::match()` always returns null. Recategorize button reports `0 updated, N still uncategorized.` No error. |
| Keyword contains regex metacharacters (`.`, `*`, `+`, etc.) | `preg_quote()` neutralizes them. The metacharacter is matched literally. |
| One category has many duplicate keywords | First match still wins for THAT category. Ambiguity is across-category, not within. |
| Description contains Unicode (accents, etc.) | `/u` flag on regex handles UTF-8. Lowercasing via `mb_strtolower`. |
| Two categories share an identical keyword | Every match across that keyword is ambiguous; row stays null. Acceptable — user should refine keywords. |

## Testing

### `KeywordMatcherTest` (unit)
- Matches a single keyword case-insensitively.
- Word-boundary positive: `gas` matches `"GAS STATION"`, `"Gas-N-Go"`, `"123 GAS"`.
- Word-boundary negative: `gas` does NOT match `"VEGAS"` or `"OREGAS"`.
- Ambiguity returns null: if description matches keywords from two different categories, `match()` returns null.
- Special-character keyword (`a.b`) is matched literally, not as regex.
- Empty keywords on a category are ignored.
- Category with no keywords never matches.

### `RecategorizeUncategorizedTest`
- Only touches `category_id IS NULL` rows; already-categorized rows untouched.
- Returns correct counts `['updated' => n, 'still_uncategorized' => m]`.
- Soft-deleted transactions are skipped (default scope handles this).
- Re-running is idempotent: second invocation against unchanged data returns `updated=0`.

### `ParseCsvForPreviewTest` (existing — update)
Replace the existing `it('auto-categorizes rows matching Transfer keywords')` test with a more general version that creates two categories (e.g., `Coffee` and `Transfer`) and verifies a description matching each category resolves to the right id; one matching neither resolves to null; one matching BOTH resolves to null (ambiguity rule).

### Livewire `CategoriesIndexTest` (existing — extend)
- Add: clicking the button calls `RecategorizeUncategorized`, transactions get categorized.
- The button is visible and labelled correctly.

## Out of Scope (Phase 3+)

- Amount-aware matching (`Caseys < $10 = Snacks` style rules)
- "Suggest a keyword when I manually recategorize" workflow
- Re-evaluating already-categorized transactions (override existing assignments)
- Multi-keyword AND logic (comma list stays OR-only)
- Regex-style keywords (literal substrings + word-boundary only)
- Bulk-uncategorize UI (workaround: clear `category_id` via tinker if needed)
- Manual vs auto-categorization tracking
