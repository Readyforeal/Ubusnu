# Ollama Coach Implementation Design

**Phase:** 6 (after Phase 5b — pay-timing optimizer)

**Goal:** Ship a self-hosted financial coach that uses local Ollama to answer freeform questions about the user's finances, and surface deterministic insights proactively on the dashboard. Architected so the analytics and insights work without Ollama connected — Ollama is a plug-in endpoint.

## Foundational Decisions

| Decision | Choice |
|---|---|
| Integration transport | **HTTP + Ollama native tool-calling.** Laravel hits a configurable Ollama URL, registers our actions as tools using Ollama's function-calling format. Ollama picks tools, Laravel executes them locally. |
| Streaming | **Yes.** Ollama's streaming endpoint; chat UI types tokens as they arrive via NDJSON over a Laravel route. |
| Chat history | **Persisted per-user.** New `chat_threads` + `chat_messages` tables. Multi-thread sidebar. |
| Insights placement | **Dashboard widget** — clicking an insight opens a new chat thread with the suggested prompt pre-filled. |
| Capabilities (v1) | **Read-only.** Architected for write tools later via a `kind` flag on every registered tool; v1 only registers read tools. |
| Configuration | **Settings page** at `/settings/coach` — Ollama URL + model name, stored on `app_settings`. Test-connection button. |
| Analytics ship list | **All 8** (top movers, anomalies, budget variance, goal pace, savings rate trend, recurring subs, spending velocity, fixed-vs-variable ratio). |
| Plug-in-later behavior | When Ollama URL is empty/unreachable: chat page shows config-required state; insights widget still works (analytics actions are pure DB reads). |

## Architecture

Four layers, cleanly separated. Three of four work with no Ollama configured.

1. **Analytics actions** (`app/Actions/Finance/Analytics/`) — 8 pure-function actions. No DB writes. No Ollama dependency.
2. **Insights builder** (`app/Actions/Coach/BuildInsights`) — composes analytics actions into a ranked `Insight[]` list. Pure.
3. **Coach service** (`app/Services/Coach/`) — Ollama HTTP client, tool registry, chat loop. Read tools only in v1; write-tool seam is built.
4. **Chat UI** (`resources/views/pages/chat/`) — `/chat` page. Streamed messages. Per-thread state. Settings page at `/settings/coach`.

## Data Model

### New table `chat_threads`

| column | type | notes |
|---|---|---|
| `id` | bigInt PK | |
| `user_id` | FK → users, cascadeOnDelete | |
| `title` | string(120) | auto-set from first user message; user can rename |
| `pinned_at` | timestamp nullable | reserved for future pinning |
| `last_message_at` | timestamp nullable | for sorting sidebar |
| `created_at` / `updated_at` | timestamps | |

### New table `chat_messages`

| column | type | notes |
|---|---|---|
| `id` | bigInt PK | |
| `chat_thread_id` | FK → chat_threads, cascadeOnDelete | |
| `role` | string(16) | `user` / `assistant` / `tool` |
| `content` | longText | message body, or tool result JSON for `role=tool` |
| `tool_calls` | json nullable | for assistant messages that invoked tools — array of `{name, arguments, result_summary_text}` where `result_summary_text` is a short truncated rendering of the tool result (full result stays on the corresponding `role=tool` message) |
| `model` | string(64) nullable | which Ollama model produced this assistant message |
| `created_at` | timestamp | |

### New columns on `app_settings`

- `ollama_base_url` string(255) nullable
- `ollama_model` string(64) nullable

No write defaults — null = not configured.

## Analytics Actions

All 8 live under `app/Actions/Finance/Analytics/`. Each is a pure invokable class with one `__invoke()` and a documented array-shape return.

| Action | Signature | Returns |
|---|---|---|
| `TopMovers` | `(int $monthsBack = 1, int $limit = 5)` | array of `{category_id, name, current_cents, previous_cents, delta_pct, direction}` |
| `DetectAnomalies` | `(int $lookbackDays = 90, float $stdDevThreshold = 2.0)` | array of `{transaction_id, description, amount_cents, category_id, category_median_cents, std_devs_from_median}` |
| `BudgetVariance` | `()` | array of `{bucket_id, name, planned_cents, actual_cents, variance_pct, days_remaining_in_period}` |
| `GoalPaceForecast` | `()` | array of `{goal_id, name, target_cents, current_cents, monthly_pace_cents, projected_hit_date, target_date, on_track, months_off}` |
| `SavingsRateTrend` | `(int $monthsBack = 12)` | array of `{month, income_cents, spend_cents, savings_rate_pct}` |
| `DetectRecurringSubscriptions` | `()` | array of `{merchant_pattern, occurrence_count, monthly_avg_cents, last_seen_on, already_tracked_as_bill_id}` |
| `SpendingVelocity` | `()` | `{this_month_cents_so_far, last_month_cents_through_same_day, delta_pct, projected_full_month_cents}` |
| `FixedVariableRatio` | `(int $monthsBack = 6)` | array of `{month, fixed_cents, variable_cents, fixed_ratio_pct}` |

