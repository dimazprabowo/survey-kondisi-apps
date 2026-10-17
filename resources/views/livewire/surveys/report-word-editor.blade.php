<div>
    <div class="mb-4 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex-1 min-w-0">
            <h2 class="text-xl lg:text-2xl font-bold text-gray-900 dark:text-white">
                <span class="lg:hidden">Edit Word</span>
                <span class="hidden lg:block truncate" title="Edit Word — {{ $report?->report_title ?? 'Laporan Survey' }}">
                    Edit Word — {{ $report?->report_title ?? 'Laporan Survey' }}
                </span>
            </h2>
            <p class="lg:hidden mt-0.5 truncate text-sm font-medium text-gray-700 dark:text-gray-300"
                title="{{ $report?->report_title ?? 'Laporan Survey' }}">
                {{ $report?->report_title ?? 'Laporan Survey' }}
            </p>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ $report?->survey?->ship?->name ?? '-' }}
                @if($report?->edited_at)
                    &middot; Versi edit terakhir: {{ $report->edited_at->format('d/m/Y H:i') }}
                    @if($report->editor)
                        oleh {{ $report->editor->name }}
                    @endif
                @else
                    &middot; Perubahan disimpan sebagai versi edit terpisah — file hasil generate tetap utuh
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
            <x-loading-button type="button" wire:click="download" target="download"
                variant="success" size="md" loadingText="Mengunduh..." icon="download"
                class="flex-1 lg:flex-none">
                Unduh DOCX{{ $report?->edited_file_path ? ' (Versi Edit)' : '' }}
            </x-loading-button>
            <x-loading-button type="button" wire:click="back" target="back"
                variant="secondary" size="md" loadingText="Memuat..."
                class="flex-1 lg:flex-none">
                Kembali ke Laporan
            </x-loading-button>
        </div>
    </div>

    @if($unavailableReason)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg px-6 py-12 text-center">
            <svg class="h-12 w-12 mx-auto text-amber-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <p class="mt-3 text-sm font-medium text-amber-800 dark:text-amber-300">{{ $unavailableReason }}</p>
        </div>
    @else
        @if($statusNotice)
            <div class="mb-4 flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <span>{{ $statusNotice }}</span>
            </div>
        @endif

        <div id="onlyoffice-editor-shell" wire:ignore
            class="w-full bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
            style="height: calc(100dvh - 200px); min-height: 480px;">
            <div id="onlyoffice-editor" class="w-full h-full">
                <div id="onlyoffice-editor-loading" class="flex h-full items-center justify-center gap-2.5 text-sm text-gray-500 dark:text-gray-400">
                    <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Memuat editor Word...
                </div>
            </div>
        </div>

        <script>
            (function () {
                var apiJsUrl = @js($apiJsUrl);
                var editorConfig = @js($editorConfig);
                var apiState = 'idle'; // idle | loading | loaded | error
                var apiCallbacks = [];

                function editorEl() {
                    return document.getElementById('onlyoffice-editor');
                }

                function showLoadError() {
                    var el = editorEl();
                    if (! el) {
                        return;
                    }
                    el.innerHTML = '<div class="px-6 py-12 text-center text-sm text-red-600 dark:text-red-400">'
                        + 'Gagal memuat OnlyOffice Document Server. Periksa koneksi ke server dokumen, lalu muat ulang halaman.</div>';
                }

                // api.js diinject via JS agar deterministik: saat wire:navigate,
                // <script src> di body dimuat async sehingga DocsAPI sering belum
                // ada saat init jalan. Di sini init selalu menunggu onload.
                function ensureDocsApi(callback) {
                    if (typeof DocsAPI !== 'undefined') {
                        apiState = 'loaded';
                        callback();

                        return;
                    }
                    if (apiState === 'error') {
                        showLoadError();

                        return;
                    }
                    apiCallbacks.push(callback);
                    if (apiState === 'loading') {
                        return;
                    }
                    apiState = 'loading';
                    var tag = document.createElement('script');
                    tag.src = apiJsUrl;
                    tag.async = true;
                    tag.onload = function () {
                        apiState = 'loaded';
                        apiCallbacks.splice(0).forEach(function (cb) { cb(); });
                    };
                    tag.onerror = function () {
                        apiState = 'error';
                        apiCallbacks.splice(0);
                        showLoadError();
                    };
                    document.head.appendChild(tag);
                }

                function fitOnlyOfficeEditor() {
                    var shell = document.getElementById('onlyoffice-editor-shell');
                    if (! shell) {
                        return;
                    }
                    var top = shell.getBoundingClientRect().top;
                    shell.style.height = Math.max(480, Math.floor(window.innerHeight - top - 8)) + 'px';
                }

                function createOnlyOfficeEditor() {
                    var el = editorEl();
                    if (! el) {
                        return;
                    }
                    fitOnlyOfficeEditor();
                    if (window.__onlyOfficeEditor && typeof window.__onlyOfficeEditor.destroyEditor === 'function') {
                        window.__onlyOfficeEditor.destroyEditor();
                        window.__onlyOfficeEditor = null;
                    }
                    editorConfig.events = editorConfig.events || {};
                    editorConfig.events.onDocumentReady = fitOnlyOfficeEditor;
                    window.__onlyOfficeEditor = new DocsAPI.DocEditor('onlyoffice-editor', editorConfig);

                    // Re-fit setelah layout settle (font, iframe, sidebar) —
                    // pengukuran sekali sebelum DocEditor load sering terlalu dini.
                    requestAnimationFrame(fitOnlyOfficeEditor);
                    setTimeout(fitOnlyOfficeEditor, 300);
                    setTimeout(fitOnlyOfficeEditor, 1000);
                    if (document.fonts && document.fonts.ready) {
                        document.fonts.ready.then(fitOnlyOfficeEditor);
                    }
                }

                function initOnlyOfficeEditor() {
                    if (! editorEl()) {
                        return;
                    }
                    ensureDocsApi(createOnlyOfficeEditor);
                }

                // Hancurkan editor saat halaman ditinggalkan via navigate agar
                // sesi DS tidak tertinggal; livewire:navigated akan init ulang
                // bila halaman editor tampil kembali (termasuk restore cache).
                function destroyOnlyOfficeEditor() {
                    if (window.__onlyOfficeEditor && typeof window.__onlyOfficeEditor.destroyEditor === 'function') {
                        window.__onlyOfficeEditor.destroyEditor();
                        window.__onlyOfficeEditor = null;
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initOnlyOfficeEditor);
                } else {
                    initOnlyOfficeEditor();
                }
                document.addEventListener('livewire:navigated', initOnlyOfficeEditor);
                document.addEventListener('livewire:navigating', destroyOnlyOfficeEditor);
                window.addEventListener('load', fitOnlyOfficeEditor);
                window.addEventListener('resize', fitOnlyOfficeEditor);
            })();
        </script>
    @endif
</div>
