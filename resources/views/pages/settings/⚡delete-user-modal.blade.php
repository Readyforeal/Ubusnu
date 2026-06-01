<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';
    public bool $showModal = false;

    protected $listeners = ['open-delete-user-modal' => 'openModal'];

    public function openModal(): void
    {
        $this->showModal = true;
    }

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <x-modal wire:model="showModal" title="{{ __('Are you sure you want to delete your account?') }}">
        <p class="text-sm text-base-content/60">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
        </p>

        <form method="POST" wire:submit="deleteUser" class="mt-4 space-y-6">
            <x-input wire:model="password" label="{{ __('Password') }}" type="password" inline />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <x-button label="{{ __('Cancel') }}" @click="$wire.showModal = false" />
                <x-button label="{{ __('Delete account') }}" type="submit" class="btn-error" data-test="confirm-delete-user-button" />
            </div>
        </form>
    </x-modal>
</div>
