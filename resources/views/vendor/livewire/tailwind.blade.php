@php
    $pageName = $paginator->getPageName();
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $pages = collect([1, $current - 1, $current, $current + 1, $last])
        ->filter(fn ($page) => $page >= 1 && $page <= $last)->unique()->sort()->values();
    $buttonClass = 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-2.5 text-sm font-medium text-gray-600 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:pointer-events-none disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-blue-500 dark:hover:bg-gray-700 dark:hover:text-blue-300 dark:focus:ring-offset-gray-800';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav aria-label="Navigasi halaman" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-center text-xs text-gray-500 dark:text-gray-400 sm:text-left sm:text-sm" aria-live="polite">
                Menampilkan <span class="font-semibold text-gray-900 dark:text-white">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                dari <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($paginator->total(), 0, ',', '.') }}</span> data
            </p>
            <div class="flex items-center justify-center gap-1.5">
                <button type="button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" wire:target="previousPage('{{ $pageName }}'),nextPage('{{ $pageName }}'),gotoPage" @disabled($paginator->onFirstPage())
                    class="{{ $buttonClass }}" title="Halaman sebelumnya" aria-label="Halaman sebelumnya">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6"/></svg>
                </button>
                @foreach ($pages as $page)
                    @if ($loop->index > 0 && $page - $pages[$loop->index - 1] > 1)
                        <span class="px-1 text-xs text-gray-400" aria-hidden="true">…</span>
                    @endif
                    @if ($page === $current)
                        <span wire:key="pagination-{{ $pageName }}-{{ $page }}" aria-current="page" aria-label="Halaman {{ $page }}"
                            class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-blue-600 bg-blue-600 px-2.5 text-sm font-semibold text-white shadow-sm dark:border-blue-500 dark:bg-blue-500">{{ $page }}</span>
                    @else
                        <button wire:key="pagination-{{ $pageName }}-{{ $page }}" type="button" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" wire:loading.attr="disabled" wire:target="previousPage('{{ $pageName }}'),nextPage('{{ $pageName }}'),gotoPage"
                            class="{{ $buttonClass }}" aria-label="Buka halaman {{ $page }}" title="Halaman {{ $page }}">{{ $page }}</button>
                    @endif
                @endforeach
                <button type="button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" wire:target="previousPage('{{ $pageName }}'),nextPage('{{ $pageName }}'),gotoPage" @disabled(! $paginator->hasMorePages())
                    class="{{ $buttonClass }}" title="Halaman berikutnya" aria-label="Halaman berikutnya">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 6 6 6-6 6"/></svg>
                </button>
            </div>
        </nav>
    @endif
</div>
