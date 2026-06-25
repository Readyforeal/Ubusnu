<?php

use App\Models\AppSetting;
use App\Services\Coach\CoachConfig;
use App\Services\Coach\Drivers\AnthropicDriver;
use App\Services\Coach\Drivers\GeminiDriver;
use App\Services\Coach\Drivers\OllamaDriver;

it('defaults provider to gemini', function () {
    expect((new CoachConfig)->provider())->toBe('gemini');
});

it('returns the stored coach_model when set', function () {
    AppSetting::current()->update(['coach_model' => 'gemini-2.5-pro']);

    expect((new CoachConfig)->model())->toBe('gemini-2.5-pro');
});

it('falls back to gemini-2.5-flash when no model is stored', function () {
    AppSetting::current()->update(['coach_provider' => 'gemini', 'coach_model' => null]);

    expect((new CoachConfig)->model())->toBe('gemini-2.5-flash');
});

it('falls back to claude-sonnet-4-6 when provider is anthropic and model is unset', function () {
    AppSetting::current()->update(['coach_provider' => 'anthropic', 'coach_model' => null]);

    expect((new CoachConfig)->model())->toBe('claude-sonnet-4-6');
});

it('returns the right API key per provider', function () {
    AppSetting::current()->update([
        'coach_provider' => 'gemini',
        'gemini_api_key' => 'gem-key',
        'anthropic_api_key' => 'ant-key',
    ]);
    expect((new CoachConfig)->apiKey())->toBe('gem-key');

    AppSetting::current()->update(['coach_provider' => 'anthropic']);
    expect((new CoachConfig)->apiKey())->toBe('ant-key');

    AppSetting::current()->update(['coach_provider' => 'ollama']);
    expect((new CoachConfig)->apiKey())->toBeNull();
});

it('isConfigured: gemini needs an API key', function () {
    AppSetting::current()->update(['coach_provider' => 'gemini', 'gemini_api_key' => null]);
    expect((new CoachConfig)->isConfigured())->toBeFalse();

    AppSetting::current()->update(['gemini_api_key' => 'gem-key']);
    expect((new CoachConfig)->isConfigured())->toBeTrue();
});

it('isConfigured: ollama needs a base URL', function () {
    AppSetting::current()->update(['coach_provider' => 'ollama', 'ollama_base_url' => null]);
    expect((new CoachConfig)->isConfigured())->toBeFalse();

    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    expect((new CoachConfig)->isConfigured())->toBeTrue();
});

it('driver() returns the right concrete class for each provider', function () {
    AppSetting::current()->update(['coach_provider' => 'gemini', 'gemini_api_key' => 'k']);
    expect((new CoachConfig)->driver())->toBeInstanceOf(GeminiDriver::class);

    AppSetting::current()->update(['coach_provider' => 'anthropic', 'anthropic_api_key' => 'k']);
    expect((new CoachConfig)->driver())->toBeInstanceOf(AnthropicDriver::class);

    AppSetting::current()->update(['coach_provider' => 'ollama', 'ollama_base_url' => 'http://h:11434']);
    expect((new CoachConfig)->driver())->toBeInstanceOf(OllamaDriver::class);
});
