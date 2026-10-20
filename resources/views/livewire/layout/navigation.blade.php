<div>
{{-- Impersonation Banner --}}
@if($isImpersonating)
    <div class="relative z-40 bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-md">
        <div class="px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between gap-4">
            <div class="flex items-center space-x-3 min-w-0">
                <div class="flex items-center space-x-2 flex-shrink-0">
                    <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-wider bg-white/20 px-2 py-0.5 rounded">Impersonating</span>
                </div>
                <div class="flex items-center space-x-2 min-w-0 text-sm">
                    @if($originalUser)
                        <span class="flex items-center space-x-1.5 flex-shrink-0">
                            <span class="w-6 h-6 rounded-full bg-white/25 flex items-center justify-center text-xs font-bold">{{ substr($originalUser->name, 0, 1) }}</span>
                            <span class="hidden sm:inline text-white/80">{{ $originalUser->name }}</span>
                        </span>
                        <svg class="w-4 h-4 flex-shrink-0 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    @endif
                    <span class="flex items-center space-x-1.5 min-w-0">
                        <span class="w-6 h-6 rounded-full bg-white flex items-center justify-center text-xs font-bold text-orange-600">{{ substr($authUser->name, 0, 1) }}</span>
                        <span class="font-semibold truncate">{{ $authUser->name }}</span>
                    </span>
                </div>
            </div>
            <button wire:click="stopImpersonating"
                    wire:loading.attr="disabled"
                    wire:target="stopImpersonating"
                    class="inline-flex items-center px-3.5 py-1.5 rounded-lg bg-white text-orange-600 hover:bg-orange-50 transition-all text-sm font-semibold shadow-sm disabled:opacity-50 flex-shrink-0">
                <svg wire:loading.remove wire:target="stopImpersonating" class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <svg wire:loading wire:target="stopImpersonating" class="animate-spin w-4 h-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Kembali ke Akun Saya
            </button>
        </div>
    </div>
@endif

