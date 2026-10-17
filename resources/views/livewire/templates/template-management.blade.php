<div>
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center gap-3">
        <!-- Search -->
        <div class="flex-1 w-full lg:w-auto">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari template (nama, kode, deskripsi)..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>

        <!-- Filter Popover -->
        <x-filter-popover :filters="['statusFilter']">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                    <x-searchable-select
                        wire:model.live="statusFilter"
                        :options="[
                            ['value' => 'active', 'label' => 'Aktif'],
                            ['value' => 'inactive', 'label' => 'Nonaktif'],
                        ]"
                        placeholder="Semua Status"
                        searchPlaceholder="Cari status..."
                    />
                </div>
            </div>
        </x-filter-popover>

        <!-- Action Buttons: utama (Excel/PDF/Tambah) satu baris,
             aksi sekunder turun ke baris sendiri mengisi lebar penuh di mobile -->
        <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto lg:justify-end">
            @can('survey_templates_export_excel')
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel" icon="excel" class="lg:order-1">
                    Excel
                </x-loading-button>
            @endcan
            @can('survey_templates_export_pdf')
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF" icon="pdf" class="lg:order-2">
                    PDF
                </x-loading-button>
            @endcan
            @can('survey_templates_create')
                <x-loading-button wire:click="createTemplate" target="createTemplate" variant="primary" size="md" loadingText="Memuat..." icon="plus" class="flex-1 lg:flex-none lg:order-4">
                    Tambah Template
                </x-loading-button>
            @endcan

            @if(auth()->user()->can('survey_templates_export_excel') || auth()->user()->can('survey_templates_import'))
                <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto lg:order-3">
                    @can('survey_templates_export_excel')
                        <x-loading-button wire:click="downloadImportTemplate" target="downloadImportTemplate" variant="secondary" size="md" loadingText="Mengunduh..." title="Unduh template Excel untuk import struktur" icon="download" class="flex-1 lg:flex-none">
                            Template
                        </x-loading-button>
                    @endcan
                    @can('survey_templates_import')
                        <x-loading-button wire:click="openImportModal" target="openImportModal" variant="warning" size="md" loadingText="Memuat..." title="Import struktur template dari Excel" class="flex-1 lg:flex-none">
                            <x-slot:icon>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                            </x-slot:icon>
                            Import
                        </x-loading-button>
                    @endcan
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <x-sortable-header field="name" :sort-field="$sortField" :sort-dir="$sortDir">Nama Template</x-sortable-header>
                        <x-sortable-header field="code" :sort-field="$sortField" :sort-dir="$sortDir">Kode</x-sortable-header>
                        <x-sortable-header field="categories_count" :sort-field="$sortField" :sort-dir="$sortDir">Kategori</x-sortable-header>
                        <x-sortable-header field="surveys_count" :sort-field="$sortField" :sort-dir="$sortDir">Surveys</x-sortable-header>
                        <x-sortable-header field="is_active" :sort-field="$sortField" :sort-dir="$sortDir">Status</x-sortable-header>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($templates as $template)
                        <tr wire:key="row-{{ $template->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $template->name }}
                                    @if($template->is_default)
                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">Default</span>
                                    @endif
                                </div>
                                @if($template->description)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-1">{{ $template->description }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-mono">
                                {{ $template->code }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $template->categories_count ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $template->surveys_count ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @can('survey_templates_update')
                                    <x-toggle-switch wire:click="toggleStatus({{ $template->id }})"
                                        :active="$template->is_active"
                                        target="toggleStatus({{ $template->id }})"
                                        wire:key="toggle-status-{{ $template->id }}"
                                        title="Aktifkan/Nonaktifkan" />
                                @else
                                    @if($template->is_active)
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Aktif</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">Nonaktif</span>
                                    @endif
                                @endcan
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <x-loading-button wire:click="viewTemplate({{ $template->id }})"
                                        target="viewTemplate({{ $template->id }})"
                                        variant="icon-blue" icon="view"
                                        wire:key="btn-view-{{ $template->id }}"
                                        title="Lihat Detail" />
                                    @can('survey_templates_create')
                                        <x-loading-button wire:click="duplicateTemplate({{ $template->id }})"
                                            target="duplicateTemplate({{ $template->id }})"
                                            variant="icon-gray" icon="copy"
                                            wire:key="btn-duplicate-{{ $template->id }}"
                                            title="Duplikasi" />
                                    @endcan
                                    @can('survey_templates_export_excel')
                                        <x-loading-button wire:click="exportStructure({{ $template->id }})"
                                            target="exportStructure({{ $template->id }})"
                                            variant="icon-green" icon="excel"
                                            wire:key="btn-export-structure-{{ $template->id }}"
                                            title="Export struktur ke Excel (bisa di-import kembali)" />
                                    @endcan
                                    @can('survey_templates_update')
                                        <x-loading-button wire:click="editTemplate({{ $template->id }})"
                                            target="editTemplate({{ $template->id }})"
                                            variant="icon-blue" icon="edit"
                                            wire:key="btn-edit-{{ $template->id }}"
                                            title="Edit" />
                                    @endcan
                                    @can('survey_templates_delete')
                                        @if(!$template->is_default)
                                            <x-loading-button wire:click="confirmDelete({{ $template->id }})"
                                                target="confirmDelete({{ $template->id }})"
                                                variant="icon-red" icon="delete"
                                                wire:key="btn-delete-{{ $template->id }}"
                                                title="Hapus" />
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada template ditemukan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $templates->links() }}
        </div>
    </div>

    <!-- Import Struktur Modal -->
    <x-modal name="template-import-modal" maxWidth="md" focusable>
        <form wire:submit="importStructure" class="px-4 py-4 sm:p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Import Struktur Template</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Upload file Excel (.xlsx) sesuai format struktur. Belum punya formatnya?
                <button type="button" wire:click="downloadImportTemplate" wire:loading.attr="disabled" wire:target="downloadImportTemplate"
                    class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300 underline disabled:opacity-50">
                    Unduh template kosong
                </button>
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <x-input-label value="Mode Import" :required="true" />
                    <div class="mt-1 flex items-center gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input type="radio" wire:model.live="importMode" value="new"
                                class="text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            Template Baru
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                            <input type="radio" wire:model.live="importMode" value="existing"
                                class="text-blue-600 focus:ring-blue-500 border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                            Timpa Template Existing
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('importMode')" class="mt-1" />
                </div>

                @if($importMode === 'existing')
                    <div>
                        <x-input-label for="importTemplateId" value="Template Tujuan" :required="true" />
                        <x-searchable-select
                            wire:model="importTemplateId"
                            :options="$this->importTemplateOptions"
                            placeholder="Pilih template yang strukturnya akan ditimpa"
                            searchPlaceholder="Cari template..."
                            emptyText="Belum ada template"
                        />
                        <x-input-error :messages="$errors->get('importTemplateId')" class="mt-1" />
                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            Seluruh struktur template terpilih akan ditimpa oleh isi file. Survey yang sudah dibuat tidak terpengaruh (memakai snapshot masing-masing).
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="importName" value="Nama Template" :required="true" />
                            <x-text-input id="importName" wire:model="importName" type="text" class="mt-1 w-full"
                                placeholder="Nama template survey" />
                            <x-input-error :messages="$errors->get('importName')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="importCode" value="Kode Template" :required="true" />
                            <x-text-input id="importCode" wire:model="importCode" type="text" class="mt-1 w-full"
                                placeholder="Kode unik, mis. TPL-CS" />
                            <x-input-error :messages="$errors->get('importCode')" class="mt-1" />
                        </div>
                    </div>
                @endif

                <div>
                    <x-input-label for="importFile" value="File Excel" :required="true" />
                    <input id="importFile" wire:model="importFile" type="file" accept=".xlsx"
                        class="mt-1 block w-full text-sm text-gray-500 dark:text-gray-400
                            file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                            file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700
                            hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-300" />
                    <div wire:loading wire:target="importFile" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Mengupload file...</div>
                    @if($importFile)
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">File: {{ $importFile->getClientOriginalName() }}</p>
                    @endif
                    <x-input-error :messages="$errors->get('importFile')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-cancel-button type="button" wire:click="closeImportModal" target="closeImportModal" />
                <x-loading-button type="submit" target="importStructure" variant="primary" size="md" loadingText="Mengimport..." icon="check" wire:loading.attr="disabled" wire:target="importFile">
                    Import
                </x-loading-button>
            </div>
        </form>
    </x-modal>

    <!-- Delete Confirmation Modal -->
    <x-delete-modal
        :show="$showDeleteModal"
        wire:model="showDeleteModal"
        title="Hapus Template"
        message="Apakah Anda yakin ingin menghapus template"
        :itemName="$deletingTemplateName"
        confirmMethod="delete"
    />
</div>
