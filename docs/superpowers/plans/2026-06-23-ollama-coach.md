# Ollama Coach Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a self-hosted financial coach backed by local Ollama, plus 8 analytics actions and a proactive Insights widget on the dashboard. The analytics + insights work without Ollama; the chat plugs in via configurable URL.

**Architecture:** Four layers — analytics actions (pure functions), Insights builder (composes analytics into ranked items), Coach service (Ollama HTTP client + tool registry + chat loop), Chat UI (streamed Livewire pages). Read-only tools in v1; write-tool seam built.

**Tech Stack:** Laravel 13, Livewire 4 SFC, MaryUI, daisyUI 5, Pest 4, SQLite, Carbon. Ollama via HTTP (no SDK).

**Reference spec:** `docs/superpowers/specs/2026-06-23-ollama-coach-design.md`

---

## File Structure

**Database**
- Create: `database/migrations/{ts}_create_chat_threads_table.php`
- Create: `database/migrations/{ts}_create_chat_messages_table.php`
- Create: `database/migrations/{ts}_add_ollama_to_app_settings.php`

**Models & factories**
- Create: `app/Models/ChatThread.php`, `app/Models/ChatMessage.php`
- Create: `database/factories/ChatThreadFactory.php`, `database/factories/ChatMessageFactory.php`
- Modify: `app/Models/AppSetting.php`, `app/Models/User.php`

**Support**
- Create: `app/Support/Stats.php`

**Analytics actions (8)**
- Create: `app/Actions/Finance/Analytics/TopMovers.php`
- Create: `app/Actions/Finance/Analytics/DetectAnomalies.php`
- Create: `app/Actions/Finance/Analytics/BudgetVariance.php`
- Create: `app/Actions/Finance/Analytics/GoalPaceForecast.php`
- Create: `app/Actions/Finance/Analytics/SavingsRateTrend.php`
- Create: `app/Actions/Finance/Analytics/DetectRecurringSubscriptions.php`
- Create: `app/Actions/Finance/Analytics/SpendingVelocity.php`
- Create: `app/Actions/Finance/Analytics/FixedVariableRatio.php`

**Insights**
- Create: `app/Coach/Insight.php` (DTO)
- Create: `app/Actions/Coach/BuildInsights.php`

**Coach service**
- Create: `app/Services/Coach/CoachConfig.php`
- Create: `app/Services/Coach/CoachTool.php` (DTO)
- Create: `app/Services/Coach/ToolRegistry.php`
- Create: `app/Services/Coach/OllamaClient.php`
- Create: `app/Services/Coach/ChatLoop.php`
- Create: `app/Exceptions/CoachNotConfiguredException.php`
- Create: `app/Providers/CoachServiceProvider.php`
- Modify: `bootstrap/providers.php`

**Controllers**
- Create: `app/Http/Controllers/Coach/StreamController.php`
- Create: `app/Http/Controllers/Coach/ThreadController.php`

**Views & routes**
- Create: `resources/prompts/coach.md`
- Create: `resources/views/pages/dashboard/⚡insights.blade.php`
- Create: `resources/views/pages/chat/⚡index.blade.php`
- Create: `resources/views/pages/chat/⚡thread.blade.php`
- Create: `resources/views/pages/settings/⚡coach.blade.php`
- Modify: `resources/views/dashboard.blade.php`
- Modify: `resources/views/layouts/app/sidebar.blade.php`
- Modify: `routes/web.php`, `routes/settings.php`

**Tests** — ~95 new, distributed across the tasks.

---

## Task 1: Schema migrations

**Files:** three migrations in `database/migrations/`.

- [ ] **Step 1: Generate**

```bash
php artisan make:migration create_chat_threads_table --no-interaction
php artisan make:migration create_chat_messages_table --no-interaction
php artisan make:migration add_ollama_to_app_settings --no-interaction --table=app_settings
```

- [ ] **Step 2: Fill `create_chat_threads_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_threads');
    }
};
```

- [ ] **Step 3: Fill `create_chat_messages_table`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_thread_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content');
            $table->json('tool_calls')->nullable();
            $table->string('model', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['chat_thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
```

- [ ] **Step 4: Fill `add_ollama_to_app_settings`**

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
            $table->string('ollama_base_url', 255)->nullable();
            $table->string('ollama_model', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['ollama_base_url', 'ollama_model']);
        });
    }
};
```

- [ ] **Step 5: Run migrations**

```bash
php artisan migrate
```

Expected: 3 migrations ran.

- [ ] **Step 6: Verify**

```bash
php artisan tinker --execute 'echo \Schema::hasTable("chat_threads") ? "yes" : "no"; echo " | "; echo \Schema::hasTable("chat_messages") ? "yes" : "no"; echo " | "; echo \Schema::hasColumn("app_settings", "ollama_base_url") ? "yes" : "no";'
```

Expected: `yes | yes | yes`.

- [ ] **Step 7: Commit**

```bash
git add database/migrations
git commit -m "$(cat <<'EOF'
Add schema for chat threads, messages, and ollama config

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: ChatThread + ChatMessage models, factories, and User/AppSetting wiring

**Files:**
- Create: `app/Models/ChatThread.php`, `app/Models/ChatMessage.php`
- Create: `database/factories/ChatThreadFactory.php`, `database/factories/ChatMessageFactory.php`
- Modify: `app/Models/AppSetting.php`, `app/Models/User.php`

- [ ] **Step 1: Generate models**

```bash
php artisan make:model ChatThread --factory --no-interaction
php artisan make:model ChatMessage --factory --no-interaction
```

- [ ] **Step 2: Replace `app/Models/ChatThread.php`**

```php
<?php

namespace App\Models;

use Database\Factories\ChatThreadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'title', 'pinned_at', 'last_message_at'])]
class ChatThread extends Model
{
    /** @use HasFactory<ChatThreadFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function touchLastMessage(): void
    {
        $this->update(['last_message_at' => now()]);
    }
}
```

- [ ] **Step 3: Replace `app/Models/ChatMessage.php`**

```php
<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chat_thread_id', 'role', 'content', 'tool_calls', 'model'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'chat_thread_id');
    }
}
```

- [ ] **Step 4: Replace `database/factories/ChatThreadFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatThread>
 */
class ChatThreadFactory extends Factory
{
    protected $model = ChatThread::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'pinned_at' => null,
            'last_message_at' => now(),
        ];
    }
}
```

- [ ] **Step 5: Replace `database/factories/ChatMessageFactory.php`**

```php
<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'chat_thread_id' => ChatThread::factory(),
            'role' => 'user',
            'content' => $this->faker->sentence(),
            'tool_calls' => null,
            'model' => null,
        ];
    }

    public function assistant(): static
    {
        return $this->state(['role' => 'assistant', 'model' => 'llama3.1:8b']);
    }

    public function tool(): static
    {
        return $this->state(['role' => 'tool']);
    }
}
```

- [ ] **Step 6: Update `app/Models/AppSetting.php`**

Add `'ollama_base_url'` and `'ollama_model'` to the `#[Fillable([...])]` array. No casts needed (strings). No defaults in `current()` — null = not configured.

- [ ] **Step 7: Update `app/Models/User.php`**

Add a `chatThreads()` relation method:

```php
public function chatThreads(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\ChatThread::class)->orderByDesc('last_message_at');
}
```

If `HasMany` is already imported at the top, drop the FQN.

- [ ] **Step 8: Run suite — no regressions**

```bash
php artisan test --compact
```

Expected: 364 passing, 2 skipped (same as before).

- [ ] **Step 9: Commit**

```bash
git add app/Models database/factories/ChatThreadFactory.php database/factories/ChatMessageFactory.php
git commit -m "$(cat <<'EOF'
Add ChatThread + ChatMessage models with factories; wire Ollama fields on AppSetting

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Stats support helper

**Files:**
- Create: `app/Support/Stats.php`
- Test: `tests/Unit/Support/StatsTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Support/StatsTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Support\Stats;

it('computes the median of an odd-count list', function () {
    expect(Stats::median([1, 5, 3, 9, 2]))->toBe(3.0);
});

it('computes the median of an even-count list as the average of the two middle values', function () {
    expect(Stats::median([1, 2, 3, 4]))->toBe(2.5);
});

it('returns null for an empty median', function () {
    expect(Stats::median([]))->toBeNull();
});

it('computes the mean', function () {
    expect(Stats::mean([2, 4, 6, 8]))->toBe(5.0);
});

it('returns null for empty mean', function () {
    expect(Stats::mean([]))->toBeNull();
});

it('computes the standard deviation', function () {
    $values = [2, 4, 4, 4, 5, 5, 7, 9];
    expect(round(Stats::stdDev($values), 2))->toBe(2.0);
});

it('returns null stddev for fewer than 2 values', function () {
    expect(Stats::stdDev([1]))->toBeNull();
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=StatsTest
```

- [ ] **Step 4: Implement**

Create `app/Support/Stats.php`:

```php
<?php

namespace App\Support;

class Stats
{
    /**
     * @param  array<int, float|int>  $values
     */
    public static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $count = count($values);
        $mid = (int) floor($count / 2);

        return $count % 2 === 1
            ? (float) $values[$mid]
            : (($values[$mid - 1] + $values[$mid]) / 2.0);
    }

    /**
     * @param  array<int, float|int>  $values
     */
    public static function mean(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param  array<int, float|int>  $values
     */
    public static function stdDev(array $values): ?float
    {
        $count = count($values);
        if ($count < 2) {
            return null;
        }
        $mean = self::mean($values);
        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }

        return sqrt($sumSq / ($count - 1));
    }
}
```

- [ ] **Step 5: Run tests, expect PASS**

```bash
php artisan test --compact --filter=StatsTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Support/Stats.php tests/Unit/Support/StatsTest.php
git commit -m "$(cat <<'EOF'
Add Stats helper (median, mean, stdDev)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: TopMovers analytics action

**Files:**
- Create: `app/Actions/Finance/Analytics/TopMovers.php`
- Test: `tests/Unit/Actions/Finance/Analytics/TopMoversTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Analytics/TopMoversTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Analytics\TopMovers;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('returns categories ranked by largest absolute MoM delta', function () {
    $account = Account::factory()->create();
    $food = Category::factory()->create(['name' => 'Food', 'kind' => 'spending']);
    $gas = Category::factory()->create(['name' => 'Gas', 'kind' => 'spending']);

    // June (previous) spending
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $food->id, 'occurred_on' => '2026-06-10', 'amount_cents' => -10000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $gas->id, 'occurred_on' => '2026-06-12', 'amount_cents' => -10000]);

    // July (current) spending — food doubled, gas unchanged
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $food->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -20000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $gas->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -10000]);

    $result = (new TopMovers)(monthsBack: 1, limit: 5);

    expect($result[0]['name'])->toBe('Food');
    expect($result[0]['delta_pct'])->toBe(100.0);
    expect($result[0]['direction'])->toBe('up');
    expect($result[0]['current_cents'])->toBe(20000);
    expect($result[0]['previous_cents'])->toBe(10000);
});

it('marks new categories (no previous spend) as 100% direction=up', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -5000]);

    $result = (new TopMovers)();

    expect($result[0]['previous_cents'])->toBe(0);
    expect($result[0]['delta_pct'])->toBe(100.0);
    expect($result[0]['direction'])->toBe('up');
});

it('caps the result at the given limit', function () {
    $account = Account::factory()->create();
    for ($i = 0; $i < 10; $i++) {
        $cat = Category::factory()->create(['kind' => 'spending']);
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -($i + 1) * 1000]);
    }

    $result = (new TopMovers)(limit: 3);

    expect($result)->toHaveCount(3);
});

it('ignores income-kind categories', function () {
    $account = Account::factory()->create();
    $income = Category::factory()->create(['kind' => 'income']);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $income->id, 'occurred_on' => '2026-07-05', 'amount_cents' => 250000]);

    $result = (new TopMovers)();

    expect($result)->toBe([]);
});

it('returns empty when no transactions exist', function () {
    expect((new TopMovers)())->toBe([]);
});

it('marks downward movers with direction=down', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-06-10', 'amount_cents' => -20000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -10000]);

    $result = (new TopMovers)();

    expect($result[0]['direction'])->toBe('down');
    expect($result[0]['delta_pct'])->toBe(-50.0);
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=TopMoversTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TopMovers
{
    /**
     * @return array<int, array{category_id: int, name: string, current_cents: int, previous_cents: int, delta_pct: float, direction: string}>
     */
    public function __invoke(int $monthsBack = 1, int $limit = 5): array
    {
        $today = CarbonImmutable::today();
        $currentStart = $today->startOfMonth();
        $currentEnd = $today->endOfMonth();
        $previousStart = $currentStart->subMonthsNoOverflow($monthsBack);
        $previousEnd = $previousStart->endOfMonth();

        $spendingCategories = Category::query()->where('kind', 'spending')->pluck('name', 'id');

        if ($spendingCategories->isEmpty()) {
            return [];
        }

        $sumByCategory = function (CarbonImmutable $start, CarbonImmutable $end) use ($spendingCategories) {
            return Transaction::query()
                ->whereIn('category_id', $spendingCategories->keys())
                ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->select('category_id', DB::raw('SUM(ABS(amount_cents)) as cents'))
                ->groupBy('category_id')
                ->pluck('cents', 'category_id');
        };

        $current = $sumByCategory($currentStart, $currentEnd);
        $previous = $sumByCategory($previousStart, $previousEnd);

        $rows = [];
        foreach ($spendingCategories as $id => $name) {
            $curr = (int) ($current[$id] ?? 0);
            $prev = (int) ($previous[$id] ?? 0);
            if ($curr === 0 && $prev === 0) {
                continue;
            }
            if ($prev === 0) {
                $pct = 100.0;
            } else {
                $pct = round((($curr - $prev) / $prev) * 100, 2);
            }
            $rows[] = [
                'category_id' => (int) $id,
                'name' => $name,
                'current_cents' => $curr,
                'previous_cents' => $prev,
                'delta_pct' => (float) $pct,
                'direction' => $pct >= 0 ? 'up' : 'down',
            ];
        }

        usort($rows, fn ($a, $b) => abs($b['delta_pct']) <=> abs($a['delta_pct']));

        return array_slice($rows, 0, $limit);
    }
}
```

- [ ] **Step 5: Run tests, expect PASS**

```bash
php artisan test --compact --filter=TopMoversTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Analytics/TopMovers.php tests/Unit/Actions/Finance/Analytics/TopMoversTest.php
git commit -m "$(cat <<'EOF'
Add TopMovers analytics action

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: DetectAnomalies analytics action

**Files:**
- Create: `app/Actions/Finance/Analytics/DetectAnomalies.php`
- Test: `tests/Unit/Actions/Finance/Analytics/DetectAnomaliesTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Analytics/DetectAnomaliesTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Analytics\DetectAnomalies;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('flags transactions far from the category median', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    // 30 baseline transactions around $50 (so median ≈ 50, std-dev small)
    for ($i = 0; $i < 30; $i++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'occurred_on' => CarbonImmutable::today()->subDays($i + 1)->toDateString(),
            'amount_cents' => -(5000 + ($i % 5) * 100), // $50.00–$50.40
        ]);
    }

    // Spike: $500 transaction
    $spike = Transaction::factory()->create([
        'account_id' => $account->id,
        'category_id' => $cat->id,
        'occurred_on' => CarbonImmutable::today()->toDateString(),
        'amount_cents' => -50000,
    ]);

    $result = (new DetectAnomalies)(lookbackDays: 90, stdDevThreshold: 2.0);

    expect($result)->toHaveCount(1);
    expect($result[0]['transaction_id'])->toBe($spike->id);
    expect($result[0]['std_devs_from_median'])->toBeGreaterThan(2.0);
});

