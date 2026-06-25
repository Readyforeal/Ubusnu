<?php

namespace App\Services\Coach;

use App\Models\AppSetting;
use App\Services\Coach\Drivers\AnthropicDriver;
use App\Services\Coach\Drivers\GeminiDriver;
use App\Services\Coach\Drivers\OllamaDriver;

class CoachConfig
{
    private const DEFAULT_MODELS = [
        'gemini' => 'gemini-2.5-flash',
        'anthropic' => 'claude-sonnet-4-6',
        'ollama' => 'llama3.1:8b',
    ];

    public function provider(): string
    {
        return (string) (AppSetting::current()->coach_provider ?: 'gemini');
    }

    public function model(): string
    {
        $stored = (string) (AppSetting::current()->coach_model ?? '');
        if ($stored !== '') {
            return $stored;
        }

        return self::DEFAULT_MODELS[$this->provider()] ?? 'gemini-2.5-flash';
    }

    public function apiKey(): ?string
    {
        $setting = AppSetting::current();

        return match ($this->provider()) {
            'gemini' => $setting->gemini_api_key,
            'anthropic' => $setting->anthropic_api_key,
            default => null,
        };
    }

    public function ollamaBaseUrl(): ?string
    {
        $url = AppSetting::current()->ollama_base_url;

        return $url ? rtrim((string) $url, '/') : null;
    }

    public function isConfigured(): bool
    {
        return match ($this->provider()) {
            'gemini', 'anthropic' => ! empty($this->apiKey()),
            'ollama' => ! empty($this->ollamaBaseUrl()),
            default => false,
        };
    }

    public function useTools(): bool
    {
        return (bool) AppSetting::current()->coach_use_tools;
    }

    public function driver(): CoachDriver
    {
        return match ($this->provider()) {
            'gemini' => new GeminiDriver($this->apiKey(), $this->model()),
            'anthropic' => new AnthropicDriver($this->apiKey(), $this->model()),
            'ollama' => new OllamaDriver($this->ollamaBaseUrl(), $this->model()),
            default => throw new \RuntimeException('Unknown coach provider: '.$this->provider()),
        };
    }
}
