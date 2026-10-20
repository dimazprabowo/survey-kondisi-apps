@props([
    'show' => false,
    'title' => 'Hapus Data',
    'message' => 'Yakin ingin menghapus data ini?',
    'itemName' => '',
    'note' => '',
    'confirmMethod' => 'delete',
])

@if($show)
    <div class="fixed inset-0 z-[60] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" wire:click="$set('{{ $attributes->wire('model')->value() }}', false)"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-sm z-10 p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">{{ $title }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 {{ $note ? 'mb-2' : 'mb-6' }}">
                    {{ $message }}
                    @if($itemName)
                        <span class="font-semibold text-gray-700 dark:text-white">{{ $itemName }}</span>?
                    @endif
                </p>
                @if($note)
                    <p class="text-xs text-gray-400 dark:text-gray-500 mb-6">{{ $note }}</p>
                @endif
                <div class="flex items-center justify-center gap-3">
                    <x-cancel-button wire:click="$set('{{ $attributes->wire('model')->value() }}', false)" target="$set('{{ $attributes->wire('model')->value() }}', false)" />
                    <x-loading-button wire:click="{{ $confirmMethod }}"
                        target="{{ $confirmMethod }}"
                        variant="danger" size="md"
                        class="px-5 py-2.5 rounded-xl shadow-sm"
                        loadingText="Menghapus...">
                        Ya, Hapus
                    </x-loading-button>
                </div>
            </div>
        </div>
    </div>
@endif
