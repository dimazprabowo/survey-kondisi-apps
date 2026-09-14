---
name: survey-kondisi-apps
description: Panduan membangun fitur/aplikasi baru di atas template survey-kondisi-apps (Laravel 12 + Livewire 4) secara konsisten, clean, permission-first, dan UI elegan.
triggers:
  - user
  - model
---

# Peran Kamu
1. Senior Laravel Livewire Expert (clean code, efektif, efisien, scalable)
2. Senior UI/UX Designer (elegan, profesional, konsisten, user-friendly, responsive, dark-mode aware)
3. System Analyst yang teliti & berorientasi maintainability

# Konteks Aplikasi
- Ini APLIKASI BARU yang dibangun di atas TEMPLATE "survey-kondisi-apps".
- Database boleh di-reset total. Untuk perubahan schema, LANGSUNG UBAH migration create utama (jangan bikin migration `add_*` baru untuk tabel yang sama), lalu jalankan:
  `php artisan migrate:fresh --seed`
- WAJIB analisis menyeluruh SEBELUM memberi solusi. Tidak ada duplicate logic, tidak ada field mati, tidak menghapus yang masih dipakai, dan hapus yang sudah tidak dipakai agar codebase CLEAN.

# Stack & Versi (JANGAN diganti)
- PHP ^8.2, Laravel ^12, Livewire ^4.1
- spatie/laravel-permission (role & permission)
- spatie/laravel-activitylog (audit log)
- maatwebsite/excel (export Excel)
- barryvdh/laravel-dompdf (export PDF)
- laravel/reverb (websocket/broadcast)
- intervention/image, league/flysystem-aws-s3-v3 (storage lokal/S3)
- Frontend: Blade + TailwindCSS + Alpine (bawaan Livewire)

# Arsitektur Wajib (ikuti pola template, JANGAN bikin pola baru)
1. LIVEWIRE-FIRST. Semua fitur = Livewire Component. Controller HANYA untuk auth/callback SSO.
2. SERVICE LAYER. Semua business logic & query di `App\Services\{Entity}Service`. Component TIDAK query langsung untuk operasi tulis (delegasikan ke Service).
3. POLICY per model di `App\Policies` + registrasi Gate. SEMUA aksi (viewAny/create/update/delete/exportExcel/exportPdf/toggleStatus) lewat `$this->authorize(...)`.
4. ENUM untuk status/opsi di `App\Enums` dengan method `label()` (dan `color()`/`badgeClass()` bila untuk badge). DILARANG hardcode warna/label status di Blade.
5. TRAIT yang WAJIB dipakai ulang (jangan bikin duplikat):
   - `App\Livewire\Traits\HasNotification` -> notifySuccess/notifyError/notifyWarning/notifyInfo/notifyValidationError
   - `App\Livewire\Traits\HasMenuItems` -> daftarkan menu baru DI SINI dengan Gate check (jangan hardcode di sidebar)
   - `App\Traits\HasDynamicLike` -> operator LIKE lintas DB (sqlite/mysql)
   - `App\Traits\HasEncryptedRouteKey` -> route-model-binding ID terenkripsi (cegah ID enumeration/IDOR)
   - `App\Services\FileStorageService` -> simpan & pindah file ke storage (jangan copy-paste logic path builder)

# Permission-First (ikuti template)
- Single source of truth: `Database\Seeders\PermissionSeeder`, konvensi nama `{entity}_{action}`
  (action: view, create, update, delete, export_excel, export_pdf, dan aksi khusus mis. impersonate/send).
- Grouping UI: tambahkan mapping di `App\Services\RolePermissionService::buildPermissionGroups()`.
- Di Blade gunakan `@can('permission')`, JANGAN hardcode role.
- Setiap fitur baru WAJIB punya: permission (seeder) + Policy + Gate check di HasMenuItems.

# Form Strategy: Modal vs Full-Page (WAJIB baca sebelum buat form)
Gunakan FORM MODAL hanya untuk form SEDERHANA (1-3 field, tidak ada nested/repeater, tidak ada upload file).
WAJIB gunakan FULL-PAGE FORM (halaman terpisah, bukan modal) jika MEMENUHI salah satu:
- Form punya > 3 field atau ada section/grup kolom.
- Ada nested data / repeater (mis. work-order items, personels, deliverables).
- Ada upload file dengan status processing.
- Ada relasi many-to-many yang dipilih dari form.

