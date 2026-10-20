<div>
    {{-- Mobile Sidebar Overlay --}}
    <div x-show="Alpine.store('sidebar').open"
         @click="Alpine.store('sidebar').close()"
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden backdrop-blur-sm"></div>

    {{-- Sidebar --}}
    <div class="fixed inset-y-0 left-0 z-50 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transform transition-all duration-300 ease-in-out lg:translate-x-0 flex flex-col"
         :class="{
             '-translate-x-full': !Alpine.store('sidebar').open,
             'w-72 sm:w-80 lg:w-64': !Alpine.store('sidebar').collapsed,
             'w-72 sm:w-80 lg:w-20': Alpine.store('sidebar').collapsed,
         }"
         x-cloak>

        {{-- Logo --}}
        <div class="border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
            <div class="flex items-center h-16 px-4"
                 :class="Alpine.store('sidebar').collapsed ? 'lg:justify-center lg:px-2' : 'justify-between'">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-2" :class="Alpine.store('sidebar').collapsed && 'lg:justify-center lg:space-x-0'">
                    <div class="w-9 h-9 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                        </svg>
                    </div>
                    {{-- App name: always visible on mobile, hidden on desktop when collapsed --}}
                    <div class="leading-tight min-w-0" :class="Alpine.store('sidebar').collapsed && 'lg:hidden'"
                         title="{{ app_name() }} — Marine Assessment & Technical Reporting Automation">
                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ app_name() }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">PT. Biro Klasifikasi Indonesia</div>
                    </div>
                </a>
                {{-- Close button: mobile only --}}
                <button @click="Alpine.store('sidebar').close()" class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 py-4 space-y-1 overflow-y-auto overflow-x-hidden custom-scrollbar"
             :class="Alpine.store('sidebar').collapsed ? 'lg:px-2 px-3' : 'px-3'">
            @foreach($menuItems as $item)
                @if(isset($item['children']))
                    {{-- Menu with Children --}}
                    <div x-data="{ open: {{ $item['active'] ?? false ? 'true' : 'false' }} }" class="space-y-1">
                        {{-- Expanded button: visible on mobile always, on desktop when not collapsed --}}
                        <button @click="open = !open"
                                class="w-full flex items-center justify-between px-3 py-2.5 text-sm font-medium rounded-lg transition-colors
                                       {{ ($item['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}"
                                :class="Alpine.store('sidebar').collapsed && 'lg:hidden'">
                            <div class="flex items-center space-x-3">
                                <x-icon name="{{ $item['icon'] }}" class="w-5 h-5 flex-shrink-0" />
                                <span>{{ $item['name'] }}</span>
                            </div>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        {{-- Collapsed icon button with flyout --}}
                        <div x-data="{ flyout: false, pos: { left: 0, top: 0 } }"
                             class="relative hidden"
                             :class="Alpine.store('sidebar').collapsed && 'lg:!block'"
                             @mouseenter="let r = $el.getBoundingClientRect(); pos = { left: r.right + 8, top: r.top }; flyout = true"
                             @mouseleave="flyout = false"
                             x-on:livewire:navigating.window="flyout = false">
                            <button class="w-full flex items-center justify-center p-2.5 rounded-lg transition-colors
                                           {{ ($item['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                <x-icon name="{{ $item['icon'] }}" class="w-5 h-5" />
                            </button>
                            {{-- Flyout teleported to body --}}
                            <template x-teleport="body">
                                <div x-show="flyout"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 translate-x-1"
                                     x-transition:enter-end="opacity-100 translate-x-0"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 translate-x-0"
                                     x-transition:leave-end="opacity-0 translate-x-1"
                                     class="fixed w-48 py-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-[100]"
                                     :style="`left: ${pos.left}px; top: ${pos.top}px;`"
                                     @mouseenter="flyout = true"
                                     @mouseleave="flyout = false"
                                     style="display: none;">
                                    <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $item['name'] }}</div>
                                    @foreach(array_filter($item['children']) as $child)
                                        <a href="{{ route($child['route']) }}"
                                           wire:navigate
                                           class="block px-3 py-2 text-sm transition-colors
                                                  {{ ($child['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                                            {{ $child['name'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </template>
                        </div>
                        {{-- Expanded children: visible on mobile always, on desktop when not collapsed --}}
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-2"
                             class="pl-11 space-y-1"
                             :class="Alpine.store('sidebar').collapsed && 'lg:hidden'">
                            @foreach(array_filter($item['children']) as $child)
                                <a href="{{ route($child['route']) }}"
                                   wire:navigate
                                   @click="if(window.innerWidth < 1024) Alpine.store('sidebar').close()"
                                   class="block px-3 py-2 text-sm rounded-lg transition-colors
                                          {{ ($child['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                                    {{ $child['name'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    {{-- Single Menu Item --}}
                    <div x-data="{ tooltip: false, pos: { left: 0, top: 0 } }"
                         class="relative"
                         @mouseenter="if(Alpine.store('sidebar').collapsed) { let r = $el.getBoundingClientRect(); pos = { left: r.right + 8, top: r.top + r.height / 2 }; tooltip = true; }"
                         @mouseleave="tooltip = false"
                         x-on:livewire:navigating.window="tooltip = false"
                         x-on:click="tooltip = false">
                        <a href="{{ route($item['route']) }}"
                           wire:navigate
                           @click="if(window.innerWidth < 1024) Alpine.store('sidebar').close()"
                           class="flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors
                                  {{ ($item['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}"
                           :class="Alpine.store('sidebar').collapsed ? 'lg:justify-center lg:p-2.5' : 'justify-between'">
                            <div class="flex items-center space-x-3" :class="Alpine.store('sidebar').collapsed && 'lg:space-x-0'">
                                <x-icon name="{{ $item['icon'] }}" class="w-5 h-5 flex-shrink-0" />
                                <span :class="Alpine.store('sidebar').collapsed && 'lg:hidden'">{{ $item['name'] }}</span>
                            </div>
                            @if(isset($item['badge']) && $item['badge'] > 0)
                                <span :class="Alpine.store('sidebar').collapsed && 'lg:hidden'" class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400">
                                    {{ $item['badge'] }}
                                </span>
                            @endif
                        </a>
                        {{-- Tooltip teleported to body --}}
                        <template x-teleport="body">
                            <div x-show="tooltip"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100"
                                 x-transition:leave-end="opacity-0"
                                 class="fixed px-2.5 py-1.5 bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-md whitespace-nowrap z-[100] pointer-events-none hidden lg:block"
                                 :style="`left: ${pos.left}px; top: ${pos.top}px; transform: translateY(-50%);`"
                                 style="display: none;">
                                {{ $item['name'] }}
                            </div>
                        </template>
                    </div>
                @endif
            @endforeach
        </nav>

        {{-- User Info --}}
        <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50"
             :class="Alpine.store('sidebar').collapsed ? 'lg:p-2 p-4' : 'p-4'">
            {{-- Expanded: visible on mobile always, on desktop when not collapsed --}}
            <div :class="Alpine.store('sidebar').collapsed && 'lg:hidden'">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold shadow-md">
                            {{ substr($authUser->name, 0, 1) }}
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $authUser->name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $authUserRole }}</p>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('profile') }}"
                       wire:navigate
                       x-data="{ loading: false }"
                       @click="loading = true; if(window.innerWidth < 1024) Alpine.store('sidebar').close()"
                       x-on:livewire:navigated.window="loading = false"
                       class="flex items-center justify-center px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <span class="relative inline-flex w-4 h-4 mr-1.5">
                            <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4 absolute inset-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                        <span x-show="!loading">Profil</span>
                        <span x-show="loading" x-cloak>Memuat...</span>
                    </a>
                    <button x-data="{ loading: false }"
                            @click="loading = true; setTimeout(() => loading = false, 500); window.dispatchEvent(new CustomEvent('confirm-logout-sidebar', { detail: { action: 'logout', title: 'Konfirmasi Keluar', message: 'Apakah Anda yakin ingin keluar dari aplikasi?', confirmText: 'Ya, Keluar', type: 'danger' } }))"
                            class="flex items-center justify-center px-3 py-2 text-xs font-medium text-red-600 dark:text-red-400 bg-white dark:bg-gray-800 border border-red-200 dark:border-red-800 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors w-full">
                        <span class="relative inline-flex w-4 h-4 mr-1.5">
                            <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4 absolute inset-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </span>
                        <span x-show="!loading">Keluar</span>
                        <span x-show="loading" x-cloak>Memuat...</span>
                    </button>
                </div>
            </div>
            {{-- Collapsed: hidden on mobile, visible on desktop when collapsed --}}
            <div x-data="{ flyout: false, pos: { left: 0, bottom: 0 } }"
                 class="hidden flex-col items-center space-y-2"
                 :class="Alpine.store('sidebar').collapsed && 'lg:!flex'"
                 x-on:livewire:navigating.window="flyout = false">
                <div class="relative"
                     @mouseenter="let r = $el.getBoundingClientRect(); pos = { left: r.right + 8, bottom: window.innerHeight - r.bottom }; flyout = true"
                     @mouseleave="flyout = false">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold shadow-md cursor-pointer">
                        {{ substr($authUser->name, 0, 1) }}
                    </div>
                    {{-- Flyout teleported to body --}}
                    <template x-teleport="body">
                        <div x-show="flyout"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-x-1"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-x-0"
                             x-transition:leave-end="opacity-0 translate-x-1"
                             class="fixed w-48 py-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-[100]"
                             :style="`left: ${pos.left}px; bottom: ${pos.bottom}px;`"
                             @mouseenter="flyout = true"
                             @mouseleave="flyout = false"
                             style="display: none;">
                            <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-700">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $authUser->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $authUserRole }}</p>
                            </div>
                            <a href="{{ route('profile') }}" wire:navigate
                               x-data="{ loading: false }"
                               @click="loading = true"
                               x-on:livewire:navigated.window="loading = false"
                               class="flex items-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <span class="relative inline-flex w-4 h-4 mr-2">
                                    <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <svg x-show="loading" x-cloak class="animate-spin w-4 h-4 absolute inset-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                                <span x-show="!loading">Profil</span>
                                <span x-show="loading" x-cloak>Memuat...</span>
                            </a>
                            <button x-data="{ loading: false }"
                                    @click="loading = true; setTimeout(() => loading = false, 500); window.dispatchEvent(new CustomEvent('confirm-logout-sidebar', { detail: { action: 'logout', title: 'Konfirmasi Keluar', message: 'Apakah Anda yakin ingin keluar dari aplikasi?', confirmText: 'Ya, Keluar', type: 'danger' } }))"
                                    @mouseenter="flyout = true"
                                    class="flex items-center w-full px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <span class="relative inline-flex w-4 h-4 mr-2">
                                    <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    <svg x-show="loading" x-cloak class="animate-spin w-4 h-4 absolute inset-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </span>
                                <span x-show="!loading">Keluar</span>
                                <span x-show="loading" x-cloak>Memuat...</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Collapse Toggle (desktop only) --}}
        <div class="hidden lg:flex items-center justify-center border-t border-gray-200 dark:border-gray-700 py-2">
            <button @click="Alpine.store('sidebar').toggleCollapse()"
                    class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                    :title="Alpine.store('sidebar').collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
                <svg class="w-5 h-5 transition-transform duration-300" :class="Alpine.store('sidebar').collapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Confirm Logout Modal --}}
    <x-confirm-modal eventName="confirm-logout-sidebar"
        title="Konfirmasi Keluar"
        message="Apakah Anda yakin ingin keluar dari aplikasi?"
        confirmText="Ya, Keluar"
        cancelText="Batal"
        type="danger" />
</div>
