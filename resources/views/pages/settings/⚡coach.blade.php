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