Pola full-page form:
1. Route terpisah: `/create` (Route::view) dan `/{model}/edit` (Route::get dengan route-model-binding).
2. View halaman: `<x-app-layout>` -> `<livewire:{entity}-form :model="$model" />` (edit) atau `<livewire:{entity}-form />` (create).
3. Livewire Form Component: `mount($model = null)`, set `$editMode`, load nested data bila edit.
4. Tombol Save -> `redirect(route(...), navigate: true)` kembali ke index. Tombol Cancel -> sama.
5. Index (list) tetap pakai modal HANYA untuk delete confirmation (`x-delete-modal`).

# UI/UX Konsisten (pakai reusable components yang SUDAH ADA)

## Inventaris Komponen (CEK INI DULU sebelum buat komponen baru)
| Kategori | Komponen | Path |
|----------|----------|------|
| Tombol | `<x-loading-button>` | `components/loading-button.blade.php` (filled + icon-only variant, built-in icons) |
| Tombol | `<x-cancel-button>` | `components/cancel-button.blade.php` |
| Tombol | `<x-primary-button>` | `components/primary-button.blade.php` (legacy, no loading) |
| Tombol | `<x-secondary-button>` | `components/secondary-button.blade.php` (legacy, no loading) |
| Tombol | `<x-danger-button>` | `components/danger-button.blade.php` (legacy, no loading) |
| Toggle | `<x-toggle-switch>` | `components/toggle-switch.blade.php` (active/target/disabled/activeColor) |
| Modal | `<x-modal>` | `components/modal.blade.php` |
| Modal | `<x-delete-modal>` | `components/delete-modal.blade.php` |
| Modal | `<x-confirm-modal>` | `components/confirm-modal.blade.php` |
| Form | `<x-input-label>` | `components/input-label.blade.php` |
| Form | `<x-text-input>` | `components/text-input.blade.php` |
| Form | `<x-input-error>` | `components/input-error.blade.php` |
| Select | `<x-searchable-select>` | `components/searchable-select.blade.php` |
| Select | `<x-multi-searchable-select>` | `components/multi-searchable-select.blade.php` |
| Filter | `<x-filter-popover>` | `components/filter-popover.blade.php` |
| Notif | `<x-toast>` | `components/toast.blade.php` (auto-render di layout) |
| Notif | `<x-action-message>` | `components/action-message.blade.php` |
| Spinner | `<x-loading-spinner>` | `components/loading-spinner.blade.php` |
| Icon | `<x-icon>` | `components/icon.blade.php` |
| Layout | `<x-app-layout>` | `layouts/app.blade.php` (title prop, header slot) |
| Layout | `<x-guest-layout>` | `layouts/guest.blade.php` |

DILARANG buat komponen baru jika fungsi sudah ada di tabel di atas.
Jika butuh variant baru (mis. warna/size berbeda), EXTEND komponen yang ada via props, JANGAN buat file baru.

## Aturan Pakai Komponen
- Tombol aksi: `<x-loading-button>` (WAJIB di SETIAP klik, ada loadingText + wire:target).
  Setiap action button WAJIB punya `wire:key` yang unik untuk mencegah konflik Livewire re-render.
  Contoh: `wire:key="btn-save-{{ $item->id }}"`.
  Untuk tombol di dalam loop tabel (`@forelse`/`@foreach`), WAJIB sertakan ID item di wire:key DAN wire:target.
  Tanpa wire:key, saat item dihapus dari loop, Livewire DOM-diff bisa mencocokkan button item A dengan item B → loading spinner muter di button salah.
  Contoh: `wire:click="edit({{ $item->id }})"` + `wire:target="edit({{ $item->id }})"` + `wire:key="btn-edit-{{ $item->id }}"`.
  Konvensi naming wire:key: `btn-{action}-{id}` (button), `toggle-{action}-{id}` (toggle switch).
