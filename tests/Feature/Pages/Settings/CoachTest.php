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

it('reports failure when the test connection returns non-2xx', function () {
    Http::fake(['*' => Http::response('boom', 500)]);

    Livewire::test('pages::settings.coach')
        ->set('baseUrl', 'http://homelab:11434')
        ->call('testConnection')
        ->assertSet('testResult', 'Got HTTP 500');
});
