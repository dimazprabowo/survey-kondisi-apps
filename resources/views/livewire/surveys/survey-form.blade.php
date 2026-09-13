<div
    x-data="{
        autoSaveTimer: null,
        lastSaved: null,
        init() {
            // Auto-save to Livewire session (debounced 2s) on any input change
            this.$wire.$watch('responses', () => this.scheduleSave());
            this.$wire.$watch('ship_id', () => this.scheduleSave());
            this.$wire.$watch('survey_date', () => this.scheduleSave());
            this.$wire.$watch('surveyor', () => this.scheduleSave());
            this.$wire.$watch('location', () => this.scheduleSave());
            this.$wire.$watch('notes', () => this.scheduleSave());
            this.$wire.$watch('status', () => this.scheduleSave());
        },
        scheduleSave() {
            if (this.$wire.editMode) return;
            clearTimeout(this.autoSaveTimer);
            this.autoSaveTimer = setTimeout(async () => {
                await this.$wire.saveDraft();
                this.lastSaved = new Date().toLocaleTimeString('id-ID');
            }, 2000);
        }
    }"
>
    <!-- Breadcrumb -->
    <nav class="mb-6 flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2 text-sm">
            <li>
                <a href="{{ route('surveys.index') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Survey Kondisi</a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li class="text-gray-900 dark:text-white font-medium">{{ $editMode ? 'Edit' : 'Buat' }}</li>
        </ol>
    </nav>

    <form wire:submit="save">
        <!-- Header Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                    Informasi Survey
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="ship_id" value="Kapal" :required="true" />
                        <x-searchable-select wire:model="ship_id" :options="$this->shipOptions" placeholder="Pilih kapal" searchPlaceholder="Cari kapal..." />
                        <x-input-error :messages="$errors->get('ship_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="survey_date" value="Tanggal Survey" :required="true" />
                        <x-text-input wire:model="survey_date" id="survey_date" type="date" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('survey_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" :required="true" />
                        <x-searchable-select wire:model="status" :options="$this->statusOptions" placeholder="Pilih status" />
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="surveyor" value="Surveyor" />
                                <x-text-input wire:model="surveyor" id="surveyor" type="text" class="mt-1 block w-full" placeholder="Nama surveyor" />
                                <x-input-error :messages="$errors->get('surveyor')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="location" value="Lokasi" />
                                <x-text-input wire:model="location" id="location" type="text" class="mt-1 block w-full" placeholder="Lokasi survey" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3" x-data="{ notesLength: @js(strlen($this->notes ?? '')) }">
                        <x-input-label for="notes" value="Catatan" />
                        <textarea wire:model="notes" id="notes" rows="3" maxlength="5000" x-on:input="notesLength = $event.target.value.length" placeholder="Catatan tambahan" class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-blue-500 focus:ring-blue-500 resize-y"></textarea>
                        <div class="flex justify-between items-center mt-1">
                            <x-input-error :messages="$errors->get('notes')" />
                            <span class="text-xs ml-auto" :class="notesLength > 4500 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500'" x-text="notesLength + '/5000'"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall CAP Score (live) -->
        <x-survey-cap-rating :score="$this->calculateOverallAvg()" />

        <!-- Category Tabs -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="border-b border-gray-200 dark:border-gray-700 overflow-x-auto custom-scrollbar">
                <nav class="flex space-x-2 px-4 py-2 min-w-max" aria-label="Tabs">
                    @foreach($categories as $cat)
                        @php
                            $catAvg = $this->calculateCategoryAvg($cat->id);
                            $isActive = $this->activeCategory == $cat->id;
                        @endphp
                        <button type="button" wire:click="setCategory({{ $cat->id }})" wire:target="setCategory({{ $cat->id }})"
                            class="px-3 py-2 text-sm font-medium rounded-md whitespace-nowrap transition-colors {{ $isActive ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/30' }}">
                            <span wire:loading.remove="inline" wire:target="setCategory({{ $cat->id }})">{{ $cat->code }}.</span>
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

            <!-- Active Category Content -->
            <div class="p-4 sm:p-6">
                @foreach($categories as $cat)
                    @if($this->activeCategory == $cat->id)
                        @php
                            $catAvg = $this->calculateCategoryAvg($cat->id);
                        @endphp
                        <!-- Category Header -->
                        <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                            <h4 class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ $cat->code }}. {{ $cat->label }}
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

                        @foreach($cat->subCategories as $subCat)
                            @php
                                $subCatAvg = $this->calculateSubCategoryAvg($subCat->id);
                            @endphp
                            <!-- Sub-Category -->
                            <div class="mb-6">
                                <div class="flex items-center justify-between mb-3 bg-gray-50 dark:bg-gray-700/30 px-3 py-2 rounded-md">
                                    <h5 class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $subCat->order_num }}. {{ $subCat->name }}
                                    </h5>
                                    @if($subCatAvg !== null)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ survey_score_badge_class($subCatAvg) }}">
                                            {{ number_format($subCatAvg, 2) }}
                                        </span>
                                    @endif
                                </div>

                                @foreach($subCat->itemGroups as $itemGroup)
                                    @php
                                        $igAvg = $this->calculateItemGroupAvg($itemGroup->id);
                                        $firstItem = $itemGroup->items->first();
                                        $scoreLabels = $firstItem ? $firstItem->score_labels : ['C', 'V'];
                                    @endphp
                                    <!-- Item Group -->
                                    <div class="mb-4 border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden">
                                        <div class="bg-gray-50 dark:bg-gray-700/20 px-3 py-2 flex items-center justify-between">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                @if($itemGroup->code){{ $itemGroup->code }}. @endif{{ $itemGroup->name }}
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs text-gray-500 dark:text-gray-400">Avg:</span>
                                                @if($igAvg !== null)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ survey_score_badge_class($igAvg) }}">
                                                        {{ number_format($igAvg, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Score header row -->
                                        <div class="overflow-x-auto custom-scrollbar">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">No</th>
                                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Item</th>
                                                        @foreach($scoreLabels as $label)
                                                            <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase w-20">{{ $label }}</th>
                                                        @endforeach
                                                        <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase w-20">Avg</th>
                                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Note</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($itemGroup->items as $item)
                                                        @php
                                                            $itemAvg = $this->calculateItemAvg($item->id);
                                                            $response = $this->responses[$item->id] ?? ['scores' => [], 'note' => ''];
                                                        @endphp
                                                        <tr wire:key="item-{{ $item->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                                            <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ $item->code }}</td>
                                                            <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                                                                {{ $item->name }}
                                                                @if($item->has_date_fields)
                                                                    <div class="mt-1 flex flex-col gap-1">
                                                                        <div class="flex items-center gap-1">
                                                                            <span class="text-xs text-gray-400 w-14">Issued:</span>
                                                                            <input type="date" wire:model="responses.{{ $item->id }}.date_issued" class="text-xs border border-gray-300 dark:border-gray-600 rounded px-1 py-0.5 dark:bg-gray-700 dark:text-white" />
                                                                        </div>
                                                                        <div class="flex items-center gap-1">
                                                                            <span class="text-xs text-gray-400 w-14">Exp:</span>
                                                                            <input type="date" wire:model="responses.{{ $item->id }}.date_expired" class="text-xs border border-gray-300 dark:border-gray-600 rounded px-1 py-0.5 dark:bg-gray-700 dark:text-white" />
                                                                        </div>
                                                                    </div>
                                                                @endif
                                                            </td>
                                                            @foreach($item->score_labels as $label)
                                                                <td class="px-3 py-2 text-center">
                                                                    <select wire:model.live="responses.{{ $item->id }}.scores.{{ $label }}"
                                                                        class="w-16 text-sm border border-gray-300 dark:border-gray-600 rounded px-1 py-1 dark:bg-gray-700 dark:text-white text-center focus:ring-2 focus:ring-blue-500">
                                                                        <option value="">-</option>
                                                                        <option value="1">1</option>
                                                                        <option value="2">2</option>
                                                                        <option value="3">3</option>
                                                                        <option value="4">4</option>
                                                                    </select>
                                                                </td>
                                                            @endforeach
                                                            <td class="px-3 py-2 text-center">
                                                                @if($itemAvg !== null)
                                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ survey_score_badge_class($itemAvg) }}">
                                                                        {{ number_format($itemAvg, 2) }}
                                                                    </span>
                                                                @else
                                                                    <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                                                @endif
                                                            </td>
                                                            <td class="px-3 py-2">
                                                                <input type="text" wire:model="responses.{{ $item->id }}.note"
                                                                    placeholder="Lokasi temuan+kondisi kerusakan"
                                                                    class="w-full text-sm border border-gray-300 dark:border-gray-600 rounded px-2 py-1 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" />
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
            <!-- Draft auto-save indicator (create mode only) -->
            @if(! $editMode)
                <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 mr-auto" x-show="lastSaved" x-cloak>
                    <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Draft tersimpan <span x-text="lastSaved"></span></span>
                </div>
            @endif
            <x-cancel-button wire:click="cancel" target="cancel" class="w-full sm:w-auto" />
            <x-loading-button type="submit" target="save" variant="primary" size="lg" loadingText="Menyimpan..." class="w-full sm:w-auto">
                {{ $editMode ? 'Update Survey' : 'Simpan Survey' }}
            </x-loading-button>
        </div>
    </form>
</div>
