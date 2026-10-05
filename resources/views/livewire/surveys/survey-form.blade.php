<div
    x-data="{
        autoSaveTimer: null,
        lastSaved: null,
        dirty: false,
        // State collapse per item-group bertahan di root — tidak ikut
        // re-init saat Livewire morph (mis. pindah tab lalu kembali)
        collapsedGroups: {},
        init() {
            // Draft yang ter-restore = form sudah punya perubahan
            this.dirty = !! this.$wire.hasDraft;
            // Watch top-level scalars (covers programmatic changes like searchable-select)
            this.$wire.$watch('ship_id', () => this.onFieldChange('ship_id'));
            this.$wire.$watch('survey_date', () => this.onFieldChange());
            this.$wire.$watch('surveyor', () => this.onFieldChange());
            this.$wire.$watch('location', () => this.onFieldChange());
            this.$wire.$watch('notes', () => this.onFieldChange());
            this.$wire.$watch('status', () => this.onFieldChange('status'));
            // Nested arrays (responses/groupNotes) are covered by delegated
            // input/change listeners on the root — root-level $watch cannot
            // detect deferred nested mutations.

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
        flashField(el) {
            el.classList.remove('autosave-glow');
            void el.offsetWidth; // restart animasi
            el.classList.add('autosave-glow');
        },
        flashByModel(model) {
            const el = this.$root.querySelector('.autosave-field[data-model=' + model + ']');
            if (el) this.flashField(el);
        },
        markDirty() {
            this.dirty = true;
        },
        onFieldChange(model = null) {
            this.dirty = true;
            if (model && this.$wire.autoSave) this.flashByModel(model);
            this.scheduleSave();
        },
        handleInput(e) {
            this.dirty = true;
            if (! this.$wire.autoSave) return;
            const el = e.target;
            // Flash hijau langsung saat field diubah (sekali saja)
            if (el.classList.contains('autosave-field')) {
                this.flashField(el);
            }
            this.scheduleSave();
        },
        scheduleSave() {
            if (! this.$wire.autoSave) return;
            clearTimeout(this.autoSaveTimer);
            this.autoSaveTimer = setTimeout(async () => {
                await this.$wire.saveDraft();
                this.lastSaved = new Date().toLocaleTimeString('id-ID');
            }, 1200);
        },
        handleKeydown(e) {
            const key = e.key;
            if (key !== 'ArrowUp' && key !== 'ArrowDown' && key !== 'ArrowLeft' && key !== 'ArrowRight') return;
            const el = e.target;
            if (! el.classList || ! el.classList.contains('autosave-field')) return;

            // Input teks/angka/textarea: panah kiri/kanan tetap untuk caret,
            // hijack hanya saat caret sudah di ujung teks
            const isText = el.type === 'text' || el.type === 'number' || el.tagName === 'TEXTAREA';
            if (isText) {
                const val = el.value || '';
                const start = el.selectionStart;
                const end = el.selectionEnd;
                if (key === 'ArrowLeft' && start !== null && start !== 0) return;
                if (key === 'ArrowRight' && end !== null && end !== val.length) return;
                if (el.tagName === 'TEXTAREA') {
                    const firstNl = val.indexOf('\n');
                    const lastNl = val.lastIndexOf('\n');
                    if (key === 'ArrowUp' && firstNl !== -1 && start > firstNl) return;
                    if (key === 'ArrowDown' && lastNl !== -1 && ! (start > lastNl)) return;
                }
            }

            const target = this.nextField(el, key);
            if (target) {
                e.preventDefault();
                target.focus();
                if (typeof target.select === 'function') target.select();
                this.scrollFieldIntoView(target);
                return;
            }

            // Di batas field terakhir: blok perilaku native arrow yang mengubah
            // nilai (select cycle opsi, number/date increment) — nilai tidak boleh
            // berubah hanya karena user menekan arrow
            const isSelect = el.tagName === 'SELECT';
            const vertical = key === 'ArrowUp' || key === 'ArrowDown';
            if (isSelect || (vertical && (el.type === 'number' || el.type === 'date'))) {
                e.preventDefault();
            }
        },
        scrollFieldIntoView(el) {
            // Gulung horizontal/vertikal minimal agar field terlihat
            el.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            // Koreksi overlay elemen sticky (action bar bawah, header atas):
            // fokus native bisa menempatkan field tepat di bawah sticky bar
            requestAnimationFrame(() => {
                const margin = 96;
                const r = el.getBoundingClientRect();
                const overflow = r.bottom + margin - window.innerHeight;
                if (overflow > 0) {
                    window.scrollBy(0, overflow);
                } else if (margin - r.top > 0) {
                    window.scrollBy(0, r.top - margin);
                }
            });
        },
        nextField(el, key) {
            const fields = Array.from(this.$root.querySelectorAll('.autosave-field'))
                .filter((f) => ! f.disabled && f.offsetParent !== null);
            const idx = fields.indexOf(el);
            if (idx === -1) return null;

            // Kiri/kanan = field sebelumnya/berikutnya (DOM order = visual order)
            if (key === 'ArrowLeft') return fields[idx - 1] || null;
            if (key === 'ArrowRight') return fields[idx + 1] || null;

            // Atas/bawah = field terdekat di kolom yang sama (seperti sel Excel)
            const cur = el.getBoundingClientRect();
            const cx = cur.left + cur.width / 2;
            const cy = cur.top + cur.height / 2;
            let best = null;
            let bestScore = Infinity;

            fields.forEach((f) => {
                if (f === el) return;
                const r = f.getBoundingClientRect();
                const fy = r.top + r.height / 2;
                const dy = key === 'ArrowDown' ? fy - cy : cy - fy;
                if (! (dy > 4)) return;
                const dx = Math.abs(r.left + r.width / 2 - cx);
                const score = dy + dx * 3;
                if (bestScore - score > 0) {
                    bestScore = score;
                    best = f;
                }
            });

            return best;
        },
        requestCancel() {
            if (! this.dirty) {
                this.$wire.cancel();
                return;
            }
            this.$dispatch('confirm-cancel-survey', {
                title: 'Buang Perubahan?',
                message: 'Ada perubahan yang belum disimpan. Jika dibatalkan, semua isian form akan dibuang.',
                confirmText: 'Ya, Buang',
                cancelText: 'Kembali',
                type: 'warning',
                action: 'cancel',
            });
        }
    }"
    x-on:input="handleInput($event)"
    x-on:change="handleInput($event)"
    x-on:keydown="handleKeydown($event)"
    x-on:group-note-removed.window="onFieldChange()"
    x-on:structure-node-removed.window="onFieldChange()"
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
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Informasi Survey
                    </h3>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Edit Struktur</span>
                            <x-toggle-switch wire:click="toggleEditStructure" :active="$editStructure" target="toggleEditStructure" activeColor="amber" title="Aktifkan/nonaktifkan mode hapus struktur (kategori, sub kategori, grup item, item)" />
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Auto Save</span>
                            <x-toggle-switch wire:click="toggleAutoSave" :active="$autoSave" target="toggleAutoSave" activeColor="green" title="Aktifkan/nonaktifkan penyimpanan draft otomatis" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="ship_id" value="Kapal" :required="true" />
                        <x-searchable-select wire:model="ship_id" :options="$this->shipOptions" placeholder="Pilih kapal" searchPlaceholder="Cari kapal..." class="autosave-field" data-model="ship_id" />
                        <x-input-error :messages="$errors->get('ship_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="survey_date" value="Tanggal Survey" :required="true" />
                        <x-text-input wire:model="survey_date" id="survey_date" type="date" class="mt-1 block w-full autosave-field" />
                        <x-input-error :messages="$errors->get('survey_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" :required="true" />
                        <x-searchable-select wire:model="status" :options="$this->statusOptions" placeholder="Pilih status" class="autosave-field" data-model="status" />
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="surveyor" value="Surveyor" />
                                <x-text-input wire:model="surveyor" id="surveyor" type="text" class="mt-1 block w-full autosave-field" placeholder="Nama surveyor" />
                                <x-input-error :messages="$errors->get('surveyor')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="location" value="Lokasi" />
                                <x-text-input wire:model="location" id="location" type="text" class="mt-1 block w-full autosave-field" placeholder="Lokasi survey" />
                                <x-input-error :messages="$errors->get('location')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3" x-data="{ notesLength: @js(strlen($this->notes ?? '')) }">
                        <x-input-label for="notes" value="Catatan" />
                        <textarea wire:model="notes" id="notes" rows="3" maxlength="5000" x-on:input="notesLength = $event.target.value.length" placeholder="Catatan tambahan" class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-blue-500 focus:ring-blue-500 resize-y autosave-field"></textarea>
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
                        @php
                            $catTabPillClass = $isActive
                                ? 'bg-blue-100 dark:bg-blue-900/30'
                                : 'hover:bg-gray-50 dark:hover:bg-gray-700/30';
                            $catTabTextClass = $isActive
                                ? 'text-blue-700 dark:text-blue-400'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300';
                        @endphp
                        <div class="flex items-center rounded-md transition-colors {{ $catTabPillClass }}" wire:key="cattab-wrap-{{ $cat->id }}">
                            <button type="button" wire:click="setCategory({{ $cat->id }})" wire:target="setCategory({{ $cat->id }})" @if($isActive) data-tab-active @endif
                                class="{{ $editStructure ? 'pl-3 pr-1.5' : 'px-3' }} py-2 text-sm font-medium whitespace-nowrap transition-colors {{ $catTabTextClass }}">
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
                            @if($editStructure)
                                <x-loading-button wire:click="confirmRemoveNode('category', {{ $cat->id }})"
                                    target="confirmRemoveNode('category', {{ $cat->id }})"
                                    variant="icon-red" icon="delete" wire:key="cat-del-{{ $cat->id }}" title="Hapus kategori {{ $cat->label }} beserta isinya" class="mr-1" />
                            @endif
                        </div>
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
                                            $subCatAvg = $this->calculateSubCategoryAvg($subCat->id);
                                            $isSubActive = $this->activeSubCategory == $subCat->id;
                                        @endphp
                                        @php
                                            $subTabPillClass = $isSubActive
                                                ? 'bg-blue-100 dark:bg-blue-900/30'
                                                : 'hover:bg-gray-50 dark:hover:bg-gray-700/30';
                                            $subTabTextClass = $isSubActive
                                                ? 'text-blue-700 dark:text-blue-400'
                                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300';
                                        @endphp
                                        <div class="flex items-center rounded-md transition-colors {{ $subTabPillClass }}" wire:key="subtab-wrap-{{ $subCat->id }}">
                                            <button type="button" wire:click="setSubCategory({{ $subCat->id }})" wire:target="setSubCategory({{ $subCat->id }})" wire:key="subtab-{{ $subCat->id }}" @if($isSubActive) data-tab-active @endif
                                                class="{{ $editStructure ? 'pl-3 pr-1.5' : 'px-3' }} py-1.5 text-sm font-medium whitespace-nowrap transition-colors {{ $subTabTextClass }}">
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
                                            @if($editStructure)
                                                <x-loading-button wire:click="confirmRemoveNode('sub_category', {{ $subCat->id }})"
                                                    target="confirmRemoveNode('sub_category', {{ $subCat->id }})"
                                                    variant="icon-red" icon="delete" wire:key="sub-del-{{ $subCat->id }}" title="Hapus sub kategori {{ $subCat->name }} beserta isinya" class="mr-1" />
                                            @endif
                                        </div>
                                    @endforeach
                                </nav>
                            </div>
                        @endif

                        @forelse($cat->subCategories as $subCat)
                            @if($this->activeSubCategory == $subCat->id)
                                <div wire:key="subpanel-{{ $subCat->id }}">
                                @foreach($subCat->itemGroups as $itemGroup)
                                    @php
                                        $igAvg = $this->calculateItemGroupAvg($itemGroup->id);
                                        $firstItem = $itemGroup->items->first();
                                        $isInventoryGroup = $firstItem && $firstItem->item_type === \App\Enums\SurveyItemType::Inventory;
                                        $scoreLabels = $firstItem ? $firstItem->score_labels : ['C', 'V'];
                                    @endphp
                                    <!-- Item Group -->
                                    <div wire:key="ig-{{ $itemGroup->id }}" class="mb-4 border border-gray-200 dark:border-gray-700 rounded-md overflow-hidden">
                                        <div class="w-full bg-gray-50 dark:bg-gray-700/20 px-3 py-2 flex items-center justify-between gap-2 hover:bg-gray-100 dark:hover:bg-gray-700/40 transition-colors">
                                            <button type="button" x-on:click="collapsedGroups[{{ $itemGroup->id }}] = ! collapsedGroups[{{ $itemGroup->id }}]" title="Buka/tutup grup item"
                                                class="flex items-center gap-2 flex-1 min-w-0 text-left text-sm font-medium text-gray-900 dark:text-white">
                                                <svg class="w-4 h-4 shrink-0 text-gray-400 transition-transform duration-200" :class="{ 'rotate-90': ! collapsedGroups[{{ $itemGroup->id }}] }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                                </svg>
                                                <span class="truncate">{{ $subCat->order_num }}.{{ $itemGroup->order_num }} {{ $itemGroup->name }}</span>
                                            </button>
                                            <span class="flex items-center gap-1 shrink-0">
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
                                                @if($editStructure)
                                                    <x-loading-button wire:click="confirmRemoveNode('item_group', {{ $itemGroup->id }})"
                                                        target="confirmRemoveNode('item_group', {{ $itemGroup->id }})"
                                                        variant="icon-red" icon="delete" wire:key="ig-del-{{ $itemGroup->id }}" title="Hapus grup item ini beserta isinya" />
                                                @endif
                                            </span>
                                        </div>

                                        <div x-show="! collapsedGroups[{{ $itemGroup->id }}]">
                                        <div class="overflow-x-auto custom-scrollbar">
                                            <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-gray-700">
                                                <colgroup>
                                                    <col class="w-10">
                                                    @if($isInventoryGroup)
                                                        {{-- Item dikunci persentase agar kolom Qty/Spesifikasi sejajar antar grup --}}
                                                        <col class="w-[45%]">
                                                        <col class="w-24">
                                                        <col>
                                                    @else
                                                        <col>
                                                        @foreach($scoreLabels as $label)
                                                            <col class="w-20">
                                                        @endforeach
                                                        <col class="w-20">
                                                    @endif
                                                    @if($editStructure)
                                                        <col class="w-10">
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
                                                        @if($editStructure)
                                                            <th class="w-10"></th>
                                                        @endif
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($itemGroup->items as $item)
                                                        @if($item->item_type === \App\Enums\SurveyItemType::Inventory)
                                                            <tr wire:key="item-{{ $item->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ to_letter($loop->iteration) }}</td>
                                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">{{ $item->name }}</td>
                                                                <td class="px-3 py-2 text-center">
                                                                    <input type="number" min="0" wire:model="responses.{{ $item->id }}.qty"
                                                                        placeholder="0"
                                                                        class="autosave-field w-20 text-sm border border-gray-300 dark:border-gray-600 rounded px-2 py-1 dark:bg-gray-700 dark:text-white text-center focus:ring-2 focus:ring-blue-500" />
                                                                </td>
                                                                <td class="px-3 py-2">
                                                                    <input type="text" wire:model="responses.{{ $item->id }}.specification"
                                                                        placeholder="Spesifikasi alat"
                                                                        class="autosave-field w-full text-sm border border-gray-300 dark:border-gray-600 rounded px-2 py-1 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" />
                                                                </td>
                                                                @if($editStructure)
                                                                    <td class="px-1 py-2 text-center">
                                                                        <x-loading-button wire:click="confirmRemoveNode('item', {{ $item->id }})"
                                                                            target="confirmRemoveNode('item', {{ $item->id }})"
                                                                            variant="icon-red" icon="delete" wire:key="item-del-{{ $item->id }}" title="Hapus item ini" />
                                                                    </td>
                                                                @endif
                                                            </tr>
                                                        @else
                                                            @php
                                                                $itemAvg = $this->calculateItemAvg($item->id);
                                                            @endphp
                                                            <tr wire:key="item-{{ $item->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                                                <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">{{ to_letter($loop->iteration) }}</td>
                                                                <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">
                                                                    {{ $item->name }}
                                                                    @if($item->has_date_fields)
                                                                        <div class="mt-1 flex flex-col gap-1">
                                                                            <div class="flex items-center gap-1">
                                                                                <span class="text-xs text-gray-400 w-14">Issued:</span>
                                                                                <input type="date" wire:model="responses.{{ $item->id }}.date_issued" class="autosave-field text-xs border border-gray-300 dark:border-gray-600 rounded px-1 py-0.5 dark:bg-gray-700 dark:text-white" />
                                                                            </div>
                                                                            <div class="flex items-center gap-1">
                                                                                <span class="text-xs text-gray-400 w-14">Exp:</span>
                                                                                <input type="date" wire:model="responses.{{ $item->id }}.date_expired" class="autosave-field text-xs border border-gray-300 dark:border-gray-600 rounded px-1 py-0.5 dark:bg-gray-700 dark:text-white" />
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                </td>
                                                                @foreach($item->score_labels as $label)
                                                                    <td class="px-3 py-2 text-center">
                                                                        <select wire:model.live="responses.{{ $item->id }}.scores.{{ $label }}"
                                                                            class="autosave-field w-16 text-sm border border-gray-300 dark:border-gray-600 rounded px-1 py-1 dark:bg-gray-700 dark:text-white text-center focus:ring-2 focus:ring-blue-500">
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
                                                                @if($editStructure)
                                                                    <td class="px-1 py-2 text-center">
                                                                        <x-loading-button wire:click="confirmRemoveNode('item', {{ $item->id }})"
                                                                            target="confirmRemoveNode('item', {{ $item->id }})"
                                                                            variant="icon-red" icon="delete" wire:key="item-del-{{ $item->id }}" title="Hapus item ini" />
                                                                    </td>
                                                                @endif
                                                            </tr>
                                                        @endif
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        <!-- Catatan Grup Item (list dinamis) -->
                                        <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/20 px-3 py-2">
                                            <div class="flex flex-col gap-1.5">
                                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Catatan</span>
                                                @foreach($this->groupNotes[$itemGroup->id] ?? [] as $noteIndex => $noteValue)
                                                    <div wire:key="gn-{{ $itemGroup->id }}-{{ $noteIndex }}">
                                                        <div class="flex items-center gap-2">
                                                            <input type="text" wire:model="groupNotes.{{ $itemGroup->id }}.{{ $noteIndex }}" maxlength="500"
                                                                placeholder="Catatan {{ $noteIndex + 1 }} untuk grup ini"
                                                                class="autosave-field flex-1 min-w-0 text-sm border border-gray-300 dark:border-gray-600 rounded px-2 py-1 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-blue-500" />
                                                            <x-loading-button wire:click="confirmRemoveGroupNote({{ $itemGroup->id }}, {{ $noteIndex }})"
                                                                target="confirmRemoveGroupNote({{ $itemGroup->id }}, {{ $noteIndex }})"
                                                                variant="icon-red" icon="delete" wire:key="gn-del-{{ $itemGroup->id }}-{{ $noteIndex }}" title="Hapus catatan" />
                                                        </div>
                                                        <x-input-error :messages="$errors->get('groupNotes.'.$itemGroup->id.'.'.$noteIndex)" class="mt-1" />
                                                    </div>
                                                @endforeach
                                                <div>
                                                    <x-loading-button wire:click="addGroupNote({{ $itemGroup->id }})" target="addGroupNote({{ $itemGroup->id }})"
                                                        x-on:click="markDirty()"
                                                        variant="secondary" size="sm" icon="plus" wire:key="gn-add-{{ $itemGroup->id }}" loadingText="" title="Tambah catatan">
                                                        Tambah Catatan
                                                    </x-loading-button>
                                                </div>
                                            </div>
                                        </div>
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

        <!-- Action Bar (Sticky, width matches cards above; offset dari tepi
             viewport agar tidak mepet — terlihat mengambang, tidak tertutup chrome) -->
        <div class="sticky bottom-3 sm:bottom-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-3 px-4 sm:px-6 z-10">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-end">
                <!-- Draft auto-save indicator -->
                <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 mr-auto" x-show="lastSaved" x-cloak>
                    <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Draft tersimpan <span x-text="lastSaved"></span></span>
                </div>
                <x-cancel-button x-on:click="requestCancel()" target="cancel" class="w-full sm:w-auto sm:min-w-[160px]" />
                <x-loading-button type="submit" target="save" variant="primary" size="lg" loadingText="Menyimpan..." class="w-full sm:w-auto sm:min-w-[160px]">
                    {{ $editMode ? 'Update Survey' : 'Simpan Survey' }}
                </x-loading-button>
            </div>
        </div>
    </form>

    <!-- Modal konfirmasi buang perubahan saat Batal -->
    <x-confirm-modal eventName="confirm-cancel-survey" />
    <x-confirm-modal eventName="confirm-remove-note" />
    <x-confirm-modal eventName="confirm-remove-node" />
</div>
