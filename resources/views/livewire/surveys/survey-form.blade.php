<div
    x-data="{
        autoSaveTimer: null,
        lastSaved: null,
        dirty: false,
        // Kolaborasi realtime (Reverb presence channel survey.{id})
        myId: {{ (int) auth()->id() }},
        collaborators: [],
        focusMap: {},
        // Sinkron per-field: pendingRemote = batch perubahan kolaborator
        // yang menunggu diterapkan; pendingSince = awal ketikan lokal yang
        // belum tersinkron (batas tunggu maksimum saat mengetik terus);
        // liveSyncBy/Text = indikator transien di action bar.
        pendingRemote: {},
        pendingRemoteTimer: null,
        pendingSince: null,
        liveSyncBy: null,
        liveSyncText: '',
        liveSyncTimer: null,
        // State collapse per item-group bertahan di root — tidak ikut
        // re-init saat Livewire morph (mis. pindah tab lalu kembali)
        collapsedGroups: {},
        init() {
            // Draft yang ter-restore = form sudah punya perubahan
            this.dirty = !! this.$wire.hasDraft;
            this.initCollab();
            this.consumeDeepLink();
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
        // Deep-link ?category=&sub= (dari halaman detail) hanya untuk
        // pembukaan pertama — hapus dari URL agar refresh memakai posisi
        // tab terakhir, bukan kembali ke tab saat tombol Edit diklik.
        consumeDeepLink() {
            const url = new URL(window.location.href);
            if (! url.searchParams.has('category') && ! url.searchParams.has('sub')) return;
            url.searchParams.delete('category');
            url.searchParams.delete('sub');
            history.replaceState(history.state, '', url);
        },
        initCollab() {
            if (! this.$wire.editMode || ! window.Echo) return;
            const channel = 'survey.' + this.$wire.surveyId;
            // Re-init setelah wire:navigate — lepas dulu agar tidak double-join
            window.Echo.leave(channel);
            window.Echo.join(channel)
                .here((members) => {
                    this.collaborators = members.filter((m) => m.id !== this.myId);
                })
                .joining((member) => {
                    if (member.id !== this.myId && ! this.collaborators.some((c) => c.id === member.id)) {
                        this.collaborators.push(member);
                    }
                    // Anggota baru (mis. habis refresh) belum tahu posisi
                    // tab kita — kirim ulang agar chip langsung tampil.
                    if (this.$wire.activeSubCategory) this.$wire.broadcastFocus(this.$wire.activeSubCategory);
                })
                .leaving((member) => {
                    this.collaborators = this.collaborators.filter((c) => c.id !== member.id);
                    delete this.focusMap[member.id];
                })
                .error((e) => console.warn('[collab] gagal join presence channel', e))
                .listen('SurveySectionFocus', (e) => {
                    if (e.user_id === this.myId) return;
                    this.focusMap[e.user_id] = e.sub_category_id;
                })
                .listen('SurveyContentUpdated', (e) => {
                    if (e.user_id === this.myId) return;
                    if (e.kind === 'draft') {
                        this.queueRemoteChanges(e);
                        return;
                    }
                    if (this.dirty) {
                        // Isian lokal sudah tersinkron ke penyimpan → adopsi
                        // versinya agar Simpan tidak memicu konfirmasi timpa.
                        this.$wire.$set('loadedVersion', e.version, false);
                        this.showLiveSync(e.user_name, 'disimpan');
                    } else {
                        this.$wire.refreshCollaborativeState(e.user_name);
                    }
                });
            // Broadcast posisi tab saat join + setiap pindah sub kategori
            this.$wire.$watch('activeSubCategory', (v) => { if (v) this.$wire.broadcastFocus(v); });
            if (this.$wire.activeSubCategory) this.$wire.broadcastFocus(this.$wire.activeSubCategory);
        },
        collaboratorById(id) {
            return this.collaborators.find((c) => c.id === Number(id));
        },
        focusedOnSub(subId) {
            return Object.keys(this.focusMap)
                .filter((uid) => this.focusMap[uid] === subId)
                .map((uid) => this.collaboratorById(uid))
                .filter(Boolean);
        },
        namesOf(list) {
            return list.map((c) => c.name).join(', ');
        },
        initialsOf(name) {
            return (name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
        },
        colorFor(id) {
            const palette = ['bg-blue-500', 'bg-green-500', 'bg-amber-500', 'bg-purple-500', 'bg-pink-500', 'bg-teal-500', 'bg-orange-500', 'bg-indigo-500'];
            return palette[Number(id) % palette.length];
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
        // Timestamp "Draft tersimpan" — dipakai untuk autosave lokal dan
        // sinkron dari kolaborator (iso dari saved_at event broadcast).
        markSavedAt(iso = null) {
            const d = iso ? new Date(iso) : new Date();
            this.lastSaved = (isNaN(d) ? new Date() : d).toLocaleTimeString('id-ID');
        },
        onFieldChange(model = null) {
            this.dirty = true;
            if (model && this.$wire.autoSave) this.flashByModel(model);
            this.scheduleSave();
        },
        handleInput(e) {
            this.dirty = true;
            const el = e.target;
            // Flash hijau langsung saat field diubah (sekali saja)
            if (this.$wire.autoSave && el.classList.contains('autosave-field')) {
                this.flashField(el);
            }
            this.scheduleSave();
        },
        // Mode edit selalu sinkron ke kolaborator meski Auto Save nonaktif;
        // draft sesi tetap hanya ditulis bila Auto Save aktif (di server).
        // Debounce 600ms dengan batas tunggu maksimum 1.5s — saat mengetik
        // terus-menerus perubahan tetap terkirim berkala, tidak menunggu jeda.
        scheduleSave() {
            if (! this.$wire.autoSave && ! this.$wire.editMode) return;
            clearTimeout(this.autoSaveTimer);
            this.pendingSince = this.pendingSince || Date.now();
            const wait = Date.now() - this.pendingSince >= 1500 ? 0 : 600;
            this.autoSaveTimer = setTimeout(async () => {
                this.pendingSince = null;
                await this.$wire.saveDraft();
                if (this.$wire.autoSave) this.markSavedAt();
            }, wait);
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
        onRemoteDraftApplied(name, savedAt = null) {
            this.dirty = true;
            this.showLiveSync(name, 'disinkronkan');
            this.markSavedAt(savedAt);
        },
        showLiveSync(name, action) {
            if (! name) return;
            this.liveSyncBy = name;
            this.liveSyncText = 'Isian ' + name + ' ' + action;
            clearTimeout(this.liveSyncTimer);
            this.liveSyncTimer = setTimeout(() => this.liveSyncBy = null, 4000);
        },
        // Path wire:model input yang sedang difokus user — field ini tidak
        // ditimpa perubahan remote agar ketikan lokal tidak hilang.
        focusedModel() {
            const el = document.activeElement;
            if (! el || ! this.$root.contains(el)) return null;
            const attr = Array.from(el.attributes).find((a) => a.name.startsWith('wire:model'));
            return attr ? attr.value : null;
        },
        // Perubahan beruntun digabung (80ms) lalu diterapkan lewat satu
        // request Livewire. Request masuk antrean commit, jadi nilai tidak
        // tertimpa respons request lain yang sedang berjalan.
        queueRemoteChanges(e) {
            if (e.structure) {
                this.$wire.applyRemoteDraft(e.user_id);
                return;
            }
            const focused = this.focusedModel();
            Object.keys(e.changes || {}).forEach((key) => {
                const typing = focused && (focused === key || focused.startsWith(key + '.') || key.startsWith(focused + '.'));
                if (! typing) this.pendingRemote[key] = e.changes[key];
            });
            if (! Object.keys(this.pendingRemote).length) return;
            this.dirty = true;
            this.showLiveSync(e.user_name, 'disinkronkan');
            // Indikator draft ikut bergerak saat isian kolaborator masuk —
            // draft gabungan juga ditulis server (applyRemoteChanges) bila
            // Auto Save aktif, jadi label ini mencerminkan penulisan nyata.
            if (this.$wire.autoSave) this.markSavedAt(e.saved_at);
            clearTimeout(this.pendingRemoteTimer);
            this.pendingRemoteTimer = setTimeout(() => {
                const batch = this.pendingRemote;
                this.pendingRemote = {};
                this.$wire.applyRemoteChanges(batch);
            }, 80);
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
    x-on:ship-data-changed.window="onFieldChange()"
    x-on:structure-node-removed.window="onFieldChange()"
    x-on:structure-node-saved.window="onFieldChange()"
    x-on:remote-draft-applied.window="onRemoteDraftApplied($event.detail.name, $event.detail.savedAt)"
>
    <!-- Breadcrumb -->
    <nav class="mb-6 flex items-center justify-between gap-4" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2 text-sm min-w-0">
            <li>
                <a href="{{ route('surveys.index') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Survey Kondisi</a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li class="text-gray-900 dark:text-white font-medium">{{ $editMode ? 'Edit' : 'Buat' }}</li>
        </ol>
        @if($editMode)
            <!-- Kolaborator yang sedang membuka form ini (presence Reverb) -->
            <div class="flex items-center gap-2 shrink-0" x-show="collaborators.length" x-cloak>
                <div class="flex -space-x-2">
                    <template x-for="c in collaborators.slice(0, 4)" :key="'collab-' + c.id">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-[10px] font-bold text-white ring-2 ring-white dark:ring-gray-900 cursor-default"
                            :class="colorFor(c.id)" :title="c.name + ' sedang mengedit'" x-text="initialsOf(c.name)"></span>
                    </template>
                    <!-- Sisa kolaborator (>4) diringkas agar header tidak memanjang -->
                    <span x-show="collaborators.length > 4"
                        class="inline-flex items-center justify-center w-7 h-7 rounded-full text-[10px] font-bold bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200 ring-2 ring-white dark:ring-gray-900 cursor-default"
                        :title="namesOf(collaborators.slice(4))" x-text="'+' + (collaborators.length - 4)"></span>
                </div>
                <span class="hidden sm:inline text-xs font-medium text-gray-500 dark:text-gray-400" x-text="collaborators.length + ' kolaborator aktif'"></span>
            </div>
        @endif
    </nav>

    <!-- Wizard: 1 Informasi Survey → 2 Data Kapal → 3 Penilaian & CAP.
         Klik step maju melewati validasi per-langkah; mundur bebas. -->
    <nav class="mb-6 px-2 sm:px-8" aria-label="Langkah form survey">
        @php
            $wizardSteps = [
                1 => 'Informasi Survey',
                2 => 'Data Kapal',
                3 => 'Penilaian & CAP',
            ];
        @endphp
        <ol class="flex items-start w-full">
            @foreach($wizardSteps as $stepNum => $stepLabel)
                @php
                    $stepActive = $activeStep === $stepNum;
                    $stepDone = $activeStep > $stepNum;
                    $stepCircleClass = $stepDone
                        ? 'bg-green-50 dark:bg-green-900/20 border-green-500 dark:border-green-400 text-green-600 dark:text-green-400'
                        : ($stepActive
                            ? 'bg-blue-600 border-blue-600 text-white shadow-md shadow-blue-600/30 ring-4 ring-blue-100 dark:ring-blue-900/40'
                            : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500 group-hover:border-blue-400 group-hover:text-blue-500 dark:group-hover:border-blue-500');
                    $stepLabelClass = $stepActive
                        ? 'text-blue-600 dark:text-blue-400 font-semibold'
                        : ($stepDone
                            ? 'text-green-600 dark:text-green-400 font-medium'
                            : 'text-gray-500 dark:text-gray-400 group-hover:text-gray-700 dark:group-hover:text-gray-300');
                    $stepConnectorClass = $stepDone ? 'bg-green-500 dark:bg-green-600' : 'bg-gray-200 dark:bg-gray-700';
                @endphp
                <li class="flex flex-col items-center" wire:key="step-{{ $stepNum }}">
                    <button type="button" wire:click="goToStep({{ $stepNum }})" wire:target="goToStep({{ $stepNum }})"
                        wire:loading.attr="disabled" wire:key="btn-step-{{ $stepNum }}"
                        @if($stepActive) aria-current="step" @endif
                        title="{{ $stepLabel }}"
                        class="group flex flex-col items-center gap-1.5 w-16 sm:w-28 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        <span class="flex items-center justify-center w-10 h-10 rounded-full border-2 text-sm font-semibold transition-colors duration-200 {{ $stepCircleClass }}">
                            <svg wire:loading wire:target="goToStep({{ $stepNum }})" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.class="hidden" wire:target="goToStep({{ $stepNum }})" class="inline-flex">
                                @if($stepDone)
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    {{ $stepNum }}
                                @endif
                            </span>
                        </span>
                        <span class="text-xs sm:text-sm text-center leading-tight transition-colors {{ $stepLabelClass }}">{{ $stepLabel }}</span>
                    </button>
                </li>
                @if(! $loop->last)
                    <li class="flex-1 h-10 flex items-center px-1 sm:px-3" aria-hidden="true" wire:key="step-conn-{{ $stepNum }}">
                        <div class="h-0.5 w-full rounded transition-colors duration-200 {{ $stepConnectorClass }}"></div>
                    </li>
                @endif
            @endforeach
        </ol>
    </nav>

    <form wire:submit="save">
        @if($activeStep === 1)
        <!-- Langkah 1 — Informasi Survey -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Informasi Survey
                    </h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="ship_id" value="Kapal" :required="true" />
                        <x-searchable-select wire:model="ship_id" :options="$this->shipOptions" placeholder="Pilih kapal" searchPlaceholder="Cari kapal..." class="autosave-field" data-model="ship_id" />
                        @if($isNewShip)
                            <p class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                                Kapal baru — isi particulars di langkah Data Kapal; record master dibuat saat sinkron.
                            </p>
                        @endif
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

        @endif

        @if($activeStep === 2)
        <!-- Langkah 2 — Data Kapal (snapshot — auto-isi dari kapal terpilih,
             dapat dikoreksi saat toggle Edit aktif; sinkron ke master kapal
             yang cocok berdasarkan Call Sign lewat tombol Update Master
             atau konfirmasi saat status Selesai disimpan) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="border-b border-gray-200 dark:border-gray-700 pb-3 mb-4">
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Data Kapal</h3>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Edit Data Kapal</span>
                            <x-toggle-switch wire:click="toggleEditShipData" :active="$editShipData" target="toggleEditShipData" activeColor="blue" title="Kunci/buka pengeditan data kapal" />
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Snapshot data kapal saat survey diisi — tersimpan bersama survey &amp; dipakai laporan.
                    </p>
                    <div class="mt-3 flex items-center gap-2 sm:justify-end">
                        <x-loading-button type="button" wire:click="confirmReloadShipFromMaster" target="confirmReloadShipFromMaster" variant="secondary" size="sm" loadingText="Memuat..." icon="import" title="Isi ulang data kapal dari Master Data (menimpa isian snapshot saat ini)" class="flex-1 sm:flex-none">
                            Tarik Master
                        </x-loading-button>
                        <x-loading-button type="button" wire:click="updateMasterShip" target="updateMasterShip" variant="warning" size="sm" loadingText="Memproses..." icon="export" title="Timpa/buat record kapal di Master Data dari data snapshot ini" class="flex-1 sm:flex-none">
                            Update Master Kapal
                        </x-loading-button>
                    </div>
                </div>

                @unless($editShipData)
                    <p class="mb-4 rounded-lg bg-gray-50 dark:bg-gray-700/30 border border-gray-200 dark:border-gray-700 px-3 py-2 text-xs text-gray-500 dark:text-gray-400">
                        Data kapal terkunci — aktifkan toggle <span class="font-medium">Edit Data Kapal</span> untuk mengubah.
                    </p>
                @endunless

                <fieldset @disabled(! $editShipData) class="{{ $editShipData ? '' : 'opacity-60' }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="ship_name" value="Nama Kapal" :required="true" />
                        <x-text-input wire:model="shipData.name" id="ship_name" type="text" class="mt-1 block w-full autosave-field" placeholder="Nama kapal" />
                        <x-input-error :messages="$errors->get('shipData.name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_year_built" value="Tahun Pembuatan" />
                        <x-text-input wire:model="shipData.year_built" id="ship_year_built" type="number" min="1900" max="{{ (int) now()->format('Y') }}" class="mt-1 block w-full autosave-field" placeholder="Tahun pembuatan" />
                        <x-input-error :messages="$errors->get('shipData.year_built')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_imo_number" value="Nomor IMO" />
                        <x-text-input wire:model="shipData.imo_number" id="ship_imo_number" type="text" class="mt-1 block w-full autosave-field" placeholder="Nomor IMO" />
                        <x-input-error :messages="$errors->get('shipData.imo_number')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_call_sign" value="Call Sign" />
                        <x-text-input wire:model="shipData.call_sign" id="ship_call_sign" type="text" class="mt-1 block w-full autosave-field" placeholder="Call sign" />
                        <x-input-error :messages="$errors->get('shipData.call_sign')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_type" value="Jenis Kapal" />
                        <x-text-input wire:model="shipData.ship_type" id="ship_type" type="text" class="mt-1 block w-full autosave-field" placeholder="Contoh: FERRY RO-RO" />
                        <x-input-error :messages="$errors->get('shipData.ship_type')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_flag" value="Bendera" />
                        <x-text-input wire:model="shipData.flag" id="ship_flag" type="text" class="mt-1 block w-full autosave-field" placeholder="Bendera kapal" />
                        <x-input-error :messages="$errors->get('shipData.flag')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_gross_tonnage" value="Gross Tonnage" />
                        <x-text-input wire:model="shipData.gross_tonnage" id="ship_gross_tonnage" type="text" class="mt-1 block w-full autosave-field" placeholder="Gross tonnage" />
                        <x-input-error :messages="$errors->get('shipData.gross_tonnage')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_net_tonnage" value="Net Tonnage" />
                        <x-text-input wire:model="shipData.net_tonnage" id="ship_net_tonnage" type="text" class="mt-1 block w-full autosave-field" placeholder="Net tonnage" />
                        <x-input-error :messages="$errors->get('shipData.net_tonnage')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_owner" value="Pemilik" />
                        <x-text-input wire:model="shipData.owner" id="ship_owner" type="text" class="mt-1 block w-full autosave-field" placeholder="Pemilik kapal" />
                        <x-input-error :messages="$errors->get('shipData.owner')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_operator" value="Operator" />
                        <x-text-input wire:model="shipData.operator" id="ship_operator" type="text" class="mt-1 block w-full autosave-field" placeholder="Operator kapal" />
                        <x-input-error :messages="$errors->get('shipData.operator')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_class_status" value="Status Kelas" :required="true" />
                        <x-searchable-select wire:model="shipData.class_status" :options="$this->shipClassStatusOptions" placeholder="Pilih status kelas" searchPlaceholder="Cari status kelas..." class="autosave-field" />
                        <x-input-error :messages="$errors->get('shipData.class_status')" class="mt-2" />
                    </div>
                </div>

                <h4 class="mt-6 mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 pb-2">Dimensi &amp; Particulars</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="ship_loa" value="Length Overall (LOA)" />
                        <div class="relative mt-1">
                            <x-text-input wire:model="shipData.loa" id="ship_loa" type="text" class="block w-full pr-9 autosave-field" placeholder="Contoh: 43,35" />
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 dark:text-gray-400">m</span>
                        </div>
                        <x-input-error :messages="$errors->get('shipData.loa')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_lpp" value="Length Between Perpendiculars (LPP)" />
                        <div class="relative mt-1">
                            <x-text-input wire:model="shipData.lpp" id="ship_lpp" type="text" class="block w-full pr-9 autosave-field" placeholder="Contoh: 38,50" />
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 dark:text-gray-400">m</span>
                        </div>
                        <x-input-error :messages="$errors->get('shipData.lpp')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_breadth" value="Breadth (B)" />
                        <div class="relative mt-1">
                            <x-text-input wire:model="shipData.breadth" id="ship_breadth" type="text" class="block w-full pr-9 autosave-field" placeholder="Contoh: 12,00" />
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 dark:text-gray-400">m</span>
                        </div>
                        <x-input-error :messages="$errors->get('shipData.breadth')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_depth" value="Tinggi (Depth)" />
                        <div class="relative mt-1">
                            <x-text-input wire:model="shipData.depth" id="ship_depth" type="text" class="block w-full pr-9 autosave-field" placeholder="Contoh: 03,00" />
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 dark:text-gray-400">m</span>
                        </div>
                        <x-input-error :messages="$errors->get('shipData.depth')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_draft" value="Draft" />
                        <div class="relative mt-1">
                            <x-text-input wire:model="shipData.draft" id="ship_draft" type="text" class="block w-full pr-9 autosave-field" placeholder="Contoh: 02,00" />
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-sm text-gray-500 dark:text-gray-400">m</span>
                        </div>
                        <x-input-error :messages="$errors->get('shipData.draft')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_dwt" value="DWT" />
                        <x-text-input wire:model="shipData.dwt" id="ship_dwt" type="text" class="mt-1 block w-full autosave-field" placeholder="Deadweight tonnage" />
                        <x-input-error :messages="$errors->get('shipData.dwt')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_builder" value="Galangan Pembangun" />
                        <x-text-input wire:model="shipData.builder" id="ship_builder" type="text" class="mt-1 block w-full autosave-field" placeholder="Nama galangan" />
                        <x-input-error :messages="$errors->get('shipData.builder')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_port_of_registry" value="Pelabuhan Registrasi" />
                        <x-text-input wire:model="shipData.port_of_registry" id="ship_port_of_registry" type="text" class="mt-1 block w-full autosave-field" placeholder="Contoh: JAKARTA" />
                        <x-input-error :messages="$errors->get('shipData.port_of_registry')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_hull_material" value="Material Lambung" />
                        <x-text-input wire:model="shipData.hull_material" id="ship_hull_material" type="text" class="mt-1 block w-full autosave-field" placeholder="Contoh: STEEL" />
                        <x-input-error :messages="$errors->get('shipData.hull_material')" class="mt-2" />
                    </div>
                </div>

                <h4 class="mt-6 mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 pb-2">Kelas &amp; Permesinan</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="ship_class_name" value="Kelas" />
                        <x-text-input wire:model="shipData.class_name" id="ship_class_name" type="text" class="mt-1 block w-full autosave-field" placeholder="Contoh: BIRO KLASIFIKASI INDONESIA" />
                        <x-input-error :messages="$errors->get('shipData.class_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_class_notations" value="Notasi Kelas" />
                        <x-text-input wire:model="shipData.class_notations" id="ship_class_notations" type="text" class="mt-1 block w-full autosave-field" placeholder="Notasi kelas" />
                        <x-input-error :messages="$errors->get('shipData.class_notations')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_main_engine" value="Main Engine" />
                        <x-text-input wire:model="shipData.main_engine" id="ship_main_engine" type="text" class="mt-1 block w-full autosave-field" placeholder="Merek/tipe main engine" />
                        <x-input-error :messages="$errors->get('shipData.main_engine')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_main_engine_power" value="Daya Main Engine" />
                        <x-text-input wire:model="shipData.main_engine_power" id="ship_main_engine_power" type="text" class="mt-1 block w-full autosave-field" placeholder="Contoh: 2 x 650 HP/1450 RPM" />
                        <x-input-error :messages="$errors->get('shipData.main_engine_power')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_aux_engine" value="Auxiliary Engine" />
                        <x-text-input wire:model="shipData.aux_engine" id="ship_aux_engine" type="text" class="mt-1 block w-full autosave-field" placeholder="Merek/tipe auxiliary engine" />
                        <x-input-error :messages="$errors->get('shipData.aux_engine')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_aux_engine_power" value="Daya Auxiliary Engine" />
                        <x-text-input wire:model="shipData.aux_engine_power" id="ship_aux_engine_power" type="text" class="mt-1 block w-full autosave-field" placeholder="Contoh: 2 x 150 BHP" />
                        <x-input-error :messages="$errors->get('shipData.aux_engine_power')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 border-b border-gray-200 dark:border-gray-700 pb-2 mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h4 class="text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status Class</h4>
                    <x-loading-button type="button" wire:click="addShipCertificate" target="addShipCertificate" variant="secondary" size="sm" loadingText="Menambah..." icon="plus" wire:key="btn-add-ship-cert">
                        Tambah Sertifikat
                    </x-loading-button>
                </div>
                <div class="space-y-4">
                    @forelse($shipCertificates as $index => $cert)
                        <div wire:key="ship-cert-{{ $cert['row_key'] }}-{{ $index }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 items-end p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                            <div class="sm:col-span-2 lg:col-span-1">
                                <x-input-label for="ship_cert_type_{{ $index }}" value="Jenis Sertifikat" />
                                <x-text-input wire:model="shipCertificates.{{ $index }}.certificate_type" id="ship_cert_type_{{ $index }}" type="text" class="mt-1 block w-full autosave-field" placeholder="Contoh: Special Survey" />
                                <x-input-error :messages="$errors->get('shipCertificates.'.$index.'.certificate_type')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="ship_cert_last_{{ $index }}" value="Last" />
                                <x-text-input wire:model="shipCertificates.{{ $index }}.last_date" id="ship_cert_last_{{ $index }}" type="date" class="mt-1 block w-full autosave-field" />
                                <x-input-error :messages="$errors->get('shipCertificates.'.$index.'.last_date')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="ship_cert_next1_{{ $index }}" value="Next 1" />
                                <x-text-input wire:model="shipCertificates.{{ $index }}.next_1_date" id="ship_cert_next1_{{ $index }}" type="date" class="mt-1 block w-full autosave-field" />
                                <x-input-error :messages="$errors->get('shipCertificates.'.$index.'.next_1_date')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="ship_cert_next2_{{ $index }}" value="Next 2" />
                                <x-text-input wire:model="shipCertificates.{{ $index }}.next_2_date" id="ship_cert_next2_{{ $index }}" type="date" class="mt-1 block w-full autosave-field" />
                                <x-input-error :messages="$errors->get('shipCertificates.'.$index.'.next_2_date')" class="mt-2" />
                            </div>
                            <div class="flex items-end gap-2">
                                <div class="flex-1 min-w-0">
                                    <x-input-label for="ship_cert_postpone_{{ $index }}" value="Postpone" />
                                    <x-text-input wire:model="shipCertificates.{{ $index }}.postpone_date" id="ship_cert_postpone_{{ $index }}" type="date" class="mt-1 block w-full autosave-field" />
                                    <x-input-error :messages="$errors->get('shipCertificates.'.$index.'.postpone_date')" class="mt-2" />
                                </div>
                                <x-loading-button type="button" wire:click="confirmRemoveShipCertificate({{ $index }})"
                                    target="confirmRemoveShipCertificate({{ $index }})"
                                    variant="icon-red" icon="delete"
                                    wire:key="btn-remove-ship-cert-{{ $cert['row_key'] }}-{{ $index }}"
                                    title="Hapus sertifikat" />
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                            Belum ada sertifikat. Klik "Tambah Sertifikat" untuk menambahkan.
                        </div>
                    @endforelse
                </div>
                </fieldset>
            </div>
        </div>
        @endif

        @if($activeStep === 3)
        <!-- Langkah 3 — Penilaian & CAP -->
        <!-- Overall CAP Score (live) -->
        <x-survey-cap-rating :score="$this->calculateOverallAvg()" />

        <!-- Category Tabs -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Penilaian Survey</h3>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Edit Struktur</span>
                        <x-toggle-switch wire:click="toggleEditStructure" :active="$editStructure" target="toggleEditStructure" activeColor="amber" title="Aktifkan/nonaktifkan mode edit struktur (tambah, ubah, hapus kategori/sub kategori/grup item/item)" />
                    </div>
                </div>
            </div>
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
                                <x-loading-button wire:click="moveStructureNode('category', {{ $cat->id }}, 'up')"
                                    target="moveStructureNode('category', {{ $cat->id }}, 'up')"
                                    variant="icon-gray" icon="arrow-left" wire:key="cat-up-{{ $cat->id }}" title="Geser kategori {{ $cat->label }} ke kiri" class="!p-1" :disabled="$loop->first" />
                                <x-loading-button wire:click="moveStructureNode('category', {{ $cat->id }}, 'down')"
                                    target="moveStructureNode('category', {{ $cat->id }}, 'down')"
                                    variant="icon-gray" icon="arrow-right" wire:key="cat-down-{{ $cat->id }}" title="Geser kategori {{ $cat->label }} ke kanan" class="!p-1" :disabled="$loop->last" />
                                <x-loading-button wire:click="openEditNode('category', {{ $cat->id }})"
                                    target="openEditNode('category', {{ $cat->id }})"
                                    variant="icon-blue" icon="edit" wire:key="cat-edit-{{ $cat->id }}" title="Ubah nama kategori {{ $cat->label }}" />
                                <x-loading-button wire:click="confirmRemoveNode('category', {{ $cat->id }})"
                                    target="confirmRemoveNode('category', {{ $cat->id }})"
                                    variant="icon-red" icon="delete" wire:key="cat-del-{{ $cat->id }}" title="Hapus kategori {{ $cat->label }} beserta isinya" class="mr-1" />
                            @endif
                        </div>
                    @endforeach
                    @if($editStructure)
                        <x-loading-button wire:click="openAddNode('category')"
                            target="openAddNode('category')"
                            variant="icon-green" icon="plus" wire:key="btn-add-category" title="Tambah kategori baru"
                            class="self-center" />
                    @endif
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
                                            <!-- Penanda kolaborator yang sedang membuka sub kategori ini -->
                                            <span class="inline-flex -space-x-1.5 mx-0.5" x-data="{ get here() { return focusedOnSub({{ $subCat->id }}) } }" x-show="here.length" x-cloak>
                                                <template x-for="c in here.slice(0, 2)" :key="'focus-' + c.id">
                                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[9px] font-bold text-white ring-1 ring-white dark:ring-gray-800 cursor-default"
                                                        :class="colorFor(c.id)" :title="c.name + ' sedang di sini'" x-text="initialsOf(c.name)"></span>
                                                </template>
                                                <span x-show="here.length > 2"
                                                    class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[9px] font-bold bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200 ring-1 ring-white dark:ring-gray-800 cursor-default"
                                                    :title="namesOf(here.slice(2)) + ' sedang di sini'" x-text="'+' + (here.length - 2)"></span>
                                            </span>
                                            @if($editStructure)
                                                <x-loading-button wire:click="moveStructureNode('sub_category', {{ $subCat->id }}, 'up')"
                                                    target="moveStructureNode('sub_category', {{ $subCat->id }}, 'up')"
                                                    variant="icon-gray" icon="arrow-left" wire:key="sub-up-{{ $subCat->id }}" title="Geser sub kategori {{ $subCat->name }} ke kiri" class="!p-1" :disabled="$loop->first" />
                                                <x-loading-button wire:click="moveStructureNode('sub_category', {{ $subCat->id }}, 'down')"
                                                    target="moveStructureNode('sub_category', {{ $subCat->id }}, 'down')"
                                                    variant="icon-gray" icon="arrow-right" wire:key="sub-down-{{ $subCat->id }}" title="Geser sub kategori {{ $subCat->name }} ke kanan" class="!p-1" :disabled="$loop->last" />
                                                <x-loading-button wire:click="openEditNode('sub_category', {{ $subCat->id }})"
                                                    target="openEditNode('sub_category', {{ $subCat->id }})"
                                                    variant="icon-blue" icon="edit" wire:key="sub-edit-{{ $subCat->id }}" title="Ubah nama sub kategori {{ $subCat->name }}" />
                                                <x-loading-button wire:click="confirmRemoveNode('sub_category', {{ $subCat->id }})"
                                                    target="confirmRemoveNode('sub_category', {{ $subCat->id }})"
                                                    variant="icon-red" icon="delete" wire:key="sub-del-{{ $subCat->id }}" title="Hapus sub kategori {{ $subCat->name }} beserta isinya" class="mr-1" />
                                            @endif
                                        </div>
                                    @endforeach
                                    @if($editStructure)
                                        <x-loading-button wire:click="openAddNode('sub_category', {{ $cat->id }})"
                                            target="openAddNode('sub_category', {{ $cat->id }})"
                                            variant="icon-green" icon="plus" wire:key="sub-add-{{ $cat->id }}" title="Tambah sub kategori ke kategori {{ $cat->label }}"
                                            class="self-center" />
                                    @endif
                                </nav>
                            </div>
                        @endif

                        @if($editStructure && $cat->subCategories->isEmpty())
                            <div class="mb-4">
                                <x-loading-button wire:click="openAddNode('sub_category', {{ $cat->id }})"
                                    target="openAddNode('sub_category', {{ $cat->id }})"
                                    variant="secondary" size="sm" icon="plus" wire:key="sub-add-empty-{{ $cat->id }}" title="Tambah sub kategori ke kategori ini">
                                    Tambah Sub Kategori
                                </x-loading-button>
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
                                                    <x-loading-button wire:click="moveStructureNode('item_group', {{ $itemGroup->id }}, 'up')"
                                                        target="moveStructureNode('item_group', {{ $itemGroup->id }}, 'up')"
                                                        variant="icon-gray" icon="arrow-up" wire:key="ig-up-{{ $itemGroup->id }}" title="Geser grup item ke atas" class="!p-1" :disabled="$loop->first" />
                                                    <x-loading-button wire:click="moveStructureNode('item_group', {{ $itemGroup->id }}, 'down')"
                                                        target="moveStructureNode('item_group', {{ $itemGroup->id }}, 'down')"
                                                        variant="icon-gray" icon="arrow-down" wire:key="ig-down-{{ $itemGroup->id }}" title="Geser grup item ke bawah" class="!p-1" :disabled="$loop->last" />
                                                    <x-loading-button wire:click="openEditNode('item_group', {{ $itemGroup->id }})"
                                                        target="openEditNode('item_group', {{ $itemGroup->id }})"
                                                        variant="icon-blue" icon="edit" wire:key="ig-edit-{{ $itemGroup->id }}" title="Ubah nama grup item" />
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
                                                        <col class="w-28">
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
                                                            <th class="w-28"></th>
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
                                                                        <div class="flex items-center justify-center">
                                                                            <x-loading-button wire:click="moveStructureNode('item', {{ $item->id }}, 'up')"
                                                                                target="moveStructureNode('item', {{ $item->id }}, 'up')"
                                                                                variant="icon-gray" icon="arrow-up" wire:key="item-up-{{ $item->id }}" title="Geser item ke atas" class="!p-1" :disabled="$loop->first" />
                                                                            <x-loading-button wire:click="moveStructureNode('item', {{ $item->id }}, 'down')"
                                                                                target="moveStructureNode('item', {{ $item->id }}, 'down')"
                                                                                variant="icon-gray" icon="arrow-down" wire:key="item-down-{{ $item->id }}" title="Geser item ke bawah" class="!p-1" :disabled="$loop->last" />
                                                                            <x-loading-button wire:click="openEditNode('item', {{ $item->id }})"
                                                                                target="openEditNode('item', {{ $item->id }})"
                                                                                variant="icon-blue" icon="edit" wire:key="item-edit-{{ $item->id }}" title="Ubah item ini" />
                                                                            <x-loading-button wire:click="confirmRemoveNode('item', {{ $item->id }})"
                                                                                target="confirmRemoveNode('item', {{ $item->id }})"
                                                                                variant="icon-red" icon="delete" wire:key="item-del-{{ $item->id }}" title="Hapus item ini" />
                                                                        </div>
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
                                                                        <div class="flex items-center justify-center">
                                                                            <x-loading-button wire:click="moveStructureNode('item', {{ $item->id }}, 'up')"
                                                                                target="moveStructureNode('item', {{ $item->id }}, 'up')"
                                                                                variant="icon-gray" icon="arrow-up" wire:key="item-up-{{ $item->id }}" title="Geser item ke atas" class="!p-1" :disabled="$loop->first" />
                                                                            <x-loading-button wire:click="moveStructureNode('item', {{ $item->id }}, 'down')"
                                                                                target="moveStructureNode('item', {{ $item->id }}, 'down')"
                                                                                variant="icon-gray" icon="arrow-down" wire:key="item-down-{{ $item->id }}" title="Geser item ke bawah" class="!p-1" :disabled="$loop->last" />
                                                                            <x-loading-button wire:click="openEditNode('item', {{ $item->id }})"
                                                                                target="openEditNode('item', {{ $item->id }})"
                                                                                variant="icon-blue" icon="edit" wire:key="item-edit-{{ $item->id }}" title="Ubah item ini" />
                                                                            <x-loading-button wire:click="confirmRemoveNode('item', {{ $item->id }})"
                                                                                target="confirmRemoveNode('item', {{ $item->id }})"
                                                                                variant="icon-red" icon="delete" wire:key="item-del-{{ $item->id }}" title="Hapus item ini" />
                                                                        </div>
                                                                    </td>
                                                                @endif
                                                            </tr>
                                                        @endif
                                                    @endforeach
                                                </tbody>
                                                @if($editStructure)
                                                    <tfoot>
                                                        <tr>
                                                            <td></td>
                                                            <td colspan="{{ $isInventoryGroup ? 4 : count($scoreLabels) + 3 }}" class="px-3 py-1.5">
                                                                <x-loading-button wire:click="openAddNode('item', {{ $itemGroup->id }})"
                                                                    target="openAddNode('item', {{ $itemGroup->id }})"
                                                                    variant="secondary" size="sm" icon="plus" wire:key="item-add-{{ $itemGroup->id }}" title="Tambah item ke grup ini">
                                                                    Tambah Item
                                                                </x-loading-button>
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                @endif
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
                                @if($editStructure)
                                    <div class="mb-4">
                                        <x-loading-button wire:click="openAddNode('item_group', {{ $subCat->id }})"
                                            target="openAddNode('item_group', {{ $subCat->id }})"
                                            variant="secondary" size="sm" icon="plus" wire:key="ig-add-{{ $subCat->id }}" title="Tambah grup item ke sub kategori ini">
                                            Tambah Grup Item
                                        </x-loading-button>
                                    </div>
                                @endif
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
        @endif

        <!-- Action Bar (Sticky, width matches cards above; offset dari tepi
             viewport agar tidak mepet — terlihat mengambang, tidak tertutup chrome) -->
        <div class="sticky bottom-3 sm:bottom-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg py-3 px-4 sm:px-6 z-10">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-end">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mr-auto">
                    <!-- Toggle penyimpanan draft otomatis — global, berlaku semua langkah -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Auto Save</span>
                        <x-toggle-switch wire:click="toggleAutoSave" :active="$autoSave" target="toggleAutoSave" activeColor="green" title="Aktifkan/nonaktifkan penyimpanan draft otomatis" />
                    </div>
                    <!-- Draft auto-save indicator -->
                    <div class="flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500" x-show="lastSaved" x-cloak>
                        <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Draft tersimpan <span x-text="lastSaved"></span></span>
                    </div>
                    <!-- Indikator isian kolaborator baru saja disinkronkan (transien) -->
                    <div class="flex items-center gap-1.5 text-xs text-blue-500 dark:text-blue-400" x-show="liveSyncBy" x-cloak>
                        <svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                        </svg>
                        <span x-text="liveSyncText"></span>
                    </div>
                </div>
                <x-cancel-button x-on:click="requestCancel()" target="cancel" class="w-full sm:w-auto sm:min-w-[160px]" />
                {{-- Kembali + Lanjut sejajar di mobile (wrapper hilang via sm:contents
                     sehingga tombol kembali jadi anak flex parent di desktop) --}}
                <div class="flex items-center gap-3 w-full sm:contents">
                    @if($activeStep > 1)
                        <x-loading-button type="button" wire:click="prevStep" target="prevStep" variant="secondary" size="lg" icon="arrow-left" class="flex-1 sm:flex-none sm:w-auto sm:min-w-[160px]">
                            Kembali
                        </x-loading-button>
                    @endif
                    @if($activeStep < 3)
                        <x-loading-button type="button" wire:click="nextStep" target="nextStep" variant="secondary" size="lg" icon="arrow-right" iconPosition="right" loadingText="Memeriksa..." class="flex-1 sm:flex-none sm:w-auto sm:min-w-[160px]">
                            Lanjut
                        </x-loading-button>
                    @endif
                </div>
                <x-loading-button type="submit" target="save" variant="primary" size="lg" loadingText="Menyimpan..." class="w-full sm:w-auto sm:min-w-[160px]">
                    {{ $editMode ? 'Update Survey' : 'Simpan Survey' }}
                </x-loading-button>
            </div>
        </div>
    </form>

    <!-- Modal tambah/ubah node struktur (kategori, sub kategori, grup item, item) -->
    <x-modal name="survey-node-modal" maxWidth="md" focusable>
        @php
            $nodeLevelLabels = [
                'category' => 'Kategori',
                'sub_category' => 'Sub Kategori',
                'item_group' => 'Grup Item',
                'item' => 'Item',
            ];
            $nodeLevelLabel = $nodeLevelLabels[$nodeLevel] ?? 'Node';
        @endphp
        <div class="px-4 py-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                {{ $nodeModalMode === 'add' ? 'Tambah' : 'Ubah' }} {{ $nodeLevelLabel }}
            </h3>

            <div class="space-y-4">
                <div>
                    <x-input-label for="node_name" :value="$nodeLevel === 'category' ? 'Label Kategori' : 'Nama '.$nodeLevelLabel" :required="true" />
                    <x-text-input wire:model="nodeName" id="node_name" type="text" class="mt-1 block w-full"
                        placeholder="Nama {{ strtolower($nodeLevelLabel) }}" />
                    <x-input-error :messages="$errors->get('nodeName')" class="mt-2" />
                </div>

                @if($nodeLevel === 'item')
                    <div>
                        <x-input-label for="node_item_type" value="Tipe Item" :required="true" />
                        <select wire:model.live="nodeItemType" id="node_item_type" @disabled($nodeTypeLocked)
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:opacity-60 disabled:cursor-not-allowed">
                            @foreach(\App\Enums\SurveyItemType::cases() as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('nodeItemType')" class="mt-2" />
                    </div>

                    @if($nodeTypeLocked)
                        <p class="text-xs text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 rounded-md px-3 py-2">
                            Tipe & label skor mengikuti grup item (grup sudah berisi item lain).
                        </p>
                    @elseif($nodeItemType === \App\Enums\SurveyItemType::Score->value)
                        <div>
                            <x-input-label for="node_score_labels" value="Label Kolom Skor" :required="true" />
                            <x-text-input wire:model="nodeScoreLabels" id="node_score_labels" type="text" class="mt-1 block w-full"
                                placeholder="Contoh: C, V, F, M" />
                            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Pisahkan dengan koma.</p>
                            <x-input-error :messages="$errors->get('nodeScoreLabels')" class="mt-1" />
                        </div>
                    @endif

                    @if($nodeItemType === \App\Enums\SurveyItemType::Score->value)
                        <label for="node_has_date_fields" class="inline-flex items-center gap-2">
                            <input wire:model="nodeHasDateFields" id="node_has_date_fields" type="checkbox"
                                class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500 dark:bg-gray-700">
                            <span class="text-sm text-gray-700 dark:text-gray-300">Punya field tanggal (Issued/Expired)</span>
                        </label>
                    @endif
                @endif
            </div>

            <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3">
                <x-cancel-button wire:click="closeNodeModal" target="closeNodeModal" class="w-full sm:w-auto" />
                <x-loading-button wire:click="saveNode" target="saveNode" variant="primary" size="lg"
                    loadingText="Menyimpan..." class="w-full sm:w-auto">
                    Simpan
                </x-loading-button>
            </div>
        </div>
    </x-modal>

    <!-- Modal konfirmasi sinkron data kapal ke master (status Selesai) -->
    <x-modal name="survey-ship-sync-modal" maxWidth="xl" focusable>
        @php
            $preview = $this->shipSyncPreview;
            $syncTarget = $preview['target'] ?? null;
            $syncDiff = $preview['diff'] ?? ['fields' => [], 'certificates_changed' => false];
        @endphp
        <div class="px-4 py-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                {{ $shipSyncMode === 'manual' ? 'Update Master Data Kapal?' : 'Simpan Data Kapal ke Master Data?' }}
            </h3>

            @if($shipSyncMode === 'manual')
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Data kapal dari form ini akan {{ ($preview['creating'] ?? false) ? 'membuat record baru' : 'menimpa' }}
                    di Master Data Kapal{{ ($preview['creating'] ?? false) ? '' : ' berikut (dicocokkan via Call Sign)' }}:
                </p>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Status survey diset <span class="font-semibold">Selesai</span>. Data kapal dari survey ini akan
                    {{ ($preview['creating'] ?? false) ? 'membuat record baru' : 'menimpa master data kapal berikut (dicocokkan via Call Sign)' }}
                    di Master Data Kapal:
                </p>
            @endif

            @if($preview['creating'] ?? false)
                <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-3 py-2">
                    <span class="text-sm font-semibold text-green-900 dark:text-green-200">{{ $shipData['name'] ?? 'Kapal Baru' }}</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                        Kapal baru — akan dibuat di Master Data
                    </span>
                </div>
            @elseif($syncTarget)
                <div class="mt-3 flex flex-wrap items-center gap-2 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-3 py-2">
                    <span class="text-sm font-semibold text-blue-900 dark:text-blue-200">{{ $syncTarget->name }}</span>
                    @if($syncTarget->call_sign)
                        <span class="text-xs text-blue-700 dark:text-blue-300">Call Sign: {{ $syncTarget->call_sign }}</span>
                    @endif
                    @if($preview['matched_by_call_sign'] ?? false)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
                            title="Call Sign survey cocok dengan kapal lain di master — kapal ini yang akan diperbarui, bukan kapal yang dipilih di form.">
                            Ditimpa berdasarkan Call Sign
                        </span>
                    @endif
                </div>
            @endif

            @if(!empty($syncDiff['fields']))
                <div class="mt-4 max-h-64 overflow-y-auto custom-scrollbar rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Field</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Master Saat Ini</th>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Data Survey</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 bg-white dark:bg-gray-800">
                            @foreach($syncDiff['fields'] as $field)
                                <tr wire:key="sync-diff-{{ $field['key'] }}">
                                    <td class="px-3 py-2 text-xs font-medium text-gray-900 dark:text-white">{{ $field['label'] }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400">{{ $field['old'] ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs font-medium text-blue-700 dark:text-blue-300">{{ $field['new'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($syncDiff['certificates_changed'] ?? false)
                <p class="mt-3 text-xs text-gray-600 dark:text-gray-300">
                    Daftar sertifikat Status Class akan diperbarui
                    ({{ $syncDiff['old_cert_count'] ?? 0 }} → {{ $syncDiff['new_cert_count'] ?? 0 }} sertifikat).
                </p>
            @endif

            @if($shipSyncMode !== 'manual')
                <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    Jika ada data kapal yang salah, pilih Batal lalu perbaiki di langkah
                    <span class="font-medium">Data Kapal</span> pada form sebelum menyimpan kembali.
                </p>
            @endif

            <div class="mt-6 flex flex-col sm:flex-row sm:justify-end gap-3">
                <x-cancel-button wire:click="closeShipSyncModal" target="closeShipSyncModal" class="w-full sm:w-auto" />
                <x-loading-button wire:click="confirmShipSync" target="confirmShipSync" variant="primary" size="lg"
                    loadingText="Menyimpan..." class="w-full sm:w-auto" wire:key="btn-confirm-ship-sync">
                    {{ $shipSyncMode === 'manual' ? 'Update Master Kapal' : 'Simpan & Update Master Kapal' }}
                </x-loading-button>
            </div>
        </div>
    </x-modal>

    <!-- Modal konfirmasi buang perubahan saat Batal -->
    <x-confirm-modal eventName="confirm-cancel-survey" />
    <x-confirm-modal eventName="confirm-overwrite-survey" />
    <x-confirm-modal eventName="confirm-remove-note" />
    <x-confirm-modal eventName="confirm-remove-node" />
    <x-confirm-modal eventName="confirm-pull-master" />
    <x-confirm-modal eventName="confirm-remove-cert" />
</div>
