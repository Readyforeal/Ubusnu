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

        <template x-if="liveAssistant !== ''">
            <div class="flex justify-start">
                <div class="max-w-prose bg-base-200 rounded-lg px-3 py-2 text-sm whitespace-pre-wrap" x-text="liveAssistant"></div>
            </div>
        </template>

        <template x-for="tc in liveToolCalls" :key="tc.id">
            <div class="text-xs opacity-60 px-3">🔧 looking up <span x-text="tc.name"></span>…</div>
        </template>
    </div>

    <form @submit.prevent="send" class="p-3 border-t border-base-300 flex gap-2">
        <input type="text" x-model="text" placeholder="Ask the coach..." class="input input-bordered flex-1" :disabled="sending" />
        <button type="submit" class="btn btn-primary" :disabled="sending || ! text.trim()" x-text="sending ? '…' : 'Send'"></button>
    </form>
</div>

@script
<script>
Alpine.data('chatThread', (initialThreadId, initialPrompt) => ({
    threadId: initialThreadId,
    text: initialPrompt || '',
    sending: false,
    liveAssistant: '',
    liveToolCalls: [],
    init() {
        this.scrollToBottom();
    },
    scrollToBottom() {
        this.$nextTick(() => {
            const el = this.$refs.messages;
            if (el) el.scrollTop = el.scrollHeight;
        });
    },
    async send() {
        if (! this.text.trim() || this.sending) return;
        this.sending = true;
        const messageText = this.text;
        this.text = '';
        this.liveAssistant = '';
        this.liveToolCalls = [];

        if (! this.threadId) {
            const r = await fetch('/chat/threads', { method: 'POST', headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            }});
            const data = await r.json();
            this.threadId = data.id;
            this.$dispatch('thread-created', this.threadId);
        }

        const url = '/chat/' + this.threadId + '/stream';
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ message: messageText }),
        });

        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            let nl;
            while ((nl = buffer.indexOf('\n')) !== -1) {
                const line = buffer.slice(0, nl).trim();
                buffer = buffer.slice(nl + 1);
                if (! line) continue;
                try {
                    const event = JSON.parse(line);
                    if (event.type === 'token' && event.content) {
                        this.liveAssistant += event.content;
                        this.scrollToBottom();
                    } else if (event.type === 'tool_call') {
                        this.liveToolCalls.push({ id: this.liveToolCalls.length, name: event.tool_name });
                    }
                } catch (e) {}
            }
        }

        this.sending = false;
        this.liveAssistant = '';
        this.liveToolCalls = [];
        $wire.refreshMessages();
        this.scrollToBottom();
    },
}));
</script>
@endscript
