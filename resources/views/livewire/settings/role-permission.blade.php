<div>
    <div class="mb-6 flex flex-col lg:flex-row gap-6">
        <!-- Roles List -->
        <div class="lg:w-1/3">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Roles</h3>
                    <div class="flex items-center gap-2">
                        @can('roles_export_excel')
                            <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="sm" title="Export Excel" icon="excel" />
                        @endcan
                        @can('roles_export_pdf')
                            <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="sm" title="Export PDF" icon="pdf" />
                        @endcan
                        @can('roles_create')
                            <x-loading-button wire:click="create" target="create" variant="primary" size="sm" loadingText="Memuat...">
                                <x-slot:icon>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </x-slot:icon>
                                Tambah
                            </x-loading-button>
                        @endcan
                    </div>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($roles as $role)
                        <div wire:key="role-{{ $role->id }}" wire:click="selectRole({{ $role->id }})"
                            class="p-4 cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-gray-700 {{ $selectedRole == $role->id ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">{{ ucfirst($role->name) }}</h4>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $role->permissions->count() }} permissions</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @can('roles_update')
                                        <x-loading-button wire:click.stop="edit({{ $role->id }})"
                                            target="edit({{ $role->id }})"
                                            variant="icon-blue" icon="edit" iconClass="w-4 h-4"
                                            wire:key="btn-edit-{{ $role->id }}"
                                            title="Edit" />
                                    @endcan
                                    @can('roles_delete')
                                        <x-loading-button wire:click.stop="confirmDelete({{ $role->id }})"
                                            target="confirmDelete({{ $role->id }})"
                                            variant="icon-red" icon="delete"
                                            wire:key="btn-delete-{{ $role->id }}"
                                            title="Hapus" />
                                    @endcan
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada role</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Permissions -->
        <div class="lg:w-2/3">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $selectedRole ? 'Permissions untuk ' . ucfirst($roles->find($selectedRole)->name ?? '') : 'Pilih Role' }}
                    </h3>
                </div>
                
                @if($selectedRole)
                    <div class="p-4 space-y-6">
                        @foreach($permissionGroups as $groupName => $groupPermissions)
                            <div>
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">{{ $groupName }}</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    @foreach($groupPermissions as $permission)
                                        @php
                                            $hasPermission = in_array($permission['name'], $rolePermissions);
                                        @endphp
                                        <label class="flex items-center p-3 rounded-lg border {{ $hasPermission ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : 'border-gray-200 dark:border-gray-700' }} @can('roles_update') cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 @else cursor-not-allowed opacity-60 @endcan transition-colors">
                                            <input type="checkbox" 
                                                wire:click="togglePermission('{{ $permission['name'] }}')" 
                                                {{ $hasPermission ? 'checked' : '' }}
                                                @cannot('roles_update') disabled @endcannot
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 @cannot('roles_update') cursor-not-allowed @endcannot">
                                            <span class="ml-3 text-sm {{ $hasPermission ? 'text-blue-700 dark:text-blue-400 font-medium' : 'text-gray-700 dark:text-gray-300' }}">
                                                {{ $permission['label'] }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-12 text-center">
                        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Pilih role dari daftar untuk mengelola permissions</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showModal') }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" @click="$wire.closeModal()"></div>

                <div class="inline-block align-bottom w-full bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                {{ $editMode ? 'Edit Role' : 'Tambah Role' }}
                            </h3>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Role <span class="text-red-500">*</span></label>
                                    <input wire:model="roleName" type="text" {{ $editMode ? 'readonly' : '' }}
                                        placeholder="Contoh: admin, manager, surveyor"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white {{ $editMode ? 'bg-gray-100 dark:bg-gray-900' : '' }}">
                                    @error('roleName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Permissions</label>
                                    <div class="max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-4 space-y-4">
                                        @foreach($permissionGroups as $groupName => $groupPermissions)
                                            <div>
                                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">{{ $groupName }}</h4>
                                                <div class="space-y-2 pl-4">
                                                    @foreach($groupPermissions as $permission)
                                                        <label class="flex items-center">
                                                            <input type="checkbox" 
                                                                wire:model="selectedPermissions" 
                                                                value="{{ $permission['name'] }}"
                                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                                                {{ $permission['label'] }}
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('selectedPermissions') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
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
        title="Hapus Role"
        message="Apakah Anda yakin ingin menghapus role"
        :itemName="$deletingRoleName"
        confirmMethod="delete"
    />
</div>