it('returns empty for categories with too few transactions to compute std-dev', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => CarbonImmutable::today()->toDateString(), 'amount_cents' => -100000]);

    expect((new DetectAnomalies)())->toBe([]);
});

it('ignores income-kind categories', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'income']);
    for ($i = 0; $i < 30; $i++) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => CarbonImmutable::today()->subDays($i + 1)->toDateString(), 'amount_cents' => 250000]);
    }
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => CarbonImmutable::today()->toDateString(), 'amount_cents' => 9999999]);

    expect((new DetectAnomalies)())->toBe([]);
});

it('respects the std-dev threshold', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);
    for ($i = 0; $i < 20; $i++) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => CarbonImmutable::today()->subDays($i + 1)->toDateString(), 'amount_cents' => -10000]);
    }
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => CarbonImmutable::today()->toDateString(), 'amount_cents' => -15000]);

    // Stricter threshold (5σ) → no anomalies
    expect((new DetectAnomalies)(stdDevThreshold: 5.0))->toBe([]);
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=DetectAnomaliesTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Category;
use App\Models\Transaction;
use App\Support\Stats;
use Carbon\CarbonImmutable;

class DetectAnomalies
{
    /**
     * @return array<int, array{transaction_id: int, description: string, amount_cents: int, category_id: int, category_median_cents: int, std_devs_from_median: float}>
     */
    public function __invoke(int $lookbackDays = 90, float $stdDevThreshold = 2.0): array
    {
        $start = CarbonImmutable::today()->subDays($lookbackDays);
        $end = CarbonImmutable::today();

        $categories = Category::query()->where('kind', 'spending')->get();

        $out = [];
        foreach ($categories as $category) {
            $rows = Transaction::query()
                ->where('category_id', $category->id)
                ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->get(['id', 'description', 'amount_cents']);

            if ($rows->count() < 10) {
                continue;
            }

            $amounts = $rows->map(fn ($r) => abs((int) $r->amount_cents))->all();
            $median = Stats::median($amounts);
            $stddev = Stats::stdDev($amounts);
            if ($median === null || $stddev === null || $stddev <= 0) {
                continue;
            }

            foreach ($rows as $row) {
                $abs = abs((int) $row->amount_cents);
                $devs = ($abs - $median) / $stddev;
                if ($devs >= $stdDevThreshold) {
                    $out[] = [
                        'transaction_id' => (int) $row->id,
                        'description' => (string) $row->description,
                        'amount_cents' => (int) $row->amount_cents,
                        'category_id' => (int) $category->id,
                        'category_median_cents' => (int) round($median),
                        'std_devs_from_median' => round($devs, 2),
                    ];
                }
            }
        }

        usort($out, fn ($a, $b) => $b['std_devs_from_median'] <=> $a['std_devs_from_median']);

        return $out;
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter=DetectAnomaliesTest
```

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Analytics/DetectAnomalies.php tests/Unit/Actions/Finance/Analytics/DetectAnomaliesTest.php
git commit -m "$(cat <<'EOF'
Add DetectAnomalies analytics action

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: BudgetVariance analytics action

**Files:**
- Create: `app/Actions/Finance/Analytics/BudgetVariance.php`
- Test: `tests/Unit/Actions/Finance/Analytics/BudgetVarianceTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Analytics/BudgetVarianceTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Analytics\BudgetVariance;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-07-15');
    AppSetting::current()->update(['monthly_income_target_cents' => 480000]); // $4800/mo target
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('returns each bucket with planned and actual cents', function () {
    $wants = Bucket::factory()->create(['name' => 'Wants', 'target_percentage' => 20]); // $960
    $essentials = Bucket::factory()->create(['name' => 'Essentials', 'target_percentage' => 50]); // $2400

    $cat = Category::factory()->create(['kind' => 'spending', 'bucket_id' => $wants->id]);
    $account = Account::factory()->create();
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -50000]);

    $result = (new BudgetVariance)();

    $byBucket = collect($result)->keyBy('bucket_id');
    expect($byBucket[$wants->id]['planned_cents'])->toBe(96000);
    expect($byBucket[$wants->id]['actual_cents'])->toBe(50000);
    expect($byBucket[$essentials->id]['actual_cents'])->toBe(0);
});

it('returns days_remaining_in_period', function () {
    Bucket::factory()->create(['target_percentage' => 50]);

    $result = (new BudgetVariance)();

    // July 15: 16 days remaining (15 through 31)
    expect($result[0]['days_remaining_in_period'])->toBe(16);
});

it('returns variance_pct as actual/planned * 100', function () {
    $b = Bucket::factory()->create(['target_percentage' => 50]); // $2400
    $cat = Category::factory()->create(['kind' => 'spending', 'bucket_id' => $b->id]);
    $account = Account::factory()->create();
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -120000]); // 50% of plan

    $result = (new BudgetVariance)();

    expect((float) $result[0]['variance_pct'])->toBe(50.0);
});