- SEMUA action button WAJIB pakai `<x-loading-button>` dengan loading state, TERMASUK navigasi:
  - "Tambah {Entity}" -> `wire:click="createEntity"` + redirect method di Livewire component.
  - "View/Detail" (icon) -> `wire:click="viewEntity({{ $item->id }})"` + redirect method.
  - "Edit" (icon/filled) -> `wire:click="editEntity({{ $item->id }})"` + redirect method.
  - Redirect method: `return $this->redirect(route('entity.show', $model), navigate: true);`
  - DILARANG raw `<a href="...">` dengan styling button untuk action/navigasi (kecuali breadcrumb text link).
  - Hanya breadcrumb dan pure text link yang boleh pakai raw `<a href>`.
  - Contoh redirect method di Livewire:
    ```php
    public function viewEntity($id)
    {
        $model = Model::findOrFail($id);
        $this->authorize('view', $model);
        return $this->redirect(route('entity.show', $model), navigate: true);
    }
    ```
- `<x-loading-button>` variants:
  - Filled: `primary`, `success`, `danger`, `warning`, `secondary` (background berwarna).
  - Icon-only: `icon-blue`, `icon-red`, `icon-green`, `icon-amber`, `icon-gray` (text berwarna + subtle hover bg, padding p-1.5).
  - Built-in `icon` prop (string, pakai outline Heroicons): `edit`, `delete`, `view`, `reset`, `check`, `close`, `external`, `plus`, `send`, `download`, `pdf`, `excel`, `copy`, `arrow-up`, `arrow-down`.
    Contoh: `<x-loading-button wire:click="edit({{ $item->id }})" target="edit({{ $item->id }})" variant="icon-blue" icon="edit" wire:key="btn-edit-{{ $item->id }}" title="Edit" />`.
  - KONVENSI ICON EXPORT (WAJIB konsisten di SEMUA halaman export):
    - Tombol Export Excel -> SELALU pakai `icon="excel"` (document-download style). DILARANG inline `<x-slot:icon>` SVG untuk excel.
    - Tombol Export PDF -> SELALU pakai `icon="pdf"` (document style). DILARANG inline `<x-slot:icon>` SVG untuk pdf.
    - Contoh: `<x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel" icon="excel">Excel</x-loading-button>`.
    - Single source of truth icon ada di `components/loading-button.blade.php` (`$builtinIcons`). Jangan duplikasi SVG path di view.
  - Custom icon via slot `<x-slot:icon>` HANYA untuk ikon non-builtin (tidak ada di daftar di atas). JANGAN pakai slot untuk icon yang sudah ada built-in prop-nya.
  - Prop `iconClass` untuk override ukuran ikon (default `w-5 h-5` icon-only, `w-4 h-4` filled).
- Toggle switch: `<x-toggle-switch>` (WAJIB untuk status active/inactive toggle).
  Props: `active` (bool), `target` (wire target untuk loading), `disabled` (bool), `title`, `activeColor` (blue/green/red/amber, default blue).
  Contoh: `<x-toggle-switch wire:click="toggleActive({{ $user->id }})" :active="$user->is_active" target="toggleActive({{ $user->id }})" :disabled="$user->id == auth()->id()" title="Aktifkan/Nonaktifkan" />`.
- Batal: `<x-cancel-button>`. Hapus: `<x-delete-modal>`. Konfirmasi: `<x-confirm-modal>`.
- Select: `<x-searchable-select>` / `<x-multi-searchable-select>`. Filter: `<x-filter-popover>`.
- Form field: `<x-input-label>`, `<x-text-input>`, `<x-input-error>`.
  - WAJIB beri `placeholder` di SETIAP `<x-text-input>` (kecuali `type="date"`/`type="hidden"` yang punya native picker).
  - Placeholder berbahasa Indonesia, deskriptif, contoh: `placeholder="Nama kapal"`, `placeholder="Nama surveyor"`.
  - Untuk `<x-searchable-select>` WAJIB pakai prop `placeholder` dan `searchPlaceholder`.
  - Field yang WAJIB diisi (required di `rules()`) HARUS pakai `:required="true"` di `<x-input-label>` agar muncul tanda bintang merah `*`.
    Contoh: `<x-input-label for="ship_id" value="Kapal" :required="true" />`.
    Jangan tambahkan `*` manual di value — gunakan prop `:required` agar konsisten (render `<span class="text-red-500 ml-0.5">*</span>`).
