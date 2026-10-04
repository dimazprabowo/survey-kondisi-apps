<div>
    <!-- Breadcrumb -->
    <nav class="mb-6 flex" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2 text-sm">
            <li>
                <a href="{{ route('dashboard') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Dashboard</a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li>
                <a href="{{ route('master-data.ships') }}" wire:navigate class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">Kapal</a>
            </li>
            <li class="text-gray-400 dark:text-gray-600">/</li>
            <li class="text-gray-900 dark:text-white font-medium">{{ $editMode ? 'Edit' : 'Tambah' }}</li>
        </ol>
    </nav>

    <form wire:submit="save">
        <!-- Section: Data Utama -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">Data Utama</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="name" value="Nama Kapal" :required="true" />
                        <x-text-input wire:model="name" id="name" type="text" class="mt-1 block w-full" placeholder="Nama kapal" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="code" value="Kode Kapal" />
                        <x-text-input wire:model="code" id="code" type="text" class="mt-1 block w-full" placeholder="Kode kapal" />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="year_built" value="Tahun Pembuatan" />
                        <x-text-input wire:model="year_built" id="year_built" type="number" min="1900" max="{{ (int) now()->format('Y') }}" class="mt-1 block w-full" placeholder="Tahun pembuatan" />
                        <x-input-error :messages="$errors->get('year_built')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="imo_number" value="Nomor IMO" />
                        <x-text-input wire:model="imo_number" id="imo_number" type="text" class="mt-1 block w-full" placeholder="Nomor IMO" />
                        <x-input-error :messages="$errors->get('imo_number')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="ship_type" value="Jenis Kapal" />
                        <x-text-input wire:model="ship_type" id="ship_type" type="text" class="mt-1 block w-full" placeholder="Contoh: FERRY RO-RO" />
                        <x-input-error :messages="$errors->get('ship_type')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="flag" value="Bendera" />
                        <x-text-input wire:model="flag" id="flag" type="text" class="mt-1 block w-full" placeholder="Bendera kapal" />
                        <x-input-error :messages="$errors->get('flag')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="gross_tonnage" value="Gross Tonnage" />
                        <x-text-input wire:model="gross_tonnage" id="gross_tonnage" type="text" class="mt-1 block w-full" placeholder="Gross tonnage" />
                        <x-input-error :messages="$errors->get('gross_tonnage')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="net_tonnage" value="Net Tonnage" />
                        <x-text-input wire:model="net_tonnage" id="net_tonnage" type="text" class="mt-1 block w-full" placeholder="Net tonnage" />
                        <x-input-error :messages="$errors->get('net_tonnage')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="call_sign" value="Call Sign" />
                        <x-text-input wire:model="call_sign" id="call_sign" type="text" class="mt-1 block w-full" placeholder="Call sign" />
                        <x-input-error :messages="$errors->get('call_sign')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="owner" value="Pemilik" />
                        <x-text-input wire:model="owner" id="owner" type="text" class="mt-1 block w-full" placeholder="Pemilik kapal" />
                        <x-input-error :messages="$errors->get('owner')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="operator" value="Operator" />
                        <x-text-input wire:model="operator" id="operator" type="text" class="mt-1 block w-full" placeholder="Operator kapal" />
                        <x-input-error :messages="$errors->get('operator')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" :required="true" />
                        <x-searchable-select wire:model="status" :options="$this->statusOptions" placeholder="Pilih status" searchPlaceholder="Cari status..." />
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Dimensi & Particulars -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">Dimensi &amp; Particulars</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="loa" value="Length Overall (LOA)" />
                        <x-text-input wire:model="loa" id="loa" type="text" class="mt-1 block w-full" placeholder="Contoh: 43,35" />
                        <x-input-error :messages="$errors->get('loa')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="lpp" value="Length Between Perpendiculars (LPP)" />
                        <x-text-input wire:model="lpp" id="lpp" type="text" class="mt-1 block w-full" placeholder="Contoh: 38,50" />
                        <x-input-error :messages="$errors->get('lpp')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="breadth" value="Breadth (B)" />
                        <x-text-input wire:model="breadth" id="breadth" type="text" class="mt-1 block w-full" placeholder="Contoh: 12,00" />
                        <x-input-error :messages="$errors->get('breadth')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="depth" value="Tinggi (Depth)" />
                        <x-text-input wire:model="depth" id="depth" type="text" class="mt-1 block w-full" placeholder="Contoh: 03,00" />
                        <x-input-error :messages="$errors->get('depth')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="draft" value="Draft" />
                        <x-text-input wire:model="draft" id="draft" type="text" class="mt-1 block w-full" placeholder="Contoh: 02,00" />
                        <x-input-error :messages="$errors->get('draft')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="dwt" value="DWT" />
                        <x-text-input wire:model="dwt" id="dwt" type="text" class="mt-1 block w-full" placeholder="Deadweight tonnage" />
                        <x-input-error :messages="$errors->get('dwt')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="builder" value="Galangan Pembangun" />
                        <x-text-input wire:model="builder" id="builder" type="text" class="mt-1 block w-full" placeholder="Nama galangan" />
                        <x-input-error :messages="$errors->get('builder')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="port_of_registry" value="Pelabuhan Registrasi" />
                        <x-text-input wire:model="port_of_registry" id="port_of_registry" type="text" class="mt-1 block w-full" placeholder="Contoh: JAKARTA" />
                        <x-input-error :messages="$errors->get('port_of_registry')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="hull_material" value="Material Lambung" />
                        <x-text-input wire:model="hull_material" id="hull_material" type="text" class="mt-1 block w-full" placeholder="Contoh: STEEL" />
                        <x-input-error :messages="$errors->get('hull_material')" class="mt-2" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Kelas & Permesinan -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">Kelas &amp; Permesinan</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="class_name" value="Kelas" />
                        <x-text-input wire:model="class_name" id="class_name" type="text" class="mt-1 block w-full" placeholder="Contoh: BIRO KLASIFIKASI INDONESIA" />
                        <x-input-error :messages="$errors->get('class_name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="class_notations" value="Notasi Kelas" />
                        <x-text-input wire:model="class_notations" id="class_notations" type="text" class="mt-1 block w-full" placeholder="Notasi kelas" />
                        <x-input-error :messages="$errors->get('class_notations')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="main_engine" value="Main Engine" />
                        <x-text-input wire:model="main_engine" id="main_engine" type="text" class="mt-1 block w-full" placeholder="Merek/tipe main engine" />
                        <x-input-error :messages="$errors->get('main_engine')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="main_engine_power" value="Daya Main Engine" />
                        <x-text-input wire:model="main_engine_power" id="main_engine_power" type="text" class="mt-1 block w-full" placeholder="Contoh: 2 x 650 HP/1450 RPM" />
                        <x-input-error :messages="$errors->get('main_engine_power')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="aux_engine" value="Auxiliary Engine" />
                        <x-text-input wire:model="aux_engine" id="aux_engine" type="text" class="mt-1 block w-full" placeholder="Merek/tipe auxiliary engine" />
                        <x-input-error :messages="$errors->get('aux_engine')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="aux_engine_power" value="Daya Auxiliary Engine" />
                        <x-text-input wire:model="aux_engine_power" id="aux_engine_power" type="text" class="mt-1 block w-full" placeholder="Contoh: 2 x 150 BHP" />
                        <x-input-error :messages="$errors->get('aux_engine_power')" class="mt-2" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section: Status Class (repeater sertifikat) -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 pb-2 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Status Class</h3>
                    <x-loading-button type="button" wire:click="addCertificate" target="addCertificate" variant="secondary" size="sm" loadingText="Menambah..." icon="plus">
                        Tambah Sertifikat
                    </x-loading-button>
                </div>

                <div class="space-y-4">
                    @forelse($certificates as $index => $cert)
                        <div wire:key="cert-{{ $cert['row_key'] }}-{{ $index }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end p-4 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40">
                            <div class="sm:col-span-2 lg:col-span-2">
                                <x-input-label for="cert_type_{{ $index }}" value="Jenis Sertifikat" />
                                <x-text-input wire:model="certificates.{{ $index }}.certificate_type" id="cert_type_{{ $index }}" type="text" class="mt-1 block w-full" placeholder="Contoh: Special Survey" />
                                <x-input-error :messages="$errors->get('certificates.'.$index.'.certificate_type')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="cert_last_{{ $index }}" value="Last" />
                                <x-text-input wire:model="certificates.{{ $index }}.last_date" id="cert_last_{{ $index }}" type="date" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('certificates.'.$index.'.last_date')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="cert_next1_{{ $index }}" value="Next 1" />
                                <x-text-input wire:model="certificates.{{ $index }}.next_1_date" id="cert_next1_{{ $index }}" type="date" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('certificates.'.$index.'.next_1_date')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="cert_next2_{{ $index }}" value="Next 2" />
                                <x-text-input wire:model="certificates.{{ $index }}.next_2_date" id="cert_next2_{{ $index }}" type="date" class="mt-1 block w-full" />
                                <x-input-error :messages="$errors->get('certificates.'.$index.'.next_2_date')" class="mt-2" />
                            </div>
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <x-input-label for="cert_postpone_{{ $index }}" value="Postpone" />
                                    <x-text-input wire:model="certificates.{{ $index }}.postpone_date" id="cert_postpone_{{ $index }}" type="date" class="mt-1 block w-full" />
                                    <x-input-error :messages="$errors->get('certificates.'.$index.'.postpone_date')" class="mt-2" />
                                </div>
                                <x-loading-button type="button" wire:click="removeCertificate({{ $index }})"
                                    target="removeCertificate({{ $index }})"
                                    variant="icon-red" icon="delete"
                                    wire:key="btn-remove-cert-{{ $cert['row_key'] }}-{{ $index }}"
                                    title="Hapus sertifikat" />
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                            Belum ada sertifikat. Klik "Tambah Sertifikat" untuk menambahkan.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-end">
            <x-cancel-button wire:click="cancel" target="cancel" class="w-full sm:w-auto" />
            <x-loading-button type="submit" target="save" variant="primary" size="lg" loadingText="Menyimpan..." class="w-full sm:w-auto">
                {{ $editMode ? 'Update Kapal' : 'Simpan Kapal' }}
            </x-loading-button>
        </div>
    </form>
</div>
