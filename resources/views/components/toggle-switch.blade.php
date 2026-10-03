@props([
    'active' => false,
    'target' => null,
    'disabled' => false,
    'title' => null,
    'activeColor' => 'blue',
])

@php
    $colors = [
        'blue'   => 'bg-blue-600',
        'green'  => 'bg-emerald-600',
        'red'    => 'bg-red-600',
        'amber'  => 'bg-yellow-600',
    ];

    $activeBg = $colors[$activeColor] ?? $colors['blue'];
    $inactiveBg = 'bg-gray-200 dark:bg-gray-700';
    $isDisabled = $disabled;
    $bgClass = $active ? $activeBg : $inactiveBg;
    $disabledClass = $isDisabled ? 'opacity-50 cursor-not-allowed' : '';
    $knobPosition = $active ? 'translate-x-6' : 'translate-x-1';
    $spinnerColor = $active ? 'text-white' : 'text-gray-600 dark:text-gray-300';
@endphp

<button
    type="button"
    @if($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @endif
    @if($isDisabled) disabled @endif
    title="{{ $title }}"
    {{ $attributes->merge([
        'class' => "relative inline-flex h-6 w-11 shrink-0 items-center justify-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 {$bgClass} {$disabledClass}",
    ]) }}
>
    {{-- Switch knob (absolute positioned, hidden during loading) --}}
    <span @if($target) wire:loading.class="hidden" wire:target="{{ $target }}" @endif
        class="absolute top-1/2 left-0 -translate-y-1/2 h-4 w-4 rounded-full bg-white shadow-sm transition-transform {{ $knobPosition }}">
    </span>

    {{-- Loading spinner (absolute, same position as knob, centered within knob area) --}}
    @if($target)
        <span class="absolute top-1/2 left-0 -translate-y-1/2 h-4 w-4 {{ $knobPosition }}"
              wire:loading.class="flex items-center justify-center"
              wire:target="{{ $target }}">
            <svg wire:loading wire:target="{{ $target }}"
                class="animate-spin h-3 w-3 {{ $spinnerColor }}"
                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </span>
    @endif
</button>
