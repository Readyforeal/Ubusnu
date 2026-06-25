<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

new class extends Component {
    #[Reactive]
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
    <div class="flex-1 overflow-y-auto p-4 space-y-1" x-ref="messages">
        @foreach ($this->messages as $msg)
            @if ($msg->role === 'user')
                <div class="chat chat-end">
                    <div class="chat-bubble chat-bubble-primary text-sm">{{ $msg->content }}</div>
                </div>
            @elseif ($msg->role === 'assistant')
                <div class="chat chat-start">
                    <div class="chat-bubble text-sm whitespace-pre-wrap">{{ $msg->content }}
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
            <div class="chat chat-end">
                <div class="chat-bubble chat-bubble-primary text-sm" x-text="msg.text"></div>
            </div>
        </template>

        <template x-if="sending && liveAssistant === '' && liveToolCalls.length === 0">
            <div class="chat chat-start">
                <div class="chat-bubble text-sm opacity-70 flex items-center gap-2">
                    <span class="loading loading-dots loading-sm"></span>
                    <span>Coach is thinking…</span>
                </div>
            </div>
        </template>

        <template x-if="liveAssistant !== ''">
            <div class="chat chat-start">
                <div class="chat-bubble text-sm whitespace-pre-wrap" x-text="liveAssistant"></div>
            </div>
        </template>

        <template x-for="tc in liveToolCalls" :key="tc.id">
            <div class="text-xs opacity-60 px-3">🔧 looking up <span x-text="tc.name"></span>…</div>
        </template>
    </div>

    <div class="p-3">
        <div class="relative rounded-lg backdrop-blur-lg bg-base-100/70 shadow-lg border border-base-300/40">
            <textarea
                x-model="text"
                x-ref="composer"
                x-effect="text === '' && $refs.composer && ($refs.composer.style.height = 'auto')"
                rows="1"
                placeholder="Ask the coach..."
                :disabled="sending"
                @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 200) + 'px'"
                @keydown.enter.prevent="if (! $event.shiftKey) { send() }"
                class="w-full bg-transparent border-0 focus:outline-none focus:ring-0 resize-none px-4 py-3 pr-14 text-sm leading-relaxed min-h-[3rem] max-h-[12rem]"
            ></textarea>
            <button
                type="button"
                class="btn btn-primary btn-sm btn-circle absolute right-2 bottom-2"
                :disabled="sending || ! text.trim()"
                @click="send"
                aria-label="Send"
            >
                <span x-show="!sending"><x-icon name="lucide.arrow-up" class="w-4 h-4" /></span>
                <span x-show="sending" x-cloak><span class="loading loading-spinner loading-xs"></span></span>
            </button>
        </div>
    </div>
</div>

{{-- The Alpine.data('chatThread', ...) component is registered in
     resources/js/app.js so it's on the registry before Alpine walks
     this template's x-data. Inline @script registration ran too late
     and left x-data as an empty proxy. --}}
