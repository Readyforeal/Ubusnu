<?php

use Livewire\Component;

new class extends Component {
    public ?int $threadId = null;
    public string $initialPrompt = '';

    public function mount(?int $threadId = null, string $initialPrompt = ''): void
    {
        $this->threadId = $threadId;
        $this->initialPrompt = $initialPrompt;
    }
}; ?>

<div>thread placeholder — initialized {{ $initialPrompt ?: 'empty' }}</div>
