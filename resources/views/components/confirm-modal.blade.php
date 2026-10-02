@props([
    'name' => 'confirm-modal',
    'eventName' => 'confirm-modal',
    'title' => 'Konfirmasi',
    'message' => 'Apakah Anda yakin?',
    'confirmText' => 'Ya, Lanjutkan',
    'cancelText' => 'Batal',
    'type' => 'danger' // danger, warning, info
])

<div x-data="{
    show: false,
    title: '{{ $title }}',
    message: '{{ $message }}',
    confirmText: '{{ $confirmText }}',
    cancelText: '{{ $cancelText }}',
    type: '{{ $type }}',
    action: null,
    actionParams: null,
    confirmLoading: false,
    cancelLoading: false,

    showModal(data) {
        this.title = data.title || '{{ $title }}';
        this.message = data.message || '{{ $message }}';
        this.confirmText = data.confirmText || '{{ $confirmText }}';
        this.cancelText = data.cancelText || '{{ $cancelText }}';
        this.type = data.type || '{{ $type }}';
        this.action = data.action || null;
        this.actionParams = data.actionParams || null;
        this.confirmLoading = false;
        this.cancelLoading = false;
        this.show = true;
    },

    confirm() {
        if (this.confirmLoading) return;
        this.confirmLoading = true;
        if (this.action) {
            if (this.actionParams !== null) {
                const params = Array.isArray(this.actionParams) ? this.actionParams : [this.actionParams];
                $wire.call(this.action, ...params);
            } else {
                $wire.call(this.action);
            }
        }
        // Modal tetap terbuka dengan loading state.
        // Untuk logout: halaman redirect otomatis → modal hilang.
        // Untuk aksi non-redirect: component dispatch event '{eventName}-close' untuk tutup modal.
    },

    close() {
        this.show = false;
        this.confirmLoading = false;
        this.cancelLoading = false;
    },

    cancel() {
        if (this.cancelLoading) return;
        this.cancelLoading = true;
        setTimeout(() => {
            this.show = false;
            this.cancelLoading = false;
        }, 300);
    }
}"
x-on:{{ $eventName }}.window="showModal($event.detail)"
x-on:{{ $eventName }}-close.window="close()"
x-show="show"
x-cloak
class="fixed inset-0 z-50 overflow-y-auto"
style="display: none;">
    <!-- Backdrop -->
    <div x-show="show"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75 transition-opacity"
         @click="cancel()"></div>

    <!-- Modal -->
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div x-show="show"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="inline-block align-bottom w-full bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <!-- Icon -->
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10"
                         :class="{
                             'bg-red-100 dark:bg-red-900/20': type === 'danger',
                             'bg-yellow-100 dark:bg-yellow-900/20': type === 'warning',
                             'bg-blue-100 dark:bg-blue-900/20': type === 'info'
                         }">
                        <!-- Danger Icon -->
                        <svg x-show="type === 'danger'" class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <!-- Warning Icon -->
                        <svg x-show="type === 'warning'" class="h-6 w-6 text-yellow-600 dark:text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <!-- Info Icon -->
                        <svg x-show="type === 'info'" class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    
                    <!-- Content -->
                    <div class="mt-3 text-left sm:mt-0 sm:ml-4 flex-1">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" x-text="title"></h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400" x-text="message"></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                <button type="button"
                        @click="confirm()"
                        :disabled="confirmLoading"
                        class="w-full inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-base font-medium text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm transition-colors disabled:opacity-70 disabled:cursor-not-allowed"
                        :class="{
                            'bg-red-600 hover:bg-red-700 focus:ring-red-500': type === 'danger',
                            'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500': type === 'warning',
                            'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500': type === 'info'
                        }">
                    <svg x-show="confirmLoading" x-cloak class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!confirmLoading" x-text="confirmText"></span>
                    <span x-show="confirmLoading" x-cloak>Memproses...</span>
                </button>
                <button type="button"
                        @click="cancel()"
                        :disabled="cancelLoading"
                        class="mt-3 w-full inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors disabled:opacity-70 disabled:cursor-not-allowed">
                    <svg x-show="cancelLoading" x-cloak class="animate-spin h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!cancelLoading" x-text="cancelText"></span>
                    <span x-show="cancelLoading" x-cloak>Membatalkan...</span>
                </button>
            </div>
        </div>
    </div>
</div>
