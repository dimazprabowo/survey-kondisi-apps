<div>
    <div class="mb-6 flex flex-col md:flex-row md:items-center gap-3">
        <!-- Search -->
        <div class="flex-1 w-full md:w-auto">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari konfigurasi..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>

        <!-- Filter Popover -->
        <x-filter-popover :filters="['isActiveFilter']">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                <x-searchable-select
                    wire:model.live="isActiveFilter"
                    :options="$this->isActiveOptions"
                    placeholder="Semua Status"
                    searchPlaceholder="Cari status..."
                />
            </div>
        </x-filter-popover>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 w-full md:w-auto">
            @can('configuration_export_excel')
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel" icon="excel">
                    Excel
                </x-loading-button>
            @endcan
            @can('configuration_export_pdf')
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF" icon="pdf">
                    PDF
                </x-loading-button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Key</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kategori</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Value</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tipe</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($configurations as $config)
                        <tr wire:key="config-{{ $config->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $config->key }}</div>
                                @if($config->description)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $config->description }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                    @if($config->category->value === 'threshold') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                    @elseif($config->category->value === 'sla') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                                    @elseif($config->category->value === 'notification') bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
                                    @else bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400
                                    @endif">
                                    {{ $config->category->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-white max-w-xs truncate">{{ $config->value }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $config->data_type->label() }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @can('configuration_update')
                                    <x-toggle-switch wire:click="toggleActive({{ $config->id }})"
                                        :active="$config->is_active"
                                        target="toggleActive({{ $config->id }})"
                                        wire:key="toggle-active-{{ $config->id }}"
                                        title="Aktifkan/Nonaktifkan" />
                                @else
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $config->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400' }}">
                                        {{ $config->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                @endcan
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @can('configuration_update')
                                        <x-loading-button wire:click="edit({{ $config->id }})"
                                            target="edit({{ $config->id }})"
                                            variant="icon-blue" icon="edit"
                                            wire:key="btn-edit-{{ $config->id }}"
                                            title="Edit" />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada konfigurasi ditemukan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $configurations->links() }}
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" @click="$wire.closeModal()"></div>

                <div class="inline-block align-bottom w-full bg-white dark:bg-gray-800 rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                Edit Konfigurasi
                            </h3>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Key <span class="text-red-500">*</span></label>
                                    <input wire:model="key" type="text" {{ $editMode ? 'readonly' : '' }}
                                        placeholder="Contoh: app_name"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white {{ $editMode ? 'bg-gray-100 dark:bg-gray-900' : '' }}">
                                    @error('key') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kategori <span class="text-red-500">*</span></label>
                                    <x-searchable-select
                                        wire:model.live="category"
                                        :options="collect($categories)->map(fn($label, $key) => ['value' => $key, 'label' => $label])->values()->toArray()"
                                        placeholder="Pilih Kategori"
                                        searchPlaceholder="Cari kategori..."
                                        :error="$errors->has('category')"
                                    />
                                    @error('category') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Value @if($data_type !== 'datetime')<span class="text-red-500">*</span>@endif</label>
                                    @if($data_type === 'datetime')
                                        <input wire:model="value" type="datetime-local"
                                            placeholder="Pilih tanggal dan waktu"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pilih tanggal dan waktu. Kosongkan jika tidak ada batas waktu.</p>
                                    @elseif($data_type === 'boolean')
                                        <select wire:model="value"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                            <option value="1">Ya (True)</option>
                                            <option value="0">Tidak (False)</option>
                                        </select>
                                    @elseif($data_type === 'integer')
                                        <input wire:model="value" type="number"
                                            placeholder="Masukkan nilai numerik"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @else
                                        <input wire:model="value" type="text"
                                            placeholder="Masukkan nilai"
                                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @endif
                                    @error('value') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipe Data <span class="text-red-500">*</span></label>
                                    <x-searchable-select
                                        wire:model.live="data_type"
                                        :options="collect($dataTypes)->map(fn($label, $key) => ['value' => $key, 'label' => $label])->values()->toArray()"
                                        placeholder="Pilih Tipe Data"
                                        searchPlaceholder="Cari tipe data..."
                                        :error="$errors->has('data_type')"
                                    />
                                    @error('data_type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Deskripsi</label>
                                    <textarea wire:model="description" rows="3"
                                        placeholder="Deskripsi konfigurasi (opsional)"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                                    @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div class="flex items-center gap-4">
                                    <label class="flex items-center">
                                        <input wire:model="is_editable" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Dapat Diedit</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input wire:model="is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Aktif</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <x-loading-button type="submit" target="save" variant="primary" size="lg"
                                loadingText="Menyimpan..." class="w-full sm:w-auto">
                                Update
                            </x-loading-button>
                            <x-cancel-button wire:click="closeModal" target="closeModal"
                                class="mt-3 sm:mt-0 w-full sm:w-auto" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