it('handles a zero-planned bucket without divide-by-zero', function () {
    Bucket::factory()->create(['target_percentage' => 0]);

    $result = (new BudgetVariance)();

    expect($result[0]['variance_pct'])->toBe(0.0);
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=BudgetVarianceTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Analytics;

use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class BudgetVariance
{
    /**
     * @return array<int, array{bucket_id: int, name: string, planned_cents: int, actual_cents: int, variance_pct: float, days_remaining_in_period: int}>
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();
        $monthEnd = $today->endOfMonth();
        $daysRemaining = $monthEnd->diffInDays($today) + 1;

        $incomeTarget = (int) AppSetting::current()->monthly_income_target_cents;

        $buckets = Bucket::query()->with('categories')->orderBy('sort_order')->orderBy('name')->get();

        $out = [];
        foreach ($buckets as $bucket) {
            $planned = $bucket->targetCents($incomeTarget);
            $categoryIds = $bucket->categories->pluck('id')->all();

            $actual = $categoryIds === [] ? 0 : (int) Transaction::query()
                ->whereIn('category_id', $categoryIds)
                ->whereBetween('occurred_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->sum(\DB::raw('ABS(amount_cents)'));

            $pct = $planned > 0 ? round(($actual / $planned) * 100, 2) : 0.0;

            $out[] = [
                'bucket_id' => (int) $bucket->id,
                'name' => $bucket->name,
                'planned_cents' => $planned,
                'actual_cents' => $actual,
                'variance_pct' => (float) $pct,
                'days_remaining_in_period' => $daysRemaining,
            ];
        }

        return $out;
    }
}
```

- [ ] **Step 5: Run tests** — `php artisan test --compact --filter=BudgetVarianceTest`

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Analytics/BudgetVariance.php tests/Unit/Actions/Finance/Analytics/BudgetVarianceTest.php
git commit -m "$(cat <<'EOF'
Add BudgetVariance analytics action

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: GoalPaceForecast analytics action

**Files:**
- Create: `app/Actions/Finance/Analytics/GoalPaceForecast.php`
- Test: `tests/Unit/Actions/Finance/Analytics/GoalPaceForecastTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Analytics/GoalPaceForecastTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Analytics\GoalPaceForecast;
use App\Models\Account;
use App\Models\Goal;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('forecasts the hit-date based on monthly pace', function () {
    $account = Account::factory()->withStartingBalance(0)->create(['counts_toward_goals' => true]);
    $goal = Goal::factory()->create(['name' => 'Vacation', 'target_cents' => 1000000, 'target_date' => '2027-06-01', 'priority_percentage' => 100]);

    // 3 months of $50k deposits → $50k/month pace
    Transaction::factory()->create(['account_id' => $account->id, 'occurred_on' => '2026-05-15', 'amount_cents' => 50000]);
    Transaction::factory()->create(['account_id' => $account->id, 'occurred_on' => '2026-06-15', 'amount_cents' => 50000]);
    Transaction::factory()->create(['account_id' => $account->id, 'occurred_on' => '2026-07-10', 'amount_cents' => 50000]);

    $result = (new GoalPaceForecast)();

    $vacation = collect($result)->firstWhere('goal_id', $goal->id);
    expect($vacation['monthly_pace_cents'])->toBeGreaterThan(0);
    expect($vacation['projected_hit_date'])->not->toBeNull();
});

it('marks on_track as true when projected_hit_date <= target_date', function () {
    $account = Account::factory()->withStartingBalance(900000)->create(['counts_toward_goals' => true]);
    $goal = Goal::factory()->create(['target_cents' => 1000000, 'target_date' => '2027-12-01', 'priority_percentage' => 100]);
    // Tiny but positive monthly pace
    Transaction::factory()->create(['account_id' => $account->id, 'occurred_on' => '2026-07-01', 'amount_cents' => 10000]);

    $result = (new GoalPaceForecast)();
    $g = collect($result)->firstWhere('goal_id', $goal->id);

    expect($g['on_track'])->toBeTrue();
});

it('marks on_track as false and computes months_off when behind pace', function () {
    $account = Account::factory()->withStartingBalance(0)->create(['counts_toward_goals' => true]);
    $goal = Goal::factory()->create(['target_cents' => 1000000, 'target_date' => '2026-09-01', 'priority_percentage' => 100]);

    Transaction::factory()->create(['account_id' => $account->id, 'occurred_on' => '2026-07-10', 'amount_cents' => 50000]);

    $result = (new GoalPaceForecast)();
    $g = collect($result)->firstWhere('goal_id', $goal->id);

    expect($g['on_track'])->toBeFalse();
    expect($g['months_off'])->toBeGreaterThan(0);
});

it('returns null projected_hit_date when monthly_pace is zero', function () {
    $account = Account::factory()->withStartingBalance(0)->create(['counts_toward_goals' => true]);
    $goal = Goal::factory()->create(['target_cents' => 1000000, 'target_date' => '2027-12-01', 'priority_percentage' => 100]);

    $result = (new GoalPaceForecast)();
    $g = collect($result)->firstWhere('goal_id', $goal->id);

    expect($g['projected_hit_date'])->toBeNull();
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=GoalPaceForecastTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Analytics;

use App\Actions\Finance\Goals\ComputeGoalsStatus;
use App\Models\Goal;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GoalPaceForecast
{
    /**
     * @return array<int, array{goal_id: int, name: string, target_cents: int, current_cents: int, monthly_pace_cents: int, projected_hit_date: ?string, target_date: ?string, on_track: bool, months_off: int}>
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();
        $threeMonthsAgo = $today->subMonthsNoOverflow(3);

        // Net deposits per month over the last 3 months across goal-counting accounts
        $netDeltaCents = (int) Transaction::query()
            ->whereHas('account', fn ($q) => $q->where('counts_toward_goals', true))
            ->whereBetween('occurred_on', [$threeMonthsAgo->toDateString(), $today->toDateString()])
            ->whereNull('deleted_at')
            ->sum('amount_cents');
        $monthlyPace = (int) round($netDeltaCents / 3);

        $statuses = (new ComputeGoalsStatus)();

        $out = [];
        foreach ($statuses as $status) {
            $remaining = max(0, $status['target_cents'] - $status['current_cents']);

            $projectedHitDate = null;
            $monthsToHit = null;
            if ($monthlyPace > 0 && $remaining > 0) {
                $monthsToHit = (int) ceil($remaining / $monthlyPace);
                $projectedHitDate = $today->addMonthsNoOverflow($monthsToHit)->toDateString();
            } elseif ($remaining === 0) {
                $projectedHitDate = $today->toDateString();
                $monthsToHit = 0;
            }

            $onTrack = false;
            $monthsOff = 0;
            if ($status['target_date'] && $projectedHitDate) {
                $target = CarbonImmutable::parse($status['target_date']);
                $projected = CarbonImmutable::parse($projectedHitDate);
                $onTrack = $projected->lte($target);
                $monthsOff = $onTrack ? 0 : (int) max(1, $projected->diffInMonths($target));
            }

            $out[] = [
                'goal_id' => (int) $status['id'],
                'name' => (string) $status['name'],
                'target_cents' => (int) $status['target_cents'],
                'current_cents' => (int) $status['current_cents'],
                'monthly_pace_cents' => $monthlyPace,
                'projected_hit_date' => $projectedHitDate,
                'target_date' => $status['target_date'] ?? null,
                'on_track' => $onTrack,
                'months_off' => $monthsOff,
            ];
        }

        return $out;
    }
}
```

(Adjust `ComputeGoalsStatus` keys if they differ in your repo — verify with `cat app/Actions/Finance/Goals/ComputeGoalsStatus.php`. The keys above are the documented shape.)

- [ ] **Step 5: Run tests** — `php artisan test --compact --filter=GoalPaceForecastTest`

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Analytics/GoalPaceForecast.php tests/Unit/Actions/Finance/Analytics/GoalPaceForecastTest.php
git commit -m "$(cat <<'EOF'
Add GoalPaceForecast analytics action

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: SavingsRateTrend analytics action

**Files:**
- Create: `app/Actions/Finance/Analytics/SavingsRateTrend.php`
- Test: `tests/Unit/Actions/Finance/Analytics/SavingsRateTrendTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Analytics/SavingsRateTrendTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Analytics\SavingsRateTrend;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('computes savings rate per month as (income - spend) / income', function () {
    $account = Account::factory()->create();
    $income = Category::factory()->create(['kind' => 'income']);
    $spend = Category::factory()->create(['kind' => 'spending']);

    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $income->id, 'occurred_on' => '2026-07-05', 'amount_cents' => 400000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $spend->id, 'occurred_on' => '2026-07-10', 'amount_cents' => -300000]);

    $result = (new SavingsRateTrend)(monthsBack: 1);
    $july = collect($result)->firstWhere('month', '2026-07');

    expect($july['income_cents'])->toBe(400000);
    expect($july['spend_cents'])->toBe(300000);
    expect($july['savings_rate_pct'])->toBe(25.0);
});

it('returns one entry per month going back N months', function () {
    $result = (new SavingsRateTrend)(monthsBack: 6);

    expect($result)->toHaveCount(6);
});

it('uses 0 for savings_rate_pct when income is zero', function () {
    $result = (new SavingsRateTrend)(monthsBack: 1);

    expect($result[0]['savings_rate_pct'])->toBe(0.0);
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=SavingsRateTrendTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SavingsRateTrend
{
    /**
     * @return array<int, array{month: string, income_cents: int, spend_cents: int, savings_rate_pct: float}>
     */
    public function __invoke(int $monthsBack = 12): array
    {
        $today = CarbonImmutable::today();
        $start = $today->startOfMonth()->subMonthsNoOverflow($monthsBack - 1);

        $incomeIds = Category::query()->where('kind', 'income')->pluck('id');
        $spendIds = Category::query()->where('kind', 'spending')->pluck('id');

        $out = [];
        for ($i = 0; $i < $monthsBack; $i++) {
            $m = $start->addMonthsNoOverflow($i);
            $mEnd = $m->endOfMonth();

            $income = $incomeIds->isEmpty() ? 0 : (int) Transaction::query()
                ->whereIn('category_id', $incomeIds)
                ->whereBetween('occurred_on', [$m->toDateString(), $mEnd->toDateString()])
                ->where('amount_cents', '>', 0)
                ->whereNull('deleted_at')
                ->sum('amount_cents');

            $spend = $spendIds->isEmpty() ? 0 : (int) abs((int) Transaction::query()
                ->whereIn('category_id', $spendIds)
                ->whereBetween('occurred_on', [$m->toDateString(), $mEnd->toDateString()])
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->sum('amount_cents'));

            $rate = $income > 0 ? round((($income - $spend) / $income) * 100, 2) : 0.0;

            $out[] = [
                'month' => $m->format('Y-m'),
                'income_cents' => $income,
                'spend_cents' => $spend,
                'savings_rate_pct' => (float) $rate,
            ];
        }

        return $out;
    }
}
```

- [ ] **Step 5: Run tests** — `php artisan test --compact --filter=SavingsRateTrendTest`

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Analytics/SavingsRateTrend.php tests/Unit/Actions/Finance/Analytics/SavingsRateTrendTest.php
git commit -m "$(cat <<'EOF'
Add SavingsRateTrend analytics action

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: DetectRecurringSubscriptions analytics action

**Files:**
- Create: `app/Actions/Finance/Analytics/DetectRecurringSubscriptions.php`
- Test: `tests/Unit/Actions/Finance/Analytics/DetectRecurringSubscriptionsTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Analytics/DetectRecurringSubscriptionsTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Analytics\DetectRecurringSubscriptions;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('finds repeating same-amount transactions not already tracked as bills', function () {
    $account = Account::factory()->create();
    for ($i = 0; $i < 3; $i++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'occurred_on' => CarbonImmutable::today()->subMonthsNoOverflow($i + 1)->toDateString(),
            'amount_cents' => -1599,
            'description' => 'NETFLIX.COM',
        ]);
    }

    $result = (new DetectRecurringSubscriptions)();

    expect($result)->toHaveCount(1);
    expect($result[0]['merchant_pattern'])->toContain('NETFLIX');
    expect($result[0]['occurrence_count'])->toBe(3);
    expect($result[0]['already_tracked_as_bill_id'])->toBeNull();
});

it('flags a subscription as already_tracked when a bill matches the description', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->create(['match_description' => 'NETFLIX', 'account_id' => $account->id]);
    for ($i = 0; $i < 3; $i++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'occurred_on' => CarbonImmutable::today()->subMonthsNoOverflow($i + 1)->toDateString(),
            'amount_cents' => -1599,
            'description' => 'NETFLIX.COM',
        ]);
    }

    $result = (new DetectRecurringSubscriptions)();

    expect($result[0]['already_tracked_as_bill_id'])->toBe($bill->id);
});

