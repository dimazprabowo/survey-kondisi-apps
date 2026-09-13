<div>
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
        <!-- Search -->
        <div class="flex-1 w-full sm:w-auto">
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

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 w-full sm:w-auto">
            @can('surveys_export_excel')
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel" icon="excel">
                    Excel
                </x-loading-button>
            @endcan
            @can('surveys_export_pdf')
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF" icon="pdf">
                    PDF
                </x-loading-button>
            @endcan
            @can('surveys_create')
                <x-loading-button wire:click="createSurvey" target="createSurvey" variant="primary" size="md" loadingText="Memuat..." icon="plus" class="flex-1 sm:flex-none">
                    Tambah Survey
                </x-loading-button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nomor Survey</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kapal</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Template</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Surveyor</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">CAP Score</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($surveys as $survey)
                        <tr wire:key="row-{{ $survey->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $survey->survey_number }}</div>
                                @if($survey->location)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $survey->location }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $survey->ship?->name ?? '-' }}</div>
                                @if($survey->ship?->year_built)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Thn {{ $survey->ship->year_built }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $survey->template?->name ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $survey->survey_date?->format('d M Y') ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $survey->surveyor ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($survey->overall_cap_score !== null)
                                    @php
                                        $score = (float) $survey->overall_cap_score;
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ survey_score_badge_class($score) }}">
                                        {{ number_format($score, 2) }}
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
                                    @can('surveys_update')
                                        <x-loading-button wire:click="editSurvey({{ $survey->id }})"
                                            target="editSurvey({{ $survey->id }})"
                                            variant="icon-blue" icon="edit"
                                            wire:key="btn-edit-{{ $survey->id }}"
                                            title="Edit" />
                                    @endcan
                                    @can('surveys_delete')
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
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[60vh] overflow-y-auto">
                        @foreach($this->templateOptions as $template)
                            <button type="button" wire:click="proceedWithTemplate({{ $template['id'] }})" wire:target="proceedWithTemplate({{ $template['id'] }})" wire:key="tpl-{{ $template['id'] }}"
                                class="text-left p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors">
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
