<?php

use App\Actions\Coach\SummarizeCoachUsage;
use App\Models\AppSetting;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Coach settings')] class extends Component {
    #[Validate('required|in:gemini,anthropic,ollama')]
    public string $provider = 'gemini';

    #[Validate('nullable|string|max:64')]
    public string $coachModel = '';

    #[Validate('nullable|string|max:255')]
    public string $geminiApiKey = '';

    #[Validate('nullable|string|max:255')]
    public string $anthropicApiKey = '';

    #[Validate('nullable|url|max:255')]
    public string $ollamaBaseUrl = '';

    #[Validate('nullable|string|max:64')]
    public string $ollamaModel = '';

    public bool $useTools = false;

    public bool $showWipeBanner = false;

    public function mount(): void
    {
        $setting = AppSetting::current();
        $this->provider = (string) ($setting->coach_provider ?? 'gemini');
        $this->coachModel = (string) ($setting->coach_model ?? '');
        $this->geminiApiKey = (string) ($setting->gemini_api_key ?? '');
        $this->anthropicApiKey = (string) ($setting->anthropic_api_key ?? '');
        $this->ollamaBaseUrl = (string) ($setting->ollama_base_url ?? '');
        $this->ollamaModel = (string) ($setting->ollama_model ?? '');
        $this->useTools = (bool) $setting->coach_use_tools;
    }

    public function save(): void
    {
        $this->validate();
        $previousProvider = (string) AppSetting::current()->coach_provider;

        AppSetting::current()->update([
            'coach_provider' => $this->provider,
            'coach_model' => $this->coachModel ?: null,
            'gemini_api_key' => $this->geminiApiKey ?: null,
            'anthropic_api_key' => $this->anthropicApiKey ?: null,
            'ollama_base_url' => $this->ollamaBaseUrl ?: null,
            'ollama_model' => $this->ollamaModel ?: null,
            'coach_use_tools' => $this->useTools,
        ]);

        if ($previousProvider !== $this->provider) {
            $this->showWipeBanner = true;
        }

        $this->dispatch('coach-saved');
    }

    public function wipeHistory(): void
    {
        \DB::table('chat_messages')->delete();
        \DB::table('chat_threads')->delete();
        $this->showWipeBanner = false;
    }

    public function dismissBanner(): void
    {
        $this->showWipeBanner = false;
    }

    #[Computed]
    public function modelOptions(): array
    {
        return match ($this->provider) {
            'gemini' => [
                ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash (default, cheapest)'],
                ['id' => 'gemini-2.5-pro', 'name' => 'Gemini 2.5 Pro'],
            ],
            'anthropic' => [
                ['id' => 'claude-haiku-4-5-20251001', 'name' => 'Claude Haiku 4.5'],
                ['id' => 'claude-sonnet-4-6', 'name' => 'Claude Sonnet 4.6 (default)'],
                ['id' => 'claude-opus-4-7', 'name' => 'Claude Opus 4.7'],
            ],
            'ollama' => [],
            default => [],
        };
    }

    #[Computed]
    public function usage(): array
    {
        return (new SummarizeCoachUsage(new \App\Actions\Coach\EstimateCost))();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Coach')" :subheading="__('Choose a provider and configure access')">
        <x-form wire:submit="save" class="space-y-4">
            <x-radio label="Provider" :options="[
                ['id' => 'gemini', 'name' => 'Google Gemini'],
                ['id' => 'anthropic', 'name' => 'Anthropic Claude'],
                ['id' => 'ollama', 'name' => 'Ollama (local)'],
            ]" wire:model.live="provider" />

            @if ($provider !== 'ollama')
                <x-select label="Model" :options="$this->modelOptions" option-label="name" option-value="id" wire:model="coachModel" placeholder="(use default)" />
            @endif

            @if ($provider === 'gemini')
                <x-input label="Gemini API key" type="password" wire:model="geminiApiKey" autocomplete="off" hint="Encrypted at rest. Get one at aistudio.google.com." />
            @endif

            @if ($provider === 'anthropic')
                <x-input label="Anthropic API key" type="password" wire:model="anthropicApiKey" autocomplete="off" hint="Encrypted at rest. Get one at console.anthropic.com." />
            @endif

            @if ($provider === 'ollama')
                <x-input label="Ollama base URL" wire:model="ollamaBaseUrl" placeholder="http://homelab.local:11434" />
                <x-input label="Ollama model" wire:model="ollamaModel" placeholder="llama3.1:8b" />
            @endif

            <x-checkbox label="Enable tool calling" wire:model="useTools" hint="When ON, the coach can call analytics tools (top movers, anomalies, budget variance, etc.)." />

            <div class="flex gap-2">
                <x-button label="Save" type="submit" class="btn-primary" />
            </div>
        </x-form>

        @if ($showWipeBanner)
            <div class="alert alert-warning mt-4">
                <span>Switching providers. Wipe existing chat history?</span>
                <div>
                    <x-button label="Yes, wipe" class="btn-error btn-sm" wire:click="wipeHistory" />
                    <x-button label="Keep" class="btn-ghost btn-sm" wire:click="dismissBanner" />
                </div>
            </div>
        @endif

        <x-card class="border border-base-300 mt-6">
            <h2 class="text-sm font-semibold mb-3">{{ __('Usage') }}</h2>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <div class="opacity-60">Today</div>
                    <div class="font-mono">{{ number_format($this->usage['today']['input']) }} in / {{ number_format($this->usage['today']['output']) }} out</div>
                    <div class="text-lg font-mono">${{ number_format($this->usage['today']['cents'] / 100, 2) }}</div>
                </div>
                <div>
                    <div class="opacity-60">Month to date</div>
                    <div class="font-mono">{{ number_format($this->usage['month']['input']) }} in / {{ number_format($this->usage['month']['output']) }} out</div>
                    <div class="text-lg font-mono">${{ number_format($this->usage['month']['cents'] / 100, 2) }}</div>
                </div>
            </div>
        </x-card>
    </x-pages::settings.layout>
</section>