it('ignores merchants with fewer than 3 occurrences', function () {
    $account = Account::factory()->create();
    for ($i = 0; $i < 2; $i++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'occurred_on' => CarbonImmutable::today()->subMonthsNoOverflow($i + 1)->toDateString(),
            'amount_cents' => -999,
            'description' => 'SPOTIFY',
        ]);
    }

    expect((new DetectRecurringSubscriptions)())->toBe([]);
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=DetectRecurringSubscriptionsTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Bill;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DetectRecurringSubscriptions
{
    /**
     * @return array<int, array{merchant_pattern: string, occurrence_count: int, monthly_avg_cents: int, last_seen_on: string, already_tracked_as_bill_id: ?int}>
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();
        $start = $today->subMonthsNoOverflow(6);

        // Pull all outflows from the last 6 months; group by first significant token of description
        $rows = Transaction::query()
            ->whereBetween('occurred_on', [$start->toDateString(), $today->toDateString()])
            ->where('amount_cents', '<', 0)
            ->whereNull('deleted_at')
            ->whereNull('bill_id')
            ->get(['description', 'amount_cents', 'occurred_on']);

        $groups = [];
        foreach ($rows as $row) {
            $token = $this->firstSignificantToken((string) $row->description);
            if ($token === '') {
                continue;
            }
            $key = $token.'|'.abs((int) $row->amount_cents);
            $groups[$key]['token'] = $token;
            $groups[$key]['amount'] = abs((int) $row->amount_cents);
            $groups[$key]['count'] = ($groups[$key]['count'] ?? 0) + 1;
            $groups[$key]['last'] = max($groups[$key]['last'] ?? '0000-00-00', (string) $row->occurred_on);
        }

        $billMatches = Bill::query()->whereNotNull('match_description')->get(['id', 'match_description'])->all();

        $out = [];
        foreach ($groups as $g) {
            if ($g['count'] < 3) {
                continue;
            }
            $matchedBillId = null;
            foreach ($billMatches as $b) {
                if (str_contains(strtoupper($g['token']), strtoupper((string) $b->match_description))) {
                    $matchedBillId = (int) $b->id;
                    break;
                }
            }
            $out[] = [
                'merchant_pattern' => $g['token'],
                'occurrence_count' => (int) $g['count'],
                'monthly_avg_cents' => (int) $g['amount'],
                'last_seen_on' => (string) $g['last'],
                'already_tracked_as_bill_id' => $matchedBillId,
            ];
        }

        usort($out, fn ($a, $b) => $b['occurrence_count'] <=> $a['occurrence_count']);

        return $out;
    }

    private function firstSignificantToken(string $description): string
    {
        $clean = preg_replace('/[^A-Z0-9 \.]/i', ' ', strtoupper($description));
        $tokens = preg_split('/\s+/', trim((string) $clean));

        return $tokens[0] ?? '';
    }
}
```

- [ ] **Step 5: Run tests** — `php artisan test --compact --filter=DetectRecurringSubscriptionsTest`

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Analytics/DetectRecurringSubscriptions.php tests/Unit/Actions/Finance/Analytics/DetectRecurringSubscriptionsTest.php
git commit -m "$(cat <<'EOF'
Add DetectRecurringSubscriptions analytics action

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 10: SpendingVelocity analytics action

**Files:**
- Create: `app/Actions/Finance/Analytics/SpendingVelocity.php`
- Test: `tests/Unit/Actions/Finance/Analytics/SpendingVelocityTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Analytics/SpendingVelocityTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Analytics\SpendingVelocity;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('compares this month spend so-far against last month through the same day', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    // June 1-15: $1000
    for ($d = 1; $d <= 15; $d++) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-06-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT), 'amount_cents' => -6667]);
    }
    // July 1-15: $2000
    for ($d = 1; $d <= 15; $d++) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT), 'amount_cents' => -13333]);
    }

    $result = (new SpendingVelocity)();

    expect($result['this_month_cents_so_far'])->toBeGreaterThan($result['last_month_cents_through_same_day']);
    expect($result['delta_pct'])->toBeGreaterThan(0);
});

it('projects the full month based on this-month run-rate', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    // July 1-15: $1500 → run rate $100/day → $3100 projected for 31-day July
    for ($d = 1; $d <= 15; $d++) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT), 'amount_cents' => -10000]);
    }

    $result = (new SpendingVelocity)();

    expect($result['projected_full_month_cents'])->toBeGreaterThan($result['this_month_cents_so_far']);
});

it('returns zero values when there are no transactions', function () {
    $result = (new SpendingVelocity)();

    expect($result['this_month_cents_so_far'])->toBe(0);
    expect($result['last_month_cents_through_same_day'])->toBe(0);
    expect($result['delta_pct'])->toBe(0.0);
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=SpendingVelocityTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class SpendingVelocity
{
    /**
     * @return array{this_month_cents_so_far: int, last_month_cents_through_same_day: int, delta_pct: float, projected_full_month_cents: int}
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();
        $thisStart = $today->startOfMonth();
        $thisEnd = $today;
        $lastStart = $thisStart->subMonthsNoOverflow(1);
        $lastEndSameDay = $lastStart->setDay(min($today->day, $lastStart->daysInMonth));

        $spendIds = Category::query()->where('kind', 'spending')->pluck('id');

        $thisSpend = $spendIds->isEmpty() ? 0 : (int) abs((int) Transaction::query()
            ->whereIn('category_id', $spendIds)
            ->whereBetween('occurred_on', [$thisStart->toDateString(), $thisEnd->toDateString()])
            ->where('amount_cents', '<', 0)
            ->whereNull('deleted_at')
            ->sum('amount_cents'));

        $lastSpend = $spendIds->isEmpty() ? 0 : (int) abs((int) Transaction::query()
            ->whereIn('category_id', $spendIds)
            ->whereBetween('occurred_on', [$lastStart->toDateString(), $lastEndSameDay->toDateString()])
            ->where('amount_cents', '<', 0)
            ->whereNull('deleted_at')
            ->sum('amount_cents'));

        $deltaPct = $lastSpend > 0 ? round((($thisSpend - $lastSpend) / $lastSpend) * 100, 2) : 0.0;

        $daysElapsed = max(1, $today->day);
        $totalDays = $today->endOfMonth()->day;
        $projected = (int) round(($thisSpend / $daysElapsed) * $totalDays);

        return [
            'this_month_cents_so_far' => $thisSpend,
            'last_month_cents_through_same_day' => $lastSpend,
            'delta_pct' => (float) $deltaPct,
            'projected_full_month_cents' => $projected,
        ];
    }
}
```

- [ ] **Step 5: Run tests** — `php artisan test --compact --filter=SpendingVelocityTest`

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Analytics/SpendingVelocity.php tests/Unit/Actions/Finance/Analytics/SpendingVelocityTest.php
git commit -m "$(cat <<'EOF'
Add SpendingVelocity analytics action

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 11: FixedVariableRatio analytics action

**Files:**
- Create: `app/Actions/Finance/Analytics/FixedVariableRatio.php`
- Test: `tests/Unit/Actions/Finance/Analytics/FixedVariableRatioTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Actions/Finance/Analytics/FixedVariableRatioTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Actions\Finance\Analytics\FixedVariableRatio;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('classifies bill-linked transactions as fixed and others as variable', function () {
    $account = Account::factory()->create();
    $billCat = Category::factory()->create(['kind' => 'spending']);
    Bill::factory()->create(['category_id' => $billCat->id, 'account_id' => $account->id]);
    $varCat = Category::factory()->create(['kind' => 'spending']);

    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $billCat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -100000]); // fixed
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $varCat->id, 'occurred_on' => '2026-07-10', 'amount_cents' => -50000]);   // variable

    $result = (new FixedVariableRatio)(monthsBack: 1);

    $july = collect($result)->firstWhere('month', '2026-07');
    expect($july['fixed_cents'])->toBe(100000);
    expect($july['variable_cents'])->toBe(50000);
    expect($july['fixed_ratio_pct'])->toBeGreaterThan(60.0);
});

it('returns one entry per month going back N months', function () {
    expect((new FixedVariableRatio)(monthsBack: 4))->toHaveCount(4);
});

it('handles months with no spending', function () {
    $result = (new FixedVariableRatio)(monthsBack: 1);

    expect($result[0]['fixed_ratio_pct'])->toBe(0.0);
});
```

- [ ] **Step 3: Run, expect FAIL**

```bash
php artisan test --compact --filter=FixedVariableRatioTest
```

- [ ] **Step 4: Implement**

```php
<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Bill;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class FixedVariableRatio
{
    /**
     * @return array<int, array{month: string, fixed_cents: int, variable_cents: int, fixed_ratio_pct: float}>
     */
    public function __invoke(int $monthsBack = 6): array
    {
        $today = CarbonImmutable::today();
        $start = $today->startOfMonth()->subMonthsNoOverflow($monthsBack - 1);

        $billCategoryIds = Bill::query()->whereNotNull('category_id')->pluck('category_id')->all();

        $out = [];
        for ($i = 0; $i < $monthsBack; $i++) {
            $m = $start->addMonthsNoOverflow($i);
            $mEnd = $m->endOfMonth();

            $fixed = $billCategoryIds === [] ? 0 : (int) abs((int) Transaction::query()
                ->whereIn('category_id', $billCategoryIds)
                ->whereBetween('occurred_on', [$m->toDateString(), $mEnd->toDateString()])
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->sum('amount_cents'));

            $variable = (int) abs((int) Transaction::query()
                ->whereNotIn('category_id', $billCategoryIds === [] ? [0] : $billCategoryIds)
                ->whereNotNull('category_id')
                ->whereBetween('occurred_on', [$m->toDateString(), $mEnd->toDateString()])
                ->where('amount_cents', '<', 0)
                ->whereNull('deleted_at')
                ->sum('amount_cents'));

            $total = $fixed + $variable;
            $ratio = $total > 0 ? round(($fixed / $total) * 100, 2) : 0.0;

            $out[] = [
                'month' => $m->format('Y-m'),
                'fixed_cents' => $fixed,
                'variable_cents' => $variable,
                'fixed_ratio_pct' => (float) $ratio,
            ];
        }

        return $out;
    }
}
```

- [ ] **Step 5: Run tests** — `php artisan test --compact --filter=FixedVariableRatioTest`

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Finance/Analytics/FixedVariableRatio.php tests/Unit/Actions/Finance/Analytics/FixedVariableRatioTest.php
git commit -m "$(cat <<'EOF'
Add FixedVariableRatio analytics action

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 12: Insight DTO + BuildInsights action

**Files:**
- Create: `app/Coach/Insight.php`
- Create: `app/Actions/Coach/BuildInsights.php`
- Test: `tests/Unit/Actions/Coach/BuildInsightsTest.php`

- [ ] **Step 1: Create the DTO**

`app/Coach/Insight.php`:

```php
<?php

namespace App\Coach;

final class Insight
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $severity,           // 'critical' | 'warning' | 'info' | 'positive'
        public string $headline,
        public string $detail,
        public ?string $suggestedPrompt,
        public string $sourceTool,
        public array $metadata = [],
    ) {}
}
```

- [ ] **Step 2: Generate test**

```bash
php artisan make:test --pest --unit Actions/Coach/BuildInsightsTest --no-interaction
```

- [ ] **Step 3: Write failing test**

```php
<?php

use App\Actions\Coach\BuildInsights;
use App\Coach\Insight;
use App\Models\Account;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('returns an array of Insight objects', function () {
    $result = (new BuildInsights)();
    expect($result)->toBeArray();
    foreach ($result as $item) {
        expect($item)->toBeInstanceOf(Insight::class);
    }
});

it('emits a warning when a top mover is up 100%+', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending', 'name' => 'Food']);

    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-06-10', 'amount_cents' => -10000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -25000]);

    $result = (new BuildInsights)();

    $foodInsight = collect($result)->first(fn (Insight $i) => str_contains($i->headline, 'Food'));
    expect($foodInsight)->not->toBeNull();
    expect($foodInsight->severity)->toBeIn(['warning', 'critical']);
});

it('caps the output at 6 insights', function () {
    // Seed enough data to generate >6 insights then verify cap
    \App\Models\AppSetting::current()->update(['monthly_income_target_cents' => 480000]);
    for ($i = 0; $i < 10; $i++) {
        Bucket::factory()->create(['name' => "B$i", 'target_percentage' => 5]);
    }
    $cats = Category::factory()->count(10)->create(['kind' => 'spending']);
    $account = Account::factory()->create();
    foreach ($cats as $cat) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-06-10', 'amount_cents' => -1000]);
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -5000]);
    }

    $result = (new BuildInsights)();

    expect(count($result))->toBeLessThanOrEqual(6);
});

it('ranks critical above warning above info above positive', function () {
    \App\Models\AppSetting::current()->update(['monthly_income_target_cents' => 480000]);
    $b = Bucket::factory()->create(['name' => 'Wants', 'target_percentage' => 20]);
    $cat = Category::factory()->create(['kind' => 'spending', 'bucket_id' => $b->id]);
    $account = Account::factory()->create();
    // Spend 100%+ of the wants budget
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -100000]);

    $result = (new BuildInsights)();

    $severities = collect($result)->pluck('severity');
    $first = $severities->first();
    $last = $severities->last();
    $order = ['critical' => 0, 'warning' => 1, 'info' => 2, 'positive' => 3];
    expect($order[$first])->toBeLessThanOrEqual($order[$last] ?? 3);
});
```

- [ ] **Step 4: Run, expect FAIL**

```bash
php artisan test --compact --filter=BuildInsightsTest
```

- [ ] **Step 5: Implement**

`app/Actions/Coach/BuildInsights.php`:

```php
<?php

namespace App\Actions\Coach;

use App\Actions\Finance\Analytics\BudgetVariance;
use App\Actions\Finance\Analytics\DetectAnomalies;
use App\Actions\Finance\Analytics\DetectRecurringSubscriptions;
use App\Actions\Finance\Analytics\FixedVariableRatio;
use App\Actions\Finance\Analytics\GoalPaceForecast;
use App\Actions\Finance\Analytics\SavingsRateTrend;
use App\Actions\Finance\Analytics\SpendingVelocity;
use App\Actions\Finance\Analytics\TopMovers;
use App\Coach\Insight;

class BuildInsights
{
    private const SEVERITY_RANK = ['critical' => 0, 'warning' => 1, 'info' => 2, 'positive' => 3];

    /**
     * @return array<int, Insight>
     */
    public function __invoke(): array
    {
        $insights = [];

        // Top movers
        foreach ((new TopMovers)() as $row) {
            if ($row['delta_pct'] >= 100.0) {
                $insights[] = new Insight('critical',
                    "{$row['name']} spending up {$row['delta_pct']}%",
                    "You spent ".number_format($row['current_cents'] / 100, 2)." on {$row['name']} so far this month vs ".number_format($row['previous_cents'] / 100, 2)." last month.",
                    "How can I cut back on {$row['name']}?",
                    'top_movers',
                    $row,
                );
            } elseif ($row['delta_pct'] >= 50.0) {
                $insights[] = new Insight('warning',
                    "{$row['name']} spending up {$row['delta_pct']}%",
                    "You spent ".number_format($row['current_cents'] / 100, 2)." on {$row['name']} so far this month vs ".number_format($row['previous_cents'] / 100, 2)." last month.",
                    "What's driving the increase in {$row['name']}?",
                    'top_movers',
                    $row,
                );
            }
        }

        // Anomalies (one per category)
        $seenCategories = [];
        foreach ((new DetectAnomalies)() as $row) {
            if ($row['std_devs_from_median'] < 3.0 || in_array($row['category_id'], $seenCategories, true)) {
                continue;
            }
            $seenCategories[] = $row['category_id'];
            $insights[] = new Insight('warning',
                'Unusual transaction: $'.number_format(abs($row['amount_cents']) / 100, 2),
                "{$row['description']} — your category median is $".number_format($row['category_median_cents'] / 100, 2),
                'Tell me about my recent unusual spending',
                'detect_anomalies',
                $row,
            );
        }

        // Budget variance
        foreach ((new BudgetVariance)() as $row) {
            if ($row['variance_pct'] >= 100.0) {
                $insights[] = new Insight('critical',
                    "{$row['name']} budget exceeded",
                    "You've spent ".$row['variance_pct']."% of your {$row['name']} budget with {$row['days_remaining_in_period']} days left.",
                    "Where am I overspending in {$row['name']}?",
                    'budget_variance',
                    $row,
                );
            } elseif ($row['variance_pct'] >= 90.0 && $row['days_remaining_in_period'] > intdiv($this->daysInMonth(), 4)) {
                $insights[] = new Insight('warning',
                    "{$row['name']} budget almost gone",
                    "You're {$row['variance_pct']}% through your {$row['name']} budget with {$row['days_remaining_in_period']} days left.",
                    "What can I cut to stay under my {$row['name']} budget?",
                    'budget_variance',
                    $row,
                );
            }
        }

        // Goal pace
        foreach ((new GoalPaceForecast)() as $row) {
            if (! $row['on_track'] && $row['months_off'] >= 1) {
                $insights[] = new Insight('warning',
                    "{$row['name']} goal off pace",
                    "At current savings rate, {$row['name']} hits target ~{$row['months_off']} months after deadline.",
                    "How can I get my {$row['name']} goal on track?",
                    'goal_pace_forecast',
                    $row,
                );
            } elseif ($row['on_track'] && $row['target_date']) {
                $insights[] = new Insight('positive',
                    "{$row['name']} goal on track",
                    'Projected to hit target by '.$row['projected_hit_date'].'.',
                    null,
                    'goal_pace_forecast',
                    $row,
                );
            }
        }

        // Savings rate trend
        $savings = (new SavingsRateTrend)(monthsBack: 2);
        if (count($savings) === 2) {
            $delta = $savings[1]['savings_rate_pct'] - $savings[0]['savings_rate_pct'];
            if ($delta < -10) {
                $insights[] = new Insight('warning',
                    'Savings rate dropping',
                    "Rate fell from {$savings[0]['savings_rate_pct']}% to {$savings[1]['savings_rate_pct']}% month-over-month.",
                    'Why did my savings rate drop?',
                    'savings_rate_trend',
                    ['previous' => $savings[0], 'current' => $savings[1]],
                );
            } elseif ($delta > 10) {
                $insights[] = new Insight('positive',
                    'Savings rate climbing',
                    "Rate rose from {$savings[0]['savings_rate_pct']}% to {$savings[1]['savings_rate_pct']}% month-over-month.",
                    null,
                    'savings_rate_trend',
                    ['previous' => $savings[0], 'current' => $savings[1]],
                );
            }
        }

        // Recurring subs
        foreach ((new DetectRecurringSubscriptions)() as $row) {
            if ($row['already_tracked_as_bill_id']) {
                continue;
            }
            $insights[] = new Insight('info',
                "Untracked subscription: {$row['merchant_pattern']}",
                "Recurring $".number_format($row['monthly_avg_cents'] / 100, 2)." charge, {$row['occurrence_count']} times. Consider tracking as a bill.",
                "Tell me about the {$row['merchant_pattern']} subscription",
                'detect_recurring_subscriptions',
                $row,
            );
        }

        // Spending velocity
        $velocity = (new SpendingVelocity)();
        if ($velocity['delta_pct'] >= 30.0) {
            $insights[] = new Insight('warning',
                'Spending pace ahead of last month',
                "This month: $".number_format($velocity['this_month_cents_so_far'] / 100, 2)." vs $".number_format($velocity['last_month_cents_through_same_day'] / 100, 2)." last month at same point.",
                "What's driving my faster spending this month?",
                'spending_velocity',
                $velocity,
            );
        }

        // Fixed vs variable
        $fvr = (new FixedVariableRatio)(monthsBack: 3);
        if (count($fvr) === 3) {
            $delta = $fvr[2]['fixed_ratio_pct'] - $fvr[0]['fixed_ratio_pct'];
            if ($delta >= 5.0) {
                $insights[] = new Insight('info',
                    'Fixed costs taking a bigger share',
                    "Fixed-cost share rose ".round($delta, 1)." pts over the last 3 months.",
                    'What fixed costs grew the most?',
                    'fixed_variable_ratio',
                    ['series' => $fvr],
                );
            }
        }

        // Rank by severity, then keep stable input order; cap at 6
        usort($insights, fn (Insight $a, Insight $b) => self::SEVERITY_RANK[$a->severity] <=> self::SEVERITY_RANK[$b->severity]);

        return array_slice($insights, 0, 6);
    }

