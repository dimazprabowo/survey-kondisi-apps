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

        <!-- Structure Section (Nested Repeater) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Struktur Template
                        <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">({{ count($this->categories) }} kategori)</span>
                    </h3>
                    <x-loading-button wire:click="addCategory" target="addCategory" variant="primary" size="sm" icon="plus" loadingText="...">
                        Tambah Kategori
                    </x-loading-button>
                </div>

                <x-input-error :messages="$errors->get('categories')" class="mb-4" />

                @forelse($this->categories as $catIndex => $category)
                    <div wire:key="cat-{{ $catIndex }}-{{ $category['id'] ?? 'new' }}" class="border border-gray-200 dark:border-gray-700 rounded-lg mb-4 overflow-hidden">
                        <!-- Category Header -->
                        <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 flex items-center gap-3">
                            <div class="flex items-center gap-1">
                                <x-loading-button wire:click="moveCategoryUp({{ $catIndex }})" target="moveCategoryUp({{ $catIndex }})" variant="icon-gray" icon="arrow-up" wire:key="cat-up-{{ $catIndex }}" title="Naik" class="!p-1" />
                                <x-loading-button wire:click="moveCategoryDown({{ $catIndex }})" target="moveCategoryDown({{ $catIndex }})" variant="icon-gray" icon="arrow-down" wire:key="cat-down-{{ $catIndex }}" title="Turun" class="!p-1" />
                            </div>
                            <span class="text-sm font-bold text-gray-700 dark:text-gray-300 w-6 text-center">{{ $catIndex + 1 }}.</span>
                            <div class="flex-1 grid grid-cols-1 sm:grid-cols-12 gap-2">
                                <div class="sm:col-span-2">
                                    <x-text-input wire:model="categories.{{ $catIndex }}.code" type="text" class="block w-full" placeholder="Kode (mis. I)" />
                                </div>
                                <div class="sm:col-span-10">
                                    <x-text-input wire:model="categories.{{ $catIndex }}.label" type="text" class="block w-full" placeholder="Nama kategori (mis. Hull & Construction)" />
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('categories.'.$catIndex.'.label')" class="absolute" />
                            <x-loading-button wire:click="removeCategory({{ $catIndex }})" target="removeCategory({{ $catIndex }})" variant="icon-red" icon="delete" wire:key="cat-del-{{ $catIndex }}" title="Hapus Kategori" />
                        </div>

                        <!-- Sub-categories -->
                        <div class="px-4 py-3 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sub Kategori ({{ count($category['sub_categories'] ?? []) }})</h4>
                                <x-loading-button wire:click="addSubCategory({{ $catIndex }})" target="addSubCategory({{ $catIndex }})" variant="secondary" size="sm" icon="plus" wire:key="add-sub-{{ $catIndex }}" loadingText="...">
                                    Tambah Sub Kategori
                                </x-loading-button>
                            </div>

                            @foreach($category['sub_categories'] ?? [] as $subIndex => $subCategory)
                                <div wire:key="sub-{{ $catIndex }}-{{ $subIndex }}-{{ $subCategory['id'] ?? 'new' }}" class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                    <!-- Sub-category Header -->
                                    <div class="bg-gray-100 dark:bg-gray-700/30 px-3 py-2 flex items-center gap-2">
                                        <div class="flex items-center gap-1">
                                            <x-loading-button wire:click="moveSubCategoryUp({{ $catIndex }}, {{ $subIndex }})" target="moveSubCategoryUp({{ $catIndex }}, {{ $subIndex }})" variant="icon-gray" icon="arrow-up" wire:key="sub-up-{{ $catIndex }}-{{ $subIndex }}" title="Naik" class="!p-1" />
                                            <x-loading-button wire:click="moveSubCategoryDown({{ $catIndex }}, {{ $subIndex }})" target="moveSubCategoryDown({{ $catIndex }}, {{ $subIndex }})" variant="icon-gray" icon="arrow-down" wire:key="sub-down-{{ $catIndex }}-{{ $subIndex }}" title="Turun" class="!p-1" />
                                        </div>
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-400">{{ $catIndex + 1 }}.{{ $subIndex + 1 }}</span>
                                        <div class="flex-1">
                                            <x-text-input wire:model="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.name" type="text" class="block w-full" placeholder="Nama sub kategori" />
                                        </div>
                                        <x-loading-button wire:click="removeSubCategory({{ $catIndex }}, {{ $subIndex }})" target="removeSubCategory({{ $catIndex }}, {{ $subIndex }})" variant="icon-red" icon="delete" wire:key="sub-del-{{ $catIndex }}-{{ $subIndex }}" title="Hapus Sub Kategori" />
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
                                            <div wire:key="ig-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}-{{ $itemGroup['id'] ?? 'new' }}" class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                                                <!-- Item Group Header -->
                                                <div class="bg-gray-50 dark:bg-gray-800 px-3 py-2 flex items-center gap-2">
                                                    <div class="flex items-center gap-1">
                                                        <x-loading-button wire:click="moveItemGroupUp({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" target="moveItemGroupUp({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" variant="icon-gray" icon="arrow-up" wire:key="ig-up-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}" title="Naik" class="!p-1" />
                                                        <x-loading-button wire:click="moveItemGroupDown({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" target="moveItemGroupDown({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }})" variant="icon-gray" icon="arrow-down" wire:key="ig-down-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}" title="Turun" class="!p-1" />
                                                    </div>
                                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $catIndex + 1 }}.{{ $subIndex + 1 }}.{{ $igIndex + 1 }}</span>
                                                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-12 gap-2">
                                                        <div class="sm:col-span-3">
                                                            <x-text-input wire:model="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.item_groups.{{ $igIndex }}.code" type="text" class="block w-full" placeholder="Kode group" />
                                                        </div>
                                                        <div class="sm:col-span-9">
                                                            <x-text-input wire:model="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.item_groups.{{ $igIndex }}.name" type="text" class="block w-full" placeholder="Nama item group" />
                                                        </div>
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
                                                        <div wire:key="item-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}-{{ $itemIndex }}-{{ $item['id'] ?? 'new' }}" class="border border-gray-200 dark:border-gray-700 rounded p-2 bg-white dark:bg-gray-800">
                                                            <div class="flex items-center gap-2 mb-2">
                                                                <div class="flex items-center gap-1">
                                                                    <x-loading-button wire:click="moveItemUp({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" target="moveItemUp({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" variant="icon-gray" icon="arrow-up" wire:key="item-up-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}-{{ $itemIndex }}" title="Naik" class="!p-1" />
                                                                    <x-loading-button wire:click="moveItemDown({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" target="moveItemDown({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" variant="icon-gray" icon="arrow-down" wire:key="item-down-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}-{{ $itemIndex }}" title="Turun" class="!p-1" />
                                                                </div>
                                                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $itemIndex + 1 }}.</span>
                                                                <x-loading-button wire:click="removeItem({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" target="removeItem({{ $catIndex }}, {{ $subIndex }}, {{ $igIndex }}, {{ $itemIndex }})" variant="icon-red" icon="delete" wire:key="item-del-{{ $catIndex }}-{{ $subIndex }}-{{ $igIndex }}-{{ $itemIndex }}" title="Hapus Item" class="ml-auto" />
                                                            </div>
                                                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                                                                <div class="sm:col-span-2">
                                                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Kode</label>
                                                                    <x-text-input wire:model="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.item_groups.{{ $igIndex }}.items.{{ $itemIndex }}.code" type="text" class="block w-full" placeholder="Kode item" />
                                                                </div>
                                                                <div class="sm:col-span-7">
                                                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Nama Item</label>
                                                                    <x-text-input wire:model="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.item_groups.{{ $igIndex }}.items.{{ $itemIndex }}.name" type="text" class="block w-full" placeholder="Nama item survey" />
                                                                </div>
                                                                <div class="sm:col-span-3">
                                                                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-0.5">Score Labels (pisah koma)</label>
                                                                    <x-text-input wire:model.lazy="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.item_groups.{{ $igIndex }}.items.{{ $itemIndex }}.score_labels" type="text" class="block w-full" placeholder="C, V" />
                                                                </div>
                                                                <div class="sm:col-span-12 flex items-center gap-4 mt-1">
                                                                    <label class="flex items-center gap-2 cursor-pointer">
                                                                        <input type="checkbox" wire:model="categories.{{ $catIndex }}.sub_categories.{{ $subIndex }}.item_groups.{{ $igIndex }}.items.{{ $itemIndex }}.has_date_fields" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 dark:bg-gray-700">
                                                                        <span class="text-xs text-gray-600 dark:text-gray-400">Punya field tanggal (issued/expired)</span>
                                                                    </label>
                                                                </div>
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
                            @endforeach

                            @if(empty($category['sub_categories'] ?? []))
                                <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">Belum ada sub kategori. Klik "Tambah Sub Kategori" untuk menambah.</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-8">Belum ada kategori. Klik "Tambah Kategori" untuk menambah.</p>
                @endforelse
            </div>
        </div>

        <!-- Action Bar (Sticky) -->
        <div class="sticky bottom-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 -mx-4 px-4 py-3 sm:rounded-lg sm:shadow-lg z-10">
            <div class="flex flex-col sm:flex-row gap-3 sm:justify-end">
                <x-cancel-button wire:click="cancel" target="cancel" class="w-full sm:w-auto">
                    Batal
                </x-cancel-button>
                <x-loading-button wire:click="save" target="save" variant="primary" size="md" loadingText="Menyimpan..." icon="check" class="w-full sm:w-auto">
                    {{ $editMode ? 'Update Template' : 'Simpan Template' }}
                </x-loading-button>
            </div>
        </div>
    </form>
</div>
