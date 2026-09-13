<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
        <!-- Search -->
        <div class="flex-1 w-full sm:w-auto">
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

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 w-full sm:w-auto">
            @can('survey_templates_export_excel')
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel" icon="excel">
                    Excel
                </x-loading-button>
            @endcan
            @can('survey_templates_export_pdf')
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF" icon="pdf">
                    PDF
                </x-loading-button>
            @endcan
            @can('survey_templates_create')
                <x-loading-button wire:click="createTemplate" target="createTemplate" variant="primary" size="md" loadingText="Memuat..." icon="plus" class="flex-1 sm:flex-none">
                    Tambah Template
                </x-loading-button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama Template</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kode</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kategori</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Surveys</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
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