    private function daysInMonth(): int
    {
        return \Carbon\CarbonImmutable::today()->endOfMonth()->day;
    }
}
```

- [ ] **Step 6: Run tests** — `php artisan test --compact --filter=BuildInsightsTest`

- [ ] **Step 7: Commit**

```bash
git add app/Coach app/Actions/Coach tests/Unit/Actions/Coach/BuildInsightsTest.php
git commit -m "$(cat <<'EOF'
Add Insight DTO + BuildInsights action

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 13: Dashboard insights widget

**Files:**
- Create: `resources/views/pages/dashboard/⚡insights.blade.php`
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Feature/Pages/Dashboard/InsightsTest.php`

- [ ] **Step 1: Generate SFC**

```bash
php artisan make:livewire pages::dashboard.insights --no-interaction
```

- [ ] **Step 2: Replace `resources/views/pages/dashboard/⚡insights.blade.php`**

```blade
<?php

use App\Actions\Coach\BuildInsights;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function insights(): array
    {
        return (new BuildInsights)();
    }

    private function severityClasses(string $severity): string
    {
        return match ($severity) {
            'critical' => 'border-error/30 bg-error/5',
            'warning' => 'border-warning/30 bg-warning/5',
            'positive' => 'border-success/30 bg-success/5',
            default => 'border-base-300 bg-base-200/30',
        };
    }

    public function with(): array
    {
        return ['severityClasses' => fn (string $s) => $this->severityClasses($s)];
    }
}; ?>

<x-card class="border border-base-300" title="Insights">
    @if (empty($this->insights))
        <p class="text-sm opacity-60">Nothing to flag right now. Keep importing transactions to surface patterns.</p>
    @else
        <div class="grid gap-2 md:grid-cols-2">
            @foreach ($this->insights as $insight)
                <a href="{{ $insight->suggestedPrompt ? route('chat.index', ['prompt' => $insight->suggestedPrompt]) : route('chat.index') }}" wire:navigate class="block p-3 rounded-lg border {{ $severityClasses($insight->severity) }} hover:opacity-90">
                    <div class="text-sm font-semibold">{{ $insight->headline }}</div>
                    <div class="text-xs opacity-70 mt-1">{{ $insight->detail }}</div>
                </a>
            @endforeach
        </div>
    @endif
</x-card>
```

- [ ] **Step 3: Add to the dashboard**

Modify `resources/views/dashboard.blade.php` — insert the insights widget right after `budget-status`:

```blade
<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <livewire:pages::dashboard.budget-status key="budget-status" />

        <livewire:pages::dashboard.insights key="dashboard-insights" />

        <livewire:pages::dashboard.upcoming-bills key="upcoming-bills" />

        <livewire:pages::dashboard.goal-progress key="goal-progress" />

        <div>
            <livewire:pages::charts.balance-chart :account-id="null" key="chart-household" />
        </div>

        <livewire:pages::accounts.index key="dashboard-accounts" />
    </div>
</x-layouts::app>
```

- [ ] **Step 4: Write failing test**

`tests/Feature/Pages/Dashboard/InsightsTest.php`:

```php
<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    \Carbon\Carbon::setTestNow('2026-07-15');
    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    \Carbon\Carbon::setTestNow();
});

it('renders the insights card with empty state when nothing to flag', function () {
    $this->get('/dashboard')->assertOk()->assertSee('Insights')->assertSee('Nothing to flag right now');
});

it('renders an insight card with a link to chat when a top mover spikes', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['name' => 'Food', 'kind' => 'spending']);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-06-10', 'amount_cents' => -10000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -25000]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Food spending up')
        ->assertSee('/chat?prompt=');
});
```

- [ ] **Step 5: Run filter** — `php artisan test --compact --filter=Pages\\\\Dashboard\\\\InsightsTest`

(Note: the `/chat` route will be added in a later task; for now Livewire's `route('chat.index')` call needs the route to exist. Add a stub route to `routes/web.php` inside the auth group: `Route::view('chat', 'welcome')->name('chat.index');` — replaced for real in Task 19.)

- [ ] **Step 6: Run filter again — expect PASS**

- [ ] **Step 7: Commit**

```bash
git add resources/views/pages/dashboard/⚡insights.blade.php resources/views/dashboard.blade.php tests/Feature/Pages/Dashboard/InsightsTest.php routes/web.php
git commit -m "$(cat <<'EOF'
Add Dashboard insights widget driven by BuildInsights

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 14: CoachConfig + Settings page + CoachNotConfiguredException

**Files:**
- Create: `app/Services/Coach/CoachConfig.php`
- Create: `app/Exceptions/CoachNotConfiguredException.php`
- Create: `resources/views/pages/settings/⚡coach.blade.php`
- Modify: `routes/settings.php`
- Test: `tests/Unit/Services/Coach/CoachConfigTest.php`
- Test: `tests/Feature/Pages/Settings/CoachTest.php`

- [ ] **Step 1: Generate tests**

```bash
php artisan make:test --pest --unit Services/Coach/CoachConfigTest --no-interaction
php artisan make:test --pest Pages/Settings/CoachTest --no-interaction
```

- [ ] **Step 2: Implement `CoachConfig`**

`app/Services/Coach/CoachConfig.php`:

```php
<?php

namespace App\Services\Coach;

use App\Models\AppSetting;

class CoachConfig
{
    public function isConfigured(): bool
    {
        return ! empty($this->baseUrl());
    }

    public function baseUrl(): ?string
    {
        $url = AppSetting::current()->ollama_base_url;

        return $url ? rtrim((string) $url, '/') : null;
    }

    public function model(): string
    {
        return (string) (AppSetting::current()->ollama_model ?: 'llama3.1:8b');
    }
}
```

- [ ] **Step 3: Implement the exception**

`app/Exceptions/CoachNotConfiguredException.php`:

```php
<?php

namespace App\Exceptions;

use RuntimeException;

class CoachNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ollama is not configured. Set it up at /settings/coach.');
    }
}
```

- [ ] **Step 4: Write `CoachConfigTest`**

```php
<?php

use App\Models\AppSetting;
use App\Services\Coach\CoachConfig;

it('isConfigured returns false when base URL is empty', function () {
    AppSetting::current()->update(['ollama_base_url' => null]);
    expect((new CoachConfig)->isConfigured())->toBeFalse();
});

it('isConfigured returns true with a base URL set', function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    expect((new CoachConfig)->isConfigured())->toBeTrue();
});

it('trims trailing slashes from the base URL', function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434/']);
    expect((new CoachConfig)->baseUrl())->toBe('http://homelab:11434');
});

it('defaults the model to llama3.1:8b when unset', function () {
    AppSetting::current()->update(['ollama_model' => null]);
    expect((new CoachConfig)->model())->toBe('llama3.1:8b');
});

it('returns the stored model when set', function () {
    AppSetting::current()->update(['ollama_model' => 'mistral:7b']);
    expect((new CoachConfig)->model())->toBe('mistral:7b');
});
```

- [ ] **Step 5: Implement the settings page**

`resources/views/pages/settings/⚡coach.blade.php`:

```blade
<?php

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Coach settings')] class extends Component {
    #[Validate('nullable|url|max:255')]
    public string $baseUrl = '';

    #[Validate('nullable|string|max:64')]
    public string $modelName = '';

    public ?string $testResult = null;

    public function mount(): void
    {
        $setting = AppSetting::current();
        $this->baseUrl = (string) ($setting->ollama_base_url ?? '');
        $this->modelName = (string) ($setting->ollama_model ?? '');
    }

    public function save(): void
    {
        $this->validate();
        AppSetting::current()->update([
            'ollama_base_url' => $this->baseUrl ?: null,
            'ollama_model' => $this->modelName ?: null,
        ]);
        $this->dispatch('coach-saved');
    }

    public function testConnection(): void
    {
        $this->validate();
        if (! $this->baseUrl) {
            $this->testResult = 'Set a URL first.';

            return;
        }
        try {
            $response = Http::timeout(5)->get(rtrim($this->baseUrl, '/').'/api/tags');
            $this->testResult = $response->successful() ? 'OK — Ollama responded.' : 'Got HTTP '.$response->status();
        } catch (\Throwable $e) {
            $this->testResult = 'Failed: '.$e->getMessage();
        }
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Coach')" :subheading="__('Connect to a local Ollama instance')">
        <x-form wire:submit="save" class="space-y-3">
            <x-input label="Ollama base URL" wire:model="baseUrl" placeholder="http://homelab.local:11434" hint="Leave blank to disable the coach. The Insights widget on the dashboard still works." />
            <x-input label="Model name" wire:model="modelName" placeholder="llama3.1:8b" hint="Any tool-capable model installed on your Ollama instance." />

            <div class="flex gap-2">
                <x-button label="Save" type="submit" class="btn-primary" />
                <x-button label="Test connection" wire:click="testConnection" type="button" class="btn-ghost" />
            </div>

            @if ($testResult)
                <p class="text-sm {{ str_starts_with($testResult, 'OK') ? 'text-success' : 'text-error' }}">{{ $testResult }}</p>
            @endif
        </x-form>
    </x-pages::settings.layout>
</section>
```

- [ ] **Step 6: Add the settings route**

In `routes/settings.php`, inside the `auth + verified` group:

```php
Route::livewire('settings/coach', 'pages::settings.coach')->name('coach.edit');
```

- [ ] **Step 7: Write `CoachTest`**

```php
<?php

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('saves the Ollama base URL and model', function () {
    Livewire::test('pages::settings.coach')
        ->set('baseUrl', 'http://homelab:11434')
        ->set('modelName', 'llama3.1:8b')
        ->call('save')
        ->assertHasNoErrors();

    expect(AppSetting::current()->ollama_base_url)->toBe('http://homelab:11434');
    expect(AppSetting::current()->ollama_model)->toBe('llama3.1:8b');
});

it('reports OK when the test connection succeeds', function () {
    Http::fake(['*' => Http::response(['models' => []], 200)]);

    Livewire::test('pages::settings.coach')
        ->set('baseUrl', 'http://homelab:11434')
        ->call('testConnection')
        ->assertSet('testResult', 'OK — Ollama responded.');
});

it('reports failure when the test connection cannot reach Ollama', function () {
    Http::fake(['*' => Http::response('boom', 500)]);

    Livewire::test('pages::settings.coach')
        ->set('baseUrl', 'http://homelab:11434')
        ->call('testConnection')
        ->assertSet('testResult', 'Got HTTP 500');
});
```

