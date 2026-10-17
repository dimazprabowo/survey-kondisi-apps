@props([
    'field',            // key kolom di whitelist sorting Service (sortBy('...'))
    'sortField' => '',  // kolom sort aktif saat ini ($sortField dari Livewire)
    'sortDir' => 'asc',
    'compact' => false, // padding rapat (px-4 py-2) untuk tabel kecil seperti impersonate
])

<th wire:key="th-{{ $field }}" class="{{ $compact ? 'px-4 py-2' : 'px-6 py-3' }} text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
    <button type="button" wire:click="sortBy('{{ $field }}')" wire:target="sortBy('{{ $field }}')"
        class="group inline-flex items-center gap-1 uppercase tracking-wider transition-colors {{ $sortField === $field ? 'text-blue-600 dark:text-blue-400' : 'hover:text-gray-700 dark:hover:text-gray-200' }}">
        {{ $slot }}
        <svg wire:loading class="animate-spin h-3 w-3" wire:target="sortBy('{{ $field }}')" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.121 5.824 3 7.938l3-2.647z"></path>
        </svg>
        @if($sortField === $field)
            <svg wire:loading.remove class="h-3 w-3" wire:target="sortBy('{{ $field }}')" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                @if($sortDir === 'asc')
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                @else
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                @endif
            </svg>
        @else
            <svg wire:loading.remove class="h-3 w-3 opacity-40 group-hover:opacity-80" wire:target="sortBy('{{ $field }}')" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4M8 15l4 4 4-4" />
            </svg>
        @endif
    </button>
</th>
