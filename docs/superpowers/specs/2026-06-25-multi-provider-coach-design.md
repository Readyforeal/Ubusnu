# Multi-Provider Coach Design

**Date:** 2026-06-25
**Status:** Approved (pending user review of spec)
**Supersedes:** [Ollama Coach](2026-06-23-ollama-coach-design.md) — single-provider architecture

## Goal

Replace the single-provider Ollama coach with a pluggable multi-provider implementation. Default to Google Gemini (paid tier) for cost. Keep Anthropic and the existing local Ollama as switchable alternatives. Track per-message token usage so the user can watch spend in-app.

## Motivation

Local Ollama on the user's current hardware produces low-quality coaching — small models hallucinate tool calls, larger ones don't fit. The user has separately experienced excellent coaching quality through cloud LLMs and wants that level of intelligence without locking into one provider's pricing.

Multi-provider keeps options open: Gemini 2.5 Flash is roughly 5–10× cheaper than Anthropic Sonnet at competitive quality, Anthropic is the user's preferred personality, and Ollama remains the option once the user upgrades their GPU.

## Non-Goals

- Streaming UI changes (typewriter, optimistic rendering, etc. stay as-is)
- New tools or analytics capabilities (this is a transport refactor)
- Free-tier Gemini support (training-data risk — paid tier only)
- Provider auto-fallback (explicit selection only)
- Cost caps or budget alerts (visibility only, no enforcement)

## Architecture

`ChatLoop` becomes provider-agnostic. A new `CoachDriver` interface defines the streaming protocol; three implementations sit behind it.

```
StreamController
      │
      ▼
   ChatLoop ──uses──▶ CoachDriver (interface)
                          ├── GeminiDriver     (default)
                          ├── AnthropicDriver
                          └── OllamaDriver     (refactor of existing OllamaClient)
```

Each driver translates between the provider's wire format and a normalized `StreamChunk` value object that `ChatLoop` consumes. The provider, model, and API keys are read from `app_settings` via `CoachConfig`, which constructs the right driver per request.

`CoachTool`, `ToolRegistry`, `BuildInsights`, the system prompt, and the streaming HTTP controller are untouched. The driver shoulders all protocol differences.

## Components

### `CoachDriver` interface (new)

```php
namespace App\Services\Coach;

interface CoachDriver
{
    public function name(): string;  // 'gemini' | 'anthropic' | 'ollama'

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, CoachTool>  $tools
     * @return \Generator<StreamChunk>
     */
    public function stream(array $messages, array $tools): \Generator;
}
```

### `StreamChunk` (new)

A small value object yielded by drivers. One of five types:

| `type` | `payload` shape | Emitted when |
|---|---|---|
| `text` | `['delta' => 'partial text']` | Model emits a text token / chunk |
| `tool_call` | `['id' => string, 'name' => string, 'arguments' => array]` | Model requests a tool invocation |
| `usage` | `['input_tokens' => int, 'output_tokens' => int]` | Provider reports final usage |
| `done` | `[]` | Round complete |
| `error` | `['message' => string]` | Driver-level error (HTTP, parse, etc.) |

### `GeminiDriver` (new)

- Endpoint: `POST https://generativelanguage.googleapis.com/v1beta/models/{model}:streamGenerateContent?alt=sse&key={key}`
- Streaming format: SSE
- Tool schema translation: `CoachTool` → `function_declarations` entry
- Tool result format: `functionResponse` parts in the next request
- Usage tokens: read from `usageMetadata` on the final SSE event
- Default model: `gemini-2.5-flash`
- HTTP timeout: 300s

### `AnthropicDriver` (new)

- Endpoint: `POST https://api.anthropic.com/v1/messages`
- Headers: `x-api-key: {key}`, `anthropic-version: 2023-06-01`
- Streaming format: SSE with typed events (`message_start`, `content_block_start`, `content_block_delta`, `message_delta`, `message_stop`)
- Tool schema translation: `CoachTool` → entry in `tools` array
- Tool result format: `tool_result` content block in the next request
- Usage tokens: read from `message_delta.usage` events
- Default model: `claude-sonnet-4-6`
- HTTP timeout: 300s