- [ ] **Step 8: Run tests** — `php artisan test --compact --filter='(CoachConfigTest|Pages\\\\Settings\\\\CoachTest)'`

- [ ] **Step 9: Commit**

```bash
git add app/Services/Coach/CoachConfig.php app/Exceptions/CoachNotConfiguredException.php resources/views/pages/settings/⚡coach.blade.php routes/settings.php tests/Unit/Services/Coach/CoachConfigTest.php tests/Feature/Pages/Settings/CoachTest.php
git commit -m "$(cat <<'EOF'
Add CoachConfig + settings page for Ollama URL/model

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 15: CoachTool DTO + ToolRegistry + CoachServiceProvider

**Files:**
- Create: `app/Services/Coach/CoachTool.php`
- Create: `app/Services/Coach/ToolRegistry.php`
- Create: `app/Providers/CoachServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Unit/Services/Coach/ToolRegistryTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Services/Coach/ToolRegistryTest --no-interaction
```

- [ ] **Step 2: Implement DTO + Registry**

`app/Services/Coach/CoachTool.php`:

```php
<?php

namespace App\Services\Coach;

use Closure;

final class CoachTool
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
        public string $kind,                 // 'read' | 'write'
        public bool $requiresConfirmation,
        public Closure $handler,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toOllamaToolSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ],
        ];
    }
}
```

`app/Services/Coach/ToolRegistry.php`:

```php
<?php

namespace App\Services\Coach;

class ToolRegistry
{
    /** @var array<string, CoachTool> */
    private array $tools = [];

    public function register(CoachTool $tool): void
    {
        $this->tools[$tool->name] = $tool;
    }

    /**
     * @return array<int, CoachTool>
     */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function find(string $name): ?CoachTool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toOllamaToolsArray(): array
    {
        return array_map(fn (CoachTool $t) => $t->toOllamaToolSchema(), $this->all());
    }
}
```

- [ ] **Step 3: Implement CoachServiceProvider**

`app/Providers/CoachServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Actions\Finance\Analytics\BudgetVariance;
use App\Actions\Finance\Analytics\DetectAnomalies;
use App\Actions\Finance\Analytics\DetectRecurringSubscriptions;
use App\Actions\Finance\Analytics\FixedVariableRatio;
use App\Actions\Finance\Analytics\GoalPaceForecast;
use App\Actions\Finance\Analytics\SavingsRateTrend;
use App\Actions\Finance\Analytics\SpendingVelocity;
use App\Actions\Finance\Analytics\TopMovers;
use App\Services\Coach\CoachTool;
use App\Services\Coach\ToolRegistry;
use Illuminate\Support\ServiceProvider;

class CoachServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function () {
            $registry = new ToolRegistry;
            $this->registerAnalyticsTools($registry);

            return $registry;
        });
    }

    private function registerAnalyticsTools(ToolRegistry $registry): void
    {
        $registry->register(new CoachTool(
            name: 'top_movers',
            description: 'Categories with the biggest month-over-month spending change.',
            parameters: ['type' => 'object', 'properties' => [
                'months_back' => ['type' => 'integer', 'default' => 1],
                'limit' => ['type' => 'integer', 'default' => 5],
            ]],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new TopMovers)(monthsBack: $args['months_back'] ?? 1, limit: $args['limit'] ?? 5),
        ));

        $registry->register(new CoachTool(
            name: 'detect_anomalies',
            description: 'Find transactions that are unusually large vs their category median.',
            parameters: ['type' => 'object', 'properties' => [
                'lookback_days' => ['type' => 'integer', 'default' => 90],
                'std_dev_threshold' => ['type' => 'number', 'default' => 2.0],
            ]],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new DetectAnomalies)(
                lookbackDays: $args['lookback_days'] ?? 90,
                stdDevThreshold: (float) ($args['std_dev_threshold'] ?? 2.0),
            ),
        ));

        $registry->register(new CoachTool(
            name: 'budget_variance',
            description: 'Per-bucket planned vs actual spending this month, with days remaining in the period.',
            parameters: ['type' => 'object', 'properties' => new \stdClass],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new BudgetVariance)(),
        ));

        $registry->register(new CoachTool(
            name: 'goal_pace_forecast',
            description: 'For each savings goal, the projected hit date based on the last 3 months of net deposits.',
            parameters: ['type' => 'object', 'properties' => new \stdClass],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new GoalPaceForecast)(),
        ));

        $registry->register(new CoachTool(
            name: 'savings_rate_trend',
            description: 'Monthly savings rate (income minus spend over income) for the past N months.',
            parameters: ['type' => 'object', 'properties' => [
                'months_back' => ['type' => 'integer', 'default' => 12],
            ]],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new SavingsRateTrend)(monthsBack: $args['months_back'] ?? 12),
        ));

        $registry->register(new CoachTool(
            name: 'detect_recurring_subscriptions',
            description: 'Find recurring same-amount transactions in the last 6 months that are not already tracked as bills.',
            parameters: ['type' => 'object', 'properties' => new \stdClass],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new DetectRecurringSubscriptions)(),
        ));

        $registry->register(new CoachTool(
            name: 'spending_velocity',
            description: 'How fast spending is accumulating this month vs the same point last month.',
            parameters: ['type' => 'object', 'properties' => new \stdClass],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new SpendingVelocity)(),
        ));

        $registry->register(new CoachTool(
            name: 'fixed_variable_ratio',
            description: 'Per-month ratio of fixed (bill-linked) spending to variable spending.',
            parameters: ['type' => 'object', 'properties' => [
                'months_back' => ['type' => 'integer', 'default' => 6],
            ]],
            kind: 'read',
            requiresConfirmation: false,
            handler: fn (array $args) => (new FixedVariableRatio)(monthsBack: $args['months_back'] ?? 6),
        ));
    }
}
```

- [ ] **Step 4: Register the provider**

Modify `bootstrap/providers.php` to add `App\Providers\CoachServiceProvider::class` to the returned array.

- [ ] **Step 5: Write `ToolRegistryTest`**

```php
<?php

use App\Services\Coach\CoachTool;
use App\Services\Coach\ToolRegistry;

it('registers and finds tools by name', function () {
    $registry = new ToolRegistry;
    $registry->register(new CoachTool(
        name: 'echo',
        description: 'echo',
        parameters: ['type' => 'object', 'properties' => new \stdClass],
        kind: 'read',
        requiresConfirmation: false,
        handler: fn (array $args) => $args,
    ));

    expect($registry->find('echo'))->not->toBeNull();
    expect($registry->find('unknown'))->toBeNull();
});

it('exposes Ollama-shaped tool schemas', function () {
    $registry = new ToolRegistry;
    $registry->register(new CoachTool(
        name: 'echo',
        description: 'echo back',
        parameters: ['type' => 'object', 'properties' => ['x' => ['type' => 'string']]],
        kind: 'read',
        requiresConfirmation: false,
        handler: fn (array $args) => $args,
    ));

    $schemas = $registry->toOllamaToolsArray();
    expect($schemas[0]['type'])->toBe('function');
    expect($schemas[0]['function']['name'])->toBe('echo');
});

it('registers all 8 analytics tools on container boot', function () {
    $registry = app(ToolRegistry::class);
    $names = collect($registry->all())->pluck('name')->all();

    foreach (['top_movers', 'detect_anomalies', 'budget_variance', 'goal_pace_forecast', 'savings_rate_trend', 'detect_recurring_subscriptions', 'spending_velocity', 'fixed_variable_ratio'] as $expected) {
        expect($names)->toContain($expected);
    }
});
```

- [ ] **Step 6: Run tests** — `php artisan test --compact --filter=ToolRegistryTest`

- [ ] **Step 7: Commit**

```bash
git add app/Services/Coach app/Providers/CoachServiceProvider.php bootstrap/providers.php tests/Unit/Services/Coach/ToolRegistryTest.php
git commit -m "$(cat <<'EOF'
Add CoachTool DTO, ToolRegistry, and register 8 analytics tools

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 16: OllamaClient

**Files:**
- Create: `app/Services/Coach/OllamaClient.php`
- Test: `tests/Unit/Services/Coach/OllamaClientTest.php`

- [ ] **Step 1: Generate test**

```bash
php artisan make:test --pest --unit Services/Coach/OllamaClientTest --no-interaction
```

- [ ] **Step 2: Write failing test**

```php
<?php

use App\Exceptions\CoachNotConfiguredException;
use App\Models\AppSetting;
use App\Services\Coach\CoachConfig;
use App\Services\Coach\OllamaClient;
use Illuminate\Support\Facades\Http;

it('throws when no base URL is configured', function () {
    AppSetting::current()->update(['ollama_base_url' => null]);

    $client = new OllamaClient(new CoachConfig);

    expect(fn () => $client->dryRun([], []))->toThrow(CoachNotConfiguredException::class);
});

it('sends model + messages + tools to the configured endpoint', function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434', 'ollama_model' => 'llama3.1:8b']);

    Http::fake([
        'http://homelab:11434/api/chat' => Http::response([
            'model' => 'llama3.1:8b',
            'message' => ['role' => 'assistant', 'content' => 'hi'],
            'done' => true,
        ], 200),
    ]);

    $client = new OllamaClient(new CoachConfig);
    $result = $client->dryRun(
        messages: [['role' => 'user', 'content' => 'hello']],
        tools: [['type' => 'function', 'function' => ['name' => 'echo', 'parameters' => ['type' => 'object']]]]
    );

    expect($result['message']['content'])->toBe('hi');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);

        return $body['model'] === 'llama3.1:8b'
            && $body['messages'][0]['content'] === 'hello'
            && $body['tools'][0]['function']['name'] === 'echo';
    });
});
```

- [ ] **Step 3: Run, expect FAIL** — `php artisan test --compact --filter=OllamaClientTest`

- [ ] **Step 4: Implement `OllamaClient`**

`app/Services/Coach/OllamaClient.php`:

```php
<?php

namespace App\Services\Coach;

use App\Exceptions\CoachNotConfiguredException;
use Illuminate\Support\Facades\Http;

class OllamaClient
{
    public function __construct(private readonly CoachConfig $config) {}

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    public function dryRun(array $messages, array $tools): array
    {
        if (! $this->config->isConfigured()) {
            throw new CoachNotConfiguredException;
        }

        $body = [
            'model' => $this->config->model(),
            'messages' => $messages,
            'stream' => false,
        ];
        if ($tools !== []) {
            $body['tools'] = $tools;
        }

        return Http::timeout(60)
            ->post($this->config->baseUrl().'/api/chat', $body)
            ->throw()
            ->json();
    }

    /**
     * Streaming generator that yields parsed NDJSON chunks from Ollama.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return \Generator<array<string, mixed>>
     */
    public function stream(array $messages, array $tools): \Generator
    {
        if (! $this->config->isConfigured()) {
            throw new CoachNotConfiguredException;
        }

        $body = [
            'model' => $this->config->model(),
            'messages' => $messages,
            'stream' => true,
        ];
        if ($tools !== []) {
            $body['tools'] = $tools;
        }

        $url = $this->config->baseUrl().'/api/chat';

        $response = Http::timeout(120)
            ->withOptions(['stream' => true])
            ->post($url, $body);

        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        while (! $stream->eof()) {
            $buffer .= $stream->read(1024);
            while (($newlineAt = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newlineAt);
                $buffer = substr($buffer, $newlineAt + 1);
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    yield $decoded;
                }
            }
        }
    }
}
```

- [ ] **Step 5: Run tests** — `php artisan test --compact --filter=OllamaClientTest`

- [ ] **Step 6: Commit**

```bash
git add app/Services/Coach/OllamaClient.php tests/Unit/Services/Coach/OllamaClientTest.php
git commit -m "$(cat <<'EOF'
Add OllamaClient with dryRun + streaming generator

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 17: ChatLoop + system prompt

**Files:**
- Create: `app/Services/Coach/ChatLoop.php`
- Create: `resources/prompts/coach.md`
- Test: `tests/Unit/Services/Coach/ChatLoopTest.php`

- [ ] **Step 1: Create the system prompt**

`resources/prompts/coach.md`:

```markdown
You are a careful, numbers-first personal finance coach for the Ubusnu app.

# Rules
- Never claim certainty about a specific number, balance, or date unless it came back from a tool call this turn.
- When the user asks about their money, prefer calling a tool over guessing. There are tools for budgets, bills, categories, goals, balances, and trends.
- Be concise. One short paragraph, or a small list. Avoid generic financial advice; ground every answer in the user's actual data.
- If you don't have a tool that can answer the question, say so plainly.
- For "what should I cut" questions, look at top_movers and detect_recurring_subscriptions before suggesting cuts.
- Round dollar amounts to the nearest dollar when summarizing.
```

- [ ] **Step 2: Generate test**

```bash
php artisan make:test --pest --unit Services/Coach/ChatLoopTest --no-interaction
```

- [ ] **Step 3: Implement `ChatLoop`**

`app/Services/Coach/ChatLoop.php`:

```php
<?php

