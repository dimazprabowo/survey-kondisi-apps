<div
    x-data="{
        tab: 'info',
        switching: null,
        bab3Cat: 'all',
        bab3Switching: null,
        pickerScrollY: 0,
        pickerCategoryId: null,
        uploadingCategoryId: null,
        storageKey: 'survey-report-tab-{{ $report->id }}',
        bab3Key: 'survey-report-bab3-{{ $report->id }}',
        bab3CatIds: '{{ $bab3Categories->pluck('id')->implode(',') }}'.split(',').filter(Boolean),
        validTabs: ['info', 'exec', 'toc', 'bab1', 'bab2', 'bab3', 'bab4'],
        init() {
            const hashTab = window.location.hash.replace('#', '')
            const savedTab = sessionStorage.getItem(this.storageKey)
            this.tab = this.validTabs.includes(hashTab) ? hashTab : (this.validTabs.includes(savedTab) ? savedTab : 'info')
            const savedBab3 = sessionStorage.getItem(this.bab3Key)
            if (savedBab3 === 'all' || this.bab3CatIds.includes(savedBab3)) {
                this.bab3Cat = savedBab3
            }
            this.$wire.set('previewCategoryId', this.bab3Cat === 'all' ? null : this.bab3Cat, false)
            this.$watch('tab', value => {
                sessionStorage.setItem(this.storageKey, value)
                history.replaceState(null, '', `${window.location.pathname}${window.location.search}#${value}`)
            })
            this.$watch('bab3Cat', value => {
                sessionStorage.setItem(this.bab3Key, value)
                this.$wire.set('previewCategoryId', value === 'all' ? null : value, false)
            })
            this.$nextTick(() => {
                window.dispatchEvent(new CustomEvent('report-tab-changed'))
                this.scrollActiveTab(false)
                this.scrollBab3Tab(false)
            })
        },
        scrollActiveTab(smooth = true) {
            const container = this.$refs.tabScroller
            const active = container?.querySelector('[role=tab][aria-selected=true]')
            if (container && active) {
                container.scrollTo({
                    left: active.offsetLeft - (container.clientWidth - active.clientWidth) / 2,
                    behavior: smooth ? 'smooth' : 'auto'
                })
            }
        },
        scrollBab3Tab(smooth = true) {
            const container = this.$refs.bab3TabScroller
            const active = container?.querySelector('[aria-selected=true]')
            if (container && active) {
                container.scrollTo({
                    left: active.offsetLeft - (container.clientWidth - active.clientWidth) / 2,
                    behavior: smooth ? 'smooth' : 'auto'
                })
            }
        },
        switchTab(tab) {
            if (this.tab === tab || this.switching) return
            this.switching = tab
            setTimeout(() => {
                this.tab = tab
                this.switching = null
                this.$nextTick(() => {
                    window.dispatchEvent(new CustomEvent('report-tab-changed'))
                    this.scrollActiveTab()
                    this.scrollBab3Tab(false)
                })
            }, 120)
        },
        switchBab3Cat(id) {
            if (this.bab3Cat === id || this.bab3Switching) return
            this.bab3Switching = id
            setTimeout(() => {
                this.bab3Cat = id
                this.bab3Switching = null
                this.$nextTick(() => this.scrollBab3Tab())
            }, 120)
        },
        openDocumentationPicker(categoryId) {
            this.pickerScrollY = window.scrollY
            this.pickerCategoryId = categoryId
            this.uploadingCategoryId = categoryId
            this.$wire.set('documentationCategoryId', categoryId, false)
            this.$refs.documentationInput.value = ''
            this.$refs.documentationInput.click()
            requestAnimationFrame(() => window.scrollTo(0, this.pickerScrollY))
            window.addEventListener('focus', () => {
                requestAnimationFrame(() => window.scrollTo(0, this.pickerScrollY))
                setTimeout(() => {
                    if (! this.$refs.documentationInput.files.length && this.uploadingCategoryId === categoryId) {
                        this.uploadingCategoryId = null
                        this.pickerCategoryId = null
                    }
                }, 400)
            }, { once: true })
        }
    }"
    x-on:documentation-photo-ready.window="uploadingCategoryId = null; $nextTick(() => window.scrollTo(0, pickerScrollY))"
    x-on:documentation-photo-invalid.window="uploadingCategoryId = null"