### `OllamaDriver` (refactor of `OllamaClient`)

- Same wire protocol as today (`/api/chat`, NDJSON, `Http::withOptions(['stream' => true])`)
- New responsibility: normalize each parsed NDJSON line into a `StreamChunk` instead of yielding raw arrays
- Tool schema and tool result formats already match Ollama's native shape; existing translation code moves into the driver
- Default model: `llama3.1:8b`
- HTTP timeout: 300s

### `CoachConfig` (modified)

New methods:
- `provider(): string` — returns `'gemini'` | `'anthropic'` | `'ollama'`, default `'gemini'`
- `model(): string` — returns `app_settings.coach_model` if set, otherwise the driver's hardcoded default for the active provider (`gemini-2.5-flash`, `claude-sonnet-4-6`, or `llama3.1:8b`)
- `apiKey(): ?string` — returns the API key column for the active provider (`null` for Ollama)
- `driver(): CoachDriver` — instantiates and returns the right driver

The existing `useTools()` and `isConfigured()` methods stay. `useTools()` continues to gate whether `tools` is included in the request body — applies to all drivers equally (Gemini and Anthropic respect it, Ollama already does).

### `ChatLoop` (modified)

- Constructor takes `CoachDriver` (interface) instead of concrete `OllamaClient`
- `run()` consumes `StreamChunk` objects via a `match` on `$chunk->type`
- On `usage` chunk: stash `input_tokens` / `output_tokens` in a property; write them onto the persisted assistant `ChatMessage` row at end-of-turn alongside `provider` and `model`
- Existing try/catch and partial-content persistence stay
- `convertCentsToDollars()` and `looksLikeToolCallJson()` helpers stay — they're driver-agnostic

### `ChatMessage` model (modified)

New columns:
- `input_tokens` (integer, nullable)
- `output_tokens` (integer, nullable)
- `provider` (string, nullable) — `'gemini'` | `'anthropic'` | `'ollama'`
- `model` (string, nullable) — e.g. `'gemini-2.5-flash'`

All nullable so existing rows survive. Wipe migration zeroes the table anyway.

### `AppSetting` model (modified)

New columns:
- `coach_provider` (string, default `'gemini'`)
- `coach_model` (string, nullable) — when null, driver uses its default
- `gemini_api_key` (text, nullable, encrypted cast)
- `anthropic_api_key` (text, nullable, encrypted cast)

Existing `ollama_base_url`, `ollama_model`, `coach_use_tools` columns stay untouched.

### `EstimateCost` action (new)

Pure function: `(string $provider, string $model, int $inputTokens, int $outputTokens): int` returning cents.

Pricing table lives as a class constant:

```php
private const PRICING = [
    'gemini' => [
        'gemini-2.5-flash' => ['input' => 30, 'output' => 250],   // cents per million tokens
        'gemini-2.5-pro'   => ['input' => 125, 'output' => 1000],
    ],
    'anthropic' => [
        'claude-haiku-4-5-20251001' => ['input' => 100, 'output' => 500],
        'claude-sonnet-4-6'         => ['input' => 300, 'output' => 1500],
        'claude-opus-4-7'           => ['input' => 1500, 'output' => 7500],
    ],
    'ollama' => [
        // free / local — no entry, returns 0
    ],
];
```

Caller computes period totals (today, MTD) by summing `chat_messages.input_tokens` and `output_tokens` grouped by `(provider, model)`, then mapping through `EstimateCost`.

### Settings page (modified)

`resources/views/pages/settings/⚡coach.blade.php` gets:

- Provider radio group: Gemini / Anthropic / Ollama
- Model dropdown — options swap based on selected provider (Livewire reactive)
- One API key input per provider, shown only when that provider is selected
- Existing "Enable tool calling" checkbox stays
- New usage summary block:
  - Today: `{input}` in / `{output}` out = `${cost}`
  - MTD: `{input}` in / `{output}` out = `${cost}`

On save: if the provider changed, show an inline banner — "Switching providers. Wipe existing chat history? [Yes, wipe] [Keep]". `[Yes, wipe]` truncates `chat_threads` and `chat_messages`.

