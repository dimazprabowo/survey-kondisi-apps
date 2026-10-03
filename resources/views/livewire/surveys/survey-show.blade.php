<div
    x-data="{
        // State collapse per item-group bertahan di root — tidak ikut
        // re-init saat Livewire morph (mis. pindah tab lalu kembali)
        collapsedGroups: {},
        init() {
            // Auto-geser scrollbar tab agar tab aktif selalu terlihat
            this.$wire.$watch('activeCategory', () => requestAnimationFrame(() => this.scrollActiveTabs()));
            this.$wire.$watch('activeSubCategory', () => requestAnimationFrame(() => this.scrollActiveTabs()));
            requestAnimationFrame(() => this.scrollActiveTabs());
        },
        scrollActiveTabs() {
            this.$root.querySelectorAll('[data-tab-active]').forEach((el) => {
                const scroller = el.closest('.overflow-x-auto');
                if (! scroller) return;
                const elRect = el.getBoundingClientRect();
                const scRect = scroller.getBoundingClientRect();
                const offset = elRect.left - scRect.left;
                const overshoot = elRect.right - scRect.right;
                if (offset > 0 && ! (overshoot > 0)) return; // sudah terlihat penuh
                scroller.scrollBy({ left: offset - (scRect.width - elRect.width) / 2, behavior: 'smooth' });
            });
        },
    }"
