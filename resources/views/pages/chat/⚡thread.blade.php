<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public ?int $threadId = null;
    public string $initialPrompt = '';

    public function mount(?int $threadId = null, string $initialPrompt = ''): void
    {
        $this->threadId = $threadId;
        $this->initialPrompt = $initialPrompt;
    }

    #[Computed]
    public function thread(): ?ChatThread
    {
        return $this->threadId ? ChatThread::find($this->threadId) : null;
    }

    #[Computed]
    public function messages(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->thread ? $this->thread->messages()->get() : ChatMessage::query()->whereRaw('1=0')->get();
    }

    public function refreshMessages(): void
    {
        // Triggered from JS after streaming ends so Livewire re-reads from DB.
    }
}; ?>

<div class="flex flex-col h-full" x-data="chatThread({{ $threadId ?? 'null' }}, @js($initialPrompt))" x-init="init()" wire:ignore.self>
    <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="messages">
        @foreach ($this->messages as $msg)
            @if ($msg->role === 'user')
                <div class="flex justify-end">
                    <div class="max-w-prose bg-primary text-primary-content rounded-lg px-3 py-2 text-sm">{{ $msg->content }}</div>
                </div>
            @elseif ($msg->role === 'assistant')
                <div class="flex justify-start">
                    <div class="max-w-prose bg-base-200 rounded-lg px-3 py-2 text-sm whitespace-pre-wrap">{{ $msg->content }}
                        @if ($msg->tool_calls)
                            <div class="text-xs opacity-60 mt-1">
                                @foreach ($msg->tool_calls as $tc)
                                    🔧 {{ $tc['name'] }}@if(! $loop->last), @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach

        <template x-for="msg in optimisticUserMessages" :key="msg.id">
            <div class="flex justify-end">
                <div class="max-w-prose bg-primary text-primary-content rounded-lg px-3 py-2 text-sm" x-text="msg.text"></div>
            </div>
        </template>

        <template x-if="sending && liveAssistant === '' && liveToolCalls.length === 0">
            <div class="flex justify-start">
                <div class="max-w-prose bg-base-200 rounded-lg px-3 py-2 text-sm opacity-70 flex items-center gap-2">
                    <span class="loading loading-dots loading-sm"></span>
                    <span>Coach is thinking…</span>
                </div>
            </div>
        </template>

        <template x-if="liveAssistant !== ''">
            <div class="flex justify-start">
                <div class="max-w-prose bg-base-200 rounded-lg px-3 py-2 text-sm whitespace-pre-wrap" x-text="liveAssistant"></div>
            </div>
        </template>

        <template x-for="tc in liveToolCalls" :key="tc.id">
            <div class="text-xs opacity-60 px-3">🔧 looking up <span x-text="tc.name"></span>…</div>
        </template>
    </div>

    <div class="p-3 border-t border-base-300 flex gap-2">
        <input
            type="text"
            x-model="text"
            placeholder="Ask the coach..."
            class="input input-bordered flex-1"
            :disabled="sending"
            @keydown.enter.prevent="send"
        />
        <button type="button" class="btn btn-primary" :disabled="sending || ! text.trim()" @click="send" x-text="sending ? '…' : 'Send'"></button>
    </div>
</div>

{{-- The Alpine.data('chatThread', ...) component is registered in
     resources/js/app.js so it's on the registry before Alpine walks
     this template's x-data. Inline @script registration ran too late
     and left x-data as an empty proxy. --}}
