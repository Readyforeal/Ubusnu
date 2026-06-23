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
