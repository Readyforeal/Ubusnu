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
