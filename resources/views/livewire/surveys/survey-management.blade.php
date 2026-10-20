<div>
    @php
        $canExport = auth()->user()->can('surveys_export_excel') || auth()->user()->can('surveys_export_pdf');
        $canSelect = $canExport || auth()->user()->can('surveys_update');
    @endphp
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center gap-3">
        <!-- Search -->
        <div class="flex-1 w-full lg:w-auto">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari survey (nomor, kapal, surveyor)..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>

        <!-- Filter Popover -->
        <x-filter-popover :filters="['statusFilter', 'shipFilter']">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                    <x-searchable-select
                        wire:model.live="statusFilter"
                        :options="$this->statusOptions"
                        placeholder="Semua Status"
                        searchPlaceholder="Cari status..."
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Kapal</label>
                    <x-searchable-select
                        wire:model.live="shipFilter"
                        :options="$this->shipOptions"
                        placeholder="Semua Kapal"
                        searchPlaceholder="Cari kapal..."
                    />
                </div>
            </div>
        </x-filter-popover>

        <!-- Action Buttons: utama (Excel/PDF/Tambah) satu baris,
             aksi sekunder turun ke baris sendiri mengisi lebar penuh di mobile -->
        <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto lg:justify-end">
            @can('surveys_export_excel')
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel" icon="excel" class="lg:order-1">
                    Excel
                </x-loading-button>
            @endcan
            @can('surveys_export_pdf')
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF" icon="pdf" class="lg:order-2">
                    PDF
                </x-loading-button>
            @endcan
            @can('surveys_create')
                <x-loading-button wire:click="createSurvey" target="createSurvey" variant="primary" size="md" loadingText="Memuat..." icon="plus" class="flex-1 lg:flex-none lg:order-4">
                    Tambah Survey
                </x-loading-button>
            @endcan

            @if(count($selected) || (auth()->user()->can('survey_folders_create') && !$currentFolder))
                <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto lg:order-3">
                    @if(count($selected))
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                            {{ count($selected) }} dipilih
                            <x-loading-button type="button" wire:click="clearSelection" target="clearSelection"
                                variant="icon-gray" icon="close" iconClass="w-3.5 h-3.5" wire:key="btn-clear-selection"
                                title="Hapus pilihan" />
                        </span>
                        @can('surveys_update')
                            <x-loading-button type="button" wire:click="openBulkMoveModal" target="openBulkMoveModal"
                                variant="warning" size="sm" loadingText="Memuat..."
                                wire:key="btn-bulk-move" title="Pindahkan survey terpilih ke folder" class="flex-1 lg:flex-none">
                                <x-slot:icon>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5l3 3m0 0l3-3m-3 3v-6m1.06-4.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                                </x-slot:icon>
                                Pindahkan
                            </x-loading-button>
                        @endcan
                    @endif
                    @can('survey_folders_create')
                        @if(!$currentFolder)
                            <x-loading-button wire:click="createFolder" target="createFolder" variant="secondary" size="md" loadingText="Memuat..." title="Tambah Folder" class="flex-1 lg:flex-none">
                                <x-slot:icon>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                                </x-slot:icon>
                                Folder
                            </x-loading-button>
                        @endif
                    @endcan
                </div>
            @endif
        </div>
    </div>

    <!-- Breadcrumb folder aktif -->
    @if($currentFolder)
        <div class="mb-4 flex items-center gap-2 text-sm">
            <x-loading-button type="button" wire:click="backToRoot" target="backToRoot"
                variant="icon-gray" icon="arrow-up" class="-rotate-90"
                wire:key="btn-back-root" title="Kembali ke daftar utama" />
            <button type="button" wire:click="backToRoot" wire:target="backToRoot"
                class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                Survey Kondisi
            </button>
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="inline-flex min-w-0 items-center gap-1.5 font-semibold text-gray-900 dark:text-white" title="{{ $currentFolder->name }}">
                <svg class="h-4 w-4 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M19.5 21a3 3 0 003-3v-4.5a3 3 0 00-3-3h-15a3 3 0 00-3 3V18a3 3 0 003 3h15zM1.5 10.146V6a3 3 0 013-3h5.379a3 3 0 012.12.879l2.122 2.121a3 3 0 002.12.88h5.26a3 3 0 013 3v.267"/>
                </svg>
                <span class="truncate">{{ $currentFolder->name }}</span>
            </span>
            <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">({{ $surveys->total() }} survey)</span>
        </div>
    @endif

    <!-- Strip folder (hanya di root — folder tidak bertingkat) -->
    @if(!$currentFolder && $folders->isNotEmpty())
        <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            @foreach($folders as $folder)
                <div wire:key="folder-{{ $folder->id }}"
                    class="group relative flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm transition-colors hover:border-blue-300 hover:bg-blue-50/50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-blue-700 dark:hover:bg-blue-900/10">
                    <button type="button" wire:click="openFolder({{ $folder->id }})" wire:target="openFolder({{ $folder->id }})"
                        wire:loading.attr="disabled"
                        class="flex min-w-0 flex-1 items-center gap-3 text-left">
                        <svg wire:loading.class="hidden" wire:target="openFolder({{ $folder->id }})"
                            class="h-9 w-9 flex-shrink-0 text-amber-500 dark:text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19.5 21a3 3 0 003-3v-4.5a3 3 0 00-3-3h-15a3 3 0 00-3 3V18a3 3 0 003 3h15zM1.5 10.146V6a3 3 0 013-3h5.379a3 3 0 012.12.879l2.122 2.121a3 3 0 002.12.88h5.26a3 3 0 013 3v.267"/>
                        </svg>
                        <svg wire:loading wire:target="openFolder({{ $folder->id }})"
                            class="h-9 w-9 flex-shrink-0 animate-spin text-amber-500 dark:text-amber-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-gray-900 dark:text-white" title="{{ $folder->name }}">{{ $folder->name }}</span>
                            @if(isset($folder->matched_surveys_count) && $folder->matched_surveys_count > 0)
                                <span class="block text-xs font-medium text-green-600 dark:text-green-400">{{ $folder->matched_surveys_count }} cocok dari {{ $folder->surveys_count }} survey</span>
                            @else
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $folder->surveys_count }} survey</span>
                            @endif
                        </span>
                    </button>
                    <div class="flex items-center gap-1">
                        @can('survey_folders_update')
                            <x-loading-button type="button" wire:click="editFolder({{ $folder->id }})"
                                target="editFolder({{ $folder->id }})"
                                variant="icon-blue" icon="edit"
                                wire:key="btn-edit-folder-{{ $folder->id }}"
                                title="Ubah nama folder" />
                        @endcan
                        @can('survey_folders_delete')
                            <x-loading-button type="button" wire:click="confirmDeleteFolder({{ $folder->id }})"
                                target="confirmDeleteFolder({{ $folder->id }})"
                                variant="icon-red" icon="delete"
                                wire:key="btn-delete-folder-{{ $folder->id }}"
                                title="Hapus folder" />
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full table-fixed divide-y divide-gray-200 dark:divide-gray-700">
                <colgroup>
                    @if($canSelect)
                        <col class="w-12">
                    @endif
                    <col class="w-44">
                    <col>
                    <col>
                    <col class="w-28">
                    <col class="w-40">
                    <col class="w-24">
                    <col class="w-32">
                    <col class="w-32">
                </colgroup>
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        @if($canSelect)
                            <th class="w-12 px-4 py-3 text-center">
                                <input type="checkbox" wire:model.live="selectAll"
                                    aria-label="Pilih semua survey di halaman ini"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:focus:ring-offset-gray-800">
                            </th>
                        @endif
                        <x-sortable-header field="survey_number" :sort-field="$sortField" :sort-dir="$sortDir">Nomor Survey</x-sortable-header>
                        <x-sortable-header field="ship" :sort-field="$sortField" :sort-dir="$sortDir">Kapal</x-sortable-header>
                        <x-sortable-header field="template" :sort-field="$sortField" :sort-dir="$sortDir">Template</x-sortable-header>
                        <x-sortable-header field="survey_date" :sort-field="$sortField" :sort-dir="$sortDir">Tanggal</x-sortable-header>
                        <x-sortable-header field="surveyor" :sort-field="$sortField" :sort-dir="$sortDir">Surveyor</x-sortable-header>
                        <x-sortable-header field="overall_cap_score" :sort-field="$sortField" :sort-dir="$sortDir">CAP Score</x-sortable-header>
                        <x-sortable-header field="status" :sort-field="$sortField" :sort-dir="$sortDir">Status</x-sortable-header>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($surveys as $survey)
                        <tr wire:key="row-{{ $survey->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            @if($canSelect)
                                <td class="w-12 px-4 py-4 text-center">
                                    <input type="checkbox" wire:model.live="selected" value="{{ $survey->id }}"
                                        aria-label="Pilih survey {{ $survey->survey_number }}"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:focus:ring-offset-gray-800">
                                </td>
                            @endif
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate" title="{{ $survey->survey_number }}">{{ $survey->survey_number }}</div>
                                @if($survey->location)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 truncate" title="{{ $survey->location }}">{{ $survey->location }}</div>
                                @endif
                                @if(! $currentFolder && $survey->folder)
                                    <div class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400" title="Folder: {{ $survey->folder->name }}">
                                        <svg class="h-3 w-3 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M19.5 21a3 3 0 003-3v-4.5a3 3 0 00-3-3h-15a3 3 0 00-3 3V18a3 3 0 003 3h15zM1.5 10.146V6a3 3 0 013-3h5.379a3 3 0 012.12.879l2.122 2.121a3 3 0 002.12.88h5.26a3 3 0 013 3v.267"/>
                                        </svg>
                                        <span class="truncate">{{ $survey->folder->name }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white truncate" title="{{ $survey->ship_display_name }}">{{ $survey->ship_display_name ?? '-' }}</div>
                                @if($survey->ship?->year_built)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Thn {{ $survey->ship->year_built }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white truncate" title="{{ $survey->template?->name ?? ($survey->structure['template_name'] ?? '') }}">{{ $survey->template?->name ?? ($survey->structure['template_name'] ?? '-') }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white truncate">
                                {{ $survey->survey_date?->format('d M Y') ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white truncate" title="{{ $survey->surveyor }}">
                                {{ $survey->surveyor ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($survey->overall_cap_score !== null)
                                    @php
                                        $score = (float) $survey->overall_cap_score;
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ survey_score_badge_class($score) }}" title="{{ $survey->capRating()->description() }}">
                                        {{ $survey->capRating()->label() }} &middot; {{ number_format($score, 2) }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full {{ $survey->status->badgeClass() }}">
                                    {{ $survey->status->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <x-loading-button wire:click="viewSurvey({{ $survey->id }})"
                                        target="viewSurvey({{ $survey->id }})"
                                        variant="icon-blue" icon="view"
                                        wire:key="btn-view-{{ $survey->id }}"
                                        title="Lihat Detail" />
                                    @can('surveys_create')
                                        <x-loading-button wire:click="openDuplicateModal({{ $survey->id }})"
                                            target="openDuplicateModal({{ $survey->id }})"
                                            variant="icon-green" icon="copy"
                                            wire:key="btn-duplicate-{{ $survey->id }}"
                                            title="Duplikasi (tanpa nilai respon)" />
                                    @endcan
                                    @can('update', $survey)
                                        <x-loading-button wire:click="openMoveModal({{ $survey->id }})"
                                            target="openMoveModal({{ $survey->id }})"
                                            variant="icon-amber"
                                            wire:key="btn-move-{{ $survey->id }}"
                                            title="Pindahkan ke folder">
                                            <x-slot:icon>
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5l3 3m0 0l3-3m-3 3v-6m1.06-4.19l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"/></svg>
                                            </x-slot:icon>
                                        </x-loading-button>
                                        <x-loading-button wire:click="editSurvey({{ $survey->id }})"
                                            target="editSurvey({{ $survey->id }})"
                                            variant="icon-blue" icon="edit"
                                            wire:key="btn-edit-{{ $survey->id }}"
                                            title="Edit" />
                                    @endcan
                                    @can('delete', $survey)
                                        <x-loading-button wire:click="confirmDelete({{ $survey->id }})"
                                            target="confirmDelete({{ $survey->id }})"
                                            variant="icon-red" icon="delete"
                                            wire:key="btn-delete-{{ $survey->id }}"
                                            title="Hapus" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canSelect ? 9 : 8 }}" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada survey ditemukan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $surveys->links() }}
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <x-delete-modal
        :show="$showDeleteModal"
        wire:model="showDeleteModal"
        title="Hapus Survey"
        message="Apakah Anda yakin ingin menghapus survey"
        :itemName="$deletingSurveyNumber"
        confirmMethod="delete"
    />

    <!-- Delete Folder Confirmation Modal -->
    <x-delete-modal
        :show="$showDeleteFolderModal"
        wire:model="showDeleteFolderModal"
        title="Hapus Folder"
        message="Apakah Anda yakin ingin menghapus folder"
        :itemName="$deletingFolderName"
        confirmMethod="deleteFolder"
    />

    <!-- Modal tambah/ubah folder -->
    <x-modal name="survey-folder-modal" maxWidth="md" focusable>
        <form wire:submit="saveFolder" class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $editingFolderId ? 'Ubah Folder' : 'Tambah Folder' }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Folder dipakai untuk mengelompokkan survey di halaman ini.
            </p>
            <div class="mt-4">
                <x-input-label for="folderName" value="Nama Folder" :required="true" />
                <x-text-input id="folderName" wire:model="folderName" type="text" class="mt-1 block w-full"
                    placeholder="Mis. Survey 2026, Kapal RoRo, Arsip Lama" />
                <x-input-error :messages="$errors->get('folderName')" class="mt-1" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-cancel-button type="button" wire:click="closeFolderModal" target="closeFolderModal" />
                <x-loading-button type="submit" target="saveFolder" variant="primary" size="md" loadingText="Menyimpan..." icon="check">
                    {{ $editingFolderId ? 'Simpan' : 'Buat Folder' }}
                </x-loading-button>
            </div>
        </form>
    </x-modal>

    <!-- Modal pindah survey ke folder -->
    <x-modal name="survey-move-modal" maxWidth="md" focusable>
        <form wire:submit="saveMove" class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pindahkan Survey</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if($movingSurveyIds !== [])
                    Pilih folder tujuan untuk <span class="font-semibold text-gray-700 dark:text-gray-200">{{ count($movingSurveyIds) }} survey terpilih</span>.
                @else
                    Pilih folder tujuan untuk survey <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $movingSurveyNumber }}</span>.
                @endif
            </p>
            <div class="mt-4">
                <x-input-label for="moveFolderId" value="Folder Tujuan" />
                <x-searchable-select
                    wire:model="moveFolderId"
                    :options="$this->folderOptions"
                    placeholder="Tanpa Folder (Root)"
                    searchPlaceholder="Cari folder..."
                    emptyText="Belum ada folder — buat dulu di halaman utama"
                />
                <x-input-error :messages="$errors->get('moveFolderId')" class="mt-1" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-cancel-button type="button" wire:click="closeMoveModal" target="closeMoveModal" />
                <x-loading-button type="submit" target="saveMove" variant="primary" size="md" loadingText="Memindahkan..." icon="check">
                    Pindahkan
                </x-loading-button>
            </div>
        </form>
    </x-modal>

    <!-- Modal duplikasi survey (tanpa value) -->
    <x-modal name="survey-duplicate-modal" maxWidth="md" focusable>
        <form wire:submit="duplicateSurvey" class="px-4 py-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Duplikasi Survey</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Survey <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $duplicatingSurveyNumber }}</span>
                akan disalin <span class="font-semibold">tanpa nilai respon</span> (status Draft, nomor baru).
                Pilih folder tujuan untuk hasil duplikasi.
            </p>
            <div class="mt-4">
                <x-input-label for="duplicateFolderId" value="Folder Tujuan" />
                <x-searchable-select
                    wire:model="duplicateFolderId"
                    :options="$this->folderOptions"
                    placeholder="Tanpa Folder (Root)"
                    searchPlaceholder="Cari folder..."
                    emptyText="Belum ada folder — buat dulu di halaman utama"
                />
                <x-input-error :messages="$errors->get('duplicateFolderId')" class="mt-1" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-cancel-button type="button" wire:click="closeDuplicateModal" target="closeDuplicateModal" />
                <x-loading-button type="submit" target="duplicateSurvey" variant="primary" size="md" loadingText="Menduplikasi..." icon="copy">
                    Duplikasi
                </x-loading-button>
            </div>
        </form>
    </x-modal>

    <!-- Template Picker Modal -->
    @if($showTemplateModal)
        <div class="fixed inset-0 z-[60] overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 py-6">
                <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" wire:click="$set('showTemplateModal', false)"></div>
                <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-2xl z-10 p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Pilih Template Survey</h3>
                        <button type="button" wire:click="closeTemplateModal" target="closeTemplateModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Pilih template yang akan digunakan untuk survey ini. Template tidak dapat diubah setelah survey dibuat.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[60vh] overflow-y-auto custom-scrollbar">
                        @foreach($this->templateOptions as $template)
                            <button type="button" wire:click="proceedWithTemplate({{ $template['id'] }})" wire:target="proceedWithTemplate({{ $template['id'] }})" wire:key="tpl-{{ $template['id'] }}"
                                wire:loading.attr="disabled" wire:loading.class="opacity-60 cursor-wait" wire:loading.class.remove="hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20"
                                class="relative text-left p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors disabled:cursor-wait">
                                <div wire:target="proceedWithTemplate({{ $template['id'] }})" wire:loading.class="flex" wire:loading.class.remove="hidden"
                                    class="hidden absolute inset-0 items-center justify-center bg-white/80 dark:bg-gray-800/80 rounded-lg z-10">
                                    <x-loading-spinner size="sm" />
                                </div>
                                <div class="flex items-start justify-between mb-1">
                                    <span class="text-xs font-mono font-semibold text-gray-500 dark:text-gray-400">{{ $template['code'] }}</span>
                                    @if($template['is_default'])
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Default</span>
                                    @endif
                                </div>
                                <div class="text-sm font-semibold text-gray-900 dark:text-white mb-1">{{ $template['name'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ $template['description'] ?? '-' }}</div>
                                <div class="text-xs text-gray-400 dark:text-gray-500">{{ $template['categories_count'] }} kategori</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
