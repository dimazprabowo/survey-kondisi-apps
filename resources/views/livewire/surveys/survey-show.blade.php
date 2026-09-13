<div>
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
                    <div class="text-gray-900 dark:text-white">{{ $survey->template?->name ?? '-' }}</div>
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
        <div class="border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
            <nav class="flex space-x-2 px-4 py-2 min-w-max" aria-label="Tabs">
                @foreach($categories as $cat)
                    @php $isActive = $this->activeCategory == $cat->id; @endphp
                    <button type="button" wire:click="setCategory({{ $cat->id }})"
                        class="px-3 py-2 text-sm font-medium rounded-md whitespace-nowrap transition-colors {{ $isActive ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/30' }}">
                        {{ $cat->code }}. {{ $cat->label }}
                    </button>
                @endforeach
            </nav>
        </div>

        <div class="p-4 sm:p-6">
            @foreach($categories as $cat)
                @if($this->activeCategory == $cat->id)
                    <h4 class="text-base font-semibold text-gray-900 dark:text-white mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                        {{ $cat->code }}. {{ $cat->label }}
                    </h4>

                    @foreach($cat->subCategories as $subCat)
                        <div class="mb-6">
                            <h5 class="text-sm font-semibold text-gray-900 dark:text-white mb-3 bg-gray-50 dark:bg-gray-700/30 px-3 py-2 rounded-md">
                                {{ $subCat->order_num }}. {{ $subCat->name }}
                            </h5>

                            @foreach($subCat->itemGroups as $itemGroup)
                                @php
                                    $firstItem = $itemGroup->items->first();
                                    $scoreLabels = $firstItem ? $firstItem->score_labels : ['C', 'V'];
                                @endphp
                                <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden">
                                    <div class="bg-gray-50 dark:bg-gray-700/20 px-3 py-2 text-sm font-medium text-gray-900 dark:text-white">
                                        @if($itemGroup->code){{ $itemGroup->code }}. @endif{{ $itemGroup->name }}
                                    </div>

                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">No</th>
                                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Item</th>
                                                    @foreach($scoreLabels as $label)
                                                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase w-16">{{ $label }}</th>
                                                    @endforeach
                                                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase w-16">Avg</th>
                                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Note</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($itemGroup->items as $item)
                                                    @php
                                                        $response = $responses->get($item->id);
                                                        $scores = $response?->scores ?? [];
                                                        $avg = $response?->avg_score;
                                                    @endphp
                                                    <tr wire:key="item-{{ $item->id }}">
                                                        <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ $item->code }}</td>
                                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                                                            {{ $item->name }}
                                                            @if($item->has_date_fields && $response)
                                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                                    @if($response->date_issued)Issued: {{ $response->date_issued->format('d M Y') }}@endif
                                                                    @if($response->date_expired) | Exp: {{ $response->date_expired->format('d M Y') }}@endif
                                                                </div>
                                                            @endif
                                                        </td>
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
                                                        <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                            {{ $response?->note ?? '-' }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
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
