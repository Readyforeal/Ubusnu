# Auto-Categorization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generalize the keyword-based auto-categorization that already runs for the "Transfer" category during CSV import so it applies to every category, plus add a manual "Recategorize uncategorized transactions" button on `/categories`.

**Architecture:** New `App\Support\KeywordMatcher` value object loads all categories with keywords once and exposes `match(string $description): ?int`. Word-boundary regex matching. Ambiguous multi-category matches return null (transaction stays uncategorized). New `RecategorizeUncategorized` action applies the matcher across `category_id IS NULL` rows. `ParseCsvForPreview` is refactored to use the new matcher.

**Tech Stack:** Laravel 13, Livewire 4 (SFC), MaryUI, Pest 4, SQLite.

**Spec:** `docs/superpowers/specs/2026-06-22-auto-categorization-design.md`

---

## File Structure

### New files
```
app/
  Support/
    KeywordMatcher.php
  Actions/Finance/Categories/
    RecategorizeUncategorized.php

tests/
  Unit/
    Support/KeywordMatcherTest.php
    Actions/Finance/Categories/RecategorizeUncategorizedTest.php
```

### Modified files
- `app/Actions/Finance/Imports/ParseCsvForPreview.php` — replace `matchTransferCategory()` with `KeywordMatcher` usage
- `tests/Unit/Actions/Finance/Imports/ParseCsvForPreviewTest.php` — generalize the Transfer auto-match test to cover any category
- `resources/views/pages/categories/⚡index.blade.php` — add Recategorize button + handler method
- `tests/Feature/Pages/Categories/IndexTest.php` — add tests for the button + handler

---

## Conventions

- **Each task ends with:** `vendor/bin/pint --dirty --format agent` then a commit with the exact message specified.
- **Pest tests:** `it()`/`expect()` style. Filter run with `php artisan test --compact --filter=<TestName>`.
- **PHP 8.4:** typed properties + return types throughout.
- **Money/cents/locale rule:** not relevant in this phase — we only touch categorization.

---

## Task 1: KeywordMatcher value object

**Files:**
- Create: `app/Support/KeywordMatcher.php`
- Create: `tests/Unit/Support/KeywordMatcherTest.php`

- [ ] **Step 1: Write the failing tests**

Write to `tests/Unit/Support/KeywordMatcherTest.php`:

```php
<?php

use App\Models\Category;
use App\Support\KeywordMatcher;

it('matches a description against a single category by keyword (case-insensitive)', function () {
    $cat = Category::factory()->create(['name' => 'Gas', 'keywords' => 'shell, exxon']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('SHELL GAS STATION 123'))->toBe($cat->id);
    expect($matcher->match('shell gas station 123'))->toBe($cat->id);
});

it('matches with word-boundary semantics (positive cases)', function () {
    $cat = Category::factory()->create(['keywords' => 'gas']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('GAS STATION'))->toBe($cat->id);
    expect($matcher->match('Gas-N-Go'))->toBe($cat->id);
    expect($matcher->match('123 GAS'))->toBe($cat->id);
});

it('does not match across word boundaries (negative cases)', function () {
    Category::factory()->create(['keywords' => 'gas']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('LAS VEGAS CASINO'))->toBeNull();
    expect($matcher->match('OREGAS DINER'))->toBeNull();
    expect($matcher->match('MEGASTORE'))->toBeNull();
});

it('returns null when description matches keywords from more than one category (ambiguous)', function () {
    Category::factory()->create(['name' => 'Shopping', 'keywords' => 'target']);
    Category::factory()->create(['name' => 'Groceries', 'keywords' => 'groceries']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('PAYMENT TO TARGET STORE - GROCERIES'))->toBeNull();
});

it('resolves to one category when multiple keywords from THAT category match', function () {
    $coffee = Category::factory()->create(['name' => 'Coffee', 'keywords' => 'starbucks, coffee, latte']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('STARBUCKS COFFEE #1234'))->toBe($coffee->id);
});

it('returns null when no keyword matches', function () {
    Category::factory()->create(['keywords' => 'shell, exxon']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('MYSTERY MERCHANT 9876'))->toBeNull();
});

it('treats keyword metacharacters literally (preg_quote)', function () {
    $cat = Category::factory()->create(['keywords' => 'a.b']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('SHOP a.b LLC'))->toBe($cat->id);
    expect($matcher->match('SHOP aXb LLC'))->toBeNull();
});

it('ignores categories with empty/whitespace-only keywords', function () {
    Category::factory()->create(['name' => 'Empty', 'keywords' => '   ,  , ']);
    $cat = Category::factory()->create(['name' => 'Real', 'keywords' => 'starbucks']);

    $matcher = new KeywordMatcher;

    expect($matcher->match('STARBUCKS COFFEE'))->toBe($cat->id);
});

it('returns null when no categories have keywords', function () {
    Category::factory()->create(['keywords' => null]);

    $matcher = new KeywordMatcher;

    expect($matcher->match('ANYTHING'))->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=KeywordMatcherTest`
