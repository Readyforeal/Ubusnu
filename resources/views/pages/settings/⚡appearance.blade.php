<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div
            x-data="{
                theme: localStorage.getItem('theme') || 'light',
                themes: [
                    { name: 'light', label: 'Light' },
                    { name: 'dark', label: 'Dark' },
                    { name: 'cupcake', label: 'Cupcake' },
                    { name: 'bumblebee', label: 'Bumblebee' },
                    { name: 'emerald', label: 'Emerald' },
                    { name: 'corporate', label: 'Corporate' },
                    { name: 'synthwave', label: 'Synthwave' },
                    { name: 'retro', label: 'Retro' },
                    { name: 'cyberpunk', label: 'Cyberpunk' },
                    { name: 'valentine', label: 'Valentine' },
                    { name: 'forest', label: 'Forest' },
                    { name: 'dracula', label: 'Dracula' },
                ],
                setTheme(name) {
                    this.theme = name;
                    localStorage.setItem('theme', name);
                    document.documentElement.setAttribute('data-theme', name);
                },
                init() {
                    document.documentElement.setAttribute('data-theme', this.theme);
                }
            }"
        >
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <template x-for="t in themes" :key="t.name">
                    <button
                        @click="setTheme(t.name)"
                        :class="theme === t.name ? 'ring-2 ring-primary ring-offset-2 ring-offset-base-100' : ''"
                        class="rounded-lg border border-base-300 overflow-hidden cursor-pointer transition-all hover:scale-105"
                        :data-theme="t.name"
                    >
                        <div class="bg-base-100 p-3">
                            <div class="flex gap-1 mb-2">
                                <div class="rounded-full size-3 bg-primary"></div>
                                <div class="rounded-full size-3 bg-secondary"></div>
                                <div class="rounded-full size-3 bg-accent"></div>
                            </div>
                            <div class="space-y-1">
                                <div class="rounded h-2 w-full bg-base-content/20"></div>
                                <div class="rounded h-2 w-3/4 bg-base-content/20"></div>
                            </div>
                        </div>
                        <div class="bg-base-200 px-3 py-2 text-xs font-medium text-base-content text-center" x-text="t.label"></div>
                    </button>
                </template>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