**Shared rules:**
- All money in cents (signed `int`).
- All time inputs are explicit `CarbonImmutable` or `int` — no implicit "now" inside an action.
- New helper `app/Support/Stats.php` for `median()`, `mean()`, `stdDev()` to avoid duplication.
- Categories referenced by any row in `bills.category_id` are excluded from spending averages where they'd double-count (same rule as `ForecastVariableSpend`).

**Tests:** one Pest unit-test file per action under `tests/Unit/Actions/Finance/Analytics/`, 5-8 tests each. ≈50 new tests.

## Insights Builder + Dashboard Widget

**`app/Actions/Coach/BuildInsights`** — single invokable action that calls each of the 8 analytics actions, applies threshold rules, returns a ranked `Insight[]` list capped at 6.

```php
final class Insight
{
    public function __construct(
        public string $severity,           // 'critical' | 'warning' | 'info' | 'positive'
        public string $headline,
        public string $detail,
        public ?string $suggestedPrompt,   // pre-fills chat input on click; null = no chat
        public string $sourceTool,
        public array $metadata,
    ) {}
}
```

### Trigger thresholds (deterministic — no LLM involvement)

| Action | Trigger → Severity |
|---|---|
| TopMovers | mover ≥ 50% increase → `warning`; ≥ 100% → `critical` |
| DetectAnomalies | anomaly ≥ 3 std-devs → `warning` per anomaly (cap one per category) |
| BudgetVariance | bucket ≥ 90% used with > 25% of period remaining → `warning`; ≥ 100% → `critical` |
| GoalPaceForecast | projected to miss target by ≥ 30 days → `warning`; on track → `positive` |
| SavingsRateTrend | rate dropped > 10 pts MoM → `warning`; rose > 10 pts → `positive` |
| DetectRecurringSubscriptions | every untracked subscription → `info` (suggest tracking as bill) |
| SpendingVelocity | this month ≥ 30% ahead of same-point last → `warning` |
| FixedVariableRatio | fixed ratio rose ≥ 5 pts in 3 months → `info` |

Ranking: `critical > warning > info > positive`, then by recency. Cap at top 6.

### Dashboard widget

New SFC at `resources/views/pages/dashboard/⚡insights.blade.php`, included from the existing dashboard layout. Each insight rendered as a clickable card. Click → `route('chat.index', ['prompt' => $insight->suggestedPrompt])`.

**Tests:** `BuildInsightsTest` ≈ 8 tests (one per analytics trigger, plus ranking + cap-at-6). Dashboard widget test asserts insights render and the chat link wires up.

## Coach Service

Three focused classes under `app/Services/Coach/`.

### `CoachConfig`

Reads `ollama_base_url` / `ollama_model` from `AppSetting::current()`. Methods:
- `isConfigured(): bool` — true if base URL is non-empty
- `baseUrl(): ?string`
- `model(): string` — defaults to `'llama3.1:8b'` if not set

### `ToolRegistry`

Singleton registry that exposes our actions to Ollama. Each registered tool is a `CoachTool`:

```php
final class CoachTool
{
    public function __construct(
        public string $name,                  // e.g. "top_movers"
        public string $description,
        public array  $parameters,            // JSON Schema for Ollama's tool format
        public string $kind,                  // 'read' | 'write'
        public bool   $requiresConfirmation,  // for future writes; always false in v1
        public Closure $handler,              // closure that runs the action with validated args
    ) {}
}
```

`ToolRegistry::register(CoachTool)`, `all()`, `find(string $name)`. Bootstrapped in a new `CoachServiceProvider` that wires up all 8 analytics tools + the 5 Phase-5b forecast tools (13 read tools total in v1).

Example registration:

```php
$registry->register(new CoachTool(
    name: 'top_movers',
    description: 'Categories with the biggest month-over-month spending change.',
    parameters: ['type' => 'object', 'properties' => [
        'months_back' => ['type' => 'integer', 'default' => 1],
        'limit' => ['type' => 'integer', 'default' => 5],
    ]],
    kind: 'read',
    requiresConfirmation: false,
    handler: fn (array $args) => (new TopMovers)(
        monthsBack: $args['months_back'] ?? 1,
        limit: $args['limit'] ?? 5,
    ),
));
```

### `OllamaClient`

Thin HTTP wrapper around Ollama's `/api/chat` endpoint. Methods:

- `stream(array $messages, array $tools): \Generator` — yields token chunks and tool-call requests as they arrive (Ollama's NDJSON streaming format)
- `dryRun(array $messages, array $tools): array` — non-streaming helper for tests
- Reads `CoachConfig` for URL/model. Throws `CoachNotConfiguredException` if no URL.

### `ChatLoop`

Orchestrates multi-turn conversation:

```
loop:
    1. send (system_prompt + thread_history + new_user_message) + registered_tools
       to OllamaClient->stream()
    2. yield assistant tokens to caller as they arrive
    3. if model produces a tool_call:
         a. find tool in registry (404 → return error result to model)
         b. validate arguments against tool's JSON schema
         c. for write tools: refuse in v1 (kind === 'write' → error result)
         d. execute handler with arguments
         e. append role=tool message with result JSON to thread
         f. continue loop
    4. else: persist final assistant message and exit
```

### System prompt

Lives in `resources/prompts/coach.md`. Loaded by `ChatLoop` on each request. Contents:

- Persona: "careful, numbers-first financial coach"
- Tone: "never claim certainty about a number you didn't see in a tool result"
- Tool guarantee: "if you need a number, call a tool"
- Under 300 tokens.

### Tests

- `CoachConfigTest` — happy paths + null when not configured
- `ToolRegistryTest` — registration, lookup, JSON-schema shape
- `OllamaClientTest` — `Http::fake()` to mock streaming responses; assert request body, tool-call parsing
- `ChatLoopTest` — end-to-end with a fake `OllamaClient`; persistence, tool execution, multi-round loops, error handling, write-tool refusal

≈25 tests.

## Chat UI

Single full-page Livewire SFC at `/chat` with one embedded child component.

### Files

- `resources/views/pages/chat/⚡index.blade.php` — main page (thread list sidebar + active conversation slot)
- `resources/views/pages/chat/⚡thread.blade.php` — child that owns the message stream for one thread
- `resources/views/pages/settings/⚡coach.blade.php` — settings page for Ollama URL + model
- `resources/prompts/coach.md` — system prompt
- `app/Http/Controllers/Coach/StreamController.php` — streaming endpoint returning NDJSON
- `app/Http/Controllers/Coach/ThreadController.php` — POST endpoint that creates a thread + the first user message in one call (used by the "+ New chat" flow)

### Routes

```php
Route::livewire('chat', 'pages::chat.index')->name('chat.index');
Route::livewire('settings/coach', 'pages::settings.coach')->name('settings.coach');
Route::get('chat/{thread}/stream', [Coach\StreamController::class, 'stream'])->name('chat.stream');
Route::post('chat/threads', [Coach\ThreadController::class, 'store'])->name('chat.threads.store'); // create thread + first message
```

### Behavior

- **Thread list** (left rail) — sorted by `last_message_at` desc. "+ New chat" button creates a thread when the user sends their first message.
- **Streaming** — when the user sends a message, the active `<livewire:pages::chat.thread>` opens a `fetch` stream to `/chat/{thread}/stream`. Alpine reads NDJSON chunks and appends to a `pendingAssistant` reactive property. Tool calls render inline as `🔧 looking up <tool_name>…` chips during streaming and finalize to a small summary line in the persisted message.
- **Not-configured state** — when `CoachConfig::isConfigured()` is false, the chat view replaces the message area with a `<x-empty>` block: "Coach isn't connected. [Configure Ollama →](/settings/coach)". Insights widget on the dashboard remains functional.
- **URL pre-fill** — `/chat?prompt=...` (from insight cards) creates a new thread, pre-fills the input box, but does NOT auto-send. User reviews then hits enter.
- **Sidebar entry** — add `<x-menu-item title="Coach" icon="lucide.message-circle" link="{{ route('chat.index') }}" wire:navigate />` between Calendar and Imports.

### Settings page

`/settings/coach` — two inputs (URL, model), a "Test connection" button that hits the configured URL and reports OK/error, and a save button. Stored directly via `AppSetting::current()->update([...])` — there's no existing settings-action class to extend, and adding one for two fields is YAGNI. Validation handled at the Livewire component level (URL format, non-empty model name).

### Tests

- `Pages/Chat/IndexTest.php` — renders thread list, not-configured state, switches threads
- `Pages/Chat/ThreadTest.php` — sends a user message, asserts thread + user message rows created (uses faked `OllamaClient` returning canned tokens)
- `Pages/Settings/CoachTest.php` — saves URL/model, test-connection button success/failure
- `Coach/StreamControllerTest.php` — feature test on the streaming endpoint with mocked Ollama

≈12 tests.

## Total Test Count

- Analytics (8 actions × ~6 tests): **≈50**
- BuildInsights + dashboard widget: **≈10**
- Coach service: **≈25**
- Chat UI + settings: **≈12**

**≈97 new tests** for Phase 6.

## Out of Scope (deferred)

- Write tools (any tool with `kind: 'write'` — refused in v1)
- Cross-thread memory or user-level preferences ("remember I get paid on the 15th")
- Conversation export, search, archive
- Multi-model support per thread (one model per request from `CoachConfig`)
- Token usage / cost tracking
- Insight dismissal or per-user thresholds
- Branched threads, message editing, message regeneration
- Sharing threads between users (this is a single-user app)