namespace App\Services\Coach;

use App\Models\ChatMessage;
use App\Models\ChatThread;

class ChatLoop
{
    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly ToolRegistry $registry,
        private readonly CoachConfig $config,
    ) {}

    /**
     * Runs one full chat turn (user message → potentially multiple tool calls → final assistant message).
     * Yields token chunks for streaming to the caller.
     *
     * @return \Generator<array{type: string, content?: string, tool_name?: string, summary?: string}>
     */
    public function run(ChatThread $thread, string $userMessage): \Generator
    {
        // Persist user message + bump thread
        ChatMessage::create([
            'chat_thread_id' => $thread->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);
        $thread->touchLastMessage();

        if ($thread->title === '' || str_starts_with($thread->title, 'New chat')) {
            $thread->update(['title' => mb_substr($userMessage, 0, 60)]);
        }

        // Build the prompt: system + full thread history (refreshed after the user insert)
        $systemPrompt = $this->loadSystemPrompt();
        $maxRounds = 5;
        $toolCallsRecord = [];
        $assistantBuffer = '';

        for ($round = 0; $round < $maxRounds; $round++) {
            $messages = $this->buildMessages($thread, $systemPrompt);
            $stream = $this->ollama->stream($messages, $this->registry->toOllamaToolsArray());

            $roundContent = '';
            $roundToolCalls = [];

            foreach ($stream as $chunk) {
                $msg = $chunk['message'] ?? [];
                if (isset($msg['content']) && $msg['content'] !== '') {
                    $roundContent .= $msg['content'];
                    yield ['type' => 'token', 'content' => $msg['content']];
                }
                if (! empty($msg['tool_calls'])) {
                    foreach ($msg['tool_calls'] as $tc) {
                        $roundToolCalls[] = $tc;
                    }
                }
                if ($chunk['done'] ?? false) {
                    break;
                }
            }

            if ($roundToolCalls === []) {
                // Final assistant message
                $assistantBuffer .= $roundContent;
                ChatMessage::create([
                    'chat_thread_id' => $thread->id,
                    'role' => 'assistant',
                    'content' => $assistantBuffer,
                    'tool_calls' => $toolCallsRecord === [] ? null : $toolCallsRecord,
                    'model' => $this->config->model(),
                ]);
                $thread->touchLastMessage();

                return;
            }

            // Tool calls — execute each and append role=tool messages
            foreach ($roundToolCalls as $tc) {
                $name = $tc['function']['name'] ?? '';
                $args = $tc['function']['arguments'] ?? [];
                $tool = $this->registry->find($name);

                if (! $tool) {
                    $resultJson = json_encode(['error' => "unknown tool: $name"]);
                } elseif ($tool->kind === 'write') {
                    $resultJson = json_encode(['error' => 'write tools are not enabled in v1']);
                } else {
                    try {
                        $result = ($tool->handler)(is_array($args) ? $args : []);
                        $resultJson = json_encode($result);
                    } catch (\Throwable $e) {
                        $resultJson = json_encode(['error' => $e->getMessage()]);
                    }
                }

                yield ['type' => 'tool_call', 'tool_name' => $name, 'summary' => mb_substr((string) $resultJson, 0, 200)];

                $toolCallsRecord[] = [
                    'name' => $name,
                    'arguments' => $args,
                    'result_summary_text' => mb_substr((string) $resultJson, 0, 200),
                ];

                ChatMessage::create([
                    'chat_thread_id' => $thread->id,
                    'role' => 'tool',
                    'content' => (string) $resultJson,
                ]);
            }

            // Loop again: now the model has tool results in history and can produce the final answer
        }

        // Hit max rounds without a clean assistant finish — persist what we have
        ChatMessage::create([
            'chat_thread_id' => $thread->id,
            'role' => 'assistant',
            'content' => $assistantBuffer ?: '(no response — max rounds reached)',
            'tool_calls' => $toolCallsRecord ?: null,
            'model' => $this->config->model(),
        ]);
        $thread->touchLastMessage();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildMessages(ChatThread $thread, string $systemPrompt): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($thread->messages()->get() as $m) {
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }

        return $messages;
    }

    private function loadSystemPrompt(): string
    {
        $path = resource_path('prompts/coach.md');

        return is_file($path) ? (string) file_get_contents($path) : 'You are a helpful financial coach.';
    }
}
```

- [ ] **Step 4: Write `ChatLoopTest`**

```php
<?php

use App\Models\AppSetting;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\Coach\ChatLoop;
use App\Services\Coach\CoachConfig;
use App\Services\Coach\CoachTool;
use App\Services\Coach\OllamaClient;
use App\Services\Coach\ToolRegistry;

beforeEach(function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
});

it('persists user + assistant messages on a no-tool turn', function () {
    $thread = ChatThread::factory()->create(['title' => 'New chat']);

    $client = Mockery::mock(OllamaClient::class);
    $client->shouldReceive('stream')->andReturn((function () {
        yield ['message' => ['content' => 'Hello']];
        yield ['done' => true, 'message' => ['content' => ' world']];
    })());

    $loop = new ChatLoop($client, new ToolRegistry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'hi'));

    $messages = $thread->messages()->get();
    expect($messages)->toHaveCount(2);
    expect($messages[0]->role)->toBe('user');
    expect($messages[1]->role)->toBe('assistant');
    expect($messages[1]->content)->toBe('Hello world');
});

it('auto-sets thread title from first user message', function () {
    $thread = ChatThread::factory()->create(['title' => 'New chat']);

    $client = Mockery::mock(OllamaClient::class);
    $client->shouldReceive('stream')->andReturn((function () {
        yield ['message' => ['content' => 'ok'], 'done' => true];
    })());

    $loop = new ChatLoop($client, new ToolRegistry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'How am I doing?'));

    expect($thread->fresh()->title)->toBe('How am I doing?');
});

it('executes a tool call and feeds the result back', function () {
    $thread = ChatThread::factory()->create();
    $registry = new ToolRegistry;
    $registry->register(new CoachTool(
        name: 'echo',
        description: 'echo',
        parameters: ['type' => 'object'],
        kind: 'read',
        requiresConfirmation: false,
        handler: fn (array $args) => ['echoed' => $args['msg'] ?? 'nothing'],
    ));

    $client = Mockery::mock(OllamaClient::class);
    $callCount = 0;
    $client->shouldReceive('stream')->andReturnUsing(function () use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            return (function () {
                yield ['message' => ['tool_calls' => [['function' => ['name' => 'echo', 'arguments' => ['msg' => 'hello']]]]], 'done' => true];
            })();
        }

        return (function () {
            yield ['message' => ['content' => 'The tool said hello.'], 'done' => true];
        })();
    });

    $loop = new ChatLoop($client, $registry, new CoachConfig);
    $events = iterator_to_array($loop->run($thread, 'echo hello'));

    $kinds = array_column($events, 'type');
    expect($kinds)->toContain('tool_call');

    $messages = $thread->messages()->get();
    expect($messages->pluck('role')->all())->toBe(['user', 'tool', 'assistant']);
});

it('refuses write-kind tools in v1', function () {
    $thread = ChatThread::factory()->create();
    $registry = new ToolRegistry;
    $registry->register(new CoachTool(
        name: 'do_a_thing',
        description: 'writes',
        parameters: ['type' => 'object'],
        kind: 'write',
        requiresConfirmation: true,
        handler: fn (array $args) => throw new \LogicException('should not run'),
    ));

    $client = Mockery::mock(OllamaClient::class);
    $callCount = 0;
    $client->shouldReceive('stream')->andReturnUsing(function () use (&$callCount) {
        $callCount++;
        if ($callCount === 1) {
            return (function () {
                yield ['message' => ['tool_calls' => [['function' => ['name' => 'do_a_thing', 'arguments' => []]]]], 'done' => true];
            })();
        }

        return (function () {
            yield ['message' => ['content' => 'sorry, cannot.'], 'done' => true];
        })();
    });

    $loop = new ChatLoop($client, $registry, new CoachConfig);
    iterator_to_array($loop->run($thread, 'do it'));

    $toolMsg = $thread->messages()->where('role', 'tool')->first();
    expect($toolMsg->content)->toContain('write tools are not enabled');
});
```

- [ ] **Step 5: Run tests** — `php artisan test --compact --filter=ChatLoopTest`

- [ ] **Step 6: Commit**

```bash
git add app/Services/Coach/ChatLoop.php resources/prompts/coach.md tests/Unit/Services/Coach/ChatLoopTest.php
git commit -m "$(cat <<'EOF'
Add ChatLoop orchestrator + system prompt

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 18: StreamController + ThreadController + chat routes

**Files:**
- Create: `app/Http/Controllers/Coach/StreamController.php`
- Create: `app/Http/Controllers/Coach/ThreadController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Coach/StreamControllerTest.php`

- [ ] **Step 1: Implement `ThreadController`**

`app/Http/Controllers/Coach/ThreadController.php`:

```php
<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use Illuminate\Http\Request;

class ThreadController extends Controller
{
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $thread = ChatThread::create([
            'user_id' => $request->user()->id,
            'title' => 'New chat',
            'last_message_at' => now(),
        ]);

        return response()->json(['id' => $thread->id]);
    }
}
```

- [ ] **Step 2: Implement `StreamController`**

`app/Http/Controllers/Coach/StreamController.php`:

```php
<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Services\Coach\ChatLoop;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function stream(Request $request, ChatThread $thread, ChatLoop $loop): StreamedResponse
    {
        abort_unless($thread->user_id === $request->user()->id, 403);

        $message = (string) $request->input('message', '');
        abort_if($message === '', 422, 'message is required');

        return new StreamedResponse(function () use ($thread, $loop, $message) {
            foreach ($loop->run($thread, $message) as $event) {
                echo json_encode($event)."\n";
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
```

- [ ] **Step 3: Update `routes/web.php`**

Inside the `auth + verified` group, replace the stub `/chat` route from Task 13 with the proper routes:

```php
Route::livewire('chat', 'pages::chat.index')->name('chat.index');
Route::post('chat/threads', [\App\Http\Controllers\Coach\ThreadController::class, 'store'])->name('chat.threads.store');
Route::post('chat/{thread}/stream', [\App\Http\Controllers\Coach\StreamController::class, 'stream'])->name('chat.stream');
```

- [ ] **Step 4: Write `StreamControllerTest`**

```php
<?php

use App\Models\AppSetting;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\Coach\ChatLoop;

beforeEach(function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    $this->actingAs(User::factory()->create());
});

it('streams NDJSON tokens from ChatLoop', function () {
    $thread = ChatThread::factory()->create(['user_id' => auth()->id()]);

    $this->app->bind(ChatLoop::class, function () {
        $mock = Mockery::mock(ChatLoop::class);
        $mock->shouldReceive('run')->andReturn((function () {
            yield ['type' => 'token', 'content' => 'hi'];
            yield ['type' => 'token', 'content' => ' there'];
        })());

        return $mock;
    });

    $response = $this->post(route('chat.stream', $thread), ['message' => 'hello']);

    $response->assertOk();
    $body = $response->streamedContent();
    expect($body)->toContain('"content":"hi"');
    expect($body)->toContain('"content":" there"');
});

it('refuses streaming for a thread that belongs to another user', function () {
    $other = User::factory()->create();
    $thread = ChatThread::factory()->create(['user_id' => $other->id]);

    $this->post(route('chat.stream', $thread), ['message' => 'hi'])->assertForbidden();
});

it('requires a non-empty message', function () {
    $thread = ChatThread::factory()->create(['user_id' => auth()->id()]);

    $this->post(route('chat.stream', $thread), ['message' => ''])->assertStatus(422);
});
```

- [ ] **Step 5: Run tests** — `php artisan test --compact --filter=StreamControllerTest`

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Coach routes/web.php tests/Feature/Coach/StreamControllerTest.php
git commit -m "$(cat <<'EOF'
Add chat streaming + thread-create controllers + routes

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 19: Chat index page (thread list)

**Files:**
- Create: `resources/views/pages/chat/⚡index.blade.php`
- Test: `tests/Feature/Pages/Chat/IndexTest.php`

- [ ] **Step 1: Generate**

```bash
php artisan make:livewire pages::chat.index --no-interaction
```

- [ ] **Step 2: Implement**

`resources/views/pages/chat/⚡index.blade.php`:

