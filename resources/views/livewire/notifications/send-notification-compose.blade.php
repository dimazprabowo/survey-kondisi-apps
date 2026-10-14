@php
    $types = [
        'info'    => ['label' => 'Info',       'bg' => 'bg-blue-100 dark:bg-blue-900/30',    'text' => 'text-blue-600 dark:text-blue-400',    'border' => 'border-blue-500 bg-blue-50 dark:bg-blue-900/10',    'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'success' => ['label' => 'Sukses',     'bg' => 'bg-green-100 dark:bg-green-900/30',  'text' => 'text-green-600 dark:text-green-400',  'border' => 'border-green-500 bg-green-50 dark:bg-green-900/10',  'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        'warning' => ['label' => 'Peringatan', 'bg' => 'bg-yellow-100 dark:bg-yellow-900/30','text' => 'text-yellow-600 dark:text-yellow-400','border' => 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/10','icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z'],
        'danger'  => ['label' => 'Bahaya',     'bg' => 'bg-red-100 dark:bg-red-900/30',      'text' => 'text-red-600 dark:text-red-400',      'border' => 'border-red-500 bg-red-50 dark:bg-red-900/10',      'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
    $currentType = $types[$type] ?? $types['info'];
@endphp

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    {{-- Left: Form --}}
    <div class="xl:col-span-2 space-y-5">

        {{-- Penerima --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Penerima
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {{-- All Users --}}
                <label class="relative flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all
                              {{ $target === 'all' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/10' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500' }}">
                    <input type="radio" wire:model.live="target" value="all" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Semua Pengguna</span>
                            <span class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">
                                {{ $totalUsers }} user
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Kirim ke seluruh pengguna aktif</p>
                    </div>
                </label>

                {{-- Specific Users --}}
                <label class="relative flex items-start gap-3 p-4 rounded-lg border-2 cursor-pointer transition-all
                              {{ $target === 'specific' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/10' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500' }}">
                    <input type="radio" wire:model.live="target" value="specific" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Pengguna Tertentu</span>
                            @if(count($selectedUserIds) > 0)
                                <span class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400">
                                    {{ count($selectedUserIds) }} dipilih
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Pilih satu atau beberapa pengguna</p>
                    </div>
                </label>
            </div>

            {{-- User Selector --}}
            @if($target === 'specific')
                <div class="mt-4 space-y-3">
                    {{-- Selected Chips --}}
                    @if(count($selectedUsers) > 0)
                        <div class="flex flex-wrap gap-2">
                            @foreach($selectedUsers as $su)
                                <span class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300">
                                    <span class="w-5 h-5 rounded-full bg-blue-500 text-white flex items-center justify-center text-[10px] font-bold flex-shrink-0">
                                        {{ strtoupper(substr($su->name, 0, 1)) }}
                                    </span>
                                    {{ $su->name }}
                                    <button wire:click="removeUser({{ $su->id }})" type="button"
                                            class="ml-0.5 text-blue-500 hover:text-blue-700 dark:hover:text-blue-200 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @error('selectedUserIds')
                        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    {{-- Search --}}
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text"
                               wire:model.live.debounce.300ms="userSearch"
                               placeholder="Cari nama atau email..."
                               class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>

                    {{-- User List --}}
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden divide-y divide-gray-100 dark:divide-gray-700 max-h-56 overflow-y-auto custom-scrollbar">
                        @forelse($users as $user)
                            <button type="button"
                                    wire:click="toggleUser({{ $user->id }})"
                                    wire:key="user-{{ $user->id }}"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors
                                           {{ in_array($user->id, $selectedUserIds) ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50' }}">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</p>
                                </div>
                                @if(in_array($user->id, $selectedUserIds))
                                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @endif
                            </button>
                        @empty
                            <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada pengguna ditemukan.
                            </div>
                        @endforelse
                    </div>

                    @if($users->hasPages())
                        <div class="text-xs">{{ $users->links() }}</div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Konten Notifikasi --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                Konten Notifikasi
            </h3>

            {{-- Type Selector --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Tipe Notifikasi</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach($types as $value => $cfg)
                        <label class="flex flex-col items-center gap-1.5 p-3 rounded-lg border-2 cursor-pointer transition-all
                                      {{ $type === $value ? $cfg['border'] : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500' }}">
                            <input type="radio" wire:model.live="type" value="{{ $value }}" class="sr-only">
                            <div class="w-8 h-8 rounded-full {{ $cfg['bg'] }} flex items-center justify-center">
                                <svg class="w-4 h-4 {{ $cfg['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $cfg['icon'] }}"/>
                                </svg>
                            </div>
                            <span class="text-xs font-medium {{ $type === $value ? $cfg['text'] : 'text-gray-600 dark:text-gray-400' }}">
                                {{ $cfg['label'] }}
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('type') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Title --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                    Judul <span class="text-red-500">*</span>
                </label>
                <input type="text"
                       wire:model.blur="title"
                       placeholder="Contoh: Pembaruan Sistem"
                       maxlength="255"
                       class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors
                              {{ $errors->has('title') ? 'border-red-400 dark:border-red-500' : 'border-gray-300 dark:border-gray-600' }}">
                <div class="flex items-center justify-between mt-1">
                    @error('title')
                        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @else
                        <span></span>
                    @enderror
                    <span class="text-xs text-gray-400 ml-auto">{{ strlen($title) }}/255</span>
                </div>
            </div>

            {{-- Message --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                    Pesan <span class="text-red-500">*</span>
                </label>
                <textarea wire:model.blur="notifMessage"
                          placeholder="Tulis isi notifikasi di sini..."
                          rows="4"
                          maxlength="2000"
                          class="w-full px-3 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none
                                 {{ $errors->has('notifMessage') ? 'border-red-400 dark:border-red-500' : 'border-gray-300 dark:border-gray-600' }}"></textarea>
                <div class="flex items-center justify-between mt-1">
                    @error('notifMessage')
                        <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @else
                        <span></span>
                    @enderror
                    <span class="text-xs text-gray-400 ml-auto">{{ strlen($notifMessage) }}/2000</span>
                </div>
            </div>

            {{-- Action URL --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                    URL Aksi <span class="font-normal text-gray-400">(opsional)</span>
                </label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    <input type="url"
                           wire:model.blur="actionUrl"
                           placeholder="https://contoh.com/halaman"
                           class="w-full pl-9 pr-4 py-2 text-sm border rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors
                                  {{ $errors->has('actionUrl') ? 'border-red-400 dark:border-red-500' : 'border-gray-300 dark:border-gray-600' }}">
                </div>
                @error('actionUrl') <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Right: Preview & Send --}}
    <div class="space-y-5">

        {{-- Preview --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Preview
            </h3>

            <div class="rounded-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="flex items-start gap-3 p-4 {{ $currentType['border'] }}">
                    <div class="flex-shrink-0">
                        <div class="w-9 h-9 rounded-full {{ $currentType['bg'] }} flex items-center justify-center">
                            <svg class="w-4 h-4 {{ $currentType['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $currentType['icon'] }}"/>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ $title ?: 'Judul notifikasi...' }}
                            <span class="inline-block w-2 h-2 bg-blue-500 rounded-full ml-1 align-middle"></span>
                        </p>
                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-0.5 leading-relaxed break-words">
                            {{ $notifMessage ?: 'Isi pesan notifikasi akan tampil di sini...' }}
                        </p>
                        @if($actionUrl)
                            <p class="text-xs text-blue-500 mt-1 truncate">{{ $actionUrl }}</p>
                        @endif
                        <p class="text-xs text-gray-400 mt-1.5">Baru saja</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Ringkasan & Kirim --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                Ringkasan
            </h3>

            <dl class="space-y-3 text-sm mb-5">
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-gray-500 dark:text-gray-400 flex-shrink-0">Penerima</dt>
                    <dd class="font-medium text-gray-900 dark:text-white text-right">
                        @if($target === 'all')
                            <span class="text-blue-600 dark:text-blue-400">Semua ({{ $totalUsers }})</span>
                        @else
                            <span class="{{ count($selectedUserIds) > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400' }}">
                                {{ count($selectedUserIds) > 0 ? count($selectedUserIds).' pengguna' : 'Belum dipilih' }}
                            </span>
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-gray-500 dark:text-gray-400 flex-shrink-0">Tipe</dt>
                    <dd>
                        <span class="inline-flex items-center whitespace-nowrap gap-1 px-2 py-0.5 rounded-full text-xs font-medium {{ $currentType['bg'] }} {{ $currentType['text'] }}">
                            {{ $currentType['label'] }}
                        </span>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-gray-500 dark:text-gray-400 flex-shrink-0">Judul</dt>
                    <dd class="font-medium text-gray-900 dark:text-white text-right truncate max-w-[140px]">
                        {{ $title ?: '-' }}
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <dt class="text-gray-500 dark:text-gray-400 flex-shrink-0">URL Aksi</dt>
                    <dd class="text-gray-600 dark:text-gray-400 text-right truncate max-w-[140px]">
                        {{ $actionUrl ?: 'Tidak ada' }}
                    </dd>
                </div>
            </dl>

            <div class="flex flex-col gap-2">
                <x-loading-button
                    wire:click="send"
                    target="send"
                    variant="primary"
                    size="md"
                    loadingText="Mengirim...">
                    <x-slot name="icon">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </x-slot>
                    Kirim Notifikasi
                </x-loading-button>

                <x-loading-button
                    wire:click="resetForm"
                    target="resetForm"
                    variant="secondary"
                    size="md"
                    loadingText="Meresset...">
                    Reset Form
                </x-loading-button>
            </div>
        </div>
    </div>
</div>
