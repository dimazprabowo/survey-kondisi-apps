@props(['editMode' => false, 'showStatus' => false])

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    {{-- Code --}}
    <div>
        <x-input-label for="company_code" value="Kode" :required="true" />
        <x-text-input wire:model="code" id="company_code" type="text" class="mt-1 block w-full"
            placeholder="Contoh: COMP001" />
        <x-input-error :messages="$errors->get('code')" class="mt-1" />
    </div>

    {{-- Name --}}
    <div>
        <x-input-label for="company_name" value="Nama Perusahaan" :required="true" />
        <x-text-input wire:model="name" id="company_name" type="text" class="mt-1 block w-full"
            placeholder="Nama perusahaan" />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    {{-- NPWP (masked input, strip non-digit ke nilai model) --}}
    <div>
        <x-input-label for="company_npwp" value="NPWP" :required="true" />
        <input
            id="company_npwp"
            type="text"
            x-data="{
                npwp: @entangle('npwp'),
                formatNpwp(value) {
                    let digits = value.replace(/\D/g, '').substring(0, 16);
                    let len = digits.length;

                    if (len <= 15) {
                        // Format lama: XX.XXX.XXX.X-XXX.XXX
                        let formatted = '';
                        if (len > 0) formatted += digits.substring(0, Math.min(2, len));
                        if (len > 2) formatted += '.' + digits.substring(2, Math.min(5, len));
                        if (len > 5) formatted += '.' + digits.substring(5, Math.min(8, len));
                        if (len > 8) formatted += '.' + digits.substring(8, Math.min(9, len));
                        if (len > 9) formatted += '-' + digits.substring(9, Math.min(12, len));
                        if (len > 12) formatted += '.' + digits.substring(12, 15);
                        return formatted;
                    } else {
                        // Format baru: XXXX.XXXX.XXXX.XXXX
                        let formatted = '';
                        if (len > 0) formatted += digits.substring(0, Math.min(4, len));
                        if (len > 4) formatted += '.' + digits.substring(4, Math.min(8, len));
                        if (len > 8) formatted += '.' + digits.substring(8, Math.min(12, len));
                        if (len > 12) formatted += '.' + digits.substring(12, 16);
                        return formatted;
                    }
                },
                displayValue() {
                    return this.npwp ? this.formatNpwp(this.npwp) : '';
                }
            }"
            x-init="$watch('npwp', value => { if(value) npwp = value.replace(/\D/g, '').substring(0, 16); })"
            :value="displayValue()"
            @input="npwp = $event.target.value.replace(/\D/g, '').substring(0, 16); $event.target.value = formatNpwp($event.target.value)"
            class="mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm font-mono"
            placeholder="XX.XXX.XXX.X-XXX.XXX">
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Format lama (15 digit) atau baru (16 digit)</p>
        <x-input-error :messages="$errors->get('npwp')" class="mt-1" />
    </div>

    {{-- Email --}}
    <div>
        <x-input-label for="company_email" value="Email" />
        <x-text-input wire:model="email" id="company_email" type="email" class="mt-1 block w-full"
            placeholder="email@perusahaan.com" />
        <x-input-error :messages="$errors->get('email')" class="mt-1" />
    </div>

    {{-- Phone --}}
    <div>
        <x-input-label for="company_phone" value="Telepon" />
        <x-text-input wire:model="phone" id="company_phone" type="text" class="mt-1 block w-full"
            placeholder="021-1234567" />
        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
    </div>

    {{-- Address --}}
    <div class="sm:col-span-2">
        <x-input-label for="company_address" value="Alamat" />
        <textarea wire:model="address" id="company_address" rows="2"
            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm"
            placeholder="Alamat lengkap perusahaan"></textarea>
        <x-input-error :messages="$errors->get('address')" class="mt-1" />
    </div>

    {{-- Divider --}}
    <div class="sm:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Person In Charge (PIC)</h4>
    </div>

    {{-- PIC Name --}}
    <div>
        <x-input-label for="company_pic_name" value="Nama PIC" />
        <x-text-input wire:model="pic_name" id="company_pic_name" type="text" class="mt-1 block w-full"
            placeholder="Nama person in charge" />
        <x-input-error :messages="$errors->get('pic_name')" class="mt-1" />
    </div>

    {{-- PIC Email --}}
    <div>
        <x-input-label for="company_pic_email" value="Email PIC" />
        <x-text-input wire:model="pic_email" id="company_pic_email" type="email" class="mt-1 block w-full"
            placeholder="pic@perusahaan.com" />
        <x-input-error :messages="$errors->get('pic_email')" class="mt-1" />
    </div>

    {{-- PIC Phone --}}
    <div>
        <x-input-label for="company_pic_phone" value="Telepon PIC" />
        <x-text-input wire:model="pic_phone" id="company_pic_phone" type="text" class="mt-1 block w-full"
            placeholder="08123456789" />
        <x-input-error :messages="$errors->get('pic_phone')" class="mt-1" />
    </div>

    {{-- Status (hanya ditampilkan bila caller mengizinkan) --}}
    @if($showStatus)
    <div class="sm:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4">
        <x-input-label for="company_status" value="Status" :required="true" />
        <select wire:model="status" id="company_status"
            class="mt-1 w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-blue-500 dark:focus:border-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 rounded-md shadow-sm">
            @foreach(\App\Enums\CompanyStatus::cases() as $statusCase)
                <option value="{{ $statusCase->value }}">{{ $statusCase->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-1" />
    </div>
    @endif
</div>