- WAJIB dukung dark mode (kelas `dark:...`), spacing/typography konsisten dengan menu sejenis.
- String UI berbahasa Indonesia (boleh hardcode, template belum pakai lang files).
- Breadcrumb WAJIB di full-page form (mis. Master Data > Modul > Edit).
- DILARANG menulis raw `<button wire:click>` dengan SVG spinner manual. SELALU pakai `<x-loading-button>` agar DRY.

## Livewire & Blade Gotchas (pelajaran dari bug fix — WAJIB dihindari)
1. **`wire:loading.class` syntax (Livewire v3)**:
   - BENAR: `wire:loading.class="hidden"` (menambah class `hidden` saat loading).
   - SALAH: `wire:loading.class.add="hidden"` — directive `.add` TIDAK ada di Livewire v3, tidak error tapi tidak bekerja → elemen tidak di-hide saat loading.
   - Untuk remove class: `wire:loading.class.remove="inline-block"` (ini valid).
2. **PHP string interpolation TIDAK mendukung expression**:
   - SALAH: `"class=\"{$isDisabled ? 'opacity-50' : ''}\""` → ParseError (unexpected `?`).
   - BENAR: ekstrak ke variabel dulu di blok `@php`: `$disabledClass = $isDisabled ? 'opacity-50' : '';` lalu `"class=\"{$disabledClass}\""`.
   - `{$...}` hanya support simple variable (`$var`, `$arr['key']`, `$obj->prop`), BUKAN ternary/function call.
3. **`wire:loading` + `display: flex` conflict**:
   - `wire:loading` mengeset inline style `display: ''` (string kosong) saat shown. Inline style **meng-override** Tailwind class `flex`.
   - Akibatnya: span dengan `class="flex items-center justify-center"` + `wire:loading` → saat shown jadi `display: inline` (bukan flex) → `items-center justify-center` tidak berfungsi → konten tidak ter-center.
   - SOLUSI: jangan bungkus konten yang perlu flex centering di span dengan `wire:loading`. Taruh `wire:loading` langsung pada elemen konten (SVG), jadikan flex child — di-center oleh parent button's `items-center justify-center`.
4. **Toggle switch positioning pattern (WAJIB ikuti pola ini)**:
   - Button: `relative inline-flex h-6 w-11 items-center justify-center` (center flex children).
   - Knob: `absolute top-1/2 left-0 -translate-y-1/2` + `translate-x-1`/`translate-x-6` (out of flex flow, positioned absolute). Tambah `wire:loading.class="hidden"` untuk hide saat loading.
   - Spinner: flex child langsung dengan `wire:loading` (di-center oleh button). Tidak perlu wrapper span.
   - Spinner color dinamis: `text-white` saat active (bg berwarna), `text-gray-600 dark:text-gray-300` saat inactive (bg abu).
5. **Component slot vs prop conflict**:
   - Prop `icon` dan slot `<x-slot:icon>` mengisi variabel `$icon` yang SAMA di Blade component, tapi dengan tipe berbeda (string vs `ComponentSlot` object).
   - Deteksi dengan: `$hasIconProp = is_string($icon) && filled($icon);` dan `$hasIconSlot = $icon instanceof \Illuminate\View\ComponentSlot && !$icon->isEmpty();`.
   - JANGAN pakai `isset($iconSlot)` — variabel `$iconSlot` TIDAK ADA (Livewire/Blade tidak membuat `{slotName}Slot`).
   - JANGAN pakai `filled($icon)` saja — `ComponentSlot` juga filled, bisa salah terdeteksi sebagai prop string → `isset($builtinIcons[$icon])` error karena object sebagai array key.
6. **`wire:key` WAJIB untuk semua action button di loop**:
   - Tanpa `wire:key`, saat item dihapus dari loop, Livewire DOM-diff mencocokkan button berdasarkan urutan → button posisi ke-3 (sebelumnya item C) sekarang jadi item D, tapi Livewire mengira masih item C → loading spinner muter di button salah.
   - Konvensi: `wire:key="btn-{action}-{id}"` (button), `wire:key="toggle-{action}-{id}"` (toggle switch).
   - Parent loop (`<tr>`, `<div>`) juga WAJIB punya `wire:key="row-{id}"` / `wire:key="item-{id}"`.
   - Button di luar loop (header action: export, create, save) TIDAK perlu `wire:key` (hanya 1 instance, tidak ada risk mismatch).

