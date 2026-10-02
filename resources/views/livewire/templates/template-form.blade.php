<div>
    <!-- Breadcrumb -->
    <nav class="mb-6 flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2 text-sm">
            <li>
                <a href="{{ route('master-data.survey-templates.index') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Template Form</a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li class="text-gray-900 dark:text-white font-medium">{{ $editMode ? 'Edit' : 'Buat' }}</li>
        </ol>
    </nav>

    <form wire:submit="save">
        <!-- Metadata Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                    Informasi Template
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="sm:col-span-2 lg:col-span-2">
                        <x-input-label for="name" value="Nama Template" :required="true" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" placeholder="Nama template survey" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="code" value="Kode Template" :required="true" />
                        <x-text-input wire:model="code" id="code" type="text" class="mt-1 block w-full" placeholder="Kode unik (mis. SK-DEFAULT)" />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3" x-data="{ descLength: @js(strlen($this->description ?? '')) }">
                        <x-input-label for="description" value="Deskripsi" />
                        <textarea wire:model="description" id="description" rows="2" maxlength="5000" x-on:input="descLength = $event.target.value.length" placeholder="Deskripsi template survey" class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-blue-500 focus:ring-blue-500 resize-y"></textarea>
                        <div class="flex justify-between items-center mt-1">
                            <x-input-error :messages="$errors->get('description')" />
                            <span class="text-xs ml-auto" :class="descLength > 4500 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500'" x-text="descLength + '/5000'"></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Aktif</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" wire:model="is_default" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Default</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Structure Section (Category Tabs) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 pt-5 pb-3 sm:px-6 sm:pt-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Struktur Template
                    <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">({{ count($this->categories) }} kategori)</span>
                </h3>
                <x-input-error :messages="$errors->get('categories')" class="mt-1" />
            </div>

            <!-- Category Tab Strip -->
            <div class="border-y border-gray-200 dark:border-gray-700 overflow-x-auto custom-scrollbar">
                <nav class="flex items-center gap-1 px-4 sm:px-6 py-2 min-w-max" aria-label="Tab Kategori">
                    @foreach($this->categories as $tabIndex => $tab)
                        @php
                            $isActive = $activeCategoryTab === $tabIndex;
                            $tabHasError = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, "categories.{$tabIndex}."));
                        @endphp
                        <div wire:key="tab-{{ $tab['row_key'] }}-{{ $tabIndex }}"
                            class="flex items-center rounded-md transition-colors {{ $isActive ? 'bg-blue-100 dark:bg-blue-900/30' : 'hover:bg-gray-100 dark:hover:bg-gray-700/40' }}">
                            <button type="button" wire:click="setActiveCategoryTab({{ $tabIndex }})" wire:target="setActiveCategoryTab({{ $tabIndex }})" wire:loading.attr="disabled" wire:key="tab-btn-{{ $tab['row_key'] }}-{{ $tabIndex }}"
                                class="inline-flex items-center gap-1.5 pl-3 pr-1 py-2 text-sm font-medium whitespace-nowrap transition-colors {{ $isActive ? 'text-blue-700 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                <span>
                                    {{ to_roman($tabIndex + 1) }}.
                                    @if(filled($tab['label'] ?? ''))
                                        {{ $tab['label'] }}
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 font-normal italic">Tab Baru</span>
                                    @endif
                                </span>
                                @if($tabHasError)
                                    <span class="h-1.5 w-1.5 rounded-full bg-red-500" title="Ada error validasi di kategori ini"></span>
                                @endif
                            </button>
                            <button type="button" wire:click="confirmRemoveCategory({{ $tabIndex }})" wire:target="setActiveCategoryTab({{ $tabIndex }}), confirmRemoveCategory({{ $tabIndex }}), removeCategory({{ $tabIndex }})" wire:loading.attr="disabled" wire:key="tab-del-{{ $tab['row_key'] }}-{{ $tabIndex }}" title="Hapus Kategori"
                                class="inline-flex items-center justify-center pr-2 pl-0.5 py-2 transition-colors {{ $isActive ? 'text-blue-400 hover:text-red-600 dark:text-blue-500 dark:hover:text-red-400' : 'text-gray-400 hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400' }}">
                                <svg wire:loading wire:target="setActiveCategoryTab({{ $tabIndex }}), confirmRemoveCategory({{ $tabIndex }}), removeCategory({{ $tabIndex }})" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg wire:loading.class="hidden" wire:target="setActiveCategoryTab({{ $tabIndex }}), confirmRemoveCategory({{ $tabIndex }}), removeCategory({{ $tabIndex }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    @endforeach
                    <x-loading-button wire:click="addCategory" target="addCategory" variant="icon-blue" icon="plus" wire:key="tab-add" title="Tambah Kategori" />
                </nav>
            </div>

            <!-- Active Category Panel -->
            <div class="px-4 py-5 sm:p-6">
                @if(isset($this->categories[$activeCategoryTab]))
                    @php
                        $catIndex = $activeCategoryTab;
                        $category = $this->categories[$catIndex];
                    @endphp

                    {{-- Keyed per category: switching tabs replaces the whole subtree so input nodes are re-created fresh (prevents stale DOM values leaking between categories) --}}
                    <div wire:key="cat-panel-{{ $category['row_key'] }}-{{ $catIndex }}">
                    <!-- Category Fields -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 pb-4 mb-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1">
                                <x-loading-button wire:click="moveCategoryUp({{ $catIndex }})" target="moveCategoryUp({{ $catIndex }})" variant="icon-gray" icon="arrow-left" wire:key="cat-up-{{ $catIndex }}" title="Geser ke Kiri" class="!p-1" :disabled="$catIndex === 0" />
                                <x-loading-button wire:click="moveCategoryDown({{ $catIndex }})" target="moveCategoryDown({{ $catIndex }})" variant="icon-gray" icon="arrow-right" wire:key="cat-down-{{ $catIndex }}" title="Geser ke Kanan" class="!p-1" :disabled="$catIndex === count($this->categories) - 1" />
                            </div>
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ to_roman($catIndex + 1) }}.</span>
                        </div>
                        <div class="flex-1 grid grid-cols-1 gap-2">
                            <div>
                                <x-text-input wire:model.live.debounce.500ms="categories.{{ $catIndex }}.label" type="text" class="block w-full" placeholder="Nama kategori (mis. Hull & Construction)" />
                                <x-input-error :messages="$errors->get('categories.'.$catIndex.'.label')" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <!-- Sub-categories (Tabs) -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Sub Kategori ({{ count($category['sub_categories'] ?? []) }})</h4>

                        <!-- Sub-category Tab Strip -->
                        <div class="border border-gray-200 dark:border-gray-700 rounded-md overflow-x-auto custom-scrollbar mb-3 bg-gray-50/50 dark:bg-gray-700/20">
                            <nav class="flex items-center gap-1 px-2 py-1.5 min-w-max" aria-label="Tab Sub Kategori">
                                @foreach($category['sub_categories'] ?? [] as $subTabIndex => $subTab)
                                        @php
                                            $subIsActive = $activeSubCategoryTab === $subTabIndex;
                                            $subHasError = collect($errors->keys())->contains(fn ($k) => str_starts_with($k, "categories.{$catIndex}.sub_categories.{$subTabIndex}."));
                                        @endphp
                                        <div wire:key="sub-tab-{{ $subTab['row_key'] }}-{{ $subTabIndex }}"
                                            class="flex items-center rounded-md transition-colors {{ $subIsActive ? 'bg-blue-100 dark:bg-blue-900/30' : 'hover:bg-gray-100 dark:hover:bg-gray-700/40' }}">
                                            <button type="button" wire:click="setActiveSubCategoryTab({{ $subTabIndex }})" wire:target="setActiveSubCategoryTab({{ $subTabIndex }})" wire:loading.attr="disabled" wire:key="sub-tab-btn-{{ $subTab['row_key'] }}-{{ $subTabIndex }}"
                                                class="inline-flex items-center gap-1.5 pl-2.5 pr-1 py-1.5 text-xs font-medium whitespace-nowrap transition-colors {{ $subIsActive ? 'text-blue-700 dark:text-blue-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                                <span>
                                                    {{ $subTabIndex + 1 }}.
                                                    @if(filled($subTab['name'] ?? ''))
                                                        {{ $subTab['name'] }}
                                                    @else
                                                        <span class="text-gray-400 dark:text-gray-500 font-normal italic">Sub Baru</span>
                                                    @endif
                                                </span>
                                                @if($subHasError)
                                                    <span class="h-1.5 w-1.5 rounded-full bg-red-500" title="Ada error validasi di sub kategori ini"></span>
                                                @endif
                                            </button>
                                            <button type="button" wire:click="confirmRemoveSubCategory({{ $catIndex }}, {{ $subTabIndex }})" wire:target="setActiveSubCategoryTab({{ $subTabIndex }}), confirmRemoveSubCategory({{ $catIndex }}, {{ $subTabIndex }}), removeSubCategory({{ $catIndex }}, {{ $subTabIndex }})" wire:loading.attr="disabled" wire:key="sub-tab-del-{{ $subTab['row_key'] }}-{{ $catIndex }}-{{ $subTabIndex }}" title="Hapus Sub Kategori"
                                                class="inline-flex items-center justify-center pr-1.5 pl-0.5 py-1.5 transition-colors {{ $subIsActive ? 'text-blue-400 hover:text-red-600 dark:text-blue-500 dark:hover:text-red-400' : 'text-gray-400 hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400' }}">
                                                <svg wire:loading wire:target="setActiveSubCategoryTab({{ $subTabIndex }}), confirmRemoveSubCategory({{ $catIndex }}, {{ $subTabIndex }}), removeSubCategory({{ $catIndex }}, {{ $subTabIndex }})" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                                <svg wire:loading.class="hidden" wire:target="setActiveSubCategoryTab({{ $subTabIndex }}), confirmRemoveSubCategory({{ $catIndex }}, {{ $subTabIndex }}), removeSubCategory({{ $catIndex }}, {{ $subTabIndex }})" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                @endforeach
                                <x-loading-button wire:click="addSubCategory({{ $catIndex }})" target="addSubCategory({{ $catIndex }})" variant="icon-blue" icon="plus" wire:key="sub-add-{{ $catIndex }}" title="Tambah Sub Kategori" class="!p-1" />
                            </nav>
                        </div>

                        @if(!empty($category['sub_categories'] ?? []))
                            <!-- Active Sub-category Panel -->
                            @php
                                $subIndex = isset($category['sub_categories'][$activeSubCategoryTab]) ? $activeSubCategoryTab : 0;
                                $subCategory = $category['sub_categories'][$subIndex];
                            @endphp
                            <div wire:key="sub-panel-{{ $subCategory['row_key'] }}-{{ $subIndex }}" class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                <!-- Sub-category Header -->
                                <div class="bg-gray-100 dark:bg-gray-700/30 px-3 py-2 flex items-center gap-2">
                                    <div class="flex items-center gap-1">
                                        <x-loading-button wire:click="moveSubCategoryUp({{ $catIndex }}, {{ $subIndex }})" target="moveSubCategoryUp({{ $catIndex }}, {{ $subIndex }})" variant="icon-gray" icon="arrow-left" wire:key="sub-up-{{ $catIndex }}-{{ $subIndex }}" title="Geser ke Kiri" class="!p-1" :disabled="$subIndex === 0" />
                                        <x-loading-button wire:click="moveSubCategoryDown({{ $catIndex }}, {{ $subIndex }})" target="moveSubCategoryDown({{ $catIndex }}, {{ $subIndex }})" variant="icon-gray" icon="arrow-right" wire:key="sub-down-{{ $catIndex }}-{{ $subIndex }}" title="Geser ke Kanan" class="!p-1" :disabled="$subIndex === count($category['sub_categories']) - 1" />
                                    </div>
                                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ $subIndex + 1 }}.</span>
                                    <div class="flex-1">
                                        <x-text-input wire:model.live.debounce.500ms="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.name" type="text" class="block w-full" placeholder="Nama sub kategori" />
                                        <x-input-error :messages="$errors->get('categories.'.$catIndex.'.sub_categories.'.$subIndex.'.name')" class="mt-1" />
                                    </div>
                                </div>

                                <!-- Item Groups -->
                                <div class="px-3 py-2 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <h5 class="text-xs font-semibold text-gray-600 dark:text-gray-400">Item Group ({{ count($subCategory['item_groups'] ?? []) }})</h5>
                                        <x-loading-button wire:click="addItemGroup({{ $catIndex }}, {{ $subIndex }})" target="addItemGroup({{ $catIndex }}, {{ $subIndex }})" variant="secondary" size="sm" icon="plus" wire:key="add-ig-{{ $catIndex }}-{{ $subIndex }}" loadingText="...">
                                            Tambah Item Group
                                        </x-loading-button>
                                    </div>

                                    @foreach($subCategory['item_groups'] ?? [] as $igIndex => $itemGroup)
                                        <div wire:key="ig-{{ $itemGroup['row_key'] }}-{{ $igIndex }}" class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                            <!-- Item Group Header -->
                                            <div class="bg-gray-50 dark:bg-gray-800 px-3 py-2 flex items-center gap-2">
                                                <div class="flex items-center gap-1">
                                                    <x-loading-button wire:click="moveItemGroupUp({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" target="moveItemGroupUp({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" variant="icon-gray" icon="arrow-up" wire:key="ig-up-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}" title="Naik" class="!p-1" :disabled="$igIndex === 0" />
                                                    <x-loading-button wire:click="moveItemGroupDown({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" target="moveItemGroupDown({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" variant="icon-gray" icon="arrow-down" wire:key="ig-down-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}" title="Turun" class="!p-1" :disabled="$igIndex === count($subCategory['item_groups']) - 1" />
                                                </div>
                                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $subIndex + 1 }}.{{ $igIndex + 1 }}</span>
                                                <div class="flex-1">
                                                    <x-text-input wire:model="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.item_groups.{{ $igIndex }}.name" type="text" class="block w-full" placeholder="Nama item group" />
                                                    <x-input-error :messages="$errors->get('categories.'.$catIndex.'.sub_categories.'.$subIndex.'.item_groups.'.$igIndex.'.name')" class="mt-1" />
                                                </div>
                                                <x-loading-button wire:click="removeItemGroup({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" target="removeItemGroup({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" variant="icon-red" icon="delete" wire:key="ig-del-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}" title="Hapus Item Group" />
                                            </div>

                                            <!-- Items -->
                                            <div class="px-3 py-2 space-y-2">
                                                <div class="flex items-center justify-between">
                                                    <h6 class="text-xs font-semibold text-gray-500 dark:text-gray-400">Items ({{ count($itemGroup['items'] ?? []) }})</h6>
                                                    <x-loading-button wire:click="addItem({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" target="addItem({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" variant="secondary" size="sm" icon="plus" wire:key="add-item-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}" loadingText="...">
                                                        Tambah Item
                                                    </x-loading-button>
                                                </div>

                                                @foreach($itemGroup['items'] ?? [] as $itemIndex => $item)
                                                    <div wire:key="item-{{ $item['row_key'] }}-{{ $itemIndex }}" class="border border-gray-200 dark:border-gray-700 rounded p-2 bg-white dark:bg-gray-800">
                                                        <div class="flex items-center gap-2 mb-2">
                                                            <div class="flex items-center gap-1">
                                                                <x-loading-button wire:click="moveItemUp({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" target="moveItemUp({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" variant="icon-gray" icon="arrow-up" wire:key="item-up-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}-{{ $itemIndex }}" title="Naik" class="!p-1" :disabled="$itemIndex === 0" />
                                                                <x-loading-button wire:click="moveItemDown({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" target="moveItemDown({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" variant="icon-gray" icon="arrow-down" wire:key="item-down-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}-{{ $itemIndex }}" title="Turun" class="!p-1" :disabled="$itemIndex === count($itemGroup['items']) - 1" />
                                                            </div>
                                                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ to_letter($itemIndex + 1) }}.</span>
                                                            <x-loading-button wire:click="removeItem({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" target="removeItem({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" variant="icon-red" icon="delete" wire:key="item-del-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}-{{ $itemIndex }}" title="Hapus Item" class="ml-auto" />
                                                        </div>
                                                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                                                            <div class="sm:col-span-6">
                                                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Nama Item</label>
                                                                <x-text-input wire:model="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.item_groups.{{ $igIndex }}.items.{{ $itemIndex }}.name" type="text" class="block w-full" placeholder="Nama item survey" />
                                                                <x-input-error :messages="$errors->get('categories.'.$catIndex.'.sub_categories.'.$subIndex.'.item_groups.'.$igIndex.'.items.'.$itemIndex.'.name')" class="mt-1" />
                                                            </div>
                                                            <div class="sm:col-span-3">
                                                                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Tipe Item</label>
                                                                <select wire:change="updateItemType({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }}, $event.target.value)" class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600">
                                                                    @foreach(\App\Enums\SurveyItemType::cases() as $type)
                                                                        <option value="{{ $type->value }}" @selected(($item['item_type'] ?? 'score') === $type->value)>{{ $type->label() }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                            <div class="sm:col-span-3">
                                                                @if(($item['item_type'] ?? 'score') === 'score')
                                                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Score Labels (pisah koma)</label>
                                                                    <x-text-input
                                                                        value="{{ implode(', ', $item['score_labels'] ?? ['C', 'V']) }}"
                                                                        wire:blur="updateScoreLabels({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }}, $event.target.value)"
                                                                        type="text" class="block w-full" placeholder="C, V" />
                                                                @endif
                                                            </div>
                                                            @if(($item['item_type'] ?? 'score') === 'score')
                                                                <div class="sm:col-span-12 flex items-center gap-4 mt-1">
                                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                                        <input type="checkbox" wire:model="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.item_groups.{{ $igIndex }}.items.{{ $itemIndex }}.has_date_fields" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700">
                                                                        <span class="text-xs text-gray-600 dark:text-gray-400">Punya field tanggal (issued/expired)</span>
                                                                    </label>
                                                                </div>
                                                            @else
                                                                <div class="sm:col-span-12 mt-1">
                                                                    <p class="text-xs text-gray-400 dark:text-gray-500 italic">Item inventaris: diisi dengan Qty &amp; Spesifikasi saat survey (tanpa skor).</p>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach

                                                @if(empty($itemGroup['items'] ?? []))
                                                    <p class="text-xs text-gray-400 dark:text-gray-500 text-center py-2">Belum ada item. Klik "Tambah Item" untuk menambah.</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                    @if(empty($subCategory['item_groups'] ?? []))
                                        <p class="text-xs text-gray-400 dark:text-gray-500 text-center py-2">Belum ada item group. Klik "Tambah Item Group" untuk menambah.</p>
                                    @endif
                                </div>
                            </div>
                        @else
                            <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">Belum ada sub kategori. Klik tombol + di atas untuk menambah.</p>
                        @endif
                    </div>
                    </div>
                @else
                    <div wire:key="cat-panel-empty" class="py-12 text-center">
                        <svg class="h-12 w-12 mx-auto text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada kategori.</p>
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Klik tombol + pada tab untuk menambah kategori baru.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Action Bar (Sticky, width matches cards above) -->
        <div class="sticky bottom-0 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-3 px-4 sm:px-6 z-10">
            <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                <x-cancel-button wire:click="cancel" target="cancel" class="w-full sm:w-auto sm:min-w-[160px]" />
                <x-loading-button wire:click="save" target="save" variant="primary" size="lg" loadingText="Menyimpan..." class="w-full sm:w-auto sm:min-w-[160px]">
                    {{ $editMode ? 'Update Template' : 'Simpan Template' }}
                </x-loading-button>
            </div>
        </div>
    </form>

    <!-- Confirm Remove Category Modal -->
    <x-confirm-modal eventName="confirm-remove-category" />

    <!-- Confirm Remove Sub-category Modal -->
    <x-confirm-modal eventName="confirm-remove-sub" />
</div>
