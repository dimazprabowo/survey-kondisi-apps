<div>
    <div class="mb-6 flex flex-col md:flex-row md:items-center gap-3">
        <!-- Search -->
        <div class="flex-1 w-full md:w-auto">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari kapal..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>

        <!-- Filter Popover -->
        <x-filter-popover :filters="['statusFilter']">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                <x-searchable-select
                    wire:model.live="statusFilter"
                    :options="$this->statusOptions"
                    placeholder="Semua Status"
                    searchPlaceholder="Cari status..."
                />
            </div>
        </x-filter-popover>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 w-full md:w-auto">
            @can('ships_export_excel')
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel" icon="excel">
                    Excel
                </x-loading-button>
            @endcan
            @can('ships_export_pdf')
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF" icon="pdf">
                    PDF
                </x-loading-button>
            @endcan
            @can('ships_create')
                <x-loading-button wire:click="create" target="create" variant="primary" size="md" loadingText="Memuat..." class="flex-1 md:flex-none" icon="plus">
                    Tambah Kapal
                </x-loading-button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nama Kapal</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tahun</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Umur</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pemilik/Operator</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Survey</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($ships as $ship)
                        <tr wire:key="row-{{ $ship->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-xs">
                                            {{ strtoupper(substr($ship->name, 0, 2)) }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $ship->name }}</div>
                                        @if($ship->code)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $ship->code }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $ship->year_built ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                @if($ship->age !== null)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                        {{ $ship->age }} thn
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $ship->ship_type ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $ship->owner ?? '-' }}</div>
                                @if($ship->operator)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $ship->operator }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center whitespace-nowrap px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                    {{ $ship->surveys_count }} survey
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @can('ships_update')
                                    <x-toggle-switch wire:click="toggleStatus({{ $ship->id }})"
                                        :active="$ship->status->value === 'active'"
                                        target="toggleStatus({{ $ship->id }})"
                                        wire:key="toggle-status-{{ $ship->id }}"
                                        title="Aktifkan/Nonaktifkan" />
                                @else
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $ship->status->badgeClass() }}">
                                        {{ $ship->status->label() }}
                                    </span>
                                @endcan
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @can('ships_update')
                                        <x-loading-button wire:click="edit({{ $ship->id }})"
                                            target="edit({{ $ship->id }})"
                                            variant="icon-blue" icon="edit"
                                            wire:key="btn-edit-{{ $ship->id }}"
                                            title="Edit" />
                                    @endcan
                                    @can('ships_delete')
                                        @if($ship->surveys_count === 0)
                                            <x-loading-button wire:click="confirmDelete({{ $ship->id }})"
                                                target="confirmDelete({{ $ship->id }})"
                                                variant="icon-red" icon="delete"
                                                wire:key="btn-delete-{{ $ship->id }}"
                                                title="Hapus" />
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7l1.5-1.5h15L21 7M3 7v12a1 1 0 001 1h16a1 1 0 001-1V7M3 7l4 4m4-4l4 4m4-4l4 4"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada kapal ditemukan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $ships->links() }}
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" @click="$wire.closeModal()"></div>

                <div class="inline-block align-bottom w-full bg-white dark:bg-gray-800 rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                {{ $editMode ? 'Edit Kapal' : 'Tambah Kapal' }}
                            </h3>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="name" value="Nama Kapal" :required="true" />
                                    <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" placeholder="Nama kapal" />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="code" value="Kode Kapal (opsional)" />
                                    <x-text-input wire:model="code" id="code" type="text" class="mt-1 block w-full" placeholder="Kode kapal" />
                                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="year_built" value="Tahun Pembuatan" />
                                    <x-text-input wire:model="year_built" id="year_built" type="number" min="1900" max="{{ (int) now()->format('Y') }}" class="mt-1 block w-full" placeholder="Tahun pembuatan" />
                                    <x-input-error :messages="$errors->get('year_built')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="imo_number" value="Nomor IMO" />
                                    <x-text-input wire:model="imo_number" id="imo_number" type="text" class="mt-1 block w-full" placeholder="Nomor IMO" />
                                    <x-input-error :messages="$errors->get('imo_number')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="ship_type" value="Jenis Kapal" />
                                    <x-text-input wire:model="ship_type" id="ship_type" type="text" class="mt-1 block w-full" placeholder="Jenis kapal" />
                                    <x-input-error :messages="$errors->get('ship_type')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="flag" value="Bendera" />
                                    <x-text-input wire:model="flag" id="flag" type="text" class="mt-1 block w-full" placeholder="Bendera kapal" />
                                    <x-input-error :messages="$errors->get('flag')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="gross_tonnage" value="Gross Tonnage" />
                                    <x-text-input wire:model="gross_tonnage" id="gross_tonnage" type="text" class="mt-1 block w-full" placeholder="Gross tonnage" />
                                    <x-input-error :messages="$errors->get('gross_tonnage')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="status" value="Status" :required="true" />
                                    <x-searchable-select wire:model="status" :options="$this->statusOptions" placeholder="Pilih status" />
                                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label for="owner" value="Pemilik" />
                                    <x-text-input wire:model="owner" id="owner" type="text" class="mt-1 block w-full" placeholder="Pemilik kapal" />
                                    <x-input-error :messages="$errors->get('owner')" class="mt-2" />
                                </div>
                                <div class="sm:col-span-2">
                                    <x-input-label for="operator" value="Operator" />
                                    <x-text-input wire:model="operator" id="operator" type="text" class="mt-1 block w-full" placeholder="Operator kapal" />
                                    <x-input-error :messages="$errors->get('operator')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <x-loading-button type="submit" target="save" variant="primary" size="lg"
                                loadingText="Menyimpan..." class="w-full sm:w-auto">
                                {{ $editMode ? 'Update' : 'Simpan' }}
                            </x-loading-button>
                            <x-cancel-button wire:click="closeModal" target="closeModal"
                                class="mt-3 sm:mt-0 w-full sm:w-auto" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Confirmation Modal -->
    <x-delete-modal
        :show="$showDeleteModal"
        wire:model="showDeleteModal"
        title="Hapus Kapal"
        message="Apakah Anda yakin ingin menghapus kapal"
        :itemName="$deletingShipName"
        confirmMethod="delete"
    />
</div>