## Data Flow (one chat turn)

1. User submits a message via Livewire → `StreamController` HTTP route
2. Controller asks `CoachConfig::driver()` for the currently-selected driver instance (with API key injected)
3. Controller hands driver + messages + tools to `ChatLoop::run()`
4. `ChatLoop` calls `$driver->stream($messages, $tools)` — gets a generator of `StreamChunk`
5. For each chunk:
   - `text` → append to `$assistantBuffer`, yield to SSE response
   - `tool_call` → look up via `ToolRegistry`, invoke handler, append result message, re-enter `stream()` with extended history
   - `usage` → stash `input_tokens` / `output_tokens` on a `ChatLoop` property
   - `done` → break the round loop
   - `error` → persist `$assistantBuffer . "\n\n_(error: ...)_"`, break
6. End-of-turn: persist assistant `ChatMessage` with `input_tokens`, `output_tokens`, `provider`, `model` populated
7. Settings page running totals are queries over `chat_messages` — no separate ledger table

## Error Handling

Each driver:
- Throws `RuntimeException` on HTTP 4xx/5xx with a useful message including provider name and status
- Catches its own parse errors and yields a `StreamChunk(type: 'error', ...)` rather than throwing mid-stream

`ChatLoop`:
- Existing top-level try/catch wraps the whole streaming loop
- On any exception: persists partial assistant content + `\n\n_(error: ...)_` suffix
- Yields a final SSE `error` event for the frontend

Frontend behavior is unchanged from today — partial content stays visible, error suffix is appended in the persisted message after refresh.

## Testing Strategy

| Test | Layer | Mocks |
|---|---|---|
| `GeminiDriver` smoke test | Driver | `Http::fake()` with canned SSE body — asserts text + tool_call + usage chunks emitted in order |
| `AnthropicDriver` smoke test | Driver | `Http::fake()` with canned SSE event stream — same assertions |
| `OllamaDriver` smoke test | Driver | `Http::fake()` with canned NDJSON — verifies refactor preserved behavior |
| `ChatLoop` logic test | Service | `FakeDriver` (in-memory, returns a scripted chunk sequence) — verifies tool round loop, error handling, token persistence |
| `EstimateCost` test | Action | None — pure function, table-driven cases per provider/model |
| `CoachConfig::driver()` test | Service | DB state — verifies the right driver class is returned per setting |
| Settings page Livewire test | Feature | None — verifies provider toggle changes model dropdown options + saves correctly |

No live API calls in any test. All HTTP mocked.

## Migration Plan

Three migrations in this order:

1. `add_coach_provider_settings_to_app_settings` — adds `coach_provider`, `coach_model`, `gemini_api_key`, `anthropic_api_key`. Defaults `coach_provider` to `'gemini'`.
2. `add_token_usage_to_chat_messages` — adds `input_tokens`, `output_tokens`, `provider`, `model` (all nullable).
3. `wipe_chat_threads_for_provider_switch` — truncates `chat_messages` then `chat_threads`. One-shot, intended to clear the existing Ollama-era debug threads. `down()` is a no-op (can't restore wiped data).

After deployment, future provider switches are handled by the in-app banner described in the Settings page section — the migration only covers the initial wipe.

`down()` for migrations 1 and 2 drops the added columns. The `ollama_*` columns are not touched.

## Out of Scope (future work)

- Provider-specific advanced features (Anthropic prompt caching, Gemini context caching) — flat per-request pricing for now
- Per-user multi-tenancy — single-user app, single set of keys
- API key rotation / expiry handling — manual, edit and save in settings
- Streaming UI polish — already done in earlier polish branches
- Hard cost cap — visibility only this round

## Open Questions

None. All architectural decisions confirmed during brainstorming:
- Multi-provider abstraction with three drivers
- Paid Gemini API as default; Anthropic and Ollama also available
- API keys encrypted in `app_settings`
- Token usage tracked per message, totals shown on settings page
- Existing chat threads wiped on provider switch (confirmed via banner at switch time)
