@props([
    'options' => [],
    'value' => '',
    'placeholder' => 'Pilih opsi...',
    'searchPlaceholder' => 'Cari...',
    'name' => '',
    'id' => null,
    'disabled' => false,
    'required' => false,
    'error' => false,
    'emptyText' => 'Tidak ada data',
    'noResultText' => 'Tidak ditemukan',
])

@php
    $componentId = $id ?? 'searchable-select-' . uniqid();
    $wireModel = $attributes->wire('model')->value();
    $isLive = $attributes->wire('model')->hasModifier('live');
@endphp

<div
    x-data="{
        open: false,
        search: '',
        dropUp: false,
        value: @entangle($wireModel){{ $isLive ? '.live' : '' }},
        options: @js($options),
        placeholder: @js($placeholder),

        get filteredOptions() {
            if (!this.search) return this.options;
            const searchLower = this.search.toLowerCase();
            return this.options.filter(option =>
                option.label.toLowerCase().includes(searchLower) ||
                (option.sublabel && option.sublabel.toLowerCase().includes(searchLower))
            );
        },

        get selectedOption() {
            return this.options.find(opt => String(opt.value) === String(this.value)) || null;
        },

        get displayText() {
            if (this.selectedOption) {
                return this.selectedOption.label;
            }
            return this.placeholder;
        },

        selectOption(option) {
            this.value = option.value;
            this.open = false;
            this.search = '';
        },

        clearSelection() {
            this.value = '';
            this.open = false;
            this.search = '';
        },

        closeDropdown() {
            this.open = false;
            this.search = '';
        },

        checkPosition() {
            const trigger = this.$refs.trigger;
            if (!trigger) return;

            const rect = trigger.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;
            const dropdownHeight = 280; // approximate max height of dropdown

            // If not enough space below but enough above, show dropdown above
            this.dropUp = spaceBelow < dropdownHeight && spaceAbove > spaceBelow;
        },

        toggleDropdown() {
            if (!this.open) {
                this.checkPosition();
            }
            this.open = !this.open;
        }
    }"
    x-key="{{ $componentId }}"
    x-on:click.away="closeDropdown()"
    x-on:keydown.escape.window="closeDropdown()"
    x-on:scroll.window="if(open) checkPosition()"
    x-on:resize.window="if(open) checkPosition()"
    class="relative w-full"
    wire:ignore.self
>
    {{-- Trigger Button --}}
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggleDropdown()"
        @if($disabled) disabled @endif
        {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:model.change'])->merge([
            'class' => 'relative w-full mt-1 cursor-pointer rounded-lg border bg-white dark:bg-gray-700 py-2.5 pl-4 pr-10 text-left shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 ' . 
                ($error ? 'border-red-500 dark:border-red-500' : 'border-gray-300 dark:border-gray-600') .
                ($disabled ? ' opacity-50 cursor-not-allowed bg-gray-100 dark:bg-gray-800' : ' hover:border-gray-400 dark:hover:border-gray-500')
        ]) }}
    >
        <span 
            x-text="displayText"
            :class="selectedOption ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'"
            class="block truncate text-sm"
        ></span>
        
        {{-- Chevron Icon --}}
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
            <svg 
                class="h-5 w-5 text-gray-400 transition-transform duration-200" 
                :class="{ 'rotate-180': open }"
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </span>
    </button>

    {{-- Dropdown Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        :class="dropUp ? 'bottom-full mb-1' : 'top-full mt-1'"
        class="absolute z-50 w-full rounded-lg bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
        style="display: none;"
        x-cloak
    >
        {{-- Search Input --}}
        <div class="p-2 border-b border-gray-200 dark:border-gray-700">
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input
                    type="text"
                    x-model="search"
                    x-ref="searchInput"
                    x-on:click.stop
                    placeholder="{{ $searchPlaceholder }}"
                    class="block w-full rounded-md border-0 py-2 pl-9 pr-3 text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-blue-500"
                    @keydown.enter.prevent="if(filteredOptions.length > 0) selectOption(filteredOptions[0])"
                >
                {{-- Clear Search Button --}}
                <button
                    type="button"
                    x-show="search.length > 0"
                    x-on:click.stop="search = ''"
                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Options List --}}
        <ul 
            class="max-h-60 overflow-auto custom-scrollbar py-1 text-sm focus:outline-none"
            role="listbox"
        >
            {{-- Clear/Empty Option --}}
            <template x-if="value && value !== ''">
                <li
                    x-on:click="clearSelection()"
                    class="relative cursor-pointer select-none py-2.5 px-4 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 border-b border-gray-100 dark:border-gray-700"
                    role="option"
                >
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span class="text-sm italic">Hapus pilihan</span>
                    </div>
                </li>
            </template>

            {{-- Filtered Options --}}
            <template x-for="option in filteredOptions" :key="option.value">
                <li
                    x-on:click="selectOption(option)"
                    :class="{
                        'bg-blue-50 dark:bg-blue-900/30': String(value) === String(option.value),
                        'hover:bg-gray-100 dark:hover:bg-gray-700': String(value) !== String(option.value)
                    }"
                    class="relative cursor-pointer select-none py-2.5 px-4 transition-colors"
                    role="option"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <span 
                                x-text="option.label"
                                :class="String(value) === String(option.value) ? 'font-semibold text-blue-700 dark:text-blue-300' : 'text-gray-900 dark:text-white'"
                                class="block truncate"
                            ></span>
                            <template x-if="option.sublabel">
                                <span 
                                    x-text="option.sublabel"
                                    class="block truncate text-xs text-gray-500 dark:text-gray-400 mt-0.5"
                                ></span>
                            </template>
                        </div>
                        {{-- Check Icon --}}
                        <template x-if="String(value) === String(option.value)">
                            <svg class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        </template>
                    </div>
                </li>
            </template>

            {{-- No Results --}}
            <template x-if="filteredOptions.length === 0 && search.length > 0">
                <li class="relative cursor-default select-none py-4 px-4 text-center">
                    <div class="flex flex-col items-center gap-2 text-gray-500 dark:text-gray-400">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm">{{ $noResultText }}</span>
                    </div>
                </li>
            </template>

            {{-- Empty State --}}
            <template x-if="options.length === 0">
                <li class="relative cursor-default select-none py-4 px-4 text-center">
                    <div class="flex flex-col items-center gap-2 text-gray-500 dark:text-gray-400">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                        <span class="text-sm">{{ $emptyText }}</span>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