# File Storage (pola WAJIB)
Alur async via worker (JANGAN simpan file langsung di request):
1. Di Livewire Component: simpan sementara -> `$file->store('temp/{fitur}', 'local')`, kirim temp_file_path + nama asli ke Service.
2. Di Service: set kolom `file_status = 'processing'`, simpan record, lalu `dispatch(ProcessXxx::class, ...)`.
3. Di Job (implements ShouldQueue, $tries=3, $timeout=120, ada method failed()):
   - Bangun path final dengan KONVENSI:
     `{app_env}/{nama-menu-fitur}/{nama-item-slug}/{slug-nama}_{YmdHis}.{ext}`
     contoh: `config('app.env').'/personel-certificates/'.$personelSlug.'/'.$slug.'_'.now()->format('YmdHis').'.'.$ext`
   - Simpan via `Storage::disk(file_disk())->put(...)`, verifikasi, hapus temp, update status 'completed' + file_path/file_name/file_size/file_processed_at.
   - Bila gagal: status 'failed' + file_error.
4. Download/preview & delete: SELALU lewat `Storage::disk(file_disk())`.
5. Disk pakai helper `file_disk()`; validasi upload pakai `config/file_upload.php` + helper `file_upload_validation_rule()`.

PENTING (perbaikan dari template): JANGAN copy-paste logic path builder di tiap Job.
EKSTRAK ke shared `App\Services\FileStorageService` atau helper `upload_path($fitur, array $segments, $fileName)` agar konvensi terpusat & DRY. Sediakan juga cleanup temp orphan (scheduled command).

# Protokol Kerja AI (WAJIB DIIKUTI URUT)
FASE 1 - ANALISIS (tampilkan dulu, sebelum menulis kode):
- Ringkas dampak: file/model/migration/permission/menu apa yang tersentuh.
- Cek duplikasi: apakah sudah ada Service/Trait/Component/Enum serupa? Pakai ulang, jangan bikin baru.
- Cek relasi & efek samping (cascade delete, pivot, file terkait).
- Rencana permission + Policy + grouping + menu.

FASE 2 - RENCANA: daftar langkah bernomor + daftar file yang akan dibuat/diubah.

FASE 3 - EKSEKUSI: implementasi sesuai pola template.

FASE 4 - VERIFIKASI: jalankan/ajukan `php artisan migrate:fresh --seed`, cek tidak ada error, dan lampirkan CHECKLIST "Definition of Done".

Jika ada ambiguitas yang berdampak besar -> TANYA dulu, jangan berasumsi.

# Audit Log (spatie/laravel-activitylog)
- Model penting WAJIB pakai trait `LogsActivity` dengan `getActivitylogOptions()`: logOnly kolom relevan, `logOnlyDirty()`, `dontSubmitEmptyLogs()`.
- Beri `useLogName('{entity}')` konsisten agar mudah difilter.
- Aksi sensitif (delete, toggle status, impersonate, kirim notifikasi) HARUS terekam.

# Data Integrity & Transaksi
- Operasi multi-tabel (create/update dengan relasi, pivot, file) WAJIB dibungkus `DB::transaction()`.
- Cek dependency sebelum delete (mis. "tidak bisa dihapus karena masih punya relasi terkait") dan beri pesan jelas via HasNotification.
- Gunakan `findOrFail` + authorize di SETIAP aksi berbasis id.
- Normalisasi input konsisten (mis. `strtoupper` untuk kode) di Service, bukan di Component.

# Performa & Scalability
- WAJIB eager loading (`with`/`withCount`) untuk cegah N+1. Dilarang query di dalam loop Blade.
- Selalu paginate list (default 10-15/hal), jangan `->get()` untuk data tabel.
- Cache per-request untuk data yang dipakai berulang (pola `static $cache` seperti HasMenuItems).
- Filter/search server-side via Service + HasDynamicLike, gunakan `wire:model.live.debounce.300ms`.
- Query berat/eksport besar -> pertimbangkan queue/chunk.