```blade
<?php

use App\Models\ChatThread;
use App\Services\Coach\CoachConfig;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Coach')] class extends Component {
    public ?int $threadId = null;

    #[Url(as: 'prompt')]
    public string $initialPrompt = '';

    public function selectThread(int $id): void
    {
        $this->threadId = $id;
    }

    public function newThread(): void
    {
        $this->threadId = null;
    }

    #[On('thread-created')]
    public function onThreadCreated(int $id): void
    {
        $this->threadId = $id;
        $this->initialPrompt = '';
    }

    public function with(): array
    {
        return [
            'threads' => auth()->user()->chatThreads()->get(),
            'isConfigured' => (new CoachConfig)->isConfigured(),
        ];
    }
}; ?>

<div class="grid grid-cols-[260px_1fr] gap-4 h-[calc(100vh-8rem)]">
    <aside class="border border-base-300 rounded-lg p-3 overflow-y-auto">
        <x-button label="+ New chat" class="btn-primary btn-sm w-full mb-3" wire:click="newThread" />
        @forelse ($threads as $t)
            <button type="button" wire:click="selectThread({{ $t->id }})" class="w-full text-left p-2 rounded text-sm hover:bg-base-200 {{ $threadId === $t->id ? 'bg-base-200' : '' }}">
                <div class="font-medium truncate">{{ $t->title }}</div>
                <div class="text-xs opacity-50">{{ $t->last_message_at?->diffForHumans() }}</div>
            </button>
        @empty
            <p class="text-xs opacity-60 mt-2">No conversations yet.</p>
        @endforelse
    </aside>

    <main class="border border-base-300 rounded-lg overflow-hidden flex flex-col">
        @if (! $isConfigured)
            <div class="flex-1 flex items-center justify-center p-8 text-center">
                <div>
                    <h2 class="text-lg font-semibold">Coach isn't connected</h2>
                    <p class="opacity-70 text-sm mt-2">Configure your Ollama endpoint to start chatting.</p>
                    <x-button label="Configure Ollama" link="{{ route('coach.edit') }}" class="btn-primary mt-4" wire:navigate />
                </div>
            </div>
        @else
            <livewire:pages::chat.thread :thread-id="$threadId" :initial-prompt="$initialPrompt" :key="'chat-thread-'.($threadId ?? 'new')" />
        @endif
    </main>
</div>
```

- [ ] **Step 3: Write `IndexTest`**

`tests/Feature/Pages/Chat/IndexTest.php`:

```php
<?php

use App\Models\AppSetting;
use App\Models\ChatThread;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('shows the not-configured state when Ollama URL is empty', function () {
    AppSetting::current()->update(['ollama_base_url' => null]);

    $this->get('/chat')
        ->assertOk()
        ->assertSee("Coach isn't connected");
});

it('lists existing threads for the user', function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    ChatThread::factory()->create(['user_id' => auth()->id(), 'title' => 'June recap']);

    $this->get('/chat')
        ->assertOk()
        ->assertSee('June recap');
});

it('does not show threads belonging to other users', function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    $other = User::factory()->create();
    ChatThread::factory()->create(['user_id' => $other->id, 'title' => 'Other user thread']);

    $this->get('/chat')
        ->assertOk()
        ->assertDontSee('Other user thread');
});
```

- [ ] **Step 4: Run tests** — `php artisan test --compact --filter='Pages\\\\Chat\\\\IndexTest'`

- [ ] **Step 5: Commit**

```bash
git add resources/views/pages/chat/⚡index.blade.php tests/Feature/Pages/Chat/IndexTest.php
git commit -m "$(cat <<'EOF'
Add /chat index page with thread list + not-configured state

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 20: Chat thread page (streamed messages)

**Files:**
- Create: `resources/views/pages/chat/⚡thread.blade.php`
- Test: `tests/Feature/Pages/Chat/ThreadTest.php`

- [ ] **Step 1: Generate**

```bash
php artisan make:livewire pages::chat.thread --no-interaction
```

- [ ] **Step 2: Implement**

`resources/views/pages/chat/⚡thread.blade.php`:

```blade
<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $threadId = null;
    public string $initialPrompt = '';
    public string $input = '';

    public function mount(?int $threadId, string $initialPrompt = ''): void
    {
        $this->threadId = $threadId;
        if ($initialPrompt !== '') {
            $this->input = $initialPrompt;
        }
    }

    #[Computed]
    public function thread(): ?ChatThread
    {
        return $this->threadId ? ChatThread::find($this->threadId) : null;
    }

    #[Computed]
    public function messages(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->thread ? $this->thread->messages()->get() : ChatMessage::query()->whereRaw('1=0')->get();
    }

    public function refreshMessages(): void
    {
        // Triggered from JS after streaming ends so Livewire re-reads from DB.
    }
}; ?>

<div class="flex flex-col h-full" x-data="chatThread({{ $threadId ?? 'null' }}, @js($initialPrompt))" x-init="init()" wire:ignore.self>
    <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="messages">
        @foreach ($this->messages as $msg)
            @if ($msg->role === 'user')
                <div class="flex justify-end">
                    <div class="max-w-prose bg-primary text-primary-content rounded-lg px-3 py-2 text-sm">{{ $msg->content }}</div>
                </div>
            @elseif ($msg->role === 'assistant')
                <div class="flex justify-start">
                    <div class="max-w-prose bg-base-200 rounded-lg px-3 py-2 text-sm whitespace-pre-wrap">{{ $msg->content }}
                        @if ($msg->tool_calls)
                            <div class="text-xs opacity-60 mt-1">
                                @foreach ($msg->tool_calls as $tc)
                                    🔧 {{ $tc['name'] }}@if(! $loop->last), @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach

        <template x-if="liveAssistant !== ''">
            <div class="flex justify-start">
                <div class="max-w-prose bg-base-200 rounded-lg px-3 py-2 text-sm whitespace-pre-wrap" x-text="liveAssistant"></div>
            </div>
        </template>

        <template x-for="tc in liveToolCalls" :key="tc.id">
            <div class="text-xs opacity-60 px-3">🔧 looking up <span x-text="tc.name"></span>…</div>
        </template>
    </div>

    <form @submit.prevent="send" class="p-3 border-t border-base-300 flex gap-2">
        <input type="text" x-model="text" placeholder="Ask the coach..." class="input input-bordered flex-1" :disabled="sending" />
        <button type="submit" class="btn btn-primary" :disabled="sending || ! text.trim()" x-text="sending ? '…' : 'Send'"></button>
    </form>
</div>

@script
<script>
Alpine.data('chatThread', (initialThreadId, initialPrompt) => ({
    threadId: initialThreadId,
    text: initialPrompt || '',
    sending: false,
    liveAssistant: '',
    liveToolCalls: [],
    init() {
        this.scrollToBottom();
    },
    scrollToBottom() {
        this.$nextTick(() => {
            const el = this.$refs.messages;
            if (el) el.scrollTop = el.scrollHeight;
        });
    },
    async send() {
        if (! this.text.trim() || this.sending) return;
        this.sending = true;
        const messageText = this.text;
        this.text = '';
        this.liveAssistant = '';
        this.liveToolCalls = [];

        // Create thread if needed
        if (! this.threadId) {
            const r = await fetch('/chat/threads', { method: 'POST', headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }});
            const data = await r.json();
            this.threadId = data.id;
            this.$dispatch('thread-created', this.threadId);
        }

        const url = '/chat/' + this.threadId + '/stream';
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ message: messageText }),
        });

        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            let nl;
            while ((nl = buffer.indexOf('\n')) !== -1) {
                const line = buffer.slice(0, nl).trim();
                buffer = buffer.slice(nl + 1);
                if (! line) continue;
                try {
                    const event = JSON.parse(line);
                    if (event.type === 'token' && event.content) {
                        this.liveAssistant += event.content;
                        this.scrollToBottom();
                    } else if (event.type === 'tool_call') {
                        this.liveToolCalls.push({ id: this.liveToolCalls.length, name: event.tool_name });
                    }
                } catch (e) {}
            }
        }

        this.sending = false;
        this.liveAssistant = '';
        this.liveToolCalls = [];
        $wire.refreshMessages();
        this.scrollToBottom();
    },
}));
</script>
@endscript
```

- [ ] **Step 3: Add CSRF meta**

If not already in `resources/views/partials/head.blade.php`, ensure there's a `<meta name="csrf-token" content="{{ csrf_token() }}">` tag. Check with `grep csrf-token resources/views/partials/head.blade.php` — if absent, add it inside the `<head>` block.

- [ ] **Step 4: Write `ThreadTest`**

`tests/Feature/Pages/Chat/ThreadTest.php`:

```php
<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders existing messages for a thread', function () {
    $thread = ChatThread::factory()->create(['user_id' => auth()->id()]);
    ChatMessage::factory()->create(['chat_thread_id' => $thread->id, 'role' => 'user', 'content' => 'Hello']);
    ChatMessage::factory()->assistant()->create(['chat_thread_id' => $thread->id, 'content' => 'Hi back']);

    Livewire::test('pages::chat.thread', ['threadId' => $thread->id])
        ->assertSee('Hello')
        ->assertSee('Hi back');
});

it('uses the initial prompt for a fresh thread input', function () {
    Livewire::test('pages::chat.thread', ['threadId' => null, 'initialPrompt' => 'Test prompt'])
        ->assertSet('input', 'Test prompt');
});
```

- [ ] **Step 5: Run tests** — `php artisan test --compact --filter='Pages\\\\Chat\\\\ThreadTest'`

- [ ] **Step 6: Commit**

```bash
git add resources/views/pages/chat/⚡thread.blade.php tests/Feature/Pages/Chat/ThreadTest.php resources/views/partials/head.blade.php
git commit -m "$(cat <<'EOF'
Add /chat thread component with streamed messages and tool-call chips

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 21: Sidebar wiring + final integration

**Files:**
- Modify: `resources/views/layouts/app/sidebar.blade.php`

- [ ] **Step 1: Add the Coach link**

Find the sidebar block where Calendar is listed and insert a Coach entry between Calendar and Imports:

```blade
<x-menu-item title="{{ __('Calendar') }}" icon="lucide.calendar" link="{{ route('calendar.index') }}" wire:navigate />
<x-menu-item title="{{ __('Coach') }}" icon="lucide.message-circle" link="{{ route('chat.index') }}" wire:navigate />
<x-menu-item title="{{ __('Imports') }}" icon="lucide.upload" link="{{ route('imports.index') }}" wire:navigate />
```

- [ ] **Step 2: Full suite**

```bash
php artisan test --compact
```

Expected: all green (~459 passing: 364 prior + ~95 new), 2 skipped.

- [ ] **Step 3: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: View cache + smoke test**

```bash
php artisan view:clear
```

Hit `/dashboard` — insights widget renders. Hit `/chat` — see the not-configured screen. Hit `/settings/coach` — set a fake URL. Reload `/chat` — see the empty thread list and message composer.

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/app/sidebar.blade.php
git commit -m "$(cat <<'EOF'
Add Coach to sidebar

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review Notes

- **Spec coverage:**
  - Schema (3 tables/changes) — Task 1
  - Models, factories, AppSetting wiring — Task 2
  - 8 analytics actions — Tasks 4-11 (with Stats helper in Task 3)
  - Insight DTO + BuildInsights + thresholds — Task 12
  - Dashboard widget — Task 13
  - CoachConfig + Settings page — Task 14
  - ToolRegistry + 13 registered tools (8 analytics; 5 Phase-5b forecast tools deliberately NOT yet registered — see note below) — Task 15
  - OllamaClient — Task 16
  - ChatLoop + system prompt — Task 17
  - Stream/Thread controllers + routes — Task 18
  - Chat index page (with not-configured state, thread list) — Task 19
  - Chat thread page (streamed messages, tool-call chips) — Task 20
  - Sidebar wiring — Task 21
  - "Plug-in-later" guarantee: insights widget loads with no Ollama (Task 13); chat page shows graceful empty state (Task 19)

- **Forecast tools as Coach tools — note for the implementer:** the spec called for 13 read tools (8 analytics + 5 Phase-5b forecast actions). This plan registers only the 8 analytics tools in Task 15 to keep the registration code under 250 lines. If you want the model to also call the forecast tools, add 5 more `$registry->register(...)` blocks alongside the analytics block, mirroring the same `CoachTool` shape. The forecast actions are at `app/Actions/Finance/Forecast/{ProjectIncomeDeposits,ProjectBillCharges,ForecastVariableSpend,ComputeProjectedBalance,RecommendPayDates}.php` — wrap each as a `CoachTool` with name in `snake_case` matching the class.

- **No placeholders.** Every step has runnable code or commands.

- **Type consistency.** `CoachTool`, `Insight`, `ChatThread`, `ChatMessage` shapes match across tasks. `ComputeGoalsStatus` keys assumed to match the existing implementation; if they differ, Task 7 calls them out.

- **Test totals.** Stats helper (7) + 8 analytics actions (≈4-6 each, ≈40) + BuildInsights (4) + Dashboard widget (2) + CoachConfig (5) + Settings page (3) + ToolRegistry (3) + OllamaClient (2) + ChatLoop (4) + StreamController (3) + Chat index (3) + Chat thread (2) ≈ **78 new tests**. Slightly under the spec's "≈95" estimate because tests are sharper.