<nav class="sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            {{-- Left Side: Burger + Logo (navbar mode) --}}
            <div class="flex items-center space-x-3 flex-shrink-0">
                {{-- Burger Button --}}
                <button x-show="Alpine.store('layout').isSidebar() || window.innerWidth < 1024"
                        x-on:resize.window="$el.style.display = (Alpine.store('layout').isSidebar() || window.innerWidth < 1024) ? '' : 'none'"
                        @click="window.innerWidth >= 1024 ? Alpine.store('sidebar').toggleCollapse() : Alpine.store('sidebar').toggle()"
                        class="inline-flex items-center justify-center p-2 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        :title="window.innerWidth >= 1024 ? (Alpine.store('sidebar').collapsed ? 'Expand sidebar' : 'Collapse sidebar') : 'Toggle menu'">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                {{-- Navbar mode: Logo + App Name --}}
                <a href="{{ route('dashboard') }}" class="hidden lg:flex items-center space-x-2" x-show="Alpine.store('layout').isNavbar()" x-cloak>
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-bold text-gray-900 dark:text-white hidden sm:inline">{{ app_name() }}</span>
                </a>
            </div>

            {{-- Navbar mode: Horizontal Menu Items with Scroll Arrows --}}
            <div class="hidden lg:flex items-center flex-1 min-w-0 ml-4" x-show="Alpine.store('layout').isNavbar()" x-cloak>
                <div class="relative w-full h-full flex items-center"
                     x-data="{
                         showLeftArrow: false,
                         showRightArrow: false,
                         checkScroll() {
                             const container = this.$refs.menuContainer;
                             if (container) {
                                 const hasOverflow = container.scrollWidth > (container.clientWidth + 1);
                                 this.showLeftArrow = hasOverflow && container.scrollLeft > 5;
                                 this.showRightArrow = hasOverflow && (Math.ceil(container.scrollLeft + container.clientWidth) < container.scrollWidth - 5);
                             }
                         },
                         scrollLeft() {
                             this.$refs.menuContainer.scrollBy({ left: -150, behavior: 'smooth' });
                         },
                         scrollRight() {
                             this.$refs.menuContainer.scrollBy({ left: 150, behavior: 'smooth' });
                         }
                     }"
                     x-init="
                         $nextTick(() => {
                             checkScroll();
                             const mutationObserver = new MutationObserver(() => checkScroll());
                             mutationObserver.observe($refs.menuContainer, { childList: true, subtree: true });
                             const resizeObserver = new ResizeObserver(() => checkScroll());
                             resizeObserver.observe($refs.menuContainer);
                             window.addEventListener('resize', () => checkScroll());
                         });
                     ">
                    {{-- Left Arrow --}}
                    <button x-show="showLeftArrow"
                            x-cloak
                            @click="scrollLeft()"
                            type="button"
                            x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 -translate-x-4"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 translate-x-0"
                            x-transition:leave-end="opacity-0 -translate-x-4"
                            class="absolute left-0 ml-1 top-1/2 -translate-y-1/2 z-30 p-2 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm border border-gray-200 dark:border-gray-700 rounded-full shadow-xl text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:scale-110 active:scale-95 transition-all focus:outline-none ring-2 ring-transparent focus:ring-blue-500/50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                        </svg>
                    </button>

                    {{-- Menu Container with Scroll --}}
                    <div class="relative flex items-center flex-1 overflow-hidden" 
                         @menu-updated.window="$nextTick(() => checkScroll())">
                        {{-- Gradient Shadows --}}
                        <div x-show="showLeftArrow" 
                             x-transition:enter="transition opacity duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition opacity duration-300"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute left-0 top-0 bottom-0 w-12 z-10 pointer-events-none bg-gradient-to-r from-white dark:from-gray-800 to-transparent"
                             x-cloak></div>
                        <div x-show="showRightArrow" 
                             x-transition:enter="transition opacity duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition opacity duration-300"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute right-0 top-0 bottom-0 w-12 z-10 pointer-events-none bg-gradient-to-l from-white dark:from-gray-800 to-transparent"
                             x-cloak></div>

                        <div x-ref="menuContainer"
                             @scroll="checkScroll()"
                             class="flex items-center overflow-x-auto navbar-scroll px-4"
                             :class="{ 'px-2': !showLeftArrow && !showRightArrow }">
                            <div class="flex items-center space-x-1 min-w-max py-1">
                            @foreach($menuItems as $item)
                                @if(isset($item['children']))
                                    <div x-data="{ 
                                            open: false,
                                            positionDropdown() {
                                                if (!this.open) return;
                                                const btn = this.$refs.button;
                                                const dropdown = this.$refs.dropdown;
                                                const rect = btn.getBoundingClientRect();
                                                dropdown.style.left = rect.left + 'px';
                                                dropdown.style.top = (rect.bottom + 4) + 'px';
                                            }
                                        }" 
                                        @click.outside="open = false" 
                                        class="flex-shrink-0">
                                        <button @click="open = !open; $nextTick(() => positionDropdown())"
                                                x-ref="button"
                                                type="button"
                                                class="flex items-center space-x-1.5 px-3 py-2 text-sm font-medium rounded-lg transition-colors whitespace-nowrap
                                                       {{ ($item['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                            <x-icon name="{{ $item['icon'] }}" class="w-4 h-4 flex-shrink-0" />
                                            <span>{{ $item['name'] }}</span>
                                            <svg class="w-3.5 h-3.5 transition-transform flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                        
                                        <template x-teleport="body">
                                            <div x-show="open"
                                                 x-ref="dropdown"
                                                 @scroll.window="open = false"
                                                 @resize.window="open = false"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-150"
                                                 x-transition:leave-start="opacity-100 scale-100"
                                                 x-transition:leave-end="opacity-0 scale-95"
                                                 class="fixed w-52 py-1 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-[9999]"
                                                 x-cloak>
                                                @foreach(array_filter($item['children']) as $child)
                                                    <a href="{{ route($child['route']) }}"
                                                       wire:navigate
                                                       class="block px-4 py-2 text-sm transition-colors
                                                              {{ ($child['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 font-medium' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                                        {{ $child['name'] }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        </template>
                                    </div>
                                @else
                                    <a href="{{ route($item['route']) }}"
                                       wire:navigate
                                       class="flex items-center space-x-1.5 px-3 py-2 text-sm font-medium rounded-lg transition-colors flex-shrink-0 whitespace-nowrap
                                              {{ ($item['active'] ?? false) ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                        <x-icon name="{{ $item['icon'] }}" class="w-4 h-4 flex-shrink-0" />
                                        <span>{{ $item['name'] }}</span>
                                    </a>
                                @endif
                            @endforeach
                            </div>
                        </div>

                        {{-- Right Arrow --}}
                        <button x-show="showRightArrow"
                                x-cloak
                                @click="scrollRight()"
                                type="button"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 translate-x-4"
                                x-transition:enter-end="opacity-100 translate-x-0"
                                x-transition:leave="transition ease-in duration-200"
                                x-transition:leave-start="opacity-100 translate-x-0"
                                x-transition:leave-end="opacity-0 translate-x-4"
                                class="absolute right-0 mr-1 top-1/2 -translate-y-1/2 z-30 p-2 bg-white/90 dark:bg-gray-800/90 backdrop-blur-sm border border-gray-200 dark:border-gray-700 rounded-full shadow-xl text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:scale-110 active:scale-95 transition-all focus:outline-none ring-2 ring-transparent focus:ring-blue-500/50">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Center: Page Title (mobile only) --}}
            <div class="flex-1 min-w-0 px-2 lg:hidden">
                <h1 class="truncate text-center text-sm sm:text-base font-semibold text-gray-900 dark:text-white"
                    title="{{ $title ?? 'Dashboard' }}">
                    {{ $title ?? 'Dashboard' }}
                </h1>
            </div>

            {{-- Right Side: Layout Toggle, Dark Mode & Profile --}}
            <div class="flex items-center space-x-1 sm:space-x-2 flex-shrink-0">
                {{-- Layout Mode Toggle --}}
                <button @click="Alpine.store('layout').toggleMode()"
                        class="hidden lg:inline-flex p-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        :title="Alpine.store('layout').isSidebar() ? 'Switch to navbar layout' : 'Switch to sidebar layout'">
                    {{-- Show "switch to navbar" icon when in sidebar mode --}}
                    <svg x-show="Alpine.store('layout').isSidebar()" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <line x1="3" y1="9" x2="21" y2="9"/>
                        <line x1="9" y1="9" x2="9" y2="21" opacity="0.4"/>
                    </svg>
                    {{-- Show "switch to sidebar" icon when in navbar mode --}}
                    <svg x-show="Alpine.store('layout').isNavbar()" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2"/>
                        <line x1="9" y1="3" x2="9" y2="21"/>
                        <line x1="3" y1="9" x2="9" y2="9" opacity="0.4"/>
                    </svg>
                </button>

                {{-- Dark Mode Toggle --}}
                <button @click="$store.darkMode.toggle()"
                        class="p-2 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                    <svg x-show="!$store.darkMode.dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <svg x-show="$store.darkMode.dark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>

                {{-- Notification Bell --}}
                @can('notifications_view')
                    <livewire:notifications.notification-bell />
                @endcan

                {{-- Profile Dropdown --}}
                <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                    <button @click="open = !open"
                            class="flex items-center space-x-2 sm:space-x-3 p-1.5 sm:p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                        <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm">
                            {{ substr($authUser->name, 0, 1) }}
                        </div>
                        <div class="hidden sm:block text-left">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $authUser->name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $authUserRole }}</div>
                        </div>
                        <svg class="hidden sm:block w-4 h-4 text-gray-600 dark:text-gray-400 transition-transform"
                             :class="{ 'rotate-180': open }"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 dark:divide-gray-700"
                         style="display: none;">

                        <div class="px-4 py-3 sm:hidden">
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $authUser->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $authUser->email }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $authUserRole }}</p>
                        </div>

                        <div class="py-1">
                            <a href="{{ route('profile') }}"
                               wire:navigate
                               class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Profil Saya
                            </a>

                            @can('users_impersonate')
                            <a href="{{ route('profile') }}#impersonate"
                               wire:navigate
                               class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Impersonate User
                            </a>
                            @endcan

                            @can('configuration_view')
                            <a href="{{ route('settings.system') }}"
                               wire:navigate
                               class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Pengaturan
                            </a>
                            @endcan
                        </div>

                        @if($isImpersonating)
                        <div class="py-1">
                            <button wire:click="stopImpersonating"
                                    wire:loading.attr="disabled"
                                    wire:target="stopImpersonating"
                                    class="flex items-center w-full px-4 py-2 text-sm text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors disabled:opacity-50">
                                <svg wire:loading.remove wire:target="stopImpersonating" class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                <svg wire:loading wire:target="stopImpersonating" class="animate-spin w-4 h-4 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Kembali ke Akun Saya
                            </button>
                        </div>
                        @endif

                        <div class="py-1">
                            <button @click="window.dispatchEvent(new CustomEvent('confirm-logout-nav', { detail: { action: 'logout', title: 'Konfirmasi Keluar', message: 'Apakah Anda yakin ingin keluar dari aplikasi?', confirmText: 'Ya, Keluar', type: 'danger' } }))"
                                    class="flex items-center w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                <svg wire:loading.class="hidden" wire:target="logout" class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                <svg wire:loading wire:target="logout" class="animate-spin w-4 h-4 mr-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.class="hidden" wire:target="logout">Keluar</span>
                                <span wire:loading wire:target="logout">Memproses...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</nav>

    {{-- Confirm Logout Modal --}}
    <x-confirm-modal eventName="confirm-logout-nav"
        title="Konfirmasi Keluar"
        message="Apakah Anda yakin ingin keluar dari aplikasi?"
        confirmText="Ya, Keluar"
        cancelText="Batal"
        type="danger" />
</div>
