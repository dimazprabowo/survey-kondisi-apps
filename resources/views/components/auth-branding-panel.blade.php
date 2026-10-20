{{--
    Auth Branding Panel - Left/right side panel for guest auth pages.

    App context: Sistem Survey Kondisi Kapal — pengelolaan template penilaian,
    pengisian survey kondisi, serta laporan & dokumentasi resmi.
    Visual language: enterprise blueprint grid + clean feature highlights.

    Uses currentColor so the illustration inherits the white text color
    of the blue gradient panel. Width is fixed for desktop layout.
--}}
<div class="hidden lg:flex lg:w-1/2 xl:w-2/5 bg-gradient-to-br from-blue-700 via-blue-800 to-blue-900 dark:from-blue-800 dark:via-blue-900 dark:to-gray-900 p-8 lg:p-12 flex-col justify-between relative overflow-hidden text-white">

    {{-- Subtle blueprint grid overlay --}}
    <div class="absolute inset-0 opacity-[0.07] pointer-events-none" aria-hidden="true">
        <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="bp-grid" x="0" y="0" width="32" height="32" patternUnits="userSpaceOnUse">
                    <path d="M 32 0 L 0 0 0 32" fill="none" stroke="currentColor" stroke-width="0.5"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#bp-grid)"/>
        </svg>
    </div>

    {{-- Top accent line --}}
    <div class="absolute top-0 left-0 right-0 h-1 bg-blue-400/30"></div>

    {{-- Content --}}
    <div class="relative z-10 flex flex-col h-full justify-between">

        {{-- Top Section - Logo & Title --}}
        <div>
            <div class="flex items-center space-x-3 mb-10">
                <div class="w-14 h-14 bg-white rounded-xl flex items-center justify-center shadow-lg p-1.5 overflow-hidden flex-shrink-0">
                    <img src="{{ email_logo_url() }}" alt="Logo" class="w-full h-full object-contain rounded-lg">
                </div>
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold leading-tight">{{ app_name() }}</h1>
                    <p class="text-sm text-blue-200">Marine Assessment &amp; Technical Reporting Automation</p>
                    <p class="text-xs text-blue-300 mt-0.5">PT. Biro Klasifikasi Indonesia</p>
                </div>
            </div>

            {{-- Main headline --}}
            <div class="space-y-4 max-w-lg mb-8">
                <h2 class="text-3xl lg:text-4xl xl:text-[2.75rem] font-bold leading-tight">
                    Sistem Survey<br>Kondisi Kapal
                </h2>
                <p class="text-base lg:text-lg text-blue-100 leading-relaxed">
                    Platform terpadu pengelolaan survey kondisi kapal — dari template
                    penilaian dinamis, pengisian survey, hingga laporan resmi.
                </p>
            </div>
        </div>

        {{-- Middle Section - Enterprise Platform Illustration --}}
        <div class="my-8 text-blue-50">
            <x-auth-platform-illustration />
        </div>

        {{-- Bottom Section - Key Capabilities --}}
        <div class="space-y-3">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-500/25 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-sm text-white">Penilaian Terstruktur</h3>
                    <p class="text-xs text-blue-200">Kategori kondisi bertingkat dengan skor CAP otomatis</p>
                </div>
            </div>

            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-500/25 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-sm text-white">Template Form Dinamis</h3>
                    <p class="text-xs text-blue-200">Susun struktur penilaian fleksibel per jenis survey</p>
                </div>
            </div>

            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-500/25 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-sm text-white">Laporan & Dokumentasi</h3>
                    <p class="text-xs text-blue-200">Pratinjau laporan, export DOCX/PDF, dan foto dokumentasi</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="relative z-10 text-blue-200 text-xs mt-6">
        <p>&copy; {{ date('Y') }} {{ app_name() }} &middot; PT. Biro Klasifikasi Indonesia</p>
    </div>
</div>