Expected: FAIL (class `App\Support\KeywordMatcher` does not exist).

- [ ] **Step 3: Implement the matcher**

Write to `app/Support/KeywordMatcher.php`:

```php
<?php

namespace App\Support;

use App\Models\Category;

class KeywordMatcher
{
    /** @var array<int, array{category_id: int, pattern: string}> */
    private array $keywords = [];

    public function __construct()
    {
        $this->load();
    }

    private function load(): void
    {
        $categories = Category::query()->whereNotNull('keywords')->get();

        foreach ($categories as $category) {
            foreach ($category->keywordList() as $keyword) {
                if ($keyword === '') {
                    continue;
                }
                $this->keywords[] = [
                    'category_id' => $category->id,
                    'pattern' => '/\b'.preg_quote($keyword, '/').'\b/iu',
                ];
            }
        }
    }

    public function match(string $description): ?int
    {
        $hits = [];

        foreach ($this->keywords as $kw) {
            if (preg_match($kw['pattern'], $description) === 1) {
                $hits[$kw['category_id']] = true;
            }
        }

        return count($hits) === 1 ? (int) array_key_first($hits) : null;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=KeywordMatcherTest`
Expected: PASS, 9 tests.

- [ ] **Step 5: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/KeywordMatcher.php tests/Unit/Support/KeywordMatcherTest.php
git commit -m "Add KeywordMatcher for word-boundary category matching"
```

---

## Task 2: RecategorizeUncategorized action

**Files:**
- Create: `app/Actions/Finance/Categories/RecategorizeUncategorized.php`
- Create: `tests/Unit/Actions/Finance/Categories/RecategorizeUncategorizedTest.php`

- [ ] **Step 1: Write the failing tests**

Write to `tests/Unit/Actions/Finance/Categories/RecategorizeUncategorizedTest.php`:

```php
<?php

use App\Actions\Finance\Categories\RecategorizeUncategorized;
use App\Models\Category;
use App\Models\Transaction;

it('categorizes only rows where category_id is null', function () {
    $coffee = Category::factory()->create(['keywords' => 'starbucks']);
    Transaction::factory()->create(['category_id' => null, 'description' => 'STARBUCKS #1']);
    Transaction::factory()->create(['category_id' => null, 'description' => 'SHELL OIL']); // won't match
    $alreadySet = Transaction::factory()->create(['category_id' => $coffee->id, 'description' => 'STARBUCKS #2']);

    $result = (new RecategorizeUncategorized)();

    expect($result)->toBe(['updated' => 1, 'still_uncategorized' => 1]);
    expect(Transaction::where('description', 'STARBUCKS #1')->first()->category_id)->toBe($coffee->id);
    expect(Transaction::where('description', 'SHELL OIL')->first()->category_id)->toBeNull();
});