>
    <!-- Breadcrumb -->
    <nav class="mb-6 flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2 text-sm">
            <li>
                <a href="{{ route('surveys.index') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Survey Kondisi</a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li class="text-gray-900 dark:text-white font-medium">{{ $survey->survey_number }}</li>
        </ol>
    </nav>

    <!-- Header Info -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
        <div class="px-4 py-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $survey->survey_number }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $survey->ship?->name }}</p>
                </div>
                <div class="mt-2 sm:mt-0 flex items-center gap-3">
                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $survey->status->badgeClass() }}">
                        {{ $survey->status->label() }}
                    </span>
                    @if($survey->overall_cap_score !== null)
                        @php
                            $score = (float) $survey->overall_cap_score;
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-base font-bold {{ survey_score_badge_class($score) }}">
                            CAP: {{ number_format($score, 2) }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Template</div>
                    <div class="text-gray-900 dark:text-white">{{ $survey->template?->name ?? ($survey->structure['template_name'] ?? '-') }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Tanggal Survey</div>
                    <div class="text-gray-900 dark:text-white">{{ $survey->survey_date?->format('d M Y') ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Surveyor</div>
                    <div class="text-gray-900 dark:text-white">{{ $survey->surveyor ?? '-' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Lokasi</div>
                    <div class="text-gray-900 dark:text-white">{{ $survey->location ?? '-' }}</div>
                </div>
            </div>

            @if($survey->notes)
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Catatan</div>
                    <div class="text-sm text-gray-900 dark:text-white">{{ $survey->notes }}</div>
                </div>
            @endif
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
        <div class="border-b border-gray-200 dark:border-gray-700 overflow-x-auto custom-scrollbar">
            <nav class="flex space-x-2 px-4 py-2 min-w-max" aria-label="Tabs">
                @foreach($categories as $cat)
                    @php
                        $catAvg = $this->categoryAvg($cat);
                        $isActive = $this->activeCategory == $cat->id;
                    @endphp
                    <button type="button" wire:click="setCategory({{ $cat->id }})" wire:target="setCategory({{ $cat->id }})" @if($isActive) data-tab-active @endif
                        class="px-3 py-2 text-sm font-medium rounded-md whitespace-nowrap transition-colors {{ $isActive ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/30' }}">
                        <span wire:loading.remove="inline" wire:target="setCategory({{ $cat->id }})">{{ to_roman($loop->iteration) }}.</span>
                        <svg wire:loading class="animate-spin h-3.5 w-3.5 inline" wire:target="setCategory({{ $cat->id }})" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.121 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $cat->label }}
                        @if($catAvg !== null)
                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold {{ $isActive ? 'bg-blue-200 text-blue-800 dark:bg-blue-800 dark:text-blue-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                {{ number_format($catAvg, 2) }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>

        <div class="p-4 sm:p-6">
            @foreach($categories as $cat)
                @if($this->activeCategory == $cat->id)
                    @php
                        $catAvg = $this->categoryAvg($cat);
                    @endphp
                    <!-- Category Header -->
                    <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ to_roman($loop->iteration) }}. {{ $cat->label }}
                        </h4>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">CAP Rating:</span>
                            @if($catAvg !== null)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-semibold {{ survey_score_badge_class($catAvg) }}">
                                    {{ number_format($catAvg, 2) }}
                                </span>
                            @else
                                <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                            @endif
                        </div>
                    </div>

                    <!-- Sub-Category Tabs -->
                    @if($cat->subCategories->isNotEmpty())
                        <div class="border-b border-gray-200 dark:border-gray-700 overflow-x-auto custom-scrollbar mb-4">
                            <nav class="flex space-x-2 pb-2 min-w-max" aria-label="Tabs Sub Kategori">
                                @foreach($cat->subCategories as $subCat)
                                    @php
                                        $subCatAvg = $this->subCategoryAvg($subCat);
                                        $isSubActive = $this->activeSubCategory == $subCat->id;
                                    @endphp
                                    <button type="button" wire:click="setSubCategory({{ $subCat->id }})" wire:target="setSubCategory({{ $subCat->id }})" wire:key="subtab-{{ $subCat->id }}" @if($isSubActive) data-tab-active @endif
                                        class="px-3 py-1.5 text-sm font-medium rounded-md whitespace-nowrap transition-colors {{ $isSubActive ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/30' }}">
                                        <span wire:loading.remove="inline" wire:target="setSubCategory({{ $subCat->id }})">{{ $subCat->order_num }}.</span>
                                        <svg wire:loading class="animate-spin h-3.5 w-3.5 inline" wire:target="setSubCategory({{ $subCat->id }})" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.121 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ $subCat->name }}
                                        @if($subCatAvg !== null)
                                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold {{ $isSubActive ? 'bg-blue-200 text-blue-800 dark:bg-blue-800 dark:text-blue-200' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                                {{ number_format($subCatAvg, 2) }}
                                            </span>
                                        @endif
                                    </button>
                                @endforeach
                            </nav>
                        </div>
                    @endif

                    @forelse($cat->subCategories as $subCat)
                        @if($this->activeSubCategory == $subCat->id)
                            <div wire:key="subpanel-{{ $subCat->id }}">
                            @foreach($subCat->itemGroups as $itemGroup)
                                @php
                                    $igAvg = $this->itemGroupAvg($itemGroup);
                                    $firstItem = $itemGroup->items->first();
                                    $isInventoryGroup = $firstItem && $firstItem->item_type === \App\Enums\SurveyItemType::Inventory;
                                    $scoreLabels = $firstItem ? $firstItem->score_labels : ['C', 'V'];
                                @endphp
                                <!-- Item Group -->
                                <div wire:key="ig-{{ $itemGroup->id }}" class="mb-4 border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden">
                                    <button type="button" x-on:click="collapsedGroups[{{ $itemGroup->id }}] = ! collapsedGroups[{{ $itemGroup->id }}]" title="Buka/tutup grup item"
                                        class="w-full bg-gray-50 dark:bg-gray-700/20 px-3 py-2 flex items-center justify-between hover:bg-gray-100 dark:hover:bg-gray-700/40 transition-colors">
                                        <span class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-white">
                                            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-90': ! collapsedGroups[{{ $itemGroup->id }}] }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                            </svg>
                                            {{ $subCat->order_num }}.{{ $itemGroup->order_num }} {{ $itemGroup->name }}
                                        </span>
                                        @unless($isInventoryGroup)
                                            <span class="flex items-center gap-2">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">Avg:</span>
                                                @if($igAvg !== null)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ survey_score_badge_class($igAvg) }}">
                                                        {{ number_format($igAvg, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                                @endif
                                            </span>
                                        @endunless
                                    </button>

                                    <div x-show="! collapsedGroups[{{ $itemGroup->id }}]">
                                    <div class="overflow-x-auto custom-scrollbar">
                                        <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-gray-700">
                                            <colgroup>
                                                <col class="w-10">
                                                <col>
                                                @if($isInventoryGroup)
                                                    <col class="w-24">
                                                    <col>
                                                @else
                                                    @foreach($scoreLabels as $label)
                                                        <col class="w-20">
                                                    @endforeach
                                                    <col class="w-20">
                                                @endif
                                            </colgroup>
                                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">No</th>
                                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Item</th>
                                                    @if($isInventoryGroup)
                                                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase w-24">Qty</th>
                                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Spesifikasi</th>
                                                    @else
                                                        @foreach($scoreLabels as $label)
                                                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase w-20">{{ $label }}</th>
                                                        @endforeach
                                                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase w-20">Avg</th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($itemGroup->items as $item)
                                                    @php
                                                        $response = $responses->get($item->id);
                                                        $scores = $response?->scores ?? [];
                                                        $avg = $response?->avg_score;
                                                    @endphp
                                                    <tr wire:key="item-{{ $item->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                                        <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ to_letter($loop->iteration) }}</td>
                                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                                                            {{ $item->name }}
                                                            @if($item->has_date_fields && $response)
                                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                                    @if($response->date_issued)Issued: {{ $response->date_issued->format('d M Y') }}@endif
                                                                    @if($response->date_expired) | Exp: {{ $response->date_expired->format('d M Y') }}@endif
                                                                </div>
                                                            @endif
                                                        </td>
                                                        @if($item->item_type === \App\Enums\SurveyItemType::Inventory)
                                                            <td class="px-3 py-2 text-center text-sm text-gray-900 dark:text-white">{{ $response?->qty ?? '-' }}</td>
                                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">{{ $response?->specification ?? '-' }}</td>
                                                        @else
                                                            @foreach($item->score_labels as $label)
                                                                <td class="px-3 py-2 text-center text-sm text-gray-900 dark:text-white">
                                                                    {{ $scores[$label] ?? '-' }}
                                                                </td>
                                                            @endforeach
                                                            <td class="px-3 py-2 text-center">
                                                                @if($avg !== null)
                                                                    @php
                                                                        $avgVal = (float) $avg;
                                                                    @endphp
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ survey_score_badge_class($avgVal) }}">
                                                                        {{ number_format($avgVal, 2) }}
                                                                    </span>
                                                                @else
                                                                    <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                                                @endif
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    @if($groupNotes->get($itemGroup->id)?->isNotEmpty())
                                        <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/20 px-3 py-2">
                                            <div class="flex flex-col sm:flex-row sm:items-start gap-1 sm:gap-2">
                                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 sm:w-16 shrink-0">Catatan</span>
                                                <ul class="flex-1 min-w-0 space-y-0.5">
                                                    @foreach($groupNotes->get($itemGroup->id) as $groupNote)
                                                        <li class="text-sm text-gray-900 dark:text-white">• {{ $groupNote->note }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    @endif
                                    </div>
                                </div>
                            @endforeach
                            </div>
                        @endif
                    @empty
                        <div class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                            Kategori ini belum memiliki sub kategori.
                        </div>
                    @endforelse
                @endif
            @endforeach
        </div>
    </div>

    <!-- Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-end">
        <x-cancel-button wire:click="$redirect(route('surveys.index'))" target="$redirect" class="w-full sm:w-auto" />
        @can('surveys_update', $survey)
            <x-loading-button wire:click="editSurvey" target="editSurvey" variant="primary" size="md" loadingText="Memuat..." icon="edit" class="w-full sm:w-auto">
                Edit Survey
            </x-loading-button>
        @endcan
    </div>
</div>
