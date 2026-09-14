@props([
    'target' => null,
    'variant' => 'primary',
    'size' => 'md',
    'loadingText' => null,
    'icon' => null,
    'iconClass' => null,
])

@php
    $variants = [
        // Filled background variants
        'primary'   => 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
        'success'   => 'bg-emerald-600 hover:bg-emerald-700 text-white focus:ring-emerald-500',
        'danger'    => 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500',
        'warning'   => 'bg-yellow-600 hover:bg-yellow-700 text-white focus:ring-yellow-500',
        'secondary' => 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 focus:ring-blue-500',
        // Icon-only variants (text-colored, subtle hover bg)
        'icon-blue'   => 'p-1.5 text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/20 focus:ring-blue-500',
        'icon-red'    => 'p-1.5 text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 focus:ring-red-500',
        'icon-green'  => 'p-1.5 text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 hover:bg-green-50 dark:hover:bg-green-900/20 focus:ring-green-500',
        'icon-amber'  => 'p-1.5 text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 focus:ring-yellow-500',
        'icon-gray'   => 'p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:ring-gray-500',
    ];

    $sizes = [
        'xs' => 'px-2 py-1 text-xs gap-1',
        'sm' => 'px-2.5 py-1.5 text-sm gap-1',
        'md' => 'px-3 py-2 text-sm gap-1.5',
        'lg' => 'px-4 py-2 text-base gap-2',
    ];

    $spinnerSizes = [
        'xs' => 'h-3 w-3',
        'sm' => 'h-3.5 w-3.5',
        'md' => 'h-4 w-4',
        'lg' => 'h-5 w-5',
    ];

    // Built-in action icons (Heroicons outline style, viewBox 0 0 24 24)
    $builtinIcons = [
        'edit'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>',
        'delete'  => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>',
        'view'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>',
        'reset'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>',
        'check'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
        'close'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>',
        'external'=> '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>',
        'plus'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>',
        'send'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>',
        'download'=> '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>',
        'pdf'     => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>',
        'excel'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        'copy'    => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>',
        'arrow-up'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>',
        'arrow-down' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>',
    ];

    $isIconOnly = str_starts_with($variant, 'icon-');
    // $icon can be: null (none), string (built-in prop), or ComponentSlot (custom <x-slot:icon>)
    $hasIconProp = is_string($icon) && filled($icon);
    $hasIconSlot = $icon instanceof \Illuminate\View\ComponentSlot && !$icon->isEmpty();
    $hasIcon = $hasIconProp || $hasIconSlot;
    $variantClass = $variants[$variant] ?? $variants['primary'];

    // Icon-only buttons don't use size-based padding (padding is in variant)
    $sizeClass = $isIconOnly ? '' : ($sizes[$size] ?? $sizes['md']);
    // Spinner must match icon size to prevent layout shift on toggle (icon↔spinner)
    $finalIconClass = $iconClass ?? ($isIconOnly ? 'w-5 h-5' : 'w-4 h-4');
    $spinnerSize = $isIconOnly ? $finalIconClass : ($spinnerSizes[$size] ?? $spinnerSizes['md']);
@endphp

<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => "inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap {$variantClass} {$sizeClass}",
    ]) }}
    @if($target)
        wire:loading.attr="disabled"
        wire:target="{{ $target }}"
    @endif
>
    {{-- Spinner (shown during loading) --}}
    @if($target)
        <svg wire:loading wire:target="{{ $target }}"
            class="animate-spin {{ $spinnerSize }}"
            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @endif

    {{-- Icon (hidden during loading) --}}
    @if($hasIcon)
        <span @if($target) wire:loading.class="hidden" wire:target="{{ $target }}" @endif class="inline-flex">
            @if($hasIconProp && isset($builtinIcons[$icon]))
                <svg class="{{ $finalIconClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $builtinIcons[$icon] !!}
                </svg>
            @elseif($hasIconSlot)
                {{ $icon }}
            @endif
        </span>
    @endif

    {{-- Text (with optional loading text swap) --}}
    @if($target && $loadingText)
        <span wire:loading.class="hidden" wire:target="{{ $target }}">{{ $slot }}</span>
        <span wire:loading wire:target="{{ $target }}">{{ $loadingText }}</span>
    @else
        {{ $slot }}
    @endif
</button>
