<div>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Notifikasi</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ $unreadCount }} belum dibaca
            </p>
        </div>
        <div class="flex items-center space-x-2">
            @if($unreadCount > 0)
                <x-loading-button wire:click="markAllAsRead"
                    target="markAllAsRead"
                    variant="primary" size="md" icon="check"
                    loadingText="Memproses...">
                    Tandai Semua Dibaca
                </x-loading-button>
            @endif
            <x-loading-button wire:click="confirmDeleteAllRead"
                target="confirmDeleteAllRead"
                variant="danger" size="md" icon="delete"
                loadingText="Memproses...">
                Hapus Sudah Dibaca
            </x-loading-button>
        </div>
    </div>

    {{-- Filter Tabs --}}
    <div class="flex items-center space-x-1 mb-4 bg-gray-100 dark:bg-gray-800 rounded-lg p-1 w-fit">
        @foreach(['all' => 'Semua', 'unread' => 'Belum Dibaca', 'read' => 'Sudah Dibaca'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors
                           {{ $filter === $key ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Notification List --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
        @forelse($notifications as $notification)
            <div wire:key="notif-full-{{ $notification->id }}"
                 class="flex items-start gap-4 px-4 sm:px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors {{ is_null($notification->read_at) ? 'bg-blue-50/30 dark:bg-blue-900/5' : '' }}">
                {{-- Icon --}}
                <div class="flex-shrink-0 mt-0.5">
                    @switch($notification->type)
                        @case('success')
                            <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            @break
                        @case('warning')
                            <div class="w-10 h-10 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            </div>
                            @break
                        @case('danger')
                            <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                            @break
                        @default
                            <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                    @endswitch
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $notification->title }}
                                @if(is_null($notification->read_at))
                                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full ml-1"></span>
                                @endif
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">
                                {{ $notification->message }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                {{ $notification->created_at->diffForHumans() }} &middot; {{ $notification->created_at->format('d M Y, H:i') }}
                            </p>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center space-x-1 flex-shrink-0">
                            @if(is_null($notification->read_at))
                                <x-loading-button wire:click="markAsRead({{ $notification->id }})"
                                    target="markAsRead({{ $notification->id }})"
                                    variant="icon-blue" icon="check" iconClass="w-4 h-4"
                                    wire:key="btn-read-{{ $notification->id }}"
                                    title="Tandai dibaca" />
                            @endif
                            @if($notification->action_url)
                                <a href="{{ $notification->action_url }}"
                                   wire:navigate
                                   x-data="{ loading: false }"
                                   @click="loading = true"
                                   title="Buka"
                                   class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                    <span class="relative inline-block w-4 h-4">
                                        <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                        </svg>
                                        <svg x-show="loading" class="animate-spin w-4 h-4 absolute inset-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </span>
                                </a>
                            @endif
                            <x-loading-button wire:click="confirmDeleteNotification({{ $notification->id }})"
                                target="confirmDeleteNotification({{ $notification->id }})"
                                variant="icon-red" icon="delete" iconClass="w-4 h-4"
                                wire:key="btn-delete-{{ $notification->id }}"
                                title="Hapus" />
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">Tidak ada notifikasi</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    @if($filter === 'unread')
                        Semua notifikasi sudah dibaca.
                    @elseif($filter === 'read')
                        Belum ada notifikasi yang sudah dibaca.
                    @else
                        Anda belum memiliki notifikasi.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($notifications->hasPages())
        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif

    {{-- Delete Notification Modal --}}
    <x-delete-modal
        :show="$showDeleteModal"
        wire:model="showDeleteModal"
        title="Hapus Notifikasi"
        message="Apakah Anda yakin ingin menghapus notifikasi"
        :itemName="$deletingNotificationTitle"
        confirmMethod="deleteNotification" />

    {{-- Delete All Read Modal --}}
    <x-delete-modal
        :show="$showDeleteAllModal"
        wire:model="showDeleteAllModal"
        title="Hapus Notifikasi Sudah Dibaca"
        message="Apakah Anda yakin ingin menghapus semua notifikasi yang sudah dibaca?"
        confirmMethod="deleteAllRead" />
</div>
