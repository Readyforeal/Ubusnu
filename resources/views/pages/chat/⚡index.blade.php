<?php

use App\Models\ChatThread;
use App\Services\Coach\CoachConfig;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Coach')] class extends Component {
    public ?int $threadId = null;

    #[Url(as: 'prompt')]
    public string $initialPrompt = '';

    public function selectThread(int $id): void
    {
        $this->threadId = $id;
    }

    public function newThread(): void
    {
        $this->threadId = null;
    }

    public function deleteThread(int $id): void
    {
        $thread = ChatThread::where('user_id', auth()->id())->find($id);
        if (! $thread) {
            return;
        }
        $thread->delete();
        if ($this->threadId === $id) {
            $this->threadId = null;
        }
    }

    #[On('thread-created')]
    public function onThreadCreated(int $id): void
    {
        $this->threadId = $id;
        $this->initialPrompt = '';
    }

    public function with(): array
    {
        return [
            'threads' => auth()->user()->chatThreads()->get(),
            'isConfigured' => (new CoachConfig)->isConfigured(),
        ];
    }
}; ?>

<div class="grid grid-cols-[260px_1fr] gap-4 h-[calc(100vh-8rem)]">
    <aside class="border border-base-300 rounded-lg p-3 overflow-y-auto">
        <x-button label="+ New chat" class="btn-primary btn-sm w-full mb-3" wire:click="newThread" />
        @forelse ($threads as $t)
            <div class="group flex items-center gap-1 p-1 rounded text-sm hover:bg-base-200 {{ $threadId === $t->id ? 'bg-base-200' : '' }}">
                <button type="button" wire:click="selectThread({{ $t->id }})" class="flex-1 min-w-0 text-left">
                    <div class="font-medium truncate">{{ $t->title }}</div>
                    <div class="text-xs opacity-50">{{ $t->last_message_at?->diffForHumans() }}</div>
                </button>
                <button
                    type="button"
                    wire:click="deleteThread({{ $t->id }})"
                    wire:confirm="Delete this conversation? This can't be undone."
                    class="shrink-0 opacity-0 group-hover:opacity-100 p-1 rounded text-error hover:bg-error/10"
                    aria-label="Delete conversation"
                >
                    <x-icon name="lucide.trash-2" class="w-4 h-4" />
                </button>
            </div>
        @empty
            <p class="text-xs opacity-60 mt-2">No conversations yet.</p>
        @endforelse
    </aside>

    <main class="border border-base-300 rounded-lg overflow-hidden flex flex-col">
        @if (! $isConfigured)
            <div class="flex-1 flex items-center justify-center p-8 text-center">
                <div>
                    <h2 class="text-lg font-semibold">{{ "Coach isn't connected" }}</h2>
                    <p class="opacity-70 text-sm mt-2">Configure your Ollama endpoint to start chatting.</p>
                    <x-button label="Configure Ollama" link="{{ route('coach.edit') }}" class="btn-primary mt-4" wire:navigate />
                </div>
            </div>
        @else
            {{-- Stable :key so the child never re-mounts mid-stream. The
                 reactive thread-id prop syncs new threadIds without
                 destroying Alpine state. --}}
            <livewire:pages::chat.thread :thread-id="$threadId" :initial-prompt="$initialPrompt" :key="'chat-thread-singleton'" />
        @endif
    </main>
</div>