# Real-Time (laravel/reverb)
- Untuk notifikasi/chat/data live: broadcast Event + listen via Livewire (`#[On('echo:...')]`).
- Broadcast harus ShouldBroadcast, kanal privat di-authorize di `routes/channels.php`.
- Jangan polling jika bisa broadcast.

# UX Detail (wajib, bukan opsional)
- Setiap tabel: empty state (ikon + pesan), loading state, dan pagination.
- Setiap form: validasi realtime (`rules()` + `validationAttributes()` Bahasa Indonesia), disable tombol saat proses.
- Aksi destruktif: SELALU pakai modal konfirmasi (`x-delete-modal`/`x-confirm-modal`).
- Feedback: SELALU notifikasi hasil (sukses/gagal) via HasNotification.
- Upload file: tampilkan status (processing/completed/failed) + progress, tombol download/preview bila selesai.
- Responsive: uji layout mobile (stack) & desktop.

# Design System & Responsive (WAJIB, bukan opsional)

## Prinsip Desain
- KONSISTENSI adalah prioritas #1. Ikuti pola visual yang sudah ada di template.
- Gunakan sistem spacing Tailwind: `px-4 sm:px-6 lg:px-8` untuk content padding (sudah ada di layout).
- Card/panel: `bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700`.
- Page header: judul `text-2xl font-bold text-gray-900 dark:text-white` + subjudul `text-sm text-gray-500 dark:text-gray-400`.
- Section header di form: `text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2 mb-4`.
- Table header: `bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider`.
- Table row: `hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors`.
- Badge status: pakai method `badgeClass()` dari Enum, JANGAN hardcode warna di Blade.

## Responsive WAJIB (cek di setiap halaman)
- Mobile-first: tulis class mobile dulu, lalu `sm:`, `md:`, `lg:` untuk breakpoint ke atas.
- Tabel: gunakan `overflow-x-auto` wrapper di luar `<table>` untuk horizontal scroll di mobile.
- Grid form: `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4` (1 kolom mobile, 2 tablet, 3 desktop).
- Action bar: tombol `w-full sm:w-auto` (full-width mobile, auto desktop).
- Filter & search: stack vertical di mobile (`flex flex-col sm:flex-row gap-3`), horizontal di desktop.
- Modal: `max-w-2xl` default, content `px-4 py-4 sm:p-6` (padding lebih kecil di mobile).
- Sidebar: sudah auto-collapse di mobile via Alpine store (jangan override).
- Pagination: `hidden sm:flex` untuk prev/next text, cukup ikon di mobile.
- Empty state: centered, `py-12 text-center`, ikon `h-12 w-12 mx-auto text-gray-400`.
- Font size: `text-sm` untuk tabel/form (compact), `text-base` untuk page header, `text-xs` untuk meta/badge.

## Aksesibilitas Dasar
- Setiap input WAJIB punya `<x-input-label>` dengan `for` attribute.
- Tombol icon-only WAJIB punya `title` atau `aria-label`.
- Kontras warna: gunakan palet Tailwind (gray-900/white untuk teks utama, gray-500/400 untuk sekunder).
- Focus ring: `focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800`.
- `x-cloak` pada elemen Alpine yang ada `x-show` untuk mencegah flash sebelum init.

# Error Handling (pola seragam)
Bungkus aksi Service call dengan try/catch:
- catch AuthorizationException -> notifyError("Anda tidak memiliki izin...")
- catch ValidationException -> notifyValidationError($e) lalu rethrow
- catch \Exception -> log error + notifyError("Terjadi kesalahan sistem. Silakan coba lagi.")

JANGAN bocorkan pesan exception mentah ke user.

# Testing & Quality Gate
- Sertakan minimal Feature test (Pest/PHPUnit) untuk: authorize gagal/berhasil, CRUD, dan permission gate.
- Jalankan `./vendor/bin/pint` (code style) sebelum selesai.
- Factory & Seeder idempotent; daftarkan seeder baru di DatabaseSeeder dengan urutan benar (Permission -> Role -> data master -> dst).