it('never overrides an already-categorized transaction', function () {
    $coffee = Category::factory()->create(['keywords' => 'starbucks']);
    $other = Category::factory()->create();
    $manuallySet = Transaction::factory()->create([
        'category_id' => $other->id,
        'description' => 'STARBUCKS DOWNTOWN',
    ]);

    (new RecategorizeUncategorized)();

    expect($manuallySet->fresh()->category_id)->toBe($other->id);
});

it('skips soft-deleted transactions', function () {
    $coffee = Category::factory()->create(['keywords' => 'starbucks']);
    $tx = Transaction::factory()->create(['category_id' => null, 'description' => 'STARBUCKS']);
    $tx->delete();

    $result = (new RecategorizeUncategorized)();

    expect($result['updated'])->toBe(0);
});

it('is idempotent — running twice on unchanged data updates 0 the second time', function () {
    Category::factory()->create(['keywords' => 'starbucks']);
    Transaction::factory()->create(['category_id' => null, 'description' => 'STARBUCKS']);

    (new RecategorizeUncategorized)();
    $result = (new RecategorizeUncategorized)();

    expect($result['updated'])->toBe(0);
    expect($result['still_uncategorized'])->toBe(0);
});

it('returns counts when nothing matches', function () {
    Transaction::factory()->count(3)->create(['category_id' => null]);

    $result = (new RecategorizeUncategorized)();

    expect($result)->toBe(['updated' => 0, 'still_uncategorized' => 3]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=RecategorizeUncategorizedTest`
Expected: FAIL (class `App\Actions\Finance\Categories\RecategorizeUncategorized` does not exist).

- [ ] **Step 3: Implement the action**

Write to `app/Actions/Finance/Categories/RecategorizeUncategorized.php`:

```php
<?php

namespace App\Actions\Finance\Categories;

use App\Models\Transaction;
use App\Support\KeywordMatcher;

class RecategorizeUncategorized
{
    /**
     * @return array{updated: int, still_uncategorized: int}
     */
    public function __invoke(): array
    {
        $matcher = new KeywordMatcher;
        $updated = 0;
        $still = 0;

        Transaction::query()
            ->whereNull('category_id')
            ->chunkById(500, function ($rows) use ($matcher, &$updated, &$still): void {
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
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=RecategorizeUncategorizedTest`
Expected: PASS, 5 tests.

- [ ] **Step 5: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Categories/RecategorizeUncategorized.php tests/Unit/Actions/Finance/Categories/RecategorizeUncategorizedTest.php
git commit -m "Add RecategorizeUncategorized action"
```

---

## Task 3: Refactor ParseCsvForPreview to use KeywordMatcher

**Files:**
- Modify: `app/Actions/Finance/Imports/ParseCsvForPreview.php`
- Modify: `tests/Unit/Actions/Finance/Imports/ParseCsvForPreviewTest.php`

- [ ] **Step 1: Update the existing Transfer-keyword test to generalize**

Open `tests/Unit/Actions/Finance/Imports/ParseCsvForPreviewTest.php`. Find the existing test block:

```php
it('auto-categorizes rows matching Transfer keywords', function () {
    $account = Account::factory()->create();
    $transfer = \App\Models\Category::factory()->create([
        'name' => 'Transfer',
        'keywords' => 'transfer, tfr',
        'excluded_from_totals' => true,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "Date,Description,Amount\n06/01/2026,E-Transfer to John,-100.00\n");

    $rows = (new ParseCsvForPreview)($account, $path, $this->profile);

    expect($rows[0]['category_id'])->toBe($transfer->id);

    unlink($path);
});
```

Replace it with:

```php
it('auto-categorizes import rows against any category keywords', function () {
    $account = Account::factory()->create();
    $coffee = \App\Models\Category::factory()->create(['name' => 'Coffee', 'keywords' => 'starbucks']);
    $gas = \App\Models\Category::factory()->create(['name' => 'Gas', 'keywords' => 'shell']);

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents(
        $path,
        "Date,Description,Amount\n".
        "06/01/2026,STARBUCKS #1234,-4.50\n".
        "06/02/2026,SHELL OIL,-50.00\n".
        "06/03/2026,MYSTERY VENDOR,-10.00\n"
    );

    $rows = (new ParseCsvForPreview)($account, $path, $this->profile);

    expect($rows[0]['category_id'])->toBe($coffee->id);
    expect($rows[1]['category_id'])->toBe($gas->id);
    expect($rows[2]['category_id'])->toBeNull();

    unlink($path);
});

it('leaves a row uncategorized when two categories ambiguously match', function () {
    $account = Account::factory()->create();
    \App\Models\Category::factory()->create(['name' => 'Shopping', 'keywords' => 'target']);
    \App\Models\Category::factory()->create(['name' => 'Groceries', 'keywords' => 'groceries']);

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "Date,Description,Amount\n06/01/2026,TARGET STORE - GROCERIES,-30.00\n");

    $rows = (new ParseCsvForPreview)($account, $path, $this->profile);

    expect($rows[0]['category_id'])->toBeNull();

    unlink($path);
});
```

- [ ] **Step 2: Run the updated tests — they should FAIL (production code still references Transfer-only matcher)**

Run: `php artisan test --compact --filter=ParseCsvForPreviewTest`
Expected: at least the new ambiguity test FAILS (current matcher only checks Transfer category).

- [ ] **Step 3: Refactor `ParseCsvForPreview` to use `KeywordMatcher`**

Open `app/Actions/Finance/Imports/ParseCsvForPreview.php`. Make these changes:

1. **Add the import** at the top of the use list:
```php
use App\Support\KeywordMatcher;
```

2. **Remove the unused import** for `Category` (delete `use App\Models\Category;` if it's still listed) — it's no longer referenced directly here.

3. **Replace the property declaration**:

Old:
```php
private ?Category $transferCategory = null;
```

New:
```php
private ?KeywordMatcher $matcher = null;
```

4. **In `__invoke()`, replace the Transfer category preload**:

Old:
```php
$this->transferCategory = Category::where('name', 'Transfer')->first();
```

New:
```php
$this->matcher = new KeywordMatcher;
```

5. **Replace the call site in `processRow()`** — find:

```php
$categoryId = $this->matchTransferCategory($rawDesc);
```

Replace with:

```php
$categoryId = $this->matcher->match($rawDesc);
```

6. **Delete the entire `matchTransferCategory()` method** at the bottom of the file (no longer needed).

- [ ] **Step 4: Run the full ParseCsvForPreview test suite**

Run: `php artisan test --compact --filter=ParseCsvForPreviewTest`
Expected: PASS, all tests (5 original + 1 updated generalized auto-match + 1 new ambiguity test = 7 total) green.

- [ ] **Step 5: Also re-run the full suite to confirm no regressions**

Run: `php artisan test --compact`
Expected: same total count as before + 2 new tests in ParseCsvForPreviewTest. All passing or skipped.

- [ ] **Step 6: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Finance/Imports/ParseCsvForPreview.php tests/Unit/Actions/Finance/Imports/ParseCsvForPreviewTest.php
git commit -m "Switch ParseCsvForPreview to general KeywordMatcher"
```

---

## Task 4: Categories Index — add Recategorize button + toast

**Files:**
- Modify: `resources/views/pages/categories/⚡index.blade.php`
- Modify: `tests/Feature/Pages/Categories/IndexTest.php`

- [ ] **Step 1: Add tests for the new button**

Open `tests/Feature/Pages/Categories/IndexTest.php`. Append:

```php
it('shows a Recategorize uncategorized button', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::categories.index')
        ->assertSee('Recategorize uncategorized');
});

it('runs the matcher when the button is clicked and updates counts', function () {
    $this->actingAs(User::factory()->create());
    $coffee = Category::factory()->create(['keywords' => 'starbucks']);
    \App\Models\Transaction::factory()->create(['category_id' => null, 'description' => 'STARBUCKS #1']);
    \App\Models\Transaction::factory()->create(['category_id' => null, 'description' => 'MYSTERY VENDOR']);

    Livewire::test('pages::categories.index')
        ->call('recategorize')
        ->assertHasNoErrors();

    expect(\App\Models\Transaction::where('description', 'STARBUCKS #1')->first()->category_id)->toBe($coffee->id);
    expect(\App\Models\Transaction::where('description', 'MYSTERY VENDOR')->first()->category_id)->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter="Pages.Categories"`
Expected: the two new tests FAIL (button absent, method missing).

- [ ] **Step 3: Update the Categories index SFC**

Open `resources/views/pages/categories/⚡index.blade.php`. Make these changes:

1. **In the `<?php ... ?>` block**, add the new method `recategorize()`. The final class body should look like:

```php
<?php

use App\Actions\Finance\Categories\RecategorizeUncategorized;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Categories')] class extends Component {
    use Toast;

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

    public function recategorize(): void
    {
        $result = (new RecategorizeUncategorized)();

        $this->success(
            "Recategorized {$result['updated']} transactions. {$result['still_uncategorized']} still uncategorized."
        );
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::orderBy('name')->get();
    }
}; ?>
```

2. **Update the header row** — find:

```blade
<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">{{ __('Categories') }}</h1>
    <x-button label="New category" icon="lucide.plus" class="btn-primary" wire:click="startEdit(0)" />
</div>
```

Replace with:

```blade
<div class="flex items-center justify-between flex-wrap gap-2">
    <h1 class="text-2xl font-semibold">{{ __('Categories') }}</h1>
    <div class="flex gap-2">
        <x-button label="Recategorize uncategorized" icon="lucide.wand-2" class="btn-ghost" wire:click="recategorize" wire:loading.attr="disabled" />
        <x-button label="New category" icon="lucide.plus" class="btn-primary" wire:click="startEdit(0)" />
    </div>
</div>
```

- [ ] **Step 4: Clear view cache + run tests**

```bash
php artisan view:clear
php artisan test --compact --filter="Pages.Categories"
```

Expected: PASS, the two new tests plus all prior Categories tests.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test --compact`
Expected: clean — only the 2 browser tests skipped.

- [ ] **Step 6: Lint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/pages/categories/⚡index.blade.php tests/Feature/Pages/Categories/IndexTest.php
git commit -m "Add Recategorize uncategorized button to Categories page"
```

---

## Self-Review Summary

Plan checked against the spec:

| Spec requirement | Covered by |
|---|---|
| `KeywordMatcher` value object with single-load + `match()` method | Task 1 |
| Word-boundary regex matching | Task 1 (tests + impl) |
| Case-insensitive matching | Task 1 |
| Ambiguity returns null | Task 1 + Task 3 (CSV-level test) |
| `preg_quote()` safety for metacharacters | Task 1 |
| `RecategorizeUncategorized` action with chunked iteration | Task 2 |
| Action returns `['updated' => n, 'still_uncategorized' => m]` | Task 2 |
| Action only touches `category_id IS NULL` | Task 2 |
| Action skips soft-deleted transactions | Task 2 |
| `ParseCsvForPreview` refactored to use matcher | Task 3 |
| Transfer-only logic removed | Task 3 (delete `matchTransferCategory`) |
| Existing Transfer test generalized | Task 3 |
| New CSV-level ambiguity test | Task 3 |
| Recategorize button on `/categories` | Task 4 |
| MaryUI toast with updated/still-uncategorized counts | Task 4 |
| No schema changes | (No migration task — confirmed) |
| Test coverage for KeywordMatcher | Task 1 (9 tests) |
| Test coverage for RecategorizeUncategorized | Task 2 (5 tests) |
| Test coverage for CSV import generalization | Task 3 (2 updated/new tests) |
| Test coverage for Livewire UI | Task 4 (2 new tests) |

No placeholders. No `TODO`/`TBD`. Method names (`match`, `__invoke`, `recategorize`, `matcher`) consistent across tasks. The Transfer seeder is unchanged — its row still has keywords, which still get picked up by the new general matcher.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-22-auto-categorization.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration. Phase 2 is only 4 tasks, so this is quick.

**2. Inline Execution** — I execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
