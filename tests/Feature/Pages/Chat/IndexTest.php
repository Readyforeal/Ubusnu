<?php

use App\Models\AppSetting;
use App\Models\ChatThread;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('shows the not-configured state when Ollama URL is empty', function () {
    AppSetting::current()->update(['ollama_base_url' => null]);

    $this->get('/chat')
        ->assertOk()
        ->assertSee("Coach isn't connected");
});

it('lists existing threads for the user', function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    ChatThread::factory()->create(['user_id' => auth()->id(), 'title' => 'June recap']);

    $this->get('/chat')
        ->assertOk()
        ->assertSee('June recap');
});

it('does not show threads belonging to other users', function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    $other = User::factory()->create();
    ChatThread::factory()->create(['user_id' => $other->id, 'title' => 'Other user thread']);

    $this->get('/chat')
        ->assertOk()
        ->assertDontSee('Other user thread');
});

it('deletes a thread and clears the active selection if it was selected', function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    $thread = ChatThread::factory()->create(['user_id' => auth()->id()]);

    Livewire::test('pages::chat.index')
        ->set('threadId', $thread->id)
        ->call('deleteThread', $thread->id)
        ->assertSet('threadId', null);

    expect(ChatThread::find($thread->id))->toBeNull();
});

it('cannot delete a thread that belongs to another user', function () {
    AppSetting::current()->update(['ollama_base_url' => 'http://homelab:11434']);
    $other = User::factory()->create();
    $theirThread = ChatThread::factory()->create(['user_id' => $other->id]);

    Livewire::test('pages::chat.index')
        ->call('deleteThread', $theirThread->id);

    expect(ChatThread::find($theirThread->id))->not->toBeNull();
});
