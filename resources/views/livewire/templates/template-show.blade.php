<div>
    <!-- Breadcrumb -->
    <nav class="mb-6 flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2 text-sm">
            <li>
                <a href="{{ route('master-data.survey-templates.index') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Template Form</a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li class="text-gray-900 dark:text-white font-medium">{{ $template?->name ?? '-' }}</li>
        </ol>
    </nav>

    @if($template)
        <!-- Template Info -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $template->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $template->description ?? 'Tidak ada deskripsi' }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 font-mono">{{ $template->code }}</span>
                        @if($template->is_default)
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">Default</span>
                        @endif
                        @if($template->is_active)
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Aktif</span>
                        @else
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Nonaktif</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm border-t border-gray-200 dark:border-gray-700 pt-4">
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Kategori</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $template->categories->count() }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Sub Kategori</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $template->categories->sum(fn ($c) => $c->subCategories->count()) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Item Group</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $template->categories->sum(fn ($c) => $c->subCategories->sum(fn ($s) => $s->itemGroups->count())) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Total Items</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $template->categories->sum(fn ($c) => $c->subCategories->sum(fn ($s) => $s->itemGroups->sum(fn ($ig) => $ig->items->count()))) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Structure Tree -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                    Struktur Template
                </h3>

                <div class="space-y-3">
                    @foreach($template->categories as $cat)
                        <div wire:key="cat-{{ $cat->id }}" class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-2">
                                <span class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ $cat->code }}. {{ $cat->label }}</span>
                            </div>
                            <div class="px-4 py-3 space-y-3">
                                @foreach($cat->subCategories as $sub)
                                    <div wire:key="sub-{{ $sub->id }}" class="border-l-2 border-blue-300 dark:border-blue-700 pl-3">
                                        <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $sub->name }}</div>
                                        <div class="mt-2 space-y-2">
                                            @foreach($sub->itemGroups as $ig)
                                                <div wire:key="ig-{{ $ig->id }}" class="bg-gray-50 dark:bg-gray-700/30 rounded p-2">
                                                    <div class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $ig->code }} {{ $ig->name }}</div>
                                                    <div class="mt-1 ml-3 space-y-1">
                                                        @foreach($ig->items as $item)
                                                            <div wire:key="item-{{ $item->id }}" class="text-xs text-gray-600 dark:text-gray-400 flex items-center gap-2">
                                                                <span class="font-mono text-gray-400 dark:text-gray-500">{{ $item->code }}</span>
                                                                <span>{{ $item->name }}</span>
                                                                <span class="text-gray-400 dark:text-gray-600">[{{ implode(', ', $item->score_labels ?? []) }}]</span>
                                                                @if($item->has_date_fields)
                                                                    <span class="px-1.5 py-0.5 rounded text-xs bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">+date</span>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
            <x-cancel-button wire:click="backToList" target="backToList" class="w-full sm:w-auto">
                Kembali
            </x-cancel-button>
            @can('survey_templates_update', $template)
                <x-loading-button wire:click="editTemplate" target="editTemplate" variant="primary" size="md" loadingText="Memuat..." icon="edit" class="w-full sm:w-auto">
                    Edit Template
                </x-loading-button>
            @endcan
        </div>
    @else
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Template tidak ditemukan</p>
        </div>
    @endif
</div>
