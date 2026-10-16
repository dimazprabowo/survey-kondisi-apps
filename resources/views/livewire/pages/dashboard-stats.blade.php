{{-- Partial: Dashboard Statistics View (requires dashboard_view permission) --}}
<div class="space-y-6">

    {{-- Header Banner --}}
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold">Selamat Datang, {{ $authUser->name }}!</h2>
                <p class="mt-1 text-blue-100 text-sm">
                    {{ config('app.name') }} &mdash; Panel Administrasi
                </p>
                <div class="mt-3 inline-flex items-center whitespace-nowrap gap-2 bg-white/20 rounded-full px-3 py-1 text-xs font-semibold">
                    <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                    {{ $authUserRole }}
                </div>
            </div>
            <div class="hidden md:block">
                <svg class="w-20 h-20 text-white opacity-20" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                </svg>
            </div>
        </div>
    </div>

    @isset($analytics)
        <section aria-labelledby="survey-analytics-title" class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-widest text-blue-600 dark:text-blue-400">Ringkasan Operasional</p>
                    <h2 id="survey-analytics-title" class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">Kinerja Survey Kondisi</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Berdasarkan tanggal survey, Januari–Desember {{ $analyticsYear }}. Mencakup semua folder.</p>
                </div>
                <div class="w-full sm:w-44 shrink-0">
                    <x-input-label for="dashboard-year" value="Tahun survey" />
                    <x-searchable-select id="dashboard-year" wire:model.live="year" :options="$yearOptions"
                        :clearable="false" placeholder="Pilih tahun" searchPlaceholder="Cari tahun..." aria-label="Tahun survey" />
                    <x-input-error :messages="$errors->get('year')" class="mt-1" />
                </div>
            </div>

            <div wire:loading.flex wire:target="year" role="status" class="items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                <x-loading-spinner size="sm" /> Memuat ringkasan survey...
            </div>

            <div wire:loading.class="opacity-50 pointer-events-none" wire:target="year" class="space-y-5 transition-opacity">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-5">
                        <p class="text-sm font-medium text-blue-700 dark:text-blue-300">Total Survey</p>
                        <p class="mt-3 text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($analytics['total']) }}</p>
                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Seluruh status pada tahun {{ $analyticsYear }}</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Survey Selesai</p>
                        <p class="mt-3 text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($analytics['completed']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $analytics['completionRate'] }}% dari total survey tahun ini</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Rata-rata Skor CAP</p>
                        <p class="mt-3 text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $analytics['averageScore'] !== null ? number_format($analytics['averageScore'], 2, ',', '.') : '—' }} <span class="text-sm font-normal text-gray-500 dark:text-gray-400">/ 4</span></p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Survey selesai dengan skor; semakin tinggi semakin baik</p>
                    </div>
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Survey Selesai Berskor</p>
                        <p class="mt-3 text-3xl font-bold tabular-nums text-gray-900 dark:text-white">{{ number_format($analytics['rated']) }}</p>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ number_format($analytics['unrated']) }} survey selesai belum memiliki skor</p>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 sm:p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Aktivitas survey bulanan</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Jumlah survey menurut bulan pelaksanaan, termasuk draft dan dibatalkan.</p>
                        </div>
                        <span class="inline-flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300"><span class="h-2.5 w-2.5 rounded-sm bg-blue-500"></span>Jumlah survey</span>
                    </div>
                    @if($analytics['total'] > 0)
                        <div class="overflow-x-auto custom-scrollbar">
                            <svg viewBox="0 0 730 260" class="w-full min-w-[600px] text-gray-500 dark:text-gray-400" role="img" aria-labelledby="monthly-chart-title monthly-chart-description">
                                <title id="monthly-chart-title">Jumlah survey per bulan tahun {{ $analyticsYear }}</title>
                                <desc id="monthly-chart-description">@foreach($analytics['months'] as $month){{ $month['label'] }}: {{ $month['count'] }} survey. @endforeach</desc>
                                @foreach([0, 1, 2, 3, 4] as $tick)
                                    <line x1="40" y1="{{ 220 - $tick * 48 }}" x2="724" y2="{{ 220 - $tick * 48 }}" stroke="currentColor" stroke-opacity="0.15" stroke-dasharray="4 4" />
                                    <text x="32" y="{{ 224 - $tick * 48 }}" text-anchor="end" fill="currentColor" font-size="11">{{ $analytics['axisMax'] * $tick / 4 }}</text>
                                @endforeach
                                @foreach($analytics['months'] as $month)
                                    <g wire:key="dashboard-month-{{ $loop->index }}">
                                        <rect x="{{ $month['x'] }}" y="{{ $month['y'] }}" width="32" height="{{ $month['height'] }}" rx="5" class="fill-blue-500 dark:fill-blue-400 hover:fill-blue-600 dark:hover:fill-blue-300 transition-colors">
                                            <title>{{ $month['label'] }} {{ $analyticsYear }}: {{ $month['count'] }} survey</title>
                                        </rect>
                                        <text x="{{ $month['x'] + 16 }}" y="{{ $month['y'] - 8 }}" text-anchor="middle" fill="currentColor" font-size="12" font-weight="600">{{ $month['count'] }}</text>
                                        <text x="{{ $month['x'] + 16 }}" y="246" text-anchor="middle" fill="currentColor" font-size="12">{{ $month['label'] }}</text>
                                    </g>
                                @endforeach
                            </svg>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 sm:hidden">Geser grafik untuk melihat seluruh bulan.</p>
                    @else
                        <div class="py-12 text-center rounded-lg bg-gray-50 dark:bg-gray-900/40">
                            <x-icon name="chart-bar" class="h-12 w-12 mx-auto text-gray-400" />
                            <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">Belum ada survey pada tahun ini</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pilih tahun lain untuk melihat data yang tersedia.</p>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 sm:p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Komposisi status survey</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Status terkini dari {{ number_format($analytics['total']) }} survey pada tahun {{ $analyticsYear }}.</p>
                        <div class="mt-6 space-y-5">
                            @foreach($analytics['statuses'] as $status)
                                <div wire:key="dashboard-status-{{ $loop->index }}">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm mb-2">
                                        <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $status['class'] }}">{{ $status['label'] }}</span>
                                        <span class="tabular-nums text-gray-900 dark:text-white"><strong>{{ number_format($status['count']) }}</strong> <span class="text-xs text-gray-500 dark:text-gray-400">/ {{ $status['percentage'] }}%</span></span>
                                    </div>
                                    <div role="img" aria-label="{{ $status['label'] }}: {{ $status['count'] }} survey, {{ $status['percentage'] }} persen" class="h-2.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                        <div class="h-full rounded-full {{ $status['class'] }}" style="width: {{ $status['percentage'] }}%; background-color: currentColor;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 sm:p-6 shadow-sm">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Distribusi Grade CAP maritim</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($analytics['rated']) }} survey selesai dengan skor. CAP 1 adalah grade terbaik.</p>
                        @if($analytics['rated'] > 0)
                            <div class="mt-6 space-y-5">
                                @foreach($analytics['ratings'] as $rating)
                                    <div wire:key="dashboard-cap-{{ $loop->index }}">
                                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm mb-2">
                                            <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $rating['class'] }}">{{ $rating['label'] }} · {{ $rating['description'] }}</span>
                                            <span class="tabular-nums text-gray-900 dark:text-white"><strong>{{ number_format($rating['count']) }}</strong> <span class="text-xs text-gray-500 dark:text-gray-400">/ {{ $rating['percentage'] }}%</span></span>
                                        </div>
                                        <div role="img" aria-label="{{ $rating['label'] }}: {{ $rating['count'] }} survey, {{ $rating['percentage'] }} persen" class="h-2.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                            <div class="h-full rounded-full {{ $rating['class'] }}" style="width: {{ $rating['percentage'] }}%; background-color: currentColor;"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="py-12 text-center">
                                <x-icon name="chart-bar" class="h-12 w-12 mx-auto text-gray-400" />
                                <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-300">Belum ada hasil CAP final</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Grafik muncul setelah survey selesai dan memiliki skor CAP.</p>
                            </div>
                        @endif
                    </div>
                </div>
                <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">Setiap survey dihitung satu kali, bukan per kapal unik. Data yang dihapus tidak disertakan. Persentase dibulatkan; skor CAP kosong tidak dianggap nol.</p>
            </div>
        </section>
    @endisset

    {{-- Stat Cards: User, Company & Role (gated by respective entity permissions) --}}
    @canany(['users_view', 'companies_view', 'roles_view'])
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        @can('users_view')
        {{-- Total Users --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Pengguna</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($totalUsers) }}</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Pengguna terdaftar</p>
                </div>
                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
                    <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
        </div>
        @endcan

        @can('companies_view')
        {{-- Total Companies --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Perusahaan</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($totalCompanies) }}</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Perusahaan terdaftar</p>
                </div>
                <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl">
                    <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
        </div>
        @endcan

        @can('roles_view')
        {{-- Total Roles --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Role</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($totalRoles) }}</p>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Role yang dikonfigurasi</p>
                </div>
                <div class="p-3 bg-violet-50 dark:bg-violet-900/20 rounded-xl">
                    <svg class="w-8 h-8 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
            </div>
        </div>
        @endcan

    </div>
    @endcanany

    {{-- Quick Actions (only render if user has at least one relevant permission) --}}
    @canany(['users_view', 'roles_view', 'configuration_view', 'companies_view'])
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Aksi Cepat</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">

            @can('users_view')
            <a href="{{ route('settings.users') }}" wire:navigate
               class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-blue-400 dark:hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-all group">
                <div class="flex-shrink-0 p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-lg group-hover:bg-blue-200 dark:group-hover:bg-blue-900/50 transition-colors">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Manajemen User</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Kelola akun pengguna</p>
                </div>
            </a>
            @endcan

            @can('roles_view')
            <a href="{{ route('settings.roles') }}" wire:navigate
               class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-violet-400 dark:hover:border-violet-500 hover:bg-violet-50 dark:hover:bg-violet-900/10 transition-all group">
                <div class="flex-shrink-0 p-2.5 bg-violet-100 dark:bg-violet-900/30 rounded-lg group-hover:bg-violet-200 dark:group-hover:bg-violet-900/50 transition-colors">
                    <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Roles & Permissions</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Kelola hak akses</p>
                </div>
            </a>
            @endcan

            @can('configuration_view')
            <a href="{{ route('settings.system') }}" wire:navigate
               class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-emerald-400 dark:hover:border-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/10 transition-all group">
                <div class="flex-shrink-0 p-2.5 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg group-hover:bg-emerald-200 dark:group-hover:bg-emerald-900/50 transition-colors">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Konfigurasi System</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Pengaturan aplikasi</p>
                </div>
            </a>
            @endcan

            @can('companies_view')
            <a href="{{ route('master-data.companies') }}" wire:navigate
               class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-amber-400 dark:hover:border-amber-500 hover:bg-amber-50 dark:hover:bg-amber-900/10 transition-all group">
                <div class="flex-shrink-0 p-2.5 bg-amber-100 dark:bg-amber-900/30 rounded-lg group-hover:bg-amber-200 dark:group-hover:bg-amber-900/50 transition-colors">
                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Master Data</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Kelola perusahaan</p>
                </div>
            </a>
            @endcan

        </div>
    </div>
    @endcanany

    {{-- System Info (gated by configuration_view — internal app info) --}}
    @can('configuration_view')
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Informasi System</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <span class="flex-shrink-0 w-2 h-2 bg-green-500 rounded-full"></span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Laravel {{ app()->version() }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Framework</p>
                </div>
            </div>
            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <span class="flex-shrink-0 w-2 h-2 bg-green-500 rounded-full"></span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">PHP {{ phpversion() }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Runtime</p>
                </div>
            </div>
            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <span class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full"></span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ config('app.env') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Environment</p>
                </div>
            </div>
            <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <span class="flex-shrink-0 w-2 h-2 bg-blue-500 rounded-full"></span>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ config('app.timezone') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Timezone</p>
                </div>
            </div>
        </div>
    </div>
    @endcan

</div>
