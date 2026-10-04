<div
    x-data="{
        tab: 'info',
        switching: null,
        pickerScrollY: 0,
        storageKey: 'survey-report-tab-{{ $report->id }}',
        validTabs: ['info', 'exec', 'toc', 'bab1', 'bab2', 'bab3', 'bab4'],
        init() {
            const hashTab = window.location.hash.replace('#', '')
            const savedTab = sessionStorage.getItem(this.storageKey)
            this.tab = this.validTabs.includes(hashTab) ? hashTab : (this.validTabs.includes(savedTab) ? savedTab : 'info')
            this.$watch('tab', value => {
                sessionStorage.setItem(this.storageKey, value)
                history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${value}`)
            })
            this.$nextTick(() => window.dispatchEvent(new CustomEvent('report-tab-changed')))
        },
        switchTab(tab) {
            if (this.tab === tab || this.switching) return
            this.switching = tab
            setTimeout(() => {
                this.tab = tab
                this.switching = null
                this.$nextTick(() => window.dispatchEvent(new CustomEvent('report-tab-changed')))
            }, 120)
        },
        openDocumentationPicker(categoryId) {
            this.pickerScrollY = window.scrollY
            this.$wire.set('documentationCategoryId', categoryId, false)
            this.$refs.documentationInput.value = ''
            this.$refs.documentationInput.click()
            requestAnimationFrame(() => window.scrollTo(0, this.pickerScrollY))
            window.addEventListener('focus', () => requestAnimationFrame(() => window.scrollTo(0, this.pickerScrollY)), { once: true })
        }
    }"
    x-on:documentation-photo-ready.window="$nextTick(() => window.scrollTo(0, pickerScrollY))"
>
    @php
        // Gaya "paper mode" — lembar meniru halaman Word; input borderless
        // dengan garis putus-putus sebagai affordance area yang bisa diisi.
        $ship = $survey->ship;
        $canvas = 'rounded-xl bg-gray-200/70 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800 p-2 sm:p-5 lg:p-8';
        $sheet = 'mx-auto w-full max-w-4xl rounded-sm bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10 px-5 py-8 sm:px-10 sm:py-10 lg:px-16 lg:py-14 text-[15px] leading-7 text-gray-900 dark:text-gray-100';
        $times = "font-family:'Times New Roman',Times,serif";
        $sans = 'font-family:ui-sans-serif,system-ui,sans-serif';
        $paperInput = 'bg-transparent border-0 border-b border-dashed border-gray-300 dark:border-gray-600 rounded-none px-1 py-0.5 text-[15px] text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-0';
        $paperTextarea = 'w-full bg-transparent border border-dashed border-transparent rounded-md px-2 py-1.5 text-[15px] leading-7 text-justify text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 hover:border-gray-300 dark:hover:border-gray-600 focus:border-blue-400 dark:focus:border-blue-500 focus:ring-0 resize-none overflow-hidden min-h-40';
        $paperTextareaCell = 'w-full bg-transparent border border-dashed border-transparent rounded px-1 py-0.5 text-[13px] leading-6 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 hover:border-gray-300 dark:hover:border-gray-600 focus:border-blue-400 focus:ring-0 resize-none overflow-hidden min-h-20';
        $autoZone = 'relative mt-6 rounded-md border border-dashed border-gray-300 dark:border-gray-600 p-3 pt-5 sm:p-5 sm:pt-6';
        $autoTag = 'absolute -top-2.5 left-3 bg-white dark:bg-gray-800 px-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500';
        $fitJs = "{ fit(){ const el=this.\$el; if (!el.offsetParent) return; el.style.height='auto'; el.style.height=el.scrollHeight+'px' } }";
    @endphp

    <input x-ref="documentationInput" wire:model="documentationPhoto" type="file" accept="image/jpeg,image/png,image/webp"
        tabindex="-1" aria-hidden="true" class="pointer-events-none fixed left-0 top-0 h-px w-px opacity-0">

    <!-- Breadcrumb -->
    <nav class="mb-6 flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2 text-sm">
            <li>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Dashboard</a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li>
                <a href="{{ route('surveys.index') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Survey</a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li>
                <a href="{{ route('surveys.show', $survey) }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">{{ $survey->survey_number }}</a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li class="text-gray-900 dark:text-white font-medium">Laporan</li>
        </ol>
    </nav>

    <!-- Page Header + Status File -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Laporan Survey</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $ship?->name }} &middot; {{ $survey->survey_number }}
            </p>
        </div>
        <div class="flex flex-col sm:items-end gap-2">
            @if($report->file_status)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $report->file_status->badgeClass() }}">
                    {{ $report->file_status->label() }}
                </span>
                @if($report->file_status === \App\Enums\FileStatus::Completed && $report->file_processed_at)
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Digenerate {{ $report->file_processed_at->format('d/m/Y H:i') }}
                        &middot; {{ number_format(($report->file_size ?? 0) / 1024, 0) }} KB
                    </span>
                @endif
                @if($report->file_status === \App\Enums\FileStatus::Failed && $report->file_error)
                    <span class="text-xs text-red-600 dark:text-red-400">Proses gagal — silakan coba generate ulang.</span>
                @endif
            @else
                <span class="text-xs text-gray-500 dark:text-gray-400">Belum pernah digenerate</span>
            @endif
        </div>
    </div>

    @if($report->file_status === \App\Enums\FileStatus::Processing)
        <div wire:poll.3s="refreshStatus" class="mb-6 overflow-hidden rounded-lg border border-blue-200 bg-white shadow-sm dark:border-blue-800 dark:bg-gray-800">
            <div class="flex items-center gap-3 px-4 py-3 sm:px-5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/30">
                    <svg class="h-5 w-5 animate-spin text-blue-600 dark:text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                        <path class="opacity-80" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"></path>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-col gap-0.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Menyiapkan dokumen Word</p>
                        <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Berjalan di latar belakang</span>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Data, grafik, tabel, dan dokumentasi sedang disusun. Anda tetap dapat berpindah tab.</p>
                </div>
            </div>
            <div class="h-1 overflow-hidden bg-blue-100 dark:bg-blue-950">
                <div class="h-full w-1/3 animate-pulse rounded-r-full bg-blue-500"></div>
            </div>
        </div>
    @endif

    <form wire:submit="save">
        <!-- Tab Bar (scrollable di mobile) -->
        <div class="relative mb-2 -mx-1">
            <div class="navbar-scroll overflow-x-auto px-1">
            <div class="inline-flex min-w-full sm:min-w-0 gap-1 rounded-lg bg-gray-100 dark:bg-gray-900/60 p-1" role="tablist">
                @php
                    $tabs = [
                        'info' => 'Sampul & Pengesahan',
                        'exec' => 'Executive Summary',
                        'toc' => 'Daftar Isi',
                        'bab1' => 'BAB I — Umum',
                        'bab2' => 'BAB II — Kapal',
                        'bab3' => 'BAB III — Hasil',
                        'bab4' => 'BAB IV — Saran',
                    ];
                @endphp
                @foreach($tabs as $key => $label)
                    <button type="button" role="tab"
                        @click="switchTab('{{ $key }}')"
                        :aria-selected="tab === '{{ $key }}'"
                        :disabled="switching !== null"
                        :class="tab === '{{ $key }}'
                            ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-sm'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="flex flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-md px-3.5 py-2 text-sm font-medium transition-colors disabled:cursor-wait sm:flex-none">
                        <span x-show="switching === '{{ $key }}'" x-cloak class="h-4 w-4 shrink-0">
                            <x-loading-spinner size="sm" class="h-4 w-4 [&>div]:h-4 [&>div]:w-4 [&_svg]:h-4 [&_svg]:w-4" />
                        </span>
                        <span>{{ $label }}</span>
                    </button>
                @endforeach
            </div>
            </div>
            <div class="pointer-events-none absolute inset-y-0 left-0 w-5 bg-gradient-to-r from-white/90 to-transparent dark:from-gray-900/90 sm:hidden"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-5 bg-gradient-to-l from-white/90 to-transparent dark:from-gray-900/90 sm:hidden"></div>
        </div>
        <p class="mb-4 text-center text-xs text-gray-400 dark:text-gray-500">
            Tampilan menyerupai dokumen Word — klik teks bergaris putus-putus untuk mengisi. Area bertanda "Otomatis" diisi sistem dari data survey &amp; master kapal.
        </p>

        <!-- ================= Tab: Sampul & Pengesahan ================= -->
        <div x-show="tab === 'info'" x-cloak x-transition.opacity.duration.100ms wire:key="panel-info" class="{{ $canvas }}">
            <!-- Sheet 1: Sampul -->
            <div class="{{ $sheet }}" style="{{ $times }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex flex-1 flex-wrap items-baseline gap-x-2">
                        <span class="font-semibold uppercase">No. Lap :</span>
                        <span class="inline-block w-48 sm:w-64">
                            <x-input-label for="report_number" value="Nomor Laporan" :required="true" class="sr-only" />
                            <input wire:model="report_number" id="report_number" type="text"
                                class="{{ $paperInput }} w-full" placeholder="Nomor laporan, contoh: LAP-2026-0001">
                        </span>
                        <x-input-error :messages="$errors->get('report_number')" class="w-full" />
                    </div>
                    <img src="{{ asset('images/bki-main.webp') }}" alt="Biro Klasifikasi Indonesia" class="h-14 w-auto object-contain sm:h-16">
                </div>

                <div class="mt-16 sm:mt-24 text-center">
                    <p class="text-2xl sm:text-3xl font-bold tracking-wide">LAPORAN AKHIR</p>
                    <p class="mt-10 text-xl sm:text-2xl font-bold uppercase">{{ $ship?->name ?? '—' }}</p>
                    <x-input-label for="report_title" value="Judul Laporan" :required="true" class="sr-only" />
                    <textarea wire:model="report_title" id="report_title" rows="2"
                        x-data="{{ $fitJs }}" x-init="$nextTick(() => fit())" x-on:input="fit()" x-on:report-tab-changed.window="$nextTick(() => fit())"
                        placeholder="Judul laporan, contoh: JASA KONSULTAN INDEPENDENT SURVEY KONDISI PT. ASDP INDONESIA FERRY - 2026"
                        class="{{ $paperTextarea }} mt-6 text-center text-base sm:text-lg font-bold uppercase"></textarea>
                    <x-input-error :messages="$errors->get('report_title')" class="mt-1" />
                </div>
            </div>

            <!-- Sheet 2: Lembar Pengesahan -->
            <div class="{{ $sheet }} mt-4 sm:mt-6" style="{{ $times }}">
                <div class="text-center">
                    <p class="text-lg font-bold underline underline-offset-4">LEMBAR PENGESAHAN</p>
                    <p class="mt-2 font-bold">LAPORAN AKHIR</p>
                    <p class="font-bold">PELAKSANAAN PEKERJAAN</p>
                    <p class="font-bold">JASA KONSULTANSI ASSESSMENT KONDISI KAPAL</p>
                    <p class="font-bold">PT. ASDP INDONESIA FERRY (PERSERO)</p>
                    <p class="mt-2">No: {{ $report_number ?: '—' }}</p>
                </div>

                <p class="mt-10 font-bold">Data Kapal</p>
                <table class="mt-2">
                    <tbody>
                        @foreach([
                            ['NAMA KAPAL', $ship?->name],
                            ['TIPE KAPAL', $ship?->ship_type],
                            ['LOA', $ship?->loa ? $ship->loa.' M' : null],
                            ['BREADTH', $ship?->breadth ? $ship->breadth.' M' : null],
                            ['DRAFT', $ship?->draft ? $ship->draft.' M' : null],
                        ] as [$plabel, $pval])
                            <tr>
                                <td class="w-44 pr-4 py-0.5 align-top font-medium">{{ $plabel }}</td>
                                <td class="w-4 align-top">:</td>
                                <td class="py-0.5 align-top uppercase">{{ $pval ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-14 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-12 text-center">
                    <div>
                        <p>Mengetahui,</p>
                        <p>PT. Biro Klasifikasi Indonesia (Persero)</p>
                        <p>Kepala Cabang Utama Komersial Jakarta</p>
                        <div class="mt-20">
                            <x-input-label for="approver_name" value="Nama Penyetuju" class="sr-only" />
                            <input wire:model="approver_name" id="approver_name" type="text"
                                class="{{ $paperInput }} mx-auto w-full max-w-xs text-center" placeholder="Nama penyetuju">
                            <p class="mt-1">( Kepala Cabang Utama Komersial Jakarta )</p>
                            <x-input-error :messages="$errors->get('approver_name')" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-baseline justify-center gap-x-1">
                            <x-input-label for="approval_place" value="Tempat Pengesahan" class="sr-only" />
                            <input wire:model="approval_place" id="approval_place" type="text"
                                class="{{ $paperInput }} w-28 text-center" placeholder="Kota">
                            <span>,</span>
                            <x-input-label for="approval_date" value="Tanggal Pengesahan" class="sr-only" />
                            <input wire:model="approval_date" id="approval_date" type="date"
                                class="{{ $paperInput }} w-40 text-center">
                        </div>
                        <x-input-error :messages="$errors->get('approval_place')" class="mt-1" />
                        <x-input-error :messages="$errors->get('approval_date')" class="mt-1" />
                        <p class="mt-3">Inspector,</p>
                        <div class="mt-14 space-y-12">
                            <div>
                                <x-input-label for="inspector_1" value="Inspector 1" class="sr-only" />
                                <input wire:model="inspector_1" id="inspector_1" type="text"
                                    class="{{ $paperInput }} mx-auto w-full max-w-xs text-center" placeholder="Nama inspector pertama">
                                <p class="mt-1">( Inspector )</p>
                                <x-input-error :messages="$errors->get('inspector_1')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="inspector_2" value="Inspector 2" class="sr-only" />
                                <input wire:model="inspector_2" id="inspector_2" type="text"
                                    class="{{ $paperInput }} mx-auto w-full max-w-xs text-center" placeholder="Nama inspector kedua">
                                <p class="mt-1">( Inspector )</p>
                                <x-input-error :messages="$errors->get('inspector_2')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= Tab: Executive Summary ================= -->
        <div x-show="tab === 'exec'" x-cloak x-transition.opacity.duration.100ms wire:key="panel-exec" class="{{ $canvas }}">
            <div class="{{ $sheet }}" style="{{ $times }}">
                <h3 class="text-center text-lg font-bold tracking-wide">EXECUTIVE SUMMARY</h3>
                <x-input-label for="executive_summary" value="Narasi Executive Summary" class="sr-only" />
                <textarea wire:model="sectionContent.executive_summary" id="executive_summary" rows="6"
                    x-data="{{ $fitJs }}" x-init="$nextTick(() => fit())" x-on:input="fit()" x-on:report-tab-changed.window="$nextTick(() => fit())"
                    placeholder="Ringkasan pelaksanaan survey dan metodologi CAP"
                    class="{{ $paperTextarea }} mt-6"></textarea>
                <x-input-error :messages="$errors->get('sectionContent.executive_summary')" class="mt-1" />

                <!-- Preview blok otomatis: daftar kategori + CAP breakdown + benchmark -->
                <div class="{{ $autoZone }}">
                    <span class="{{ $autoTag }}" style="{{ $sans }}">Otomatis dari data survey</span>
                    @foreach($bab3Categories as $catIndex => $cat)
                        <p class="pl-2">{{ $catIndex + 1 }}. {{ $cat->label }}</p>
                    @endforeach

                    <p class="mt-3 text-justify">
                        Tabel berikut menyajikan rincian Overall CAP Rating hasil survei kondisi kapal
                        {{ $ship?->name ?? '-' }}, yang diperoleh dari rata-rata penilaian pada komponen
                        pemeriksaan utama.
                        @php $overallCap = $this->overallAvg($bab3Categories); @endphp
                        @if($overallCap !== null)
                            Berdasarkan hasil penilaian, kapal ini memperoleh Overall CAP Rating sebesar
                            {{ number_format($overallCap, 2) }}. Adapun rincian penilaian sebagai berikut:
                        @endif
                    </p>

                    <div class="mt-2 overflow-hidden border border-gray-500 dark:border-gray-400">
                        <div class="bg-[#4472C4] px-3 py-2 text-center font-bold text-white">Overall CAP Rating Breakdown</div>
                        <div class="grid grid-cols-[1fr_auto_auto] items-center gap-x-4 px-3 py-2 font-bold">
                            <span>Overall CAP Rating Condition {{ $ship?->name }}</span>
                            <span>:</span>
                            <span>{{ $overallCap !== null ? number_format($overallCap, 2) : '-' }}</span>
                        </div>
                        <div class="h-6 bg-gray-500 dark:bg-gray-600"></div>
                        @php $half = (int) ceil(max($bab3Categories->count(), 1) / 2); @endphp
                        <div class="grid grid-cols-1 gap-x-5 px-2 py-2 sm:grid-cols-2">
                            @foreach([$bab3Categories->slice(0, $half)->values(), $bab3Categories->slice($half)->values()] as $colIndex => $colCats)
                                <div class="min-w-0">
                                    @foreach($colCats as $i => $cat)
                                        @php $catAvg = $this->categoryAvg($cat); @endphp
                                        <div class="mb-4 break-inside-avoid">
                                            <div class="grid grid-cols-[1fr_auto_auto] items-end gap-x-2 border-b-2 border-gray-500 pb-0.5 font-bold">
                                                <span>{{ ($colIndex === 0 ? 0 : $half) + $i + 1 }}. {{ $cat->label }}</span>
                                                <span>:</span>
                                                <span>{{ $catAvg !== null ? number_format($catAvg, 2) : '-' }}</span>
                                            </div>
                                            @foreach($cat->subCategories as $sc)
                                                @php $scAvg = $this->subCategoryAvg($sc); @endphp
                                                <div class="grid grid-cols-[1rem_1fr_auto] gap-x-2 px-1 pt-1">
                                                    <span>-</span>
                                                    <span>{{ $sc->name }}</span>
                                                    <span>{{ $scAvg !== null ? number_format($scAvg, 2) : '-' }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <p class="mt-4 text-justify">
                        Sebagai bahan pembanding, dilakukan analisis benchmarking terhadap database kapal
                        RoRo Passenger yang telah diinspeksi oleh PT. Biro Klasifikasi Indonesia sepanjang
                        tahun 2025, dengan memetakan hubungan antara umur kapal dan overall CAP rating.
                        Grafik berikut menunjukkan posisi {{ $ship?->name ?? '-' }} (titik merah)
                        dibandingkan dengan sebaran data kapal-kapal sejenis (titik biru), sebagai acuan
                        untuk menilai kewajaran kondisi kapal relatif terhadap populasi kapal dengan
                        rentang umur yang sama.
                    </p>
                    @if(extension_loaded('gd'))
                        <img src="{{ route('surveys.report.benchmark', $survey) }}" loading="lazy"
                            alt="Grafik benchmark Overall CAP vs umur kapal"
                            class="mx-auto my-3 w-full max-w-md rounded border border-gray-200 dark:border-gray-600">
                    @else
                        <div class="mx-auto my-3 max-w-md rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/40 py-10 text-center text-xs text-gray-400 dark:text-gray-500" style="{{ $sans }}">
                            Grafik benchmark Overall CAP vs Umur Kapal — dibuat otomatis saat generate
                        </div>
                    @endif
                    <p class="text-justify">
                        Grafik diatas menunjukan posisi {{ $ship?->name ?? '-' }} terhadap data kapal
                        pembanding yang telah diinspeksi oleh PT. Biro Klasifikasi Indonesia pada tahun
                        2025, dengan membandingan overall rating CAP dengan umur kapal.
                    </p>
                </div>

                <!-- Tabel temuan: struktur otomatis, kolom Keterangan editable -->
                <div class="{{ $autoZone }}">
                    <span class="{{ $autoTag }}" style="{{ $sans }}">Tabel otomatis — isi kolom Keterangan</span>
                    <p class="text-justify">
                        Tabel berikut menyajikan ringkasan hasil temuan inspeksi visual pada masing-masing
                        item pemeriksaan sesuai kategori CAP beserta dokumentasi foto sebagai terhadap
                        temuan utama. Uraian keterangan pada setiap item menggambarkan kondisi umum yang
                        ditemukan di lapangan dan indikasi kerusakan.
                    </p>
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-400 dark:border-gray-500">
                            <thead>
                                <tr class="bg-[#D9E2F3] dark:bg-blue-900/30">
                                    <th class="w-12 border border-gray-400 dark:border-gray-500 px-2 py-1 text-center font-bold">No.</th>
                                    <th class="w-56 border border-gray-400 dark:border-gray-500 px-2 py-1 text-center font-bold">Item Pemeriksaan</th>
                                    <th class="border border-gray-400 dark:border-gray-500 px-2 py-1 text-center font-bold">Keterangan</th>
                                    <th class="w-64 border border-gray-400 dark:border-gray-500 px-2 py-1 text-center font-bold">Dokumentasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bab3Categories as $i => $cat)
                                    <tr wire:key="finding-{{ $cat->id }}">
                                        <td class="border border-gray-400 dark:border-gray-500 px-2 py-1 text-center align-top">{{ $i + 1 }}</td>
                                        <td class="border border-gray-400 dark:border-gray-500 px-2 py-1 align-top">{{ $cat->label }}</td>
                                        <td class="border border-gray-400 dark:border-gray-500 px-1 py-1">
                                            <x-input-label for="finding_{{ $cat->id }}" value="Keterangan temuan {{ $cat->label }}" class="sr-only" />
                                            <textarea wire:model="sectionContent.finding_{{ $cat->id }}" id="finding_{{ $cat->id }}" rows="2"
                                                x-data="{{ $fitJs }}" x-init="$nextTick(() => fit())" x-on:input="fit()" x-on:report-tab-changed.window="$nextTick(() => fit())"
                                                placeholder="Keterangan temuan — satu poin per baris"
                                                class="{{ $paperTextareaCell }}"></textarea>
                                        </td>
                                        @php $documentation = $documentations->get($cat->id); @endphp
                                        <td class="border border-gray-400 dark:border-gray-500 p-2 align-top" wire:key="documentation-{{ $cat->id }}">
                                            @if($documentation?->file_status === \App\Enums\FileStatus::Completed)
                                                <img src="{{ route('surveys.report.documentation', [$survey, \Illuminate\Support\Facades\Crypt::encryptString((string) $cat->id)]) }}"
                                                    alt="Dokumentasi {{ $cat->label }}" class="aspect-[3/2] w-full rounded-md object-cover">
                                                @can('survey_reports_update')
                                                    <div class="mt-2 flex flex-wrap justify-center gap-2" style="{{ $sans }}">
                                                        <x-loading-button type="button"
                                                            x-on:click="openDocumentationPicker({{ $cat->id }})"
                                                            wire:key="btn-replace-documentation-{{ $cat->id }}" variant="icon-blue" icon="edit" title="Ganti foto" />
                                                        <x-loading-button type="button" wire:click="confirmDeleteDocumentation({{ $documentation->id }})" target="confirmDeleteDocumentation({{ $documentation->id }})"
                                                            wire:key="btn-delete-documentation-{{ $cat->id }}" variant="icon-red" icon="delete" title="Hapus foto" />
                                                    </div>
                                                @endcan
                                            @elseif($documentation?->file_status === \App\Enums\FileStatus::Processing)
                                                <div wire:poll.3s="refreshStatus" class="flex aspect-[3/2] items-center justify-center rounded-md border border-dashed border-amber-300 bg-amber-50 text-center text-xs text-amber-700 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300" style="{{ $sans }}">
                                                    <span>Foto sedang diproses...</span>
                                                </div>
                                            @else
                                                <div class="flex aspect-[3/2] flex-col items-center justify-center rounded-md border-2 border-dashed border-gray-300 bg-gray-50 px-3 text-center dark:border-gray-600 dark:bg-gray-700/30" style="{{ $sans }}">
                                                    <x-icon name="plus" class="h-7 w-7 text-gray-400" />
                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Foto utama kategori</p>
                                                    @can('survey_reports_update')
                                                        <x-loading-button type="button"
                                                            x-on:click="openDocumentationPicker({{ $cat->id }})"
                                                            wire:key="btn-upload-documentation-{{ $cat->id }}" variant="secondary" size="sm" class="mt-2">
                                                            Pilih Foto
                                                        </x-loading-button>
                                                    @endcan
                                                </div>
                                                @if($documentation?->file_status === \App\Enums\FileStatus::Failed)
                                                    <p class="mt-1 text-center text-xs text-red-600 dark:text-red-400" style="{{ $sans }}">Pemrosesan gagal. Silakan unggah ulang.</p>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @foreach($bab3Categories as $cat)
                        <x-input-error :messages="$errors->get('sectionContent.finding_'.$cat->id)" class="mt-1" />
                    @endforeach
                </div>
            </div>
        </div>

        <!-- ================= Tab: Daftar Isi ================= -->
        <div x-show="tab === 'toc'" x-cloak x-transition.opacity.duration.100ms wire:key="panel-toc" class="{{ $canvas }}">
            <div class="{{ $sheet }}" style="{{ $times }}">
                <h3 class="text-center text-lg font-bold tracking-wide">DAFTAR ISI</h3>
                <div class="mt-8 space-y-2">
                    @foreach([
                        ['LEMBAR PENGESAHAN', 'ii', 0],
                        ['EXECUTIVE SUMMARY', '3', 0],
                        ['DAFTAR ISI', '8', 0],
                        ['BAB I. UMUM/GENERAL', '9', 0],
                        ['BAB II. DATA KAPAL', '12', 0],
                        ['2.1 SHIP PARTICULAR', '12', 1],
                        ['2.2 STATUS CLASS', '13', 1],
                        ['2.3 CLASS MEMORANDA', '13', 1],
                        ['BAB III. PEMERIKSAAN KONDISI KAPAL', '14', 0],
                    ] as [$tocLabel, $tocPage, $tocLevel])
                        <div class="flex items-end gap-2 {{ $tocLevel ? 'pl-5' : 'font-semibold' }}">
                            <span>{{ $tocLabel }}</span>
                            <span class="mb-1 flex-1 border-b border-dotted border-gray-400"></span>
                            <span>{{ $tocPage }}</span>
                        </div>
                    @endforeach
                    @foreach($bab3Categories as $catIndex => $cat)
                        <div class="flex items-end gap-2 pl-5">
                            <span>{{ $catIndex + 1 }}. {{ strtoupper($cat->label) }}</span>
                            <span class="mb-1 flex-1 border-b border-dotted border-gray-400"></span>
                            <span class="text-gray-400">—</span>
                        </div>
                    @endforeach
                    @foreach([
                        ['BAB IV. SARAN', '—'],
                        ['LAMPIRAN', '—'],
                    ] as [$tocLabel, $tocPage])
                        <div class="flex items-end gap-2 font-semibold">
                            <span>{{ $tocLabel }}</span>
                            <span class="mb-1 flex-1 border-b border-dotted border-gray-400"></span>
                            <span class="text-gray-400">{{ $tocPage }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-8 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-700 dark:border-blue-800 dark:bg-blue-900/20 dark:text-blue-300" style="{{ $sans }}">
                    Nomor halaman BAB III, BAB IV, dan Lampiran mengikuti panjang konten dan diperbarui otomatis melalui field Daftar Isi Microsoft Word.
                </p>
            </div>
        </div>

        <!-- ================= Tab: BAB I — Umum ================= -->
        <div x-show="tab === 'bab1'" x-cloak x-transition.opacity.duration.100ms wire:key="panel-bab1" class="{{ $canvas }}">
            <div class="{{ $sheet }}" style="{{ $times }}">
                <h3 class="text-center text-lg font-bold tracking-wide">BAB I. UMUM/GENERAL</h3>

                <p class="mt-6 text-justify leading-8">
                    Sesuai dengan Surat Perjanjian Nomor.
                    <span class="inline-block w-44 align-baseline">
                        <x-input-label for="contract_agreement_no" value="Nomor Surat Perjanjian" class="sr-only" />
                        <input wire:model="contract_agreement_no" id="contract_agreement_no" type="text"
                            class="{{ $paperInput }} w-full" placeholder="Nomor perjanjian">
                    </span>
                    tanggal
                    <x-input-label for="contract_agreement_date" value="Tanggal Surat Perjanjian" class="sr-only" />
                    <input wire:model="contract_agreement_date" id="contract_agreement_date" type="date"
                        class="{{ $paperInput }} inline w-40 align-baseline">
                    ; dan Surat Penunjukan Pelaksanaan Pekerjaan Nomor.
                    <span class="inline-block w-44 align-baseline">
                        <x-input-label for="contract_appointment_no" value="Nomor Surat Penunjukan" class="sr-only" />
                        <input wire:model="contract_appointment_no" id="contract_appointment_no" type="text"
                            class="{{ $paperInput }} w-full" placeholder="Nomor penunjukan">
                    </span>
                    tanggal
                    <x-input-label for="contract_appointment_date" value="Tanggal Surat Penunjukan" class="sr-only" />
                    <input wire:model="contract_appointment_date" id="contract_appointment_date" type="date"
                        class="{{ $paperInput }} inline w-40 align-baseline">
                    kepada PT. Biro Klasifikasi Indonesia (Persero) – SBU Marine Services Jakarta
                    tentang Pekerjaan &ldquo;{{ $report_title ?: '—' }}&rdquo;.
                </p>
                <div class="mt-1 space-y-1" style="{{ $sans }}">
                    <x-input-error :messages="$errors->get('contract_agreement_no')" />
                    <x-input-error :messages="$errors->get('contract_agreement_date')" />
                    <x-input-error :messages="$errors->get('contract_appointment_no')" />
                    <x-input-error :messages="$errors->get('contract_appointment_date')" />
                </div>

                <x-input-label for="general" value="Narasi Umum" class="sr-only" />
                <textarea wire:model="sectionContent.general" id="general" rows="8"
                    x-data="{{ $fitJs }}" x-init="$nextTick(() => fit())" x-on:input="fit()" x-on:report-tab-changed.window="$nextTick(() => fit())"
                    placeholder="Narasi tujuan dan ruang lingkup survey kondisi"
                    class="{{ $paperTextarea }} mt-4"></textarea>
                <x-input-error :messages="$errors->get('sectionContent.general')" class="mt-1" />

                <div class="{{ $autoZone }} mt-8">
                    <span class="{{ $autoTag }}" style="{{ $sans }}">Konten tetap dari master Word</span>
                    <p class="text-justify">{{ $capReference['introduction'] }}</p>
                    <ol class="mt-4 list-decimal space-y-0.5 pl-8">
                        @foreach($capReference['standards'] as $standard)
                            <li>{{ $standard }}</li>
                        @endforeach
                    </ol>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[720px] table-fixed border-collapse border border-gray-500 text-xs leading-5 dark:border-gray-400">
                            <colgroup>
                                <col style="width: 12%">
                                <col style="width: 28%">
                                <col style="width: 15%">
                                <col style="width: 15%">
                                <col style="width: 15%">
                                <col style="width: 15%">
                            </colgroup>
                            <thead class="bg-[#4472C4] text-white">
                                <tr>
                                    <th rowspan="2" class="border border-gray-500 px-2 py-1 align-middle">CATEGORY</th>
                                    <th rowspan="2" class="border border-gray-500 px-2 py-1 align-middle">CRITERIA / ITEM</th>
                                    <th colspan="4" class="border border-gray-500 px-2 py-1 text-center">CAP SCORE</th>
                                </tr>
                                <tr>
                                    @foreach(range(1, 4) as $score)
                                        <th class="border border-gray-500 px-2 py-1 text-center">{{ $score }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($capReference['categories'] as $capCategory)
                                    @foreach($capCategory['criteria'] as $criterionIndex => $criterion)
                                        <tr>
                                            @if($criterionIndex === 0)
                                                <td rowspan="{{ count($capCategory['criteria']) }}" class="border border-gray-500 px-2 py-1 text-center align-middle font-bold">
                                                    {{ $capCategory['code'] }}
                                                </td>
                                            @endif
                                            <td class="border border-gray-500 px-2 py-1 align-top">{{ $criterion['name'] }}</td>
                                            @foreach($criterion['scores'] as $scoreDescription)
                                                <td class="border border-gray-500 px-2 py-1 align-top">{{ $scoreDescription }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= Tab: BAB II — Kapal ================= -->
        <div x-show="tab === 'bab2'" x-cloak x-transition.opacity.duration.100ms wire:key="panel-bab2" class="{{ $canvas }}">
            <div class="{{ $sheet }}" style="{{ $times }}">
                <h3 class="text-center text-lg font-bold tracking-wide">BAB II. DATA KAPAL</h3>

                <!-- Ship Particular (otomatis dari master kapal) -->
                <div class="{{ $autoZone }}">
                    <span class="{{ $autoTag }}" style="{{ $sans }}">Otomatis dari master data kapal</span>
                    <p class="font-bold">SHIP PARTICULAR</p>
                    <div class="mt-1 overflow-x-auto">
                        <table>
                            <tbody>
                                @foreach([
                                    ['Name of Vessel', $ship?->name],
                                    ['Type of Ship', $ship?->ship_type],
                                    ['IMO Number', $ship?->imo_number],
                                    ['Call Sign', $ship?->call_sign],
                                    ['GT/NT', trim(($ship?->gross_tonnage ?? '').'/'.($ship?->net_tonnage ?? ''), '/') ?: null],
                                    ['Year Build', $ship?->year_built],
                                    ['Builder', $ship?->builder],
                                    ['Port Register', $ship?->port_of_registry],
                                    ['Material of Hull', $ship?->hull_material],
                                    ['Length (LoA)', $ship?->loa ? $ship->loa.' m' : null],
                                    ['Lenght (LPP)', $ship?->lpp ? $ship->lpp.' m' : null],
                                    ['Breadth (B)', $ship?->breadth ? $ship->breadth.' m' : null],
                                    ['Tinggi (Height)', $ship?->depth ? $ship->depth.' m' : null],
                                    ['Draft', $ship?->draft ? $ship->draft.' m' : null],
                                    ['DWT', $ship?->dwt],
                                    ['Class', $ship?->class_name],
                                    ['Class Notations', $ship?->class_notations],
                                    ['Main Engine', $ship?->main_engine],
                                    ['Main Engine Power Requier/Installed', $ship?->main_engine_power],
                                    ['Auxiliary Engine', $ship?->aux_engine],
                                    ['Auxiliary Cap./Power', $ship?->aux_engine_power],
                                ] as [$plabel, $pval])
                                    <tr>
                                        <td class="w-56 pr-4 py-0.5 align-top">{{ $plabel }}</td>
                                        <td class="w-4 align-top">:</td>
                                        <td class="py-0.5 align-top">{{ $pval ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Status Class (otomatis dari sertifikat kapal) -->
                <div class="{{ $autoZone }}">
                    <span class="{{ $autoTag }}" style="{{ $sans }}">Otomatis dari master data kapal</span>
                    <p class="font-bold">STATUS CLASS</p>
                    <div class="mt-1 overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-400 dark:border-gray-500">
                            <thead>
                                <tr class="bg-[#D9E2F3] dark:bg-blue-900/30">
                                    <th class="border border-gray-400 dark:border-gray-500 px-2 py-1 text-center font-bold">CERTIFICATE TYPE</th>
                                    <th class="w-24 border border-gray-400 dark:border-gray-500 px-2 py-1 text-center font-bold">LAST</th>
                                    <th class="w-24 border border-gray-400 dark:border-gray-500 px-2 py-1 text-center font-bold">NEXT 1</th>
                                    <th class="w-24 border border-gray-400 dark:border-gray-500 px-2 py-1 text-center font-bold">NEXT 2</th>
                                    <th class="w-24 border border-gray-400 dark:border-gray-500 px-2 py-1 text-center font-bold">POSTPONE</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ship?->certificates ?? [] as $cert)
                                    <tr>
                                        <td class="border border-gray-400 dark:border-gray-500 px-2 py-1">{{ $cert->certificate_type }}</td>
                                        <td class="border border-gray-400 dark:border-gray-500 px-2 py-1 text-center">{{ $cert->last_date?->format('d/m/Y') }}</td>
                                        <td class="border border-gray-400 dark:border-gray-500 px-2 py-1 text-center">{{ $cert->next_1_date?->format('d/m/Y') }}</td>
                                        <td class="border border-gray-400 dark:border-gray-500 px-2 py-1 text-center">{{ $cert->next_2_date?->format('d/m/Y') }}</td>
                                        <td class="border border-gray-400 dark:border-gray-500 px-2 py-1 text-center">{{ $cert->postpone_date?->format('d/m/Y') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="border border-gray-400 dark:border-gray-500 px-2 py-3 text-center text-gray-400 dark:text-gray-500">
                                            Belum ada data sertifikat status class
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="mt-8 font-bold">CLASS MEMORANDA</p>
                <p class="mt-1">Memoranda</p>
                <x-input-label for="memoranda" value="Class Memoranda" class="sr-only" />
                <textarea wire:model="sectionContent.memoranda" id="memoranda" rows="3"
                    x-data="{{ $fitJs }}" x-init="$nextTick(() => fit())" x-on:input="fit()" x-on:report-tab-changed.window="$nextTick(() => fit())"
                    placeholder="Isi N/A bila tidak ada memoranda"
                    class="{{ $paperTextarea }} mt-1"></textarea>
                <x-input-error :messages="$errors->get('sectionContent.memoranda')" class="mt-1" />
            </div>

            @if($ship)
                @can('update', $ship)
                    <div class="mt-4 flex justify-end">
                        <x-loading-button type="button" wire:click="editShip" target="editShip" variant="secondary" size="lg"
                            loadingText="Memuat..." icon="edit" class="w-full sm:w-auto">
                            Edit Data Kapal
                        </x-loading-button>
                    </div>
                @endcan
            @endif
        </div>

        <!-- ================= Tab: BAB III — Hasil Pemeriksaan (read-only) ================= -->
        <div x-show="tab === 'bab3'" x-cloak x-transition.opacity.duration.100ms wire:key="panel-bab3" class="{{ $canvas }}">
            <div class="mb-4 rounded-lg border border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20 px-4 py-3 text-sm text-blue-800 dark:text-blue-300">
                Bagian ini hanya pratinjau — konten BAB III digenerate otomatis dari data penilaian survey. Gunakan tombol "Edit Data Survey" di bawah untuk mengubah nilai.
            </div>

            <div class="{{ $sheet }}" style="{{ $times }}">
                <div class="lg:ml-4 lg:-mr-4">
                <h3 class="text-center text-lg font-bold tracking-wide">BAB III. PEMERIKSAAN KONDISI KAPAL</h3>

                @forelse($bab3Categories as $catIndex => $cat)
                    @php
                        $catAvg = $this->categoryAvg($cat);
                        $groups = $cat->subCategories->flatMap(fn ($sc) => $sc->itemGroups);
                        $categorySlotCount = max(2, (int) $groups->map(function ($group) {
                            if ($group->items->every(fn ($item) => $item->item_type === \App\Enums\SurveyItemType::Inventory)) {
                                return 2;
                            }

                            return $group->items
                                ->flatMap(fn ($item) => $item->score_labels ?? [])
                                ->unique()
                                ->count();
                        })->max());
                        $scoreWidth = 23 / $categorySlotCount;
                        $standardLabels = ['C' => 'Coating', 'V' => 'Visual', 'F' => 'Function', 'M' => 'Maintenance'];
                        $usedLabels = $groups->flatMap(fn ($group) => $group->items)
                            ->flatMap(fn ($item) => $item->score_labels ?? [])
                            ->unique();
                        $legend = collect($standardLabels)
                            ->filter(fn ($description, $label) => $usedLabels->contains($label))
                            ->map(fn ($description, $label) => $label.'= '.$description)
                            ->implode('    ');
                    @endphp
                    <section class="mt-8 first:mt-6" wire:key="bab3-cat-{{ $cat->id }}">
                        <h4 class="mb-1 font-bold uppercase">{{ $cat->label }}</h4>
                        @if($legend !== '')
                            <p class="mb-2 text-center font-bold">{{ $legend }}</p>
                        @endif

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[700px] table-fixed border-collapse border border-black dark:border-gray-400 text-[13px] leading-5">
                                <colgroup>
                                    <col style="width: 7%">
                                    <col style="width: 9%">
                                    <col style="width: 4%">
                                    <col style="width: 44%">
                                    @foreach(range(1, $categorySlotCount) as $slot)
                                        <col style="width: {{ $scoreWidth }}%">
                                    @endforeach
                                    <col style="width: 13%">
                                </colgroup>
                                <thead>
                                    <tr class="bg-[#4472C4] text-white dark:bg-blue-700">
                                        <th class="border border-black dark:border-gray-400 px-1.5 py-1 text-center">No.</th>
                                        <th colspan="{{ 3 + $categorySlotCount }}" class="border border-black dark:border-gray-400 px-2 py-1 text-center">Item</th>
                                        <th class="border border-black dark:border-gray-400 px-1.5 py-1 text-center">Overall CAP Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="font-bold">
                                        <td class="border border-black dark:border-gray-400 px-1.5 py-1 text-center">{{ to_roman($catIndex + 1) }}</td>
                                        <td colspan="{{ 3 + $categorySlotCount }}" class="border border-black dark:border-gray-400 px-2 py-1 uppercase">{{ $cat->label }} Overall CAP Rating</td>
                                        <td class="border border-black dark:border-gray-400 px-1.5 py-1 text-center">{{ $catAvg !== null ? number_format($catAvg, 2) : '-' }}</td>
                                    </tr>

                                    @foreach($cat->subCategories as $scIndex => $sc)
                                        @php $scAvg = $this->subCategoryAvg($sc); @endphp
                                        <tr class="font-bold" wire:key="bab3-sc-{{ $sc->id }}">
                                            <td class="border border-black dark:border-gray-400 px-1.5 py-1 text-center">{{ $scIndex + 1 }}</td>
                                            <td colspan="{{ 3 + $categorySlotCount }}" class="border border-black dark:border-gray-400 px-2 py-1">{{ $sc->name }}</td>
                                            <td class="border border-black dark:border-gray-400 px-1.5 py-1 text-center">{{ $scAvg !== null ? number_format($scAvg, 2) : '-' }}</td>
                                        </tr>

                                        @foreach($sc->itemGroups as $igIndex => $ig)
                                            @php
                                                $igAvg = $this->itemGroupAvg($ig);
                                                $isInventory = $ig->items->every(fn ($item) => $item->item_type === \App\Enums\SurveyItemType::Inventory);
                                                $groupLabels = $isInventory
                                                    ? collect(['Qty', 'Specification'])
                                                    : $ig->items->flatMap(fn ($item) => $item->score_labels ?? [])->unique()->values();
                                                if ($groupLabels->isEmpty()) {
                                                    $groupLabels = collect(['C', 'V']);
                                                }
                                                $slotLabels = $groupLabels->pad($categorySlotCount, null);
                                                $notes = $groupNotes->get($ig->id);
                                            @endphp
                                            <tr class="font-bold" wire:key="bab3-ig-{{ $ig->id }}">
                                                <td class="border border-black dark:border-gray-400"></td>
                                                <td class="border border-black dark:border-gray-400 px-1 py-1 text-center">{{ $scIndex + 1 }}.{{ $igIndex + 1 }}</td>
                                                <td class="border border-black dark:border-gray-400"></td>
                                                <td colspan="{{ 1 + $categorySlotCount }}" class="border border-black dark:border-gray-400 px-2 py-1 uppercase">{{ $ig->name }}</td>
                                                <td class="border border-black dark:border-gray-400 px-1.5 py-1 text-center">{{ $igAvg !== null ? number_format($igAvg, 2) : '-' }}</td>
                                            </tr>

                                            <tr class="bg-gray-50 dark:bg-gray-700/30 font-bold">
                                                <td colspan="4" class="border border-black dark:border-gray-400"></td>
                                                @foreach($slotLabels as $slotLabel)
                                                    <td class="border border-black dark:border-gray-400 px-1 py-1 text-center">{{ $slotLabel }}</td>
                                                @endforeach
                                                <td class="border border-black dark:border-gray-400 px-1 py-1 text-center">{{ $isInventory ? '' : 'Avg' }}</td>
                                            </tr>

                                            @foreach($ig->items as $itemIndex => $item)
                                                @php $response = $responses->get($item->id); @endphp
                                                <tr wire:key="bab3-item-{{ $item->id }}">
                                                    <td colspan="2" class="border border-black dark:border-gray-400"></td>
                                                    <td class="border border-black dark:border-gray-400 px-1 py-1 text-center align-top">{{ to_letter($itemIndex + 1) }}</td>
                                                    <td class="border border-black dark:border-gray-400 px-2 py-1 align-top">
                                                        {{ $item->name }}
                                                        @if($item->has_date_fields && $response && ($response->date_issued || $response->date_expired))
                                                            <span class="block text-[11px] text-gray-500 dark:text-gray-400">
                                                                Issued: {{ $response->date_issued?->format('d/m/Y') ?? '-' }} &ndash; Expired: {{ $response->date_expired?->format('d/m/Y') ?? '-' }}
                                                            </span>
                                                        @endif
                                                    </td>
                                                    @foreach($slotLabels as $slotIndex => $slotLabel)
                                                        @php
                                                            $score = $slotLabel ? ($response?->scores[$slotLabel] ?? null) : null;
                                                            $slotValue = $isInventory
                                                                ? ($slotIndex === 0 ? ($response?->qty ?? '-') : ($slotIndex === 1 ? ($response?->specification ?? '-') : ''))
                                                                : ($slotLabel ? ($score !== null && $score !== '' ? $score : '-') : '');
                                                        @endphp
                                                        <td class="border border-black dark:border-gray-400 px-1 py-1 text-center align-top">{{ $slotValue }}</td>
                                                    @endforeach
                                                    <td class="border border-black dark:border-gray-400 px-1 py-1 text-center align-top">
                                                        {{ $response?->avg_score !== null ? number_format((float) $response->avg_score, 2) : '-' }}
                                                    </td>
                                                </tr>
                                            @endforeach

                                            <tr>
                                                <td class="border border-black dark:border-gray-400"></td>
                                                <td class="border border-black dark:border-gray-400 px-1 py-1 font-bold">Note:</td>
                                                <td colspan="{{ 3 + $categorySlotCount }}" class="border border-black dark:border-gray-400"></td>
                                            </tr>
                                            @forelse($notes ?? [] as $note)
                                                <tr wire:key="bab3-note-{{ $note->id }}">
                                                    <td class="border border-black dark:border-gray-400"></td>
                                                    <td class="border border-black dark:border-gray-400 px-1 py-1 text-center">-</td>
                                                    <td colspan="{{ 3 + $categorySlotCount }}" class="border border-black dark:border-gray-400 px-2 py-1">{{ $note->note }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td class="border border-black dark:border-gray-400"></td>
                                                    <td class="border border-black dark:border-gray-400 px-1 py-1 text-center">-</td>
                                                    <td colspan="{{ 3 + $categorySlotCount }}" class="border border-black dark:border-gray-400"></td>
                                                </tr>
                                            @endforelse
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @empty
                    <div class="py-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" style="{{ $sans }}">Survey belum memiliki struktur penilaian.</p>
                    </div>
                @endforelse
                </div>
            </div>

            @can('surveys_update', $survey)
                <div class="mt-4 flex justify-end">
                    <x-loading-button type="button" wire:click="editSurvey" target="editSurvey" variant="secondary" size="lg"
                        loadingText="Memuat..." icon="edit" class="w-full sm:w-auto">
                        Edit Data Survey
                    </x-loading-button>
                </div>
            @endcan
        </div>

        <!-- ================= Tab: BAB IV — Saran ================= -->
        <div x-show="tab === 'bab4'" x-cloak x-transition.opacity.duration.100ms wire:key="panel-bab4" class="{{ $canvas }}">
            <div class="{{ $sheet }}" style="{{ $times }}">
                <h3 class="text-center text-lg font-bold tracking-wide">BAB IV. SARAN</h3>
                <p class="mt-6">Adapun saran dari hasil pemeriksaan kondisi kapal yaitu sebagai berikut:</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500" style="{{ $sans }}">
                    Satu poin per baris; awali dengan "- " agar dirender sebagai bullet di Word.
                </p>

                @foreach($categories as $cat)
                    <div class="mt-6" wire:key="saran-{{ $cat['id'] }}">
                        <p class="font-bold">{{ $cat['label'] }}:</p>
                        <x-input-label for="saran_{{ $cat['id'] }}" value="Saran {{ $cat['label'] }}" class="sr-only" />
                        <textarea wire:model="sectionContent.saran_{{ $cat['id'] }}" id="saran_{{ $cat['id'] }}" rows="3"
                            x-data="{{ $fitJs }}" x-init="$nextTick(() => fit())" x-on:input="fit()" x-on:report-tab-changed.window="$nextTick(() => fit())"
                            placeholder="Saran untuk {{ $cat['label'] }}"
                            class="{{ $paperTextarea }} mt-1"></textarea>
                        <x-input-error :messages="$errors->get('sectionContent.saran_'.$cat['id'])" class="mt-1" />
                    </div>
                @endforeach
            </div>
        </div>

        <x-input-error :messages="$errors->get('documentationPhoto')" class="mt-2" />

        @if($showCropModal && $documentationPhoto)
            <div class="fixed inset-0 z-[60] overflow-y-auto" wire:key="modal-crop-documentation">
                <div class="flex min-h-screen items-center justify-center px-4 py-6">
                    <div class="fixed inset-0 bg-gray-900/80" wire:click="cancelDocumentationCrop"></div>
                    <div class="relative z-10 w-full max-w-3xl rounded-xl bg-white shadow-2xl dark:bg-gray-800"
                        x-data="{
                            cropper: null,
                            init() {
                                this.$nextTick(() => {
                                    this.cropper = new window.Cropper(this.$refs.cropImage, {
                                        aspectRatio: 3 / 2,
                                        viewMode: 1,
                                        autoCropArea: 1,
                                        responsive: true,
                                        background: false,
                                    })
                                })
                            },
                            save() {
                                const data = this.cropper.getData(true)
                                $wire.set('cropData', data).then(() => $wire.saveDocumentation())
                            }
                        }">
                        <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700 sm:px-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Atur Dokumentasi</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Geser dan perbesar gambar. Area crop dikunci landscape 3:2 agar konsisten di dokumen Word.</p>
                        </div>
                        <div class="bg-gray-100 p-4 dark:bg-gray-900 sm:p-6">
                            <div class="mx-auto max-h-[60vh] overflow-hidden rounded-lg bg-black">
                                <img x-ref="cropImage" src="{{ $documentationPhoto->temporaryUrl() }}" alt="Crop dokumentasi" class="block max-h-[60vh] w-full object-contain">
                            </div>
                        </div>
                        <div class="flex flex-col-reverse gap-3 border-t border-gray-200 px-4 py-4 dark:border-gray-700 sm:flex-row sm:justify-end sm:px-6">
                            <x-cancel-button type="button" wire:click="cancelDocumentationCrop" target="cancelDocumentationCrop" label="Batal" class="w-full sm:w-auto" />
                            <x-loading-button type="button" x-on:click="save" target="saveDocumentation" variant="primary" size="lg"
                                loadingText="Mengunggah..." icon="check" class="w-full sm:w-auto">
                                Gunakan Gambar
                            </x-loading-button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <x-delete-modal
            :show="$showDeleteDocumentationModal"
            wire:model="showDeleteDocumentationModal"
            title="Hapus Dokumentasi"
            message="Apakah Anda yakin ingin menghapus foto dokumentasi ini?"
            confirmMethod="deleteDocumentation"
        />

        <!-- Sticky Action Bar -->
        <div class="sticky bottom-3 sm:bottom-4 z-10 mt-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-between">
                <x-cancel-button wire:click="back" target="back" label="Kembali" class="w-full sm:w-auto" />
                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    @if($report->file_status === \App\Enums\FileStatus::Completed)
                        <x-loading-button type="button" wire:click="download" target="download" variant="success" size="lg"
                            loadingText="Mengunduh..." icon="download" class="w-full sm:w-auto">
                            Download DOCX
                        </x-loading-button>
                    @endif
                    <x-loading-button type="submit" target="save" variant="secondary" size="lg"
                        loadingText="Menyimpan..." class="w-full sm:w-auto">
                        Simpan
                    </x-loading-button>
                    @can('survey_reports_generate')
                        <x-loading-button type="button" wire:click="generate" target="generate" variant="primary" size="lg"
                            loadingText="Memproses..." class="w-full sm:w-auto"
                            :disabled="$report->file_status === \App\Enums\FileStatus::Processing">
                            {{ $report->file_status === \App\Enums\FileStatus::Completed ? 'Regenerate DOCX' : 'Generate DOCX' }}
                        </x-loading-button>
                    @endcan
                </div>
            </div>
        </div>
    </form>
</div>
