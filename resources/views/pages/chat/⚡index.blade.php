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

<div x-data="{ drawerOpen: false }" class="md:grid md:grid-cols-[260px_1fr] md:gap-4 h-full relative">
    {{-- Chat list — column on md+, off-canvas drawer on small screens --}}
    <aside
        x-cloak
        @keydown.escape.window="drawerOpen = false"
        :class="drawerOpen ? 'translate-x-0' : '-translate-x-full'"
        class="
            fixed inset-y-0 left-0 z-50 w-72 bg-base-100 p-3 overflow-y-auto shadow-xl transition-transform
            md:static md:inset-auto md:translate-x-0 md:w-auto md:shadow-none md:bg-transparent md:border md:border-base-300 md:rounded-lg md:transition-none
        "
    >
        <x-button label="+ New chat" class="btn-primary btn-sm w-full mb-3" @click="drawerOpen = false" wire:click="newThread" />
        @forelse ($threads as $t)
            <div class="group flex items-center gap-1 p-1 rounded text-sm hover:bg-base-200 {{ $threadId === $t->id ? 'bg-base-200' : '' }}">
                <button type="button" @click="drawerOpen = false" wire:click="selectThread({{ $t->id }})" class="flex-1 min-w-0 text-left">
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

    {{-- Mobile-only drawer backdrop --}}
    <div
        x-show="drawerOpen"
        x-cloak
        @click="drawerOpen = false"
        x-transition.opacity
        class="md:hidden fixed inset-0 z-40 bg-black/40"
    ></div>

    <main class="flex flex-col overflow-hidden h-full relative">
        {{-- Mobile floating action buttons. Two separate circles, no bar
             between them. Chat content scrolls behind. --}}
        <button
            type="button"
            @click="drawerOpen = true"
            class="md:hidden absolute top-3 left-3 z-20 size-10 rounded-full flex items-center justify-center"
            aria-label="Open chat list"
        >
            <x-icon name="lucide.menu" class="w-5 h-5" />
        </button>
        <button
            type="button"
            wire:click="newThread"
            class="md:hidden absolute top-3 right-3 z-20 size-10 rounded-full flex items-center justify-center"
            aria-label="New chat"
        >
            <x-icon name="lucide.plus" class="w-5 h-5" />
        </button>

        @if (! $isConfigured)
            <div class="flex-1 flex items-center justify-center p-8 text-center">
                <div>
                    <h2 class="text-lg font-semibold">{{ "Coach isn't connected" }}</h2>
                    <p class="opacity-70 text-sm mt-2">Pick a provider and add an API key to start chatting.</p>
                    <x-button label="Configure coach" link="{{ route('coach.edit') }}" class="btn-primary mt-4" wire:navigate />
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