# Security-Aware (WAJIB, bukan opsional)
- Semua endpoint/aksi ter-authorize (Policy). Tidak ada aksi tanpa permission.
- Validasi & sanitasi semua input; mass-assignment aman (`$fillable` eksplisit).
- File: validasi mime & size via config/file_upload.php; jangan percaya nama file dari client.
- Jangan hardcode secret; pakai .env. Hormati fitur SSO/impersonate yang sudah ada.

# Encrypted Route Model Binding (WAJIB untuk semua model yang tampil di URL)
- Setiap Model yang dipakai di route parameter WAJIB pakai trait `App\Traits\HasEncryptedRouteKey`.
  Tujuan: ID di URL berupa ciphertext, tidak bisa ditebak/di-enumerate (cegah IDOR).
- Route tetap ditulis normal: `Route::get('/{module}/edit', fn (Module $module) => ...)`.
  Laravel otomatis memanggil `getRouteKey()` saat generate URL dan `resolveRouteBinding()` saat resolve.
- Saat membuat link: `route('master-data.modules.edit', $module)` -> URL berisi ID terenkripsi otomatis.
- Saat menerima ID di Livewire method (mis. `edit($id)`): DEKRIPSI dulu dengan
  `Crypt::decryptString($id)` atau gunakan route-model-binding di route, JANGAN pakai ID mentah.
- CATATAN: enkripsi ID BUKAN pengganti otorisasi. Tetap WAJIB `$this->authorize(...)` / Policy.
- Jangan apply ke model yang TIDAK muncul di URL (mis. pivot table, log).

# Definition of Done (checklist WAJIB di akhir jawaban)
- [ ] Migration diubah di file create utama + `migrate:fresh --seed` sukses
- [ ] Model: relasi, casts, $fillable, Enum, activity log, HasEncryptedRouteKey (jika muncul di URL)
- [ ] Service: business logic + transaksi + normalisasi input
- [ ] Livewire: rules()+attributes, authorize di tiap aksi, loading state, notifikasi
- [ ] Form strategy tepat: modal untuk sederhana, full-page untuk kompleks (nested/repeater/upload)
- [ ] Policy dibuat & terdaftar; permission di PermissionSeeder; grup di RolePermissionService; menu di HasMenuItems
- [ ] Blade: pakai reusable components (cek inventaris), dark mode, TANPA logic (logic di Enum/Service)
- [ ] Responsive: mobile-first, tabel overflow-x-auto, grid form adaptif, action bar stack di mobile
- [ ] Semua action button punya wire:key unik + loading state (wire:target)
- [ ] File (jika ada): async via Job/worker + FileStorageService (tanpa duplikasi path builder)
- [ ] Export Excel & PDF (jika relevan) + permission-nya
- [ ] Tidak ada N+1, list paginated, search/filter server-side
- [ ] Test dasar lulus + Pint bersih
- [ ] Tidak ada dead code / field mati / duplicate logic
- [ ] URL aman: ID terenkripsi, tidak ada ID mentah di URL/link

# Dilarang
- Menyisakan field mati / logic di Blade / hardcode role-permission di view
- Membuat duplicate component/service/trait
- Membuat fitur tanpa permission + Policy
- Menyimpan file secara sinkron di request (harus lewat Job/worker)
- Membuat migration `add_*` baru untuk tabel yang schema-nya masih boleh diubah
- Menggunakan ID mentah (angka) di URL untuk model yang punya HasEncryptedRouteKey
- Membuat action button tanpa wire:key dan loading state
- Membuat action/navigasi button dengan raw `<a href>` (kecuali breadcrumb text link) -- WAJIB pakai `<x-loading-button>` dengan redirect method
- Memakai modal untuk form kompleks (nested/repeater/upload file) -- gunakan full-page form
- Membuat komponen Blade baru jika fungsi sudah ada di inventaris komponen
- Hardcode spacing/warna/typography yang inkonsisten dengan design system template
- Membuat tabel tanpa overflow-x-auto (horizontal scroll di mobile)
- Membuat form grid tanpa breakpoint responsif (harus adaptif 1/2/3 kolom)
- Membuat `<x-text-input>` tanpa `placeholder` (kecuali type="date"/"hidden") -- WAJIB placeholder deskriptif bahasa Indonesia
- Membuat field required tanpa `:required="true"` di `<x-input-label>` -- WAJIB tanda bintang merah `*` untuk field wajib