>
    @php
        // Gaya "paper mode" — lembar meniru halaman Word; input borderless
        // dengan garis putus-putus sebagai affordance area yang bisa diisi.
        $ship = $survey->ship;
        $canvas = 'rounded-xl bg-gray-200/70 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800 p-2 sm:p-5 lg:p-8';
        $sheet = 'mx-auto w-full max-w-4xl rounded-sm bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black/5 dark:ring-white/10 px-5 py-8 sm:px-10 sm:py-10 lg:px-16 lg:py-14 text-[15px] leading-7 text-gray-900 dark:text-gray-100';
        $times = "font-family:Garamond,'Times New Roman',serif";
        $sans = 'font-family:ui-sans-serif,system-ui,sans-serif';
        $paperInput = 'bg-transparent border-0 border-b border-dashed border-gray-300 dark:border-gray-600 rounded-none px-1 py-0.5 text-[15px] text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-0';
        $paperTextarea = 'w-full bg-transparent border border-dashed border-transparent rounded-md px-2 py-1.5 text-[15px] leading-7 text-justify text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 hover:border-gray-300 dark:hover:border-gray-600 focus:border-blue-400 dark:focus:border-blue-500 focus:ring-0 resize-none overflow-hidden min-h-40';
        $paperTextareaCell = 'w-full bg-transparent border border-dashed border-transparent rounded px-1 py-0.5 text-[13px] leading-6 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 hover:border-gray-300 dark:hover:border-gray-600 focus:border-blue-400 focus:ring-0 resize-none overflow-hidden min-h-20';
        $autoZone = 'relative mt-6 rounded-md border border-dashed border-gray-300 dark:border-gray-600 p-3 pt-5 sm:p-5 sm:pt-6';
        $autoTag = 'absolute -top-2.5 left-3 bg-white dark:bg-gray-800 px-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500';
    @endphp

    <input x-ref="documentationInput" wire:model="documentationPhoto" type="file" accept="image/jpeg,image/png,image/webp"
        x-on:livewire-upload-start="uploadingCategoryId = pickerCategoryId; pickerCategoryId = null"
        x-on:livewire-upload-error="uploadingCategoryId = null"
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
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Menyiapkan dokumen Word</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Data, grafik, tabel, dan dokumentasi sedang disusun. Anda tetap dapat berpindah tab.</p>
                </div>
                <span class="shrink-0 self-center text-xs font-medium text-blue-600 dark:text-blue-400">Berjalan di latar belakang</span>
            </div>
            <div class="h-1 overflow-hidden bg-blue-100 dark:bg-blue-950">
                <div class="progress-indeterminate h-full w-1/3 rounded-full bg-blue-500"></div>
            </div>
        </div>
    @endif

    <form wire:submit="save">
        <!-- Tab Bar (scrollable di mobile) -->
        <div class="sticky top-7 sm:top-5 z-30 mb-3">
            <div x-ref="tabScroller" class="navbar-scroll overflow-x-auto px-1">
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
                        x-autogrow
                        placeholder="Judul laporan, contoh: JASA KONSULTAN INDEPENDENT SURVEY KONDISI PT. ASDP INDONESIA FERRY - 2026"
                        class="{{ $paperTextarea }} mt-6 text-center text-base sm:text-lg font-bold uppercase"></textarea>
                    <x-input-error :messages="$errors->get('report_title')" class="mt-1" />
                </div>
            </div>

            <!-- Sheet 2: Lembar Pengesahan -->
            <div class="{{ $sheet }} mt-4 sm:mt-6 leading-tight" style="{{ $times }}">
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
                                <td class="w-44 pr-4 py-0.5 align-middle font-medium">{{ $plabel }}</td>
                                <td class="w-4 align-middle">:</td>
                                <td class="py-0.5 align-middle uppercase">{{ $pval ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-14 grid grid-cols-1 sm:grid-cols-[5fr_7fr] gap-x-6 gap-y-12 text-center">
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
            <div class="{{ $sheet }} leading-normal" style="{{ $times }}">
                <h3 class="text-center text-lg font-bold tracking-wide">EXECUTIVE SUMMARY</h3>
                <x-input-label for="executive_summary" value="Narasi Executive Summary" class="sr-only" />
                <textarea wire:model="sectionContent.executive_summary" id="executive_summary" rows="6"
                    x-autogrow
                    placeholder="Ringkasan pelaksanaan survey dan metodologi CAP"
                    class="{{ $paperTextarea }} mt-6 !text-base"></textarea>
                <x-input-error :messages="$errors->get('sectionContent.executive_summary')" class="mt-1" />
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Istilah <em>Condition Assessment Program</em> otomatis dicetak miring pada dokumen.</p>

                <!-- Preview blok otomatis: daftar kategori + CAP breakdown + benchmark -->
                <div class="{{ $autoZone }}">
                    <span class="{{ $autoTag }}" style="{{ $sans }}">Otomatis dari data survey</span>
                    @php $catHalf = (int) ceil(max($bab3Categories->count(), 1) / 2); @endphp
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8">
                        <div>
                            @foreach($bab3Categories->slice(0, $catHalf) as $cat)
                                <p class="text-base"><span class="inline-block w-4">&middot;</span>{{ $cat->label }}</p>
                            @endforeach
                        </div>
                        <div>
                            @foreach($bab3Categories->slice($catHalf) as $cat)
                                <p class="text-base"><span class="inline-block w-4">&middot;</span>{{ $cat->label }}</p>
                            @endforeach
                        </div>
                    </div>

                    <p class="mt-8 pt-6 border-t border-dashed border-gray-300 dark:border-gray-600 text-justify text-base">
                        Tabel berikut menyajikan rincian <span class="font-bold">Overall CAP Rating</span>
                        hasil survei kondisi kapal
                        {{ $ship?->name ?? '-' }}, yang diperoleh dari rata-rata penilaian pada komponen
                        pemeriksaan utama.
                        @php $overallCap = $this->overallAvg($bab3Categories); @endphp
                        @if($overallCap !== null)
                            Berdasarkan hasil penilaian, kapal ini memperoleh
                            <span class="font-bold">Overall CAP Rating sebesar {{ number_format($overallCap, 2) }}</span>.
                            Adapun rincian penilaian sebagai berikut:
                        @endif
                    </p>

                    <div class="mt-2 overflow-hidden border border-gray-500 dark:border-gray-400 text-sm">
                        <div class="bg-[#4472C4] px-3 py-3 flex items-center justify-center text-center font-bold text-white">Overall CAP Rating Breakdown</div>
                        <div class="grid grid-cols-[1fr_auto_auto] items-center gap-x-4 px-3 py-3 font-bold">
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
                                        {{-- Spasi ekstra antar kategori agar tidak mepet sub-kategori sebelumnya --}}
                                        <div class="{{ $i > 0 ? 'mt-2 ' : '' }}mb-4 break-inside-avoid">
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
                                        <td class="border border-gray-400 dark:border-gray-500 px-2 py-1 text-center align-middle">{{ $i + 1 }}</td>
                                        <td class="border border-gray-400 dark:border-gray-500 px-2 py-1 align-middle">{{ $cat->label }}</td>
                                        <td class="border border-gray-400 dark:border-gray-500 px-1 py-1">
                                            <x-input-label for="finding_{{ $cat->id }}" value="Keterangan temuan {{ $cat->label }}" class="sr-only" />
                                            <textarea wire:model="sectionContent.finding_{{ $cat->id }}" id="finding_{{ $cat->id }}" rows="2"
                                                x-autogrow
                                                placeholder="Keterangan temuan — satu poin per baris"
                                                class="{{ $paperTextareaCell }}"></textarea>
                                        </td>
                                        @php $documentation = $documentations->get($cat->id); @endphp
                                        <td class="relative border border-gray-400 dark:border-gray-500 p-2 align-middle" wire:key="documentation-{{ $cat->id }}">
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
                                            <div x-show="uploadingCategoryId === {{ $cat->id }}" x-cloak
                                                class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-md bg-white/85 p-3 text-center dark:bg-gray-800/85"
                                                style="{{ $sans }}">
                                                <x-loading-spinner class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                                <span class="text-xs font-medium text-gray-600 dark:text-gray-300"
                                                    x-text="pickerCategoryId === {{ $cat->id }} ? 'Menunggu file dipilih...' : 'Mengunggah foto...'"></span>
                                            </div>
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
                <h3 class="text-center text-lg font-bold tracking-wide print:break-before-page">BAB I. UMUM/GENERAL</h3>

                <div class="{{ $autoZone }} mt-6">
                    <span class="{{ $autoTag }}" style="{{ $sans }}">Konten tetap dari master Word</span>
                    <p class="text-justify">Sesuai dengan Surat Perjanjian Nomor. Sperj.338/UM.301/ASDP-2025 tanggal 30 April 2025; dan Surat Penunjukan Pelaksana Pekerjaan Nomor.1051/SP3/PBJ/III/ASDP-2025 tanggal 11 Maret 2025 kepada PT. Biro Klasifikasi Indonesia (Persero) – SBU Marine Services Jakarta tentang Pekerjaan Jasa Konsultansi Assessment Kondisi Teknis Kapal PT. ASDP Indonesia Ferry (Persero).</p>
                    <p class="mt-3 text-justify">Tujuan dari dilaksanakan survey kondisi ini adalah melakukan kegiatan Survey kondisi mencakup aspek legalitas kapal, konstruksi kapal, sistim kapal, navigasi komunikasi kapal dan sistim keselamatan kapal. Hasil dari survey akan dijadikan menjadi satu laporan yang akan dijadikan sebagai pertimbangan teknis bagi pihak PT ASDP Indonesia Ferry.</p>
                    <p class="mt-3 text-justify">{{ $capReference['introduction'] }}</p>
                </div>

                <x-input-label for="cap_standards" value="Daftar Standar CAP" class="sr-only" />
                <textarea wire:model="sectionContent.cap_standards" id="cap_standards" rows="4"
                    x-autogrow
                    placeholder="Satu standar per baris, contoh: 1. BKI – CAP"
                    class="{{ $paperTextarea }} mt-4"></textarea>
                <x-input-error :messages="$errors->get('sectionContent.cap_standards')" class="mt-1" />

                <div class="{{ $autoZone }} mt-8">
                    <span class="{{ $autoTag }}" style="{{ $sans }}">Konten tetap dari master Word</span>
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
                            <thead class="bg-[#C9DAF8] text-gray-900">
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
                                @foreach($capReference['categories'] as $catIndex => $capCategory)
                                    @php
                                        // Baris per kategori = total baris semua kriteria (termasuk
                                        // extra_rows seperti "Dokumen Kapal" di master)
                                        $catRowCount = array_sum(array_map(
                                            fn ($c) => 1 + count($c['extra_rows'] ?? []),
                                            $capCategory['criteria']
                                        ));
                                    @endphp
                                    @foreach($capCategory['criteria'] as $criterionIndex => $criterion)
                                        @php $criterionRowSpan = 1 + count($criterion['extra_rows'] ?? []); @endphp
                                        <tr>
                                            @if($criterionIndex === 0)
                                                <td rowspan="{{ $catRowCount }}" class="border border-gray-500 bg-[#C9DAF8] px-2 py-1 text-center align-middle font-bold text-gray-900">
                                                    {{ $capCategory['code'] }}
                                                </td>
                                            @endif
                                            <td @if($criterionRowSpan > 1) rowspan="{{ $criterionRowSpan }}" @endif class="border border-gray-500 px-2 py-1 align-middle">{{ $criterion['name'] }}</td>
                                            @foreach($criterion['scores'] as $scoreDescription)
                                                <td class="border border-gray-500 px-2 py-1 align-middle">{{ $scoreDescription }}</td>
                                            @endforeach
                                        </tr>
                                        @foreach($criterion['extra_rows'] ?? [] as $extraRow)
                                            <tr>
                                                @foreach($extraRow as $extraCell)
                                                    @php
                                                        $extraText = is_array($extraCell) ? ($extraCell['text'] ?? '') : $extraCell;
                                                        $extraColspan = is_array($extraCell) ? (int) ($extraCell['colspan'] ?? 1) : 1;
                                                    @endphp
                                                    <td @if($extraColspan > 1) colspan="{{ $extraColspan }}" @endif class="border border-gray-500 px-2 py-1 align-middle">{{ $extraText }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    @endforeach
                                    {{-- Baris pemisah antar kategori — sesuai master: kolom CATEGORY biru, sisanya abu --}}
                                    @if($catIndex < count($capReference['categories']) - 1)
                                        <tr>
                                            <td class="border border-gray-500 bg-[#C9DAF8] p-0 text-[0] leading-none" style="height: 10px">&nbsp;</td>
                                            <td colspan="5" class="border border-gray-500 bg-[#D9D9D9] p-0 text-[0] leading-none" style="height: 10px">&nbsp;</td>
                                        </tr>
                                    @endif
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
                <h3 class="text-center text-lg font-bold tracking-wide print:break-before-page">BAB II. DATA KAPAL</h3>

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
                                        <td class="w-56 pr-4 py-0.5 align-middle">{{ $plabel }}</td>
                                        <td class="w-4 align-middle">:</td>
                                        <td class="py-0.5 align-middle">{{ $pval ?: '-' }}</td>
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
                                        <td colspan="5" class="border border-gray-400 dark:border-gray-500 px-2 py-3 text-center italic text-gray-500 dark:text-gray-400">
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
                    x-autogrow
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

            @if($bab3Categories->isNotEmpty())
                <div class="sticky top-[5.25rem] sm:top-[4.75rem] z-20 mb-4" style="{{ $sans }}">
                    <div x-ref="bab3TabScroller" class="navbar-scroll overflow-x-auto px-1">
                        <div class="inline-flex min-w-full gap-1 rounded-lg bg-gray-100/95 p-1 shadow-lg ring-1 ring-black/5 backdrop-blur dark:bg-gray-900/80 dark:ring-white/10">
                            <button type="button" wire:key="bab3tab-all"
                                @click="switchBab3Cat('all')"
                                :aria-selected="bab3Cat === 'all'"
                                :disabled="bab3Switching !== null"
                                :class="bab3Cat === 'all'
                                    ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-sm'
                                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                                class="flex shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-md px-3.5 py-1.5 text-xs font-medium transition-colors disabled:cursor-wait">
                                <span x-show="bab3Switching === 'all'" x-cloak class="h-3.5 w-3.5 shrink-0">
                                    <x-loading-spinner size="sm" class="h-3.5 w-3.5 [&>div]:h-3.5 [&>div]:w-3.5 [&_svg]:h-3.5 [&_svg]:w-3.5" />
                                </span>
                                <span>Semua</span>
                            </button>
                            @foreach($bab3Categories as $catIndex => $cat)
                                <button type="button" wire:key="bab3tab-{{ $cat->id }}"
                                    @click="switchBab3Cat('{{ $cat->id }}')"
                                    :aria-selected="bab3Cat === '{{ $cat->id }}'"
                                    :disabled="bab3Switching !== null"
                                    :class="bab3Cat === '{{ $cat->id }}'
                                        ? 'bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-400 shadow-sm'
                                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                                    class="flex shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-md px-3.5 py-1.5 text-xs font-medium transition-colors disabled:cursor-wait">
                                    <span x-show="bab3Switching === '{{ $cat->id }}'" x-cloak class="h-3.5 w-3.5 shrink-0">
                                        <x-loading-spinner size="sm" class="h-3.5 w-3.5 [&>div]:h-3.5 [&>div]:w-3.5 [&_svg]:h-3.5 [&_svg]:w-3.5" />
                                    </span>
                                    <span>{{ to_roman($catIndex + 1) }} &middot; {{ $cat->label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="{{ $sheet }}" style="{{ $times }}">
                <div class="lg:ml-4 lg:-mr-4">
                <h3 class="text-center text-lg font-bold tracking-wide print:break-before-page">BAB III. PEMERIKSAAN KONDISI KAPAL</h3>

                @forelse($bab3Categories as $catIndex => $cat)
                    @php
                        $catAvg = $this->categoryAvg($cat);
                        $groups = $cat->subCategories->flatMap(fn ($sc) => $sc->itemGroups);
                        $categorySlotCount = 3;
                        $standardLabels = ['C' => 'Coating', 'V' => 'Visual', 'F' => 'Function', 'M' => 'Maintenance'];
                        $usedLabels = $groups->flatMap(fn ($group) => $group->items)
                            ->flatMap(fn ($item) => $item->score_labels ?? [])
                            ->unique();
                        $hasLongLabels = $usedLabels->contains(fn ($label) => mb_strlen((string) $label) > 3);
                        $colWidths = $hasLongLabels
                            ? [7.75, 8.77, 3.76, 44.15, 8.50, 8.50, 9.50, 8.50]
                            : [7.75, 8.77, 3.76, 41.15, 6.38, 6.67, 13.26, 11.69];
                        $legend = collect($standardLabels)
                            ->filter(fn ($description, $label) => $usedLabels->contains($label))
                            ->map(fn ($description, $label) => $label.'= '.$description)
                            ->implode('    ');
                    @endphp
                    <section class="mt-8 first:mt-6" wire:key="bab3-cat-{{ $cat->id }}"
                        x-cloak
                        x-show="bab3Cat === 'all' || bab3Cat === '{{ $cat->id }}'">
                        <h4 class="mb-1 font-bold uppercase leading-normal">{{ $cat->label }}</h4>
                        @if($legend !== '')
                            <p class="mb-2 text-center font-bold leading-normal">{{ $legend }}</p>
                        @endif

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[700px] table-fixed border-collapse border border-black dark:border-gray-400 text-[16px] leading-6">
                                <colgroup>
                                    @foreach($colWidths as $colWidth)
                                        <col style="width: {{ $colWidth }}%">
                                    @endforeach
                                </colgroup>
                                <thead>
                                    <tr class="bg-[#4285F4] text-white dark:bg-blue-700">
                                        <th class="border-0 px-1.5 py-1 text-center whitespace-nowrap">No.</th>
                                        <th colspan="{{ 3 + $categorySlotCount }}" class="border-0 px-2 py-1 text-center whitespace-nowrap">Item</th>
                                        <th class="border-0 px-1.5 py-1 text-center">Overall CAP Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="font-bold">
                                        <td class="border-0 px-1.5 py-1 text-center whitespace-nowrap">{{ to_roman($catIndex + 1) }}</td>
                                        <td colspan="{{ 3 + $categorySlotCount }}" class="border-0 px-2 py-1 uppercase">{{ $cat->label }} Overall CAP Rating</td>
                                        <td class="border-0 px-1.5 py-1 text-center whitespace-nowrap">{{ $catAvg !== null ? number_format($catAvg, 2) : '-' }}</td>
                                    </tr>
                                    @foreach($cat->subCategories as $scIndex => $sc)
                                        @php $scAvg = $this->subCategoryAvg($sc); @endphp
                                        <tr class="bg-[#808080]" wire:key="bab3-sep-{{ $sc->id }}">
                                            <td colspan="2" class="h-5"></td>
                                            <td colspan="2" class="border-0"></td>
                                            <td colspan="2" class="border-0"></td>
                                            <td colspan="2" class="border-0"></td>
                                        </tr>
                                        <tr class="font-bold" wire:key="bab3-sc-{{ $sc->id }}">
                                            <td class="border-0 px-1.5 py-1 text-center whitespace-nowrap">{{ $scIndex + 1 }}</td>
                                            <td colspan="{{ 3 + $categorySlotCount }}" class="border-0 px-2 py-1">{{ $sc->name }}</td>
                                            <td class="border-0 px-1.5 py-1 text-center whitespace-nowrap">{{ $scAvg !== null ? number_format($scAvg, 2) : '-' }}</td>
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
                                                $groupLabels = $groupLabels->take($categorySlotCount)->values();
                                                $unusedSlots = $categorySlotCount - $groupLabels->count();
                                                $notes = collect($groupNotes->get($ig->id) ?? [])->values();
                                                while ($notes->count() < 2) {
                                                    $notes->push(null);
                                                }
                                            @endphp
                                            <tr class="font-bold" wire:key="bab3-ig-{{ $ig->id }}">
                                                <td class="border-0"></td>
                                                <td class="border-0 px-1 py-1 text-center whitespace-nowrap">{{ $scIndex + 1 }}.{{ $igIndex + 1 }}</td>
                                                <td class="border-0"></td>
                                                <td class="border-0 px-2 py-1 uppercase">{{ $ig->name }}</td>
                                                <td colspan="2" class="border-0"></td>
                                                <td class="border-0"></td>
                                                <td class="border-0 px-1.5 py-1 text-center whitespace-nowrap">{{ $igAvg !== null ? number_format($igAvg, 2) : '-' }}</td>
                                            </tr>

                                            <tr class="bg-gray-50 dark:bg-gray-700/30 font-bold">
                                                <td colspan="2" class="border-0"></td>
                                                <td colspan="2" class="border-0"></td>
                                                @if($isInventory)
                                                    <td class="border-0 px-1 py-1 text-center whitespace-nowrap">{{ $groupLabels[0] }}</td>
                                                    <td colspan="{{ $categorySlotCount }}" class="border-0 px-1 py-1 text-center whitespace-nowrap">{{ $groupLabels[1] ?? '' }}</td>
                                                @else
                                                    @foreach($groupLabels as $groupLabel)
                                                        <td class="border-0 px-1 py-1 text-center whitespace-nowrap">{{ $groupLabel }}</td>
                                                    @endforeach
                                                    <td class="border-0 px-1 py-1 text-center whitespace-nowrap">Avg</td>
                                                    @for($unused = 0; $unused < $unusedSlots; $unused++)
                                                        <td class="border-0"></td>
                                                    @endfor
                                                @endif
                                            </tr>

                                            @foreach($ig->items as $itemIndex => $item)
                                                @php $response = $responses->get($item->id); @endphp
                                                <tr wire:key="bab3-item-{{ $item->id }}">
                                                    <td colspan="2" class="border-0 align-top"></td>
                                                    <td class="border-0 px-1 py-1 text-center align-top whitespace-nowrap">{{ to_letter($itemIndex + 1) }}</td>
                                                    <td class="border-0 px-2 py-1 align-top">
                                                        {{ $item->name }}
                                                        @if($item->has_date_fields && $response && ($response->date_issued || $response->date_expired))
                                                            @if($response->date_issued)
                                                                <span class="block text-[13px] text-gray-500 dark:text-gray-400">
                                                                    Date issued : {{ $response->date_issued->format('d/m/Y') }}
                                                                </span>
                                                            @endif
                                                            @if($response->date_expired)
                                                                <span class="block text-[13px] text-gray-500 dark:text-gray-400">
                                                                    Exp : {{ $response->date_expired->format('d/m/Y') }}
                                                                </span>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    @if($isInventory)
                                                        <td class="border-0 px-1 py-1 text-center align-top whitespace-nowrap">{{ $response?->qty ?? '-' }}</td>
                                                        <td colspan="{{ $categorySlotCount }}" class="border-0 px-1 py-1 text-center align-top whitespace-nowrap">{{ $response?->specification ?? '-' }}</td>
                                                    @else
                                                        @foreach($groupLabels as $groupLabel)
                                                            @php $score = $response?->scores[$groupLabel] ?? null; @endphp
                                                            <td class="border-0 px-1 py-1 text-center align-top whitespace-nowrap">{{ $score !== null && $score !== '' ? $score : '-' }}</td>
                                                        @endforeach
                                                        <td class="border-0 px-1 py-1 text-center align-top whitespace-nowrap">
                                                            {{ $response?->avg_score !== null ? number_format((float) $response->avg_score, 2) : '-' }}
                                                        </td>
                                                        @for($unused = 0; $unused < $unusedSlots; $unused++)
                                                            <td class="border-0"></td>
                                                        @endfor
                                                    @endif
                                                </tr>
                                            @endforeach

                                            <tr>
                                                <td class="border-0"></td>
                                                <td class="border-0 px-1 py-1 font-bold">Note:</td>
                                                <td colspan="{{ 3 + $categorySlotCount }}" class="border-0"></td>
                                            </tr>
                                            @foreach($notes as $noteIndex => $note)
                                                <tr wire:key="bab3-note-{{ $ig->id }}-{{ $note?->id ?? 'empty-'.$noteIndex }}">
                                                    <td class="border-0"></td>
                                                    <td class="border-0 px-1 py-1 text-right whitespace-nowrap">-</td>
                                                    <td colspan="{{ 3 + $categorySlotCount }}" class="border-0 px-2 py-1">{{ $note?->note }}</td>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td colspan="2" class="border-0"></td>
                                                <td colspan="2" class="border-0"></td>
                                                <td class="border-0"></td>
                                                <td class="border-0"></td>
                                                <td class="border-0"></td>
                                                <td class="border-0"></td>
                                            </tr>
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
                <h3 class="text-center text-lg font-bold tracking-wide print:break-before-page">BAB IV. SARAN</h3>
                <p class="mt-6 text-base">Adapun saran dari hasil pemeriksaan kondisi kapal yaitu sebagai berikut:</p>
                <p class="mt-1 text-xs text-gray-400 dark:text-gray-500" style="{{ $sans }}">
                    Satu poin per baris; awali dengan "- " agar dirender sebagai bullet di Word.
                </p>

                @foreach($categories as $cat)
                    <div class="mt-6" wire:key="saran-{{ $cat['id'] }}">
                        <p class="font-bold text-base">{{ $loop->iteration }}. {{ $cat['label'] }}:</p>
                        <x-input-label for="saran_{{ $cat['id'] }}" value="Saran {{ $cat['label'] }}" class="sr-only" />
                        <textarea wire:model="sectionContent.saran_{{ $cat['id'] }}" id="saran_{{ $cat['id'] }}" rows="3"
                            x-autogrow
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
                            ready: false,
                            saving: false,
                            init() {
                                const img = this.$refs.cropImage
                                const start = () => {
                                    this.cropper = new window.Cropper(img, {
                                        aspectRatio: 3 / 2,
                                        viewMode: 1,
                                        autoCropArea: 1,
                                        responsive: true,
                                        background: false,
                                    })
                                    this.ready = true
                                }
                                this.$nextTick(() => {
                                    if (img.complete && img.naturalWidth) start()
                                    else img.addEventListener('load', start, { once: true })
                                })
                            },
                            save() {
                                if (this.saving || !this.cropper) return
                                this.saving = true
                                $wire.set('cropData', this.cropper.getData(true), false)
                                $wire.saveDocumentation()
                            }
                        }">
                        <div class="border-b border-gray-200 px-4 py-4 dark:border-gray-700 sm:px-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Atur Dokumentasi</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Geser dan perbesar gambar. Area crop dikunci landscape 3:2 agar konsisten di dokumen Word.</p>
                        </div>
                        <div class="relative bg-gray-100 p-4 dark:bg-gray-900 sm:p-6" x-bind:class="{ 'min-h-[40vh] sm:min-h-[50vh]': !ready }">
                            <div class="mx-auto max-h-[60vh] overflow-hidden rounded-lg bg-black">
                                <img x-ref="cropImage" src="{{ $documentationPhoto->temporaryUrl() }}" alt="Crop dokumentasi" class="block max-h-[60vh] w-full object-contain">
                            </div>
                            <div x-show="!ready" x-cloak
                                class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-2 bg-gray-100 dark:bg-gray-900">
                                <x-loading-spinner class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Memuat foto...</span>
                            </div>
                            <div x-show="ready && saving" x-cloak
                                class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-2 bg-gray-100/85 dark:bg-gray-900/85">
                                <x-loading-spinner class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Menyimpan foto...</span>
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
                            :loading="$report->file_status === \App\Enums\FileStatus::Processing"
                            :disabled="$report->file_status === \App\Enums\FileStatus::Processing">
                            {{ $report->file_status === \App\Enums\FileStatus::Completed ? 'Regenerate DOCX' : 'Generate DOCX' }}
                        </x-loading-button>
                    @endcan
                </div>
            </div>
        </div>
    </form>
</div>
