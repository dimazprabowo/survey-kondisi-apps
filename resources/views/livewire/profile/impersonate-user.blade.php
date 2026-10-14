<div>
    <div class="max-w-xl">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Impersonate User
        </h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Login sebagai user lain untuk membantu debugging atau konfigurasi. Aksi ini tercatat dalam sistem.
        </p>
    </div>

    <div class="mt-4 flex flex-col sm:flex-row gap-3">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama atau email..."
                class="w-full px-4 py-2.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition text-sm">
        </div>
        <x-filter-popover :filters="['roleFilter']">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Role</label>
                <x-searchable-select
                    wire:model.live="roleFilter"
                    :options="collect($roles->map(fn($r) => ['value' => $r->name, 'label' => ucfirst($r->name)]))->toArray()"
                    placeholder="Semua Role"
                    searchPlaceholder="Cari role..."
                />
            </div>
        </x-filter-popover>
    </div>

    <div class="mt-4 overflow-hidden bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($users as $user)
                        <tr wire:key="impersonate-user-{{ $user->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-xs font-semibold">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap">
                                @if($user->roles->isNotEmpty())
                                    @foreach($user->roles as $role)
                                        <span wire:key="imp-role-{{ $user->id }}-{{ $role->id }}" class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                            {{ ucfirst($role->name) }}
                                        </span>
                                    @endforeach
                                @else
                                    <span class="text-xs text-gray-400">No Role</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-right">
                                <x-loading-button
                                    wire:click="startImpersonate({{ $user->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="startImpersonate({{ $user->id }})"
                                    target="startImpersonate({{ $user->id }})"
                                    variant="primary"
                                    size="sm"

                                >
                                    <x-slot:icon>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </x-slot:icon>
                                    Login
                                </x-loading-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada user ditemukan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
