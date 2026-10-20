<?php

namespace App\Livewire\Surveys;

use App\Enums\ShipClassStatus;
use App\Enums\SurveyStatus;
use App\Events\SurveyContentUpdated;
use App\Events\SurveySectionFocus;
use App\Livewire\Traits\HasNotification;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyTemplate;
use App\Services\ShipService;
use App\Services\SurveyService;
use App\Services\SurveyTemplateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class SurveyForm extends Component
{
    use AuthorizesRequests, HasNotification;

    // Nilai opsi "Kapal Baru" pada select kapal — kapal belum ada di master;
    // record dibuat saat sinkron (tombol Update Master / konfirmasi Selesai).
    protected const NEW_SHIP_VALUE = 'new';

    public ?Survey $survey = null;

    public bool $editMode = false;

    // Wizard: 1 = Informasi Survey, 2 = Data Kapal, 3 = Penilaian & CAP.
    // Tersinkron ke query string (?step=) agar bertahan saat refresh &
    // tombol Back browser mundur ke langkah sebelumnya.
    #[Url(as: 'step', history: true)]
    public int $activeStep = 1;

    // Header fields
    public $surveyId;

    public $ship_id;

    public $survey_template_id;

    public $survey_folder_id;

    public $survey_date;

    public $surveyor;

    public $location;

    public $status = 'draft';

    public $notes;

    // Snapshot data kapal (tersimpan di surveys.ship_snapshot — laporan
    // membaca snapshot ini, bukan relasi ship live, agar historis stabil).
    public array $shipData = [];

    public array $shipCertificates = [];

    // True saat opsi "Kapal Baru" dipilih — snapshot diisi manual; ship_id
    // survey null sampai record master dibuat lewat sinkron.
    public bool $isNewShip = false;

    // Toggle edit pada langkah Data Kapal — default OFF agar snapshot tidak
    // terubah tak sengaja (auto-ON untuk Kapal Baru).
    public bool $editShipData = false;

    // Konteks modal sinkron kapal: 'save' = konfirmasi simpan Selesai,
    // 'manual' = tombol Update Master di langkah Data Kapal.
    public string $shipSyncMode = 'save';

    // Dynamic responses: [item_id => ['scores' => [label => value], 'qty' => '', 'specification' => '', 'date_issued' => '', 'date_expired' => '']]
    public $responses = [];

    // Group-level notes: [item_group_id => ['catatan 1', 'catatan 2', ...]] — list dinamis per grup item
    public $groupNotes = [];

    // Active tab (category)
    public $activeCategory = 1;

    // Active sub-category tab (within active category)
    public $activeSubCategory;

    // Preferensi auto-save draft (persist di session)
    public bool $autoSave = true;

    // True jika ada draft yang ter-restore saat mount (form punya perubahan tersimpan)
    public bool $hasDraft = false;

    // Versi konten saat form dimuat (updated_at survey) — dipakai untuk
    // mendeteksi kolaborator lain yang sudah menyimpan selama kita mengedit.
    public ?string $loadedVersion = null;

    // True setelah user mengonfirmasi modal "Timpa perubahan" — persist
    // berikutnya melewati cek konflik remote satu kali.
    public bool $allowOverwrite = false;

    // Working copy struktur survey (snapshot) — sumber tunggal render & hapus node
    public array $structureTree = ['categories' => []];

    // Mode edit struktur (kategori/sub/grup/item) — toggle di header
    public bool $editStructure = false;

    // State modal editor node struktur (tambah/ubah) — diisi saat
    // openAddNode/openEditNode, di-reset oleh resetNodeModal.
    public string $nodeModalMode = 'add';

    public string $nodeLevel = 'item';

    public ?int $nodeParentId = null;

    public ?int $nodeId = null;

    public string $nodeName = '';

    public string $nodeItemType = 'score';

    public string $nodeScoreLabels = 'C, V';

    public bool $nodeHasDateFields = false;

    // Tipe & label skor item terkunci saat grup sudah berisi item lain —
    // render tabel (HTML & DOCX) menentukan tipe kolom dari item pertama.
    public bool $nodeTypeLocked = false;

    // Per-request cache index hierarki struktur (tidak diserialisasi Livewire)
    protected ?array $structureIndexCache = null;

    /** Umur draft/tab/baseline sinkron di cache sejak terakhir ditulis. */
    protected const WORK_STATE_TTL_HOURS = 24;

    public function mount(?Survey $survey = null)
    {
        $this->autoSave = Cache::get($this->autoSaveKey(), true);

        if ($survey && $survey->exists) {
            $this->authorize('update', $survey);
            $this->survey = $survey;
            $this->editMode = true;
            $this->surveyId = $survey->id;
            $this->loadedVersion = $survey->updated_at?->toIso8601String();
            // Survey "Kapal Baru" (ship_id null) tampil sebagai opsi sentinel.
            if ($survey->ship_id === null) {
                $this->isNewShip = true;
                $this->editShipData = true;
                $this->ship_id = self::NEW_SHIP_VALUE;
            } else {
                $this->ship_id = $survey->ship_id;
            }
            $this->survey_template_id = $survey->survey_template_id;
            $this->survey_date = $survey->survey_date?->format('Y-m-d');
            $this->surveyor = $survey->surveyor;
            $this->location = $survey->location;
            $this->status = $survey->status->value;
            $this->notes = $survey->notes;

            // Snapshot kapal: dari ship_snapshot tersimpan; fallback isi
            // dari master kapal untuk survey lama tanpa snapshot.
            $this->loadShipData($survey);

            // Working copy struktur: snapshot tersimpan (fallback bangun ulang
            // dari template untuk data lama yang belum punya structure)
            $this->structureTree = $survey->structure
                ?? app(SurveyService::class)->buildStructureSnapshot($survey->survey_template_id)
                ?? ['categories' => []];

            // Load existing responses
            $this->loadResponses();

            $firstCat = $this->categories->first();
            $this->activeCategory = $firstCat?->id ?? 1;
            $this->activeSubCategory = $firstCat?->subCategories->first()?->id;

            // Restore draft if exists (auto-recovery after reload)
            $this->hasDraft = $this->loadDraft();
            $this->restoreActiveTab();

            // Deep-link kategori dari preview laporan/halaman detail (override tab draft)
            $categoryParam = request()->query('category');
            if ($categoryParam) {
                try {
                    $targetCat = $this->categories->firstWhere('id', Crypt::decryptString($categoryParam));
                    if ($targetCat) {
                        $this->activeCategory = $targetCat->id;
                        $this->activeSubCategory = $targetCat->subCategories->first()?->id;

                        $subParam = request()->query('sub');
                        if ($subParam) {
                            $targetSub = $targetCat->subCategories->firstWhere('id', Crypt::decryptString($subParam));
                            if ($targetSub) {
                                $this->activeSubCategory = $targetSub->id;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Param tidak valid — pakai tab default/draft
                }
            }

            $this->resetCollabBaseline();
        } else {
            $this->authorize('create', Survey::class);
            $this->survey_date = now()->format('Y-m-d');
            $this->status = SurveyStatus::Draft->value;
            $this->shipData = $this->emptyShipData();

            // Template dari query param (dari template picker modal) atau default
            $templateParam = request()->query('template');
            if ($templateParam) {
                try {
                    $templateId = Crypt::decryptString($templateParam);
                    $template = SurveyTemplate::active()->find($templateId);
                    if ($template) {
                        $this->survey_template_id = $template->id;
                    }
                } catch (\Exception $e) {
                    // Invalid template param, fall back to default
                }
            }

            // Fall back to default template if not set
            if (! $this->survey_template_id) {
                $defaultTemplate = (new SurveyTemplateService)->getDefault();
                $this->survey_template_id = $defaultTemplate?->id;
            }

            // Folder tujuan dari index (create dari dalam folder aktif)
            $folderParam = request()->query('folder');
            if ($folderParam) {
                try {
                    $folderId = Crypt::decryptString($folderParam);
                    if (\App\Models\SurveyFolder::find($folderId)) {
                        $this->survey_folder_id = (int) $folderId;
                    }
                } catch (\Exception $e) {
                    // Param tidak valid — survey dibuat di root
                }
            }

            // Working copy struktur dari template terpilih (snapshot awal)
            $this->structureTree = app(SurveyService::class)->buildStructureSnapshot($this->survey_template_id)
                ?? ['categories' => []];

            $this->initEmptyResponses();
            $firstCat = $this->categories->first();
            $this->activeCategory = $firstCat?->id ?? 1;
            $this->activeSubCategory = $firstCat?->subCategories->first()?->id;

            // Restore draft if exists (auto-recovery after reload)
            $this->hasDraft = $this->loadDraft();
            $this->restoreActiveTab();
        }

        // Clamp ?step= dari query string (URL bisa diedit manual)
        $this->activeStep = max(1, min(3, $this->activeStep));
    }

    protected function loadResponses(): void
    {
        $this->responses = [];
        $existing = \App\Models\SurveyResponse::where('survey_id', $this->surveyId)
            ->get()
            ->keyBy('survey_item_id');

        $items = $this->getTemplateItems();
        foreach ($items as $item) {
            $response = $existing->get($item->id);

            if ($item->item_type === \App\Enums\SurveyItemType::Inventory) {
                $this->responses[$item->id] = [
                    'qty' => $response?->qty ?? '',
                    'specification' => $response?->specification ?? '',
                ];

                continue;
            }

            $this->responses[$item->id] = [
                'scores' => $response?->scores ?? [],
                'date_issued' => $response?->date_issued?->format('Y-m-d') ?? '',
                'date_expired' => $response?->date_expired?->format('Y-m-d') ?? '',
            ];
            // Ensure all score labels have keys
            foreach ($item->score_labels as $label) {
                if (! isset($this->responses[$item->id]['scores'][$label])) {
                    $this->responses[$item->id]['scores'][$label] = '';
                }
            }
        }

        $this->groupNotes = $this->initGroupNotes(
            \App\Models\SurveyGroupNote::where('survey_id', $this->surveyId)
                ->orderBy('order_num')
                ->get()
                ->groupBy('survey_item_group_id')
                ->map(fn ($rows) => $rows->pluck('note')->all())
                ->all()
        );
    }

    protected function initEmptyResponses(): void
    {
        $this->responses = [];
        $items = $this->getTemplateItems();
        foreach ($items as $item) {
            if ($item->item_type === \App\Enums\SurveyItemType::Inventory) {
                $this->responses[$item->id] = [
                    'qty' => '',
                    'specification' => '',
                ];

                continue;
            }

            $scores = [];
            foreach ($item->score_labels as $label) {
                $scores[$label] = '';
            }
            $this->responses[$item->id] = [
                'scores' => $scores,
                'date_issued' => '',
                'date_expired' => '',
            ];
        }

        $this->groupNotes = $this->initGroupNotes();
    }

    /**
     * Build groupNotes map keyed by item group id (stable across reorder).
     * Each entry is a list of notes: [groupId => [note, note, ...]].
     */
    protected function initGroupNotes(array $existing = []): array
    {
        $notes = [];
        foreach ($this->getTemplateItemGroupIds() as $groupId) {
            $notes[$groupId] = isset($existing[$groupId]) ? (array) $existing[$groupId] : [];
        }

        return $notes;
    }

    /**
     * Get item group ids dari struktur aktif (snapshot untuk edit, template untuk create).
     */
    protected function getTemplateItemGroupIds(): array
    {
        return $this->categories
            ->flatMap(fn ($cat) => $cat->subCategories)
            ->flatMap(fn ($sc) => $sc->itemGroups)
            ->pluck('id')
            ->all();
    }

    /**
     * Get items dari struktur aktif (snapshot untuk edit, template untuk create).
     */
    protected function getTemplateItems()
    {
        return $this->categories
            ->flatMap(fn ($cat) => $cat->subCategories)
            ->flatMap(fn ($sc) => $sc->itemGroups)
            ->flatMap(fn ($ig) => $ig->items);
    }

    public function rules()
    {
        return [
            'ship_id' => $this->isNewShip
                ? ['required', Rule::in([self::NEW_SHIP_VALUE])]
                : ['required', 'exists:ships,id'],
            // Template beku di snapshot saat survey dibuat; di edit mode boleh
            // null bila template sumber sudah dihapus.
            'survey_template_id' => $this->editMode
                ? ['nullable', 'integer']
                : ['required', 'exists:survey_templates,id'],
            'survey_date' => ['required', 'date'],
            'surveyor' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:'.implode(',', SurveyStatus::values())],
            'notes' => ['nullable', 'string', 'max:5000'],
            'groupNotes.*.*' => ['nullable', 'string', 'max:500'],
            // Snapshot data kapal — particulars dipakai laporan dan (saat
            // completed) ditimpa balik ke master data kapal.
            'shipData.name' => ['required', 'string', 'max:255'],
            'shipData.year_built' => ['nullable', 'integer', 'min:1900', 'max:'.(int) now()->format('Y')],
            'shipData.imo_number' => ['nullable', 'string', 'max:20'],
            'shipData.ship_type' => ['nullable', 'string', 'max:255'],
            'shipData.flag' => ['nullable', 'string', 'max:255'],
            'shipData.gross_tonnage' => ['nullable', 'string', 'max:20'],
            'shipData.net_tonnage' => ['nullable', 'string', 'max:20'],
            'shipData.owner' => ['nullable', 'string', 'max:255'],
            'shipData.operator' => ['nullable', 'string', 'max:255'],
            'shipData.call_sign' => ['nullable', 'string', 'max:20'],
            'shipData.loa' => ['nullable', 'string', 'max:20'],
            'shipData.lpp' => ['nullable', 'string', 'max:20'],
            'shipData.breadth' => ['nullable', 'string', 'max:20'],
            'shipData.depth' => ['nullable', 'string', 'max:20'],
            'shipData.draft' => ['nullable', 'string', 'max:20'],
            'shipData.dwt' => ['nullable', 'string', 'max:20'],
            'shipData.builder' => ['nullable', 'string', 'max:255'],
            'shipData.port_of_registry' => ['nullable', 'string', 'max:255'],
            'shipData.hull_material' => ['nullable', 'string', 'max:100'],
            'shipData.class_name' => ['nullable', 'string', 'max:255'],
            'shipData.class_notations' => ['nullable', 'string', 'max:255'],
            'shipData.class_status' => ['required', 'string', 'in:'.implode(',', ShipClassStatus::values())],
            'shipData.main_engine' => ['nullable', 'string', 'max:255'],
            'shipData.main_engine_power' => ['nullable', 'string', 'max:255'],
            'shipData.aux_engine' => ['nullable', 'string', 'max:255'],
            'shipData.aux_engine_power' => ['nullable', 'string', 'max:255'],
            'shipCertificates' => ['array'],
            'shipCertificates.*.certificate_type' => ['nullable', 'string', 'max:255'],
            'shipCertificates.*.last_date' => ['nullable', 'date'],
            'shipCertificates.*.next_1_date' => ['nullable', 'date'],
            'shipCertificates.*.next_2_date' => ['nullable', 'date'],
            'shipCertificates.*.postpone_date' => ['nullable', 'date'],
        ];
    }

    public function validationAttributes()
    {
        $attributes = [
            'ship_id' => 'kapal',
            'survey_template_id' => 'template',
            'survey_date' => 'tanggal survey',
            'surveyor' => 'surveyor',
            'location' => 'lokasi',
            'status' => 'status',
            'notes' => 'catatan',
            'groupNotes.*.*' => 'catatan grup item',
        ];

        foreach (ShipService::fieldLabels() as $field => $label) {
            $attributes["shipData.{$field}"] = strtolower($label);
        }

        return $attributes + [
            'shipCertificates.*.certificate_type' => 'jenis sertifikat',
            'shipCertificates.*.last_date' => 'tanggal last',
            'shipCertificates.*.next_1_date' => 'tanggal next 1',
            'shipCertificates.*.next_2_date' => 'tanggal next 2',
            'shipCertificates.*.postpone_date' => 'tanggal postpone',
        ];
    }

    public function getShipOptionsProperty(): array
    {
        return collect([[
            'value' => self::NEW_SHIP_VALUE,
            'label' => '+ Kapal Baru (belum terdaftar di master)',
        ]])->merge(Ship::active()->orderBy('name')->get()->map(fn ($ship) => [
            'value' => (string) $ship->id,
            'label' => $ship->name.($ship->year_built ? ' ('.$ship->year_built.')' : ''),
        ]))->toArray();
    }

    public function getStatusOptionsProperty(): array
    {
        return collect(SurveyStatus::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }

    public function getShipClassStatusOptionsProperty(): array
    {
        return collect(ShipClassStatus::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }

    /**
     * Preview sinkron master kapal untuk modal konfirmasi — target kapal
     * (Call Sign → fallback ship_id, atau "buat baru") + daftar field yang
     * akan ditimpa. Null bila tidak ada target atau tidak ada perubahan.
     */
    public function getShipSyncPreviewProperty(): ?array
    {
        $preview = $this->buildShipSyncPreview();

        if (! $preview) {
            return null;
        }

        $diff = $preview['diff'];

        if (empty($diff['fields']) && ! $diff['certificates_changed']) {
            return null;
        }

        return $preview;
    }

    /**
     * Nilai default kosong semua kolom snapshot kapal.
     */
    protected function emptyShipData(): array
    {
        $data = array_fill_keys(ShipService::SNAPSHOT_FIELDS, null);
        $data['class_status'] = ShipClassStatus::Active->value;

        return $data;
    }

    /**
     * Isi shipData/shipCertificates dari snapshot tersimpan (edit mode),
     * fallback dari master kapal untuk survey lama tanpa snapshot.
     */
    protected function loadShipData(Survey $survey): void
    {
        $this->shipData = $this->emptyShipData();
        $snapshot = $survey->ship_snapshot;

        if (is_array($snapshot) && is_array($snapshot['attributes'] ?? null)) {
            $this->shipData = array_merge($this->shipData, $snapshot['attributes']);
            $this->shipCertificates = $this->certificatesWithKeys($snapshot['certificates'] ?? []);
        } elseif ($survey->ship) {
            $this->fillShipData($survey->ship);
        }
    }

    /**
     * Isi form snapshot dari record kapal (saat kapal dipilih/diganti).
     */
    protected function fillShipData(Ship $ship): void
    {
        $snapshot = app(ShipService::class)->snapshotFromShip($ship);
        $this->shipData = array_merge($this->emptyShipData(), $snapshot['attributes']);
        $this->shipCertificates = $this->certificatesWithKeys($snapshot['certificates']);
    }

    /**
     * Tambahkan row_key stabil ke baris sertifikat agar wire:key tidak
     * bergeser saat baris dihapus/di-reorder (hindari stale wire:model).
     */
    protected function certificatesWithKeys(array $certificates): array
    {
        return array_map(fn ($cert) => [
            'row_key' => $cert['row_key'] ?? (string) Str::uuid(),
            'certificate_type' => $cert['certificate_type'] ?? '',
            'last_date' => $cert['last_date'] ?? '',
            'next_1_date' => $cert['next_1_date'] ?? '',
            'next_2_date' => $cert['next_2_date'] ?? '',
            'postpone_date' => $cert['postpone_date'] ?? '',
        ], $certificates);
    }

    /**
     * Ganti kapal → isi ulang snapshot dari kapal baru (data editan pada
     * snapshot kapal sebelumnya sengaja dibuang — kapal berbeda). Opsi
     * "Kapal Baru" mengosongkan snapshot untuk diisi manual.
     */
    public function updatedShipId($value): void
    {
        $this->isNewShip = $value === self::NEW_SHIP_VALUE;

        if ($this->isNewShip) {
            $this->shipData = $this->emptyShipData();
            $this->shipCertificates = [];
            $this->editShipData = true;

            return;
        }

        $ship = $value ? Ship::find($value) : null;

        if ($ship) {
            $this->fillShipData($ship);
        } else {
            $this->shipData = $this->emptyShipData();
            $this->shipCertificates = [];
        }
    }

    /**
     * Toggle mode edit pada langkah Data Kapal — default terkunci agar
     * snapshot tidak berubah tak sengaja.
     */
    public function toggleEditShipData(): void
    {
        $this->authorizeFormMutation();
        $this->editShipData = ! $this->editShipData;
    }

    public function addShipCertificate(): void
    {
        $this->authorizeFormMutation();
        $this->shipCertificates[] = $this->certificatesWithKeys([[]])[0];
    }

    public function confirmRemoveShipCertificate(int $index): void
    {
        $this->authorizeFormMutation();

        if (! isset($this->shipCertificates[$index])) {
            return;
        }

        $name = trim($this->shipCertificates[$index]['certificate_type'] ?? '');
        $preview = $name !== '' ? "\"{$name}\"" : 'ini';

        $this->dispatch('confirm-remove-cert',
            action: 'removeShipCertificate',
            actionParams: [$index],
            title: 'Hapus Sertifikat',
            message: "Sertifikat {$preview} akan dihapus dari data kapal survey ini.",
            confirmText: 'Ya, Hapus',
            cancelText: 'Batal',
            type: 'danger',
        );
    }

    public function removeShipCertificate(int $index): void
    {
        $this->authorizeFormMutation();
        unset($this->shipCertificates[$index]);
        $this->shipCertificates = array_values($this->shipCertificates);
        $this->dispatch('confirm-remove-cert-close');
        $this->dispatch('ship-data-changed');
        $this->notifyInfo('Sertifikat berhasil dihapus.');
    }

    public function getCategoriesProperty()
    {
        // Single source: working copy struktur (snapshot). Di create mode diisi
        // dari template saat mount; perubahan template setelahnya tidak ikut.
        return app(SurveyService::class)->hydrateStructure($this->structureTree);
    }

    /**
     * Calculate item average live (mirrors Excel =AVERAGE(E:F))
     */
    public function calculateItemAvg($itemId): ?float
    {
        $response = $this->responses[$itemId] ?? null;
        if (! $response || empty($response['scores'])) {
            return null;
        }

        $values = array_filter($response['scores'], fn ($v) => is_numeric($v) && $v !== '' && $v !== '-');
        if (empty($values)) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /**
     * Index hierarki struktur aktif: id parent -> daftar id anak.
     * Per-request cache (reset tiap request Livewire baru).
     */
    protected function structureIndex(): array
    {
        return $this->structureIndexCache ??= $this->buildStructureIndex();
    }

    protected function buildStructureIndex(): array
    {
        $index = ['igItems' => [], 'scIgs' => [], 'catScs' => []];
        foreach ($this->categories as $cat) {
            foreach ($cat->subCategories as $sc) {
                $index['catScs'][$cat->id][] = $sc->id;
                foreach ($sc->itemGroups as $ig) {
                    $index['scIgs'][$sc->id][] = $ig->id;
                    foreach ($ig->items as $item) {
                        $index['igItems'][$ig->id][] = $item->id;
                    }
                }
            }
        }

        return $index;
    }

    protected function avgOf(array $avgs): ?float
    {
        $avgs = array_filter($avgs, fn ($v) => $v !== null);

        return $avgs !== [] ? round(array_sum($avgs) / count($avgs), 2) : null;
    }

    /**
     * Calculate item group average (average of item averages)
     */
    public function calculateItemGroupAvg($itemGroupId): ?float
    {
        $itemIds = $this->structureIndex()['igItems'][$itemGroupId] ?? [];

        return $this->avgOf(array_map(fn ($id) => $this->calculateItemAvg($id), $itemIds));
    }

    /**
     * Calculate sub-category average (average of item group averages)
     */
    public function calculateSubCategoryAvg($subCategoryId): ?float
    {
        $igIds = $this->structureIndex()['scIgs'][$subCategoryId] ?? [];

        return $this->avgOf(array_map(fn ($id) => $this->calculateItemGroupAvg($id), $igIds));
    }

    /**
     * Calculate category average (average of sub-category averages)
     */
    public function calculateCategoryAvg($categoryId): ?float
    {
        $scIds = $this->structureIndex()['catScs'][$categoryId] ?? [];

        return $this->avgOf(array_map(fn ($id) => $this->calculateSubCategoryAvg($id), $scIds));
    }

    /**
     * Calculate overall CAP score (average of category averages)
     */
    public function calculateOverallAvg(): ?float
    {
        $catIds = array_keys($this->structureIndex()['catScs']);

        return $this->avgOf(array_map(fn ($id) => $this->calculateCategoryAvg($id), $catIds));
    }

    public function setCategory($categoryId): void
    {
        $this->activeCategory = $categoryId;
        $this->activeSubCategory = $this->categories
            ->firstWhere('id', $categoryId)
            ?->subCategories->first()?->id;
        $this->persistActiveTab();
    }

    public function setSubCategory($subCategoryId): void
    {
        $this->activeSubCategory = $subCategoryId;
        $this->persistActiveTab();
    }

    /**
     * Gate untuk semua aksi yang memutasi working copy form (struktur,
     * notes, draft). Persist tetap di-gate ulang oleh save().
     */
    protected function authorizeFormMutation(): void
    {
        $this->editMode
            ? $this->authorize('update', $this->survey)
            : $this->authorize('create', Survey::class);
    }

    public function addGroupNote($itemGroupId): void
    {
        $this->authorizeFormMutation();
        $this->groupNotes[$itemGroupId] = $this->groupNotes[$itemGroupId] ?? [];
        $this->groupNotes[$itemGroupId][] = '';
    }

    public function confirmRemoveGroupNote($itemGroupId, $index): void
    {
        $this->authorizeFormMutation();

        if (! isset($this->groupNotes[$itemGroupId][$index])) {
            return;
        }

        $note = trim($this->groupNotes[$itemGroupId][$index] ?? '');
        $preview = $note !== '' ? '"'.Str::limit($note, 60).'"' : '(catatan kosong)';

        $this->dispatch('confirm-remove-note',
            action: 'removeGroupNote',
            actionParams: [$itemGroupId, $index],
            title: 'Hapus Catatan',
            message: "Catatan {$preview} akan dihapus dari grup ini.",
            confirmText: 'Ya, Hapus',
            cancelText: 'Batal',
            type: 'danger',
        );
    }

    public function removeGroupNote($itemGroupId, $index): void
    {
        $this->authorizeFormMutation();

        if (! isset($this->groupNotes[$itemGroupId][$index])) {
            return;
        }

        unset($this->groupNotes[$itemGroupId][$index]);
        $this->groupNotes[$itemGroupId] = array_values($this->groupNotes[$itemGroupId]);

        $this->dispatch('confirm-remove-note-close');
        $this->dispatch('group-note-removed');
        $this->notifyInfo('Catatan berhasil dihapus.');
    }

    public function save(SurveyService $service)
    {
        $this->normalizeShipInputs();

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Lompat ke langkah wizard yang berisi error agar langsung terlihat.
            $this->activeStep = $this->stepForErrorKeys($e->validator->errors()->keys());
            $this->notifyValidationError($e);
            throw $e;
        }

        // Status Selesai + snapshot kapal beda dari master → tampilkan modal
        // konfirmasi dulu; survey & master baru tersimpan saat user setuju
        // (user bisa batal lalu memperbaiki data kapal di form).
        if ($this->offerShipSync()) {
            return;
        }

        return $this->persistSurvey($service, false);
    }

    /**
     * Navigasi wizard — maju melewati validasi per-langkah, mundur bebas.
     */
    public function goToStep(int $step): void
    {
        while ($this->activeStep < $step && $this->activeStep < 3) {
            $this->validateStep($this->activeStep);
            $this->activeStep++;
        }

        $this->activeStep = max(1, min(3, $step));
    }

    public function nextStep(): void
    {
        $this->goToStep($this->activeStep + 1);
    }

    public function prevStep(): void
    {
        $this->activeStep = max(1, $this->activeStep - 1);
    }

    /**
     * Validasi subset field milik langkah tertentu — mencegah user maju
     * membawa field wajib yang belum diisi.
     */
    protected function validateStep(int $step): void
    {
        $rules = match ($step) {
            1 => array_intersect_key($this->rules(), array_flip([
                'ship_id', 'survey_template_id', 'survey_date', 'status',
            ])),
            2 => array_filter(
                $this->rules(),
                fn ($key) => str_starts_with($key, 'shipData.') || str_starts_with($key, 'shipCertificates'),
                ARRAY_FILTER_USE_KEY
            ),
            default => [],
        };

        if (empty($rules)) {
            return;
        }

        try {
            $this->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }
    }

    /**
     * Langkah wizard pemilik field yang gagal validasi — untuk auto-jump
     * saat simpan gagal.
     */
    protected function stepForErrorKeys(array $keys): int
    {
        foreach ($keys as $key) {
            if (str_starts_with($key, 'shipData.') || str_starts_with($key, 'shipCertificates')) {
                return 2;
            }
            if (str_starts_with($key, 'groupNotes')) {
                return 3;
            }
        }

        return 1;
    }

    /**
     * Simpan survey ke DB (create/update + responses + notes + ship_snapshot).
     * $syncShip = true → satu transaksi sekaligus menimpa master data kapal
     * dari snapshot (alur confirm modal saat status Selesai).
     */
    protected function persistSurvey(SurveyService $service, bool $syncShip)
    {
        try {
            $data = [
                // "Kapal Baru" → ship_id null; master dibuat saat sinkron.
                'ship_id' => $this->isNewShip ? null : $this->ship_id,
                'survey_template_id' => $this->survey_template_id,
                'survey_date' => $this->survey_date,
                'surveyor' => $this->surveyor,
                'location' => $this->location,
                'status' => $this->status,
                'notes' => $this->notes,
                // Struktur survey ikut tersimpan — hasil snapshot (create) atau
                // working copy yang mungkin sudah dipangkas (edit).
                'structure' => $this->structureTree,
                // Snapshot particulars + sertifikat kapal saat survey diisi.
                'ship_snapshot' => $this->buildShipSnapshot(),
            ];

            if ($this->editMode) {
                $survey = Survey::findOrFail($this->surveyId);
                $this->authorize('update', $survey);

                // Kolaborator lain sudah menyimpan selama kita mengedit →
                // jangan timpa diam-diam; minta konfirmasi via modal dulu.
                if ($this->hasRemoteConflict($survey)) {
                    return;
                }

                $survey = $syncShip
                    ? $service->saveWithShipSync($survey, $data, $this->responses, $this->groupNotes)
                    : tap($survey, function () use ($service, $survey, $data) {
                        $service->update($survey, $data);
                        $service->saveResponses($survey->id, $this->responses, $this->groupNotes);
                    });
                // Touch memastikan updated_at & updated_by ikut berubah meski
                // hanya responses/notes yang beda — jadi penanda versi konten.
                $survey->touch();
                $this->loadedVersion = $survey->fresh()->updated_at?->toIso8601String();
                $this->allowOverwrite = false;
                $this->broadcastContentUpdated();
                $this->clearDraft();
                $this->notifySuccess($syncShip
                    ? 'Survey diupdate & master data kapal berhasil disinkronkan!'
                    : 'Survey berhasil diupdate!');
            } else {
                $this->authorize('create', Survey::class);
                $data['survey_folder_id'] = $this->survey_folder_id;
                $survey = $syncShip
                    ? $service->saveWithShipSync(null, $data, $this->responses, $this->groupNotes)
                    : tap($service->create($data), function ($survey) use ($service) {
                        $service->saveResponses($survey->id, $this->responses, $this->groupNotes);
                    });
                $this->clearDraft();
                $this->notifySuccess($syncShip
                    ? 'Survey dibuat & master data kapal berhasil disinkronkan!'
                    : 'Survey berhasil dibuat!');
            }

            // Deep-link kembali ke tab kategori/sub kategori yang sedang diedit
            $params = [];
            if ($this->activeCategory) {
                $params['category'] = Crypt::encryptString((string) $this->activeCategory);
            }
            if ($this->activeSubCategory) {
                $params['sub'] = Crypt::encryptString((string) $this->activeSubCategory);
            }

            $url = route('surveys.show', $survey);
            if ($params) {
                $url .= '?'.http_build_query($params);
            }

            return $this->redirect($url, navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    /**
     * True bila survey sudah disimpan pihak lain sejak form dimuat —
     * persist di-intercept dan user diminta konfirmasi lewat modal
     * "Timpa Perubahan" (dilanjutkan confirmOverwriteSave).
     */
    protected function hasRemoteConflict(Survey $survey): bool
    {
        if ($this->allowOverwrite || ! $this->loadedVersion) {
            return false;
        }

        $remoteNewer = $survey->updated_at
            && $survey->updated_at->gt(\Illuminate\Support\Carbon::parse($this->loadedVersion));

        if (! $remoteNewer || ! $survey->updated_by || $survey->updated_by === auth()->id()) {
            return false;
        }

        $editorName = $survey->updater?->name ?? 'pengguna lain';
        $this->dispatch('confirm-overwrite-survey',
            action: 'confirmOverwriteSave',
            title: 'Timpa Perubahan Kolaborator?',
            message: "{$editorName} baru saja menyimpan perubahan pada survey ini. Jika Anda lanjut menyimpan, perubahannya akan tertimpa oleh isian Anda.",
            confirmText: 'Ya, Timpa & Simpan',
            cancelText: 'Batal',
            type: 'warning',
        );

        return true;
    }

    /**
     * Dipanggil dari modal konfirmasi timpa — persist dilanjutkan satu kali
     * tanpa cek konflik ulang.
     */
    public function confirmOverwriteSave(SurveyService $service)
    {
        $this->allowOverwrite = true;
        $this->dispatch('confirm-overwrite-survey-close');

        return $this->persistSurvey($service, false);
    }

    /**
     * Broadcast posisi tab sub-kategori ke editor lain — dipicu Alpine saat
     * activeSubCategory berubah dan sekali saat presence join.
     */
    public function broadcastFocus($subCategoryId): void
    {
        if (! $this->editMode) {
            return;
        }

        $this->authorizeFormMutation();

        // Sengaja tanpa toOthers(): Livewire mengirim header X-Socket-ID
        // "undefined" saat Echo belum/ulang tersambung → Pusher melempar
        // "Invalid socket ID" dan event hilang. Echo sendiri difilter di
        // client via user_id (berlaku untuk semua broadcast kolaborasi).
        try {
            broadcast(new SurveySectionFocus(
                (int) $this->surveyId,
                (int) auth()->id(),
                auth()->user()->name,
                $subCategoryId ? (int) $subCategoryId : null,
                $this->activeCategory ? (int) $this->activeCategory : null,
            ));
        } catch (\Throwable $e) {
            // WebSocket down tidak boleh mengganggu pengisian form.
            Log::debug('SurveySectionFocus broadcast gagal: '.$e->getMessage());
        }
    }

    /**
     * Muat ulang isi form dari DB setelah kolaborator lain menyimpan —
     * dipanggil Alpine hanya bila form lokal bersih (tanpa perubahan
     * pending) agar isian user tidak tertimpa.
     */
    public function refreshCollaborativeState(string $editorName): void
    {
        if (! $this->editMode) {
            return;
        }

        $survey = Survey::findOrFail($this->surveyId);
        $this->authorize('update', $survey);
        $this->survey = $survey;

        $this->isNewShip = $survey->ship_id === null;
        $this->ship_id = $this->isNewShip ? self::NEW_SHIP_VALUE : $survey->ship_id;
        // "Kapal Baru" wajib mode edit aktif — samakan perilaku mount().
        $this->editShipData = $this->editShipData || $this->isNewShip;
        $this->survey_template_id = $survey->survey_template_id;
        $this->survey_date = $survey->survey_date?->format('Y-m-d');
        $this->surveyor = $survey->surveyor;
        $this->location = $survey->location;
        $this->status = $survey->status->value;
        $this->notes = $survey->notes;

        $this->loadShipData($survey);

        $this->structureTree = $survey->structure
            ?? app(SurveyService::class)->buildStructureSnapshot($survey->survey_template_id)
            ?? ['categories' => []];
        $this->structureIndexCache = null;

        $this->loadResponses();
        $this->fixActiveTabs();
        $this->loadedVersion = $survey->updated_at?->toIso8601String();
        $this->resetCollabBaseline();

        $this->notifyInfo("Survey diperbarui oleh {$editorName} — data terbaru dimuat otomatis.");
    }

    /**
     * Beri tahu editor lain di presence channel bahwa survey baru disimpan.
     */
    protected function broadcastContentUpdated(): void
    {
        try {
            broadcast(new SurveyContentUpdated(
                (int) $this->surveyId,
                (int) auth()->id(),
                auth()->user()->name,
                version: $this->loadedVersion,
            ));
        } catch (\Throwable $e) {
            // Kegagalan broadcast tidak boleh membatalkan simpan.
            Log::debug('SurveyContentUpdated broadcast gagal: '.$e->getMessage());
        }
    }

    /**
     * Normalisasi input snapshot kapal sebelum validasi (trim, kosong →
     * null, uppercase IMO/Call Sign) agar yang divalidasi = yang tersimpan.
     */
    protected function normalizeShipInputs(): void
    {
        $this->shipData = array_merge($this->emptyShipData(), $this->normalizedShipData());
    }

    /**
     * Attributes snapshot kapal yang sudah dinormalisasi (tanpa mengubah state).
     */
    protected function normalizedShipData(): array
    {
        $normalized = app(ShipService::class)->normalizeData($this->shipData);

        return array_intersect_key($normalized, array_flip(ShipService::SNAPSHOT_FIELDS));
    }

    /**
     * Snapshot {attributes, certificates} untuk kolom surveys.ship_snapshot.
     */
    protected function buildShipSnapshot(): array
    {
        return [
            'attributes' => $this->normalizedShipData(),
            'certificates' => app(ShipService::class)->normalizedCertificates($this->shipCertificates),
        ];
    }

    /**
     * Data preview sinkron master: kapal target (Call Sign → fallback
     * ship_id) + diff field/sertifikat. Untuk "Kapal Baru" tanpa kecocokan,
     * target null + flag creating (record master akan dibuat).
     */
    protected function buildShipSyncPreview(): ?array
    {
        $shipService = app(ShipService::class);
        $data = $this->normalizedShipData();
        $target = $shipService->resolveForSnapshot($data, $this->isNewShip ? null : (int) $this->ship_id);

        if (! $target) {
            if (! $this->isNewShip) {
                return null;
            }

            // Tidak ada master yang cocok — record baru akan dibuat; diff
            // melawan kapal kosong menampilkan seluruh isian sebagai "baru".
            return [
                'target' => null,
                'creating' => true,
                'matched_by_call_sign' => false,
                'diff' => $shipService->snapshotDiff(new Ship, $data, $this->shipCertificates),
            ];
        }

        return [
            'target' => $target,
            'creating' => false,
            // Match lewat Call Sign menghasilkan kapal berbeda dari yang
            // dipilih di form → tampilkan sebagai peringatan di modal.
            'matched_by_call_sign' => $target->id !== (int) $this->ship_id,
            'diff' => $shipService->snapshotDiff($target, $data, $this->shipCertificates),
        ];
    }

    /**
     * Cek IMO/Call Sign snapshot tidak bentrok dengan kapal LAIN sebelum
     * ditimpa/dibuat — mencegah unique violation di level DB. $target null
     * pada alur "Kapal Baru" (cek ke semua kapal, tanpa pengecualian).
     */
    protected function assertShipIdentifiersFree(?Ship $target): void
    {
        $imoRule = Rule::unique('ships', 'imo_number');
        $callSignRule = Rule::unique('ships', 'call_sign');

        if ($target) {
            $imoRule->ignore($target->id);
            $callSignRule->ignore($target->id);
        }

        try {
            Validator::make([
                'shipData' => [
                    'imo_number' => $this->shipData['imo_number'] ?? null,
                    'call_sign' => $this->shipData['call_sign'] ?? null,
                ],
            ], [
                'shipData.imo_number' => ['nullable', 'string', 'max:20', $imoRule],
                'shipData.call_sign' => ['nullable', 'string', 'max:20', $callSignRule],
            ], attributes: [
                'shipData.imo_number' => 'nomor IMO',
                'shipData.call_sign' => 'call sign',
            ])->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }
    }

    /**
     * Bila status Selesai dan snapshot berbeda dari master → buka modal
     * konfirmasi sinkron (return true = save di-intercept, belum persist).
     */
    protected function offerShipSync(): bool
    {
        if ($this->status !== SurveyStatus::Completed->value) {
            return false;
        }

        $preview = $this->buildShipSyncPreview();

        if (! $preview) {
            return false;
        }

        $this->assertShipIdentifiersFree($preview['target']);

        $diff = $preview['diff'];
        if (empty($diff['fields']) && ! $diff['certificates_changed']) {
            // Tidak ada perubahan pada master → simpan normal tanpa modal.
            return false;
        }

        $this->shipSyncMode = 'save';
        $this->dispatch('open-modal', 'survey-ship-sync-modal');

        return true;
    }

    /**
     * Tombol "Tarik Master" menimpa seluruh isian Data Kapal — minta
     * konfirmasi dulu agar koreksi sementara tidak hilang tak sengaja.
     */
    public function confirmReloadShipFromMaster(): void
    {
        $this->authorizeFormMutation();

        $this->dispatch('confirm-pull-master',
            action: 'reloadShipFromMaster',
            title: 'Tarik Data dari Master?',
            message: 'Seluruh isian Data Kapal (particulars & sertifikat) akan ditimpa dengan data dari Master Data. Perubahan yang belum disimpan akan hilang.',
            confirmText: 'Ya, Tarik Data',
            cancelText: 'Batal',
            type: 'warning',
        );
    }

    /**
     * Tombol "Tarik Master" di langkah Data Kapal — isi ulang snapshot dari
     * record master yang cocok (Call Sign → ship_id). Berguna bila master
     * baru saja dikoreksi dan survey ingin mengikuti data terkini.
     */
    public function reloadShipFromMaster(): void
    {
        $this->authorizeFormMutation();
        $this->dispatch('confirm-pull-master-close');
        $this->normalizeShipInputs();

        $target = app(ShipService::class)->resolveForSnapshot(
            $this->normalizedShipData(),
            $this->isNewShip ? null : (int) $this->ship_id
        );

        if (! $target) {
            $this->notifyInfo('Tidak ada kapal yang cocok di master data.');

            return;
        }

        $this->fillShipData($target);
        $this->dispatch('ship-data-changed');
        $this->notifySuccess('Data kapal dimuat ulang dari master data.');
    }

    /**
     * Tombol "Update Master Kapal" di langkah Data Kapal — buka modal diff
     * yang sama; konfirmasi hanya menimpa master (survey tidak tersimpan).
     */
    public function updateMasterShip(): void
    {
        $this->authorizeFormMutation();
        $this->normalizeShipInputs();
        $this->validateShipInputs();

        $preview = $this->buildShipSyncPreview();

        if (! $preview) {
            $this->notifyInfo('Tidak ada kapal yang cocok untuk disinkronkan.');

            return;
        }

        $this->assertShipIdentifiersFree($preview['target']);

        $diff = $preview['diff'];
        if (empty($diff['fields']) && ! $diff['certificates_changed']) {
            $this->notifyInfo('Tidak ada perubahan data kapal pada master.');

            return;
        }

        $this->shipSyncMode = 'manual';
        $this->dispatch('open-modal', 'survey-ship-sync-modal');
    }

    /**
     * Validasi subset field kapal (particulars + sertifikat) — dipakai alur
     * Update Master yang tidak menyimpan seluruh form.
     */
    protected function validateShipInputs(): void
    {
        $rules = array_filter(
            $this->rules(),
            fn ($key) => str_starts_with($key, 'shipData.') || str_starts_with($key, 'shipCertificates'),
            ARRAY_FILTER_USE_KEY
        );

        try {
            Validator::make(
                ['shipData' => $this->shipData, 'shipCertificates' => $this->shipCertificates],
                $rules,
                attributes: $this->validationAttributes()
            )->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }
    }

    /**
     * Konfirmasi modal mode 'manual': timpa master data kapal dari snapshot
     * form (atau buat record baru untuk "Kapal Baru") — tanpa menyimpan survey.
     */
    protected function applySnapshotToMaster(): void
    {
        $this->authorizeFormMutation();
        $this->validateShipInputs();

        $shipService = app(ShipService::class);
        $data = $this->normalizedShipData();
        $certificates = $shipService->normalizedCertificates($this->shipCertificates);
        $target = $shipService->resolveForSnapshot($data, $this->isNewShip ? null : (int) $this->ship_id);

        try {
            if ($target) {
                $shipService->update($target, $data, $certificates);
                $message = 'Master data kapal berhasil diperbarui.';
            } elseif ($this->isNewShip) {
                $ship = $shipService->create($data, $certificates);
                // Form kini menunjuk kapal sungguhan; di edit mode tautkan
                // survey ke record baru agar relasi konsisten.
                $this->ship_id = (string) $ship->id;
                $this->isNewShip = false;
                if ($this->editMode) {
                    $this->survey->update(['ship_id' => $ship->id]);
                }
                $message = 'Kapal baru berhasil dibuat di Master Data.';
            } else {
                $this->notifyInfo('Tidak ada kapal yang cocok untuk disinkronkan.');
                $this->dispatch('close-modal', 'survey-ship-sync-modal');

                return;
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Sinkron master kapal gagal: '.$e->getMessage());
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
            $this->dispatch('close-modal', 'survey-ship-sync-modal');

            return;
        }

        $this->dispatch('close-modal', 'survey-ship-sync-modal');
        $this->notifySuccess($message);
    }

    /**
     * Confirm modal: 'save' → simpan survey + timpa master; 'manual' → timpa
     * master saja (survey tetap di form).
     */
    public function confirmShipSync(SurveyService $service)
    {
        $this->normalizeShipInputs();

        if ($this->shipSyncMode === 'manual') {
            $this->applySnapshotToMaster();

            return;
        }

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('close-modal', 'survey-ship-sync-modal');
            $this->notifyValidationError($e);
            throw $e;
        }

        $this->dispatch('close-modal', 'survey-ship-sync-modal');

        return $this->persistSurvey($service, true);
    }

    /**
     * Batal di modal sinkron — survey belum tersimpan; user kembali ke form
     * untuk memperbaiki data kapal bila ada yang salah.
     */
    public function closeShipSyncModal(): void
    {
        $this->dispatch('close-modal', 'survey-ship-sync-modal');
    }

    public function cancel()
    {
        // Clear draft when user intentionally leaves the form
        $this->clearDraft();

        return $this->redirect(route('surveys.index'), navigate: true);
    }

    /**
     * Toggle auto-save draft on/off (preferensi per user, permanen).
     */
    public function toggleAutoSave()
    {
        $this->autoSave = ! $this->autoSave;
        Cache::forever($this->autoSaveKey(), $this->autoSave);
    }

    protected function autoSaveKey(): string
    {
        return 'survey_autosave:'.auth()->id();
    }

    /**
     * Toggle mode edit struktur (kategori/sub kategori/grup item/item).
     * Sengaja tidak persist di session — selalu mulai nonaktif demi keamanan.
     */
    public function toggleEditStructure()
    {
        $this->authorizeFormMutation();
        $this->editStructure = ! $this->editStructure;
        $this->notifyInfo($this->editStructure
            ? 'Mode edit struktur aktif. Tambah, ubah nama, atau hapus node — tersimpan saat survey disimpan.'
            : 'Mode edit struktur nonaktif.');
    }

    /**
     * Buka modal tambah node struktur (kategori/sub kategori/grup item/item).
     */
    public function openAddNode(string $level, $parentId = null): void
    {
        $this->authorizeFormMutation();

        if (! $this->editStructure) {
            return;
        }

        $this->resetNodeModal();
        $this->nodeModalMode = 'add';
        $this->nodeLevel = $level;
        $this->nodeParentId = $parentId === null ? null : (int) $parentId;

        // Item baru di grup yang sudah berisi mewarisi tipe & label skor grup.
        if ($level === 'item' && $this->nodeParentId) {
            $group = $this->findNodeByLevel('item_group', $this->nodeParentId);
            $firstItem = $group['items'][0] ?? null;
            if ($firstItem) {
                $this->nodeTypeLocked = true;
                $this->nodeItemType = $firstItem['item_type'] ?? 'score';
                $this->nodeScoreLabels = implode(', ', $firstItem['score_labels'] ?? ['C', 'V']);
            }
        }

        $this->dispatch('open-modal', 'survey-node-modal');
    }

    /**
     * Buka modal ubah node struktur (nama/label; untuk item juga tipe & opsinya).
     */
    public function openEditNode(string $level, $id): void
    {
        $this->authorizeFormMutation();

        if (! $this->editStructure) {
            return;
        }

        $node = $this->findNodeByLevel($level, (int) $id);
        if (! $node) {
            return;
        }

        $this->resetNodeModal();
        $this->nodeModalMode = 'edit';
        $this->nodeLevel = $level;
        $this->nodeId = (int) $id;
        $this->nodeName = (string) ($node['label'] ?? $node['name'] ?? '');

        if ($level === 'item') {
            $this->nodeItemType = $node['item_type'] ?? 'score';
            $this->nodeScoreLabels = implode(', ', $node['score_labels'] ?? ['C', 'V']);
            $this->nodeHasDateFields = (bool) ($node['has_date_fields'] ?? false);

            // Tipe/label terkunci bila item berbagi grup dengan item lain.
            $group = $this->findGroupOfItem((int) $id);
            $this->nodeTypeLocked = count($group['items'] ?? []) > 1;
        }

        $this->dispatch('open-modal', 'survey-node-modal');
    }

    /**
     * Simpan node struktur dari modal — tambah baru atau update node lama.
     */
    public function saveNode(): void
    {
        $this->authorizeFormMutation();

        if (! $this->editStructure) {
            $this->dispatch('close-modal', 'survey-node-modal');

            return;
        }

        $rules = ['nodeName' => 'required|string|max:255'];
        if ($this->nodeLevel === 'item' && ! $this->nodeTypeLocked) {
            $rules['nodeItemType'] = 'required|in:'.implode(',', \App\Enums\SurveyItemType::values());
            if ($this->nodeItemType === \App\Enums\SurveyItemType::Score->value) {
                $rules['nodeScoreLabels'] = 'required|string|max:120';
            }
        }

        try {
            $this->validate($rules, [], [
                'nodeName' => 'nama',
                'nodeItemType' => 'tipe item',
                'nodeScoreLabels' => 'label skor',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);

            throw $e;
        }

        $this->nodeModalMode === 'add'
            ? $this->addStructureNode()
            : $this->updateStructureNode();

        $this->structureIndexCache = null;
        $this->resetNodeModal();
        $this->dispatch('close-modal', 'survey-node-modal');
        $this->dispatch('structure-node-saved');
        $this->notifyInfo('Struktur diperbarui — perubahan tersimpan permanen saat survey disimpan.');
    }

    public function closeNodeModal(): void
    {
        $this->resetNodeModal();
        $this->dispatch('close-modal', 'survey-node-modal');
    }

    /**
     * Tambah node baru ke working copy struktur (id sintetis unik per survey).
     */
    protected function addStructureNode(): void
    {
        $this->structureTree['categories'] ??= [];

        $id = $this->nextNodeId();
        $name = trim($this->nodeName);

        match ($this->nodeLevel) {
            'category' => $this->structureTree['categories'][] = [
                'id' => $id, 'label' => $name, 'order_num' => 0, 'subCategories' => [],
            ],
            'sub_category' => $this->appendChildNode('category', $this->nodeParentId, [
                'id' => $id, 'name' => $name, 'order_num' => 0, 'itemGroups' => [],
            ]),
            'item_group' => $this->appendChildNode('sub_category', $this->nodeParentId, [
                'id' => $id, 'name' => $name, 'order_num' => 0, 'items' => [],
            ]),
            'item' => $this->appendChildNode('item_group', $this->nodeParentId, $this->buildItemNode($id, $name)),
            default => null,
        };

        $this->structureTree['categories'] = $this->renumberNodes($this->structureTree['categories']);

        // Fokuskan tab ke node baru agar langsung terlihat.
        if ($this->nodeLevel === 'category') {
            $this->activeCategory = $id;
            $this->activeSubCategory = null;
        } elseif ($this->nodeLevel === 'sub_category') {
            $this->activeCategory = $this->nodeParentId;
            $this->activeSubCategory = $id;
        }

        $this->persistActiveTab();
    }

    /**
     * Update node struktur: rename label/name; untuk item juga tipe & opsinya
     * (tipe/label hanya bisa diubah bila tidak terkunci oleh grup).
     */
    protected function updateStructureNode(): void
    {
        $name = trim($this->nodeName);

        if ($this->nodeLevel !== 'item') {
            $this->updateNodeFields($this->nodeLevel, $this->nodeId, [
                $this->nodeLevel === 'category' ? 'label' : 'name' => $name,
            ]);

            return;
        }

        $fields = ['name' => $name];

        if (! $this->nodeTypeLocked) {
            $isScore = $this->nodeItemType === \App\Enums\SurveyItemType::Score->value;
            $labels = $isScore ? $this->parseScoreLabels($this->nodeScoreLabels) : [];

            $fields['item_type'] = $this->nodeItemType;
            $fields['score_labels'] = $labels;
            $fields['has_date_fields'] = $isScore ? $this->nodeHasDateFields : false;

            // Re-align response agar sesuai tipe & label skor baru.
            $this->responses[$this->nodeId] = $this->realignItemResponse(
                $this->responses[$this->nodeId] ?? [],
                $this->nodeItemType,
                $labels
            );
        } elseif (($this->findNodeByLevel('item', $this->nodeId)['item_type'] ?? null) === 'score') {
            // Tipe terkunci — has_date_fields tetap bisa diubah pada item skor.
            $fields['has_date_fields'] = $this->nodeHasDateFields;
        }

        $this->updateNodeFields('item', $this->nodeId, $fields);
    }

    /**
     * Bangun node item baru + inisialisasi response kosongnya.
     */
    protected function buildItemNode(int $id, string $name): array
    {
        $isScore = $this->nodeItemType === \App\Enums\SurveyItemType::Score->value;

        $node = [
            'id' => $id,
            'name' => $name,
            'order_num' => 0,
            'item_type' => $this->nodeItemType,
            'score_labels' => $isScore ? $this->parseScoreLabels($this->nodeScoreLabels) : [],
            'has_date_fields' => $isScore ? $this->nodeHasDateFields : false,
        ];

        $this->responses[$id] = $this->emptyItemResponse($node['item_type'], $node['score_labels']);

        return $node;
    }

    /**
     * Shape response kosong untuk item baru sesuai tipenya.
     */
    protected function emptyItemResponse(string $type, array $labels = []): array
    {
        if ($type === \App\Enums\SurveyItemType::Inventory->value) {
            return ['qty' => '', 'specification' => ''];
        }

        return [
            'scores' => collect($labels ?: ['C', 'V'])->mapWithKeys(fn ($l) => [$l => ''])->all(),
            'date_issued' => '',
            'date_expired' => '',
        ];
    }

    /**
     * Re-align response item setelah tipe/label berubah — nilai label yang
     * masih ada dipertahankan, sisanya di-reset ke shape kosong.
     */
    protected function realignItemResponse(array $response, string $type, array $labels): array
    {
        if ($type === \App\Enums\SurveyItemType::Inventory->value) {
            return [
                'qty' => $response['qty'] ?? '',
                'specification' => $response['specification'] ?? '',
            ];
        }

        $scores = [];
        foreach ($labels as $label) {
            $scores[$label] = $response['scores'][$label] ?? '';
        }

        return [
            'scores' => $scores,
            'date_issued' => $response['date_issued'] ?? '',
            'date_expired' => $response['date_expired'] ?? '',
        ];
    }

    /**
     * Parse input label skor "C, V, F" -> ['C', 'V', 'F'].
     */
    protected function parseScoreLabels(string $raw): array
    {
        $labels = array_filter(array_map('trim', explode(',', $raw)), fn ($l) => $l !== '');

        return array_values(array_unique($labels)) ?: ['C', 'V'];
    }

    /**
     * Id node baru: max semua id di tree + 1 (unik per survey — snapshot
     * lokal, tidak join ke tabel template).
     */
    protected function nextNodeId(): int
    {
        $max = 0;
        $walk = function (array $nodes) use (&$walk, &$max) {
            foreach ($nodes as $node) {
                $max = max($max, (int) ($node['id'] ?? 0));
                foreach (['subCategories', 'itemGroups', 'items'] as $key) {
                    $walk($node[$key] ?? []);
                }
            }
        };
        $walk($this->structureTree['categories'] ?? []);

        return $max + 1;
    }

    /**
     * Append child ke node parent dengan id tertentu. Parent dicari dengan
     * scope level (id antar-level bisa bertabrakan — berasal dari tabel berbeda).
     * $parentLevel: 'category' | 'sub_category' | 'item_group'.
     */
    protected function appendChildNode(string $parentLevel, ?int $parentId, array $child): bool
    {
        if ($parentId === null) {
            return false;
        }

        $this->structureTree['categories'] ??= [];

        foreach ($this->structureTree['categories'] as &$cat) {
            if ($parentLevel === 'category' && ($cat['id'] ?? null) === $parentId) {
                $cat['subCategories'][] = $child;

                return true;
            }
            $cat['subCategories'] ??= [];
            foreach ($cat['subCategories'] as &$sc) {
                if ($parentLevel === 'sub_category' && ($sc['id'] ?? null) === $parentId) {
                    $sc['itemGroups'][] = $child;

                    return true;
                }
                $sc['itemGroups'] ??= [];
                foreach ($sc['itemGroups'] as &$ig) {
                    if ($parentLevel === 'item_group' && ($ig['id'] ?? null) === $parentId) {
                        $ig['items'][] = $child;

                        return true;
                    }
                }
                unset($ig);
            }
            unset($sc);
        }
        unset($cat);

        return false;
    }

    /**
     * Merge field ke node dengan level + id tertentu (in-place).
     * Scope level wajib — id antar-level bisa bertabrakan.
     */
    protected function updateNodeFields(string $level, int $id, array $fields): bool
    {
        $this->structureTree['categories'] ??= [];

        foreach ($this->structureTree['categories'] as &$cat) {
            if ($level === 'category' && ($cat['id'] ?? null) === $id) {
                $cat = $fields + $cat;

                return true;
            }
            $cat['subCategories'] ??= [];
            foreach ($cat['subCategories'] as &$sc) {
                if ($level === 'sub_category' && ($sc['id'] ?? null) === $id) {
                    $sc = $fields + $sc;

                    return true;
                }
                $sc['itemGroups'] ??= [];
                foreach ($sc['itemGroups'] as &$ig) {
                    if ($level === 'item_group' && ($ig['id'] ?? null) === $id) {
                        $ig = $fields + $ig;

                        return true;
                    }
                    $ig['items'] ??= [];
                    foreach ($ig['items'] as &$item) {
                        if ($level === 'item' && ($item['id'] ?? null) === $id) {
                            $item = $fields + $item;

                            return true;
                        }
                    }
                    unset($item);
                }
                unset($ig);
            }
            unset($sc);
        }
        unset($cat);

        return false;
    }

    /**
     * Cari grup item yang memuat item dengan id tertentu.
     */
    protected function findGroupOfItem(int $itemId): ?array
    {
        $walk = function (array $nodes) use (&$walk, $itemId) {
            foreach ($nodes as $node) {
                if (isset($node['items']) && collect($node['items'])->contains('id', $itemId)) {
                    return $node;
                }
                foreach (['subCategories', 'itemGroups'] as $key) {
                    if ($found = $walk($node[$key] ?? [])) {
                        return $found;
                    }
                }
            }

            return null;
        };

        return $walk($this->structureTree['categories'] ?? []);
    }

    protected function resetNodeModal(): void
    {
        $this->nodeModalMode = 'add';
        $this->nodeLevel = 'item';
        $this->nodeParentId = null;
        $this->nodeId = null;
        $this->nodeName = '';
        $this->nodeItemType = 'score';
        $this->nodeScoreLabels = 'C, V';
        $this->nodeHasDateFields = false;
        $this->nodeTypeLocked = false;
    }

    /**
     * Minta konfirmasi sebelum menghapus node struktur.
     */
    public function confirmRemoveNode(string $level, $id): void
    {
        $this->authorizeFormMutation();

        if (! $this->editStructure) {
            return;
        }

        $node = $this->findNodeByLevel($level, (int) $id);
        if (! $node) {
            return;
        }

        $labels = [
            'category' => 'kategori',
            'sub_category' => 'sub kategori',
            'item_group' => 'grup item',
            'item' => 'item',
        ];
        $label = $labels[$level] ?? 'node';
        $name = $node['label'] ?? $node['name'] ?? '#'.$id;

        $this->dispatch('confirm-remove-node',
            action: 'removeStructureNode',
            actionParams: [$level, $id],
            title: 'Hapus '.ucfirst($label),
            message: ucfirst($label).' "'.$name.'" beserta seluruh isinya (skor, qty, catatan) akan dihapus dari survey ini.',
            confirmText: 'Ya, Hapus',
            cancelText: 'Batal',
            type: 'danger',
        );
    }

    /**
     * Hapus node struktur dari working copy + bersihkan data terkait
     * (responses & group notes yang merujuk node di bawahnya).
     */
    public function removeStructureNode(string $level, $id): void
    {
        // Guard server-side — modal hanya bisa dibuka saat mode aktif.
        if (! $this->editStructure) {
            $this->dispatch('confirm-remove-node-close');

            return;
        }

        $this->authorizeFormMutation();

        $id = (int) $id;
        $removed = $level === 'category'
            ? $this->removeFromList($this->structureTree['categories'], $id)
            : $this->removeFromTree($this->structureTree['categories'], $level, $id);

        if (! $removed) {
            $this->dispatch('confirm-remove-node-close');

            return;
        }

        [$itemIds, $groupIds] = $this->descendantIds($removed);
        foreach ($itemIds as $itemId) {
            unset($this->responses[$itemId]);
        }
        foreach ($groupIds as $groupId) {
            unset($this->groupNotes[$groupId]);
        }

        $this->structureTree['categories'] = $this->renumberNodes($this->structureTree['categories'] ?? []);
        $this->structureIndexCache = null;
        $this->fixActiveTabs();

        $this->dispatch('confirm-remove-node-close');
        $this->dispatch('structure-node-removed');
        $this->notifyInfo('Struktur berhasil dihapus dari survey ini.');
    }

    /**
     * Cari node di tree dengan scope level — WAJIB level-aware karena id
     * node antar-level bisa bertabrakan (id berasal dari tabel berbeda:
     * kategori id=1, item id=1, dst).
     */
    protected function findNodeByLevel(string $level, int $id): ?array
    {
        foreach ($this->structureTree['categories'] ?? [] as $cat) {
            if ($level === 'category' && ($cat['id'] ?? null) === $id) {
                return $cat;
            }
            foreach ($cat['subCategories'] ?? [] as $sc) {
                if ($level === 'sub_category' && ($sc['id'] ?? null) === $id) {
                    return $sc;
                }
                foreach ($sc['itemGroups'] ?? [] as $ig) {
                    if ($level === 'item_group' && ($ig['id'] ?? null) === $id) {
                        return $ig;
                    }
                    foreach ($ig['items'] ?? [] as $item) {
                        if ($level === 'item' && ($item['id'] ?? null) === $id) {
                            return $item;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Hapus elemen dengan id dari list (in-place), kembalikan node yang dihapus.
     */
    protected function removeFromList(array &$list, int $id): ?array
    {
        foreach ($list as $i => $node) {
            if (($node['id'] ?? null) === $id) {
                $removed = $list[$i];
                array_splice($list, $i, 1);

                return $removed;
            }
        }

        return null;
    }

    /**
     * Cari parent yang memuat node target lalu hapus dari list anaknya.
     */
    protected function removeFromTree(array &$nodes, string $level, int $id): ?array
    {
        $childKey = [
            'sub_category' => 'subCategories',
            'item_group' => 'itemGroups',
            'item' => 'items',
        ][$level] ?? null;

        if (! $childKey) {
            return null;
        }

        foreach ($nodes as &$node) {
            if (! empty($node[$childKey]) && ($removed = $this->removeFromList($node[$childKey], $id))) {
                return $removed;
            }
            foreach (['subCategories', 'itemGroups', 'items'] as $key) {
                if (! empty($node[$key]) && ($found = $this->removeFromTree($node[$key], $level, $id))) {
                    return $found;
                }
            }
        }
        unset($node);

        return null;
    }

    /**
     * Kumpulkan semua id item & item-group di bawah node (termasuk node itu
     * sendiri bila ia item/group) — dipakai untuk cleanup data terkait.
     */
    protected function descendantIds(array $node): array
    {
        $itemIds = [];
        $groupIds = [];

        $walk = function (array $n) use (&$walk, &$itemIds, &$groupIds) {
            if (isset($n['item_type'])) {
                $itemIds[] = $n['id'];

                return;
            }
            if (isset($n['items'])) {
                $groupIds[] = $n['id'];
                foreach ($n['items'] as $item) {
                    $walk($item);
                }

                return;
            }
            foreach (['subCategories', 'itemGroups'] as $key) {
                foreach ($n[$key] ?? [] as $child) {
                    $walk($child);
                }
            }
        };

        $walk($node);

        return [$itemIds, $groupIds];
    }

    /**
     * Geser urutan node satu posisi (up = sebelumnya, down = berikutnya)
     * di dalam list saudaranya. Nilai respon ikut item secara otomatis
     * karena responses ter-keyed by item id; tab aktif juga id-based.
     */
    public function moveStructureNode(string $level, $id, string $direction): void
    {
        $this->authorizeFormMutation();

        if (! $this->editStructure) {
            return;
        }

        $id = (int) $id;
        $offset = $direction === 'up' ? -1 : 1;
        $this->structureTree['categories'] ??= [];

        $found = $level === 'category'
            ? $this->moveNodeInList($this->structureTree['categories'], $id, $offset)
            : $this->moveNodeInTree($this->structureTree['categories'], $level, $id, $offset);

        if (! $found) {
            return;
        }

        $this->structureTree['categories'] = $this->renumberNodes($this->structureTree['categories']);
        $this->structureIndexCache = null;
    }

    /**
     * Swap node dengan $id terhadap tetangganya di dalam list saudara.
     * Return true bila node ditemukan (di batas ujung tidak swap, tapi
     * tetap handled agar traversal tidak lanjut ke cabang lain).
     */
    protected function moveNodeInList(array &$list, int $id, int $offset): bool
    {
        foreach (array_keys($list) as $i) {
            if (($list[$i]['id'] ?? null) === $id) {
                $j = $i + $offset;
                if (isset($list[$j])) {
                    [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Cari list saudara node (level+id) di dalam tree lalu geser —
     * pola traversal sama seperti removeFromTree.
     */
    protected function moveNodeInTree(array &$nodes, string $level, int $id, int $offset): bool
    {
        $childKey = [
            'sub_category' => 'subCategories',
            'item_group' => 'itemGroups',
            'item' => 'items',
        ][$level] ?? null;

        if (! $childKey) {
            return false;
        }

        foreach ($nodes as &$node) {
            if (! empty($node[$childKey]) && $this->moveNodeInList($node[$childKey], $id, $offset)) {
                return true;
            }
            foreach (['subCategories', 'itemGroups'] as $key) {
                if (! empty($node[$key]) && $this->moveNodeInTree($node[$key], $level, $id, $offset)) {
                    return true;
                }
            }
        }
        unset($node);

        return false;
    }

    /**
     * Renumber order_num secara sekuensial di semua level (sub kategori, grup, item)
     * agar penomoran tampil berurutan tanpa loncat setelah ada node dihapus.
     * Rekursif — node children di-level berapapun ikut dinormalisasi.
     */
    protected function renumberNodes(array $nodes): array
    {
        foreach ($nodes as $i => &$node) {
            $node['order_num'] = $i + 1;
            foreach (['subCategories', 'itemGroups', 'items'] as $key) {
                if (isset($node[$key]) && is_array($node[$key])) {
                    $node[$key] = $this->renumberNodes($node[$key]);
                }
            }
        }

        return $nodes;
    }

    /**
     * Perbaiki tab aktif bila node yang dihapus sedang aktif/berisi tab aktif.
     */
    protected function fixActiveTabs(): void
    {
        $cats = $this->categories;

        if (! $cats->contains('id', $this->activeCategory)) {
            $this->activeCategory = $cats->first()?->id;
        }

        $activeCat = $cats->firstWhere('id', $this->activeCategory);
        if (! $activeCat?->subCategories->contains('id', $this->activeSubCategory)) {
            $this->activeSubCategory = $activeCat?->subCategories->first()?->id;
        }

        $this->persistActiveTab();
    }

    /**
     * Key state kerja form (draft / tab / baseline sinkron) per user —
     * terpisah per mode agar draft create tidak tabrakan dengan draft
     * edit survey lain.
     *
     * Disimpan di Cache per-key, BUKAN session: session database ditulis
     * utuh di akhir setiap request, sehingga request paralel (wire:poll
     * notifikasi/chat di layout) menimpa balik nilai yang baru ditulis
     * form → tab kembali ke kategori lama saat refresh, baseline sinkron
     * hilang, dsb. Cache menulis tiap key secara independen.
     */
    protected function draftKey(): string
    {
        return 'survey_form:'.auth()->id().':'.($this->editMode
            ? 'edit_'.$this->surveyId
            : 'create_'.$this->survey_template_id);
    }

    /**
     * Key posisi tab kategori aktif — dipisah dari draft agar tersimpan
     * langsung saat ganti tab (tidak menunggu interval auto-save) dan
     * bertahan saat refresh meski auto-save nonaktif.
     */
    protected function tabKey(): string
    {
        return $this->draftKey().':tab';
    }

    protected function putWorkState(string $key, mixed $value): void
    {
        Cache::put($key, $value, now()->addHours(self::WORK_STATE_TTL_HOURS));
    }

    /**
     * Persist posisi tab (UI state murni) — dipanggil setiap kali tab
     * aktif berubah: setCategory, setSubCategory, append node, dan
     * fixActiveTabs.
     */
    protected function persistActiveTab(): void
    {
        $this->putWorkState($this->tabKey(), [
            'category' => $this->activeCategory,
            'sub' => $this->activeSubCategory,
        ]);
    }

    /**
     * Restore posisi tab — dipanggil di mount setelah loadDraft agar
     * nilai terbaru (yang ditulis tiap ganti tab) menang atas copy di
     * draft, dan tetap bisa di-override deep-link ?category=.
     */
    protected function restoreActiveTab(): void
    {
        $tab = Cache::get($this->tabKey());
        $activeCat = $this->categories->firstWhere('id', $tab['category'] ?? null);

        if (! $activeCat) {
            return;
        }

        $this->activeCategory = $activeCat->id;
        $subId = $tab['sub'] ?? null;
        $this->activeSubCategory = $activeCat->subCategories->contains('id', $subId)
            ? $subId
            : $activeCat->subCategories->first()?->id;
    }

    /**
     * Dipanggil Alpine (debounce) setiap form berubah: simpan draft
     * bila Auto Save aktif, dan — di mode edit — selalu sinkronkan field
     * yang berubah ke kolaborator (independen dari toggle Auto Save).
     */
    public function saveDraft()
    {
        $this->authorizeFormMutation();

        if ($this->autoSave) {
            $this->putWorkState($this->draftKey(), $this->draftPayload());
        }

        if ($this->editMode) {
            $this->syncCollaborators();
        }
    }

    /**
     * Terapkan perubahan per-field dari kolaborator (payload event draft).
     * Lewat request Livewire — bukan `$set` lokal di client — agar ikut
     * antrean commit: nilai tidak tertimpa respons request lain yang
     * sedang berjalan saat perubahan datang beruntun.
     *
     * Hanya path yang ada di snapshot kolaboratif yang diterima (whitelist),
     * dan baseline path tsb ikut diperbarui agar tidak dipantulkan balik.
     *
     * @param  array<string, mixed>  $changes
     */
    public function applyRemoteChanges(array $changes): void
    {
        if (! $this->editMode) {
            return;
        }

        $this->authorize('update', Survey::findOrFail($this->surveyId));

        $allowed = $this->collabSnapshot();
        $baseline = Cache::get($this->collabKey()) ?? $allowed;
        $appliedPaths = [];

        foreach ($changes as $path => $value) {
            if (! $this->isCollabPath($path, $allowed)) {
                continue;
            }

            [$prop, $key] = array_pad(explode('.', $path, 2), 2, null);
            if ($key === null) {
                $this->{$prop} = match ($prop) {
                    'isNewShip', 'editShipData' => (bool) $value,
                    'shipCertificates' => $this->certificatesWithKeys(is_array($value) ? $value : []),
                    default => $value,
                };
            } else {
                // Path bertingkat (responses.12.scores.C) — set hanya leaf
                // tsb, field lain di baris yang sama tidak tersentuh.
                $data = $this->{$prop};
                Arr::set($data, $key, $prop === 'groupNotes' && ! str_contains($key, '.') ? array_values((array) $value) : $value);
                $this->{$prop} = $data;
            }

            $appliedPaths[] = $path;
        }

        if ($appliedPaths === []) {
            return;
        }

        // Selaraskan baseline untuk path yang diterapkan (nilai pasca-
        // normalisasi, termasuk baris catatan yang hilang) agar diff
        // berikutnya tidak memantulkannya balik sebagai perubahan lokal.
        $snapshot = $this->collabSnapshot();
        foreach ($appliedPaths as $path) {
            $inScope = fn ($k) => $k === $path || str_starts_with($k, $path.'.');
            $baseline = array_filter($baseline, fn ($k) => ! $inScope($k), ARRAY_FILTER_USE_KEY)
                + array_filter($snapshot, $inScope, ARRAY_FILTER_USE_KEY);
        }

        $this->putWorkState($this->collabKey(), $baseline);

        // Draft gabungan ikut ditulis ke sesi penerima (bila Auto Save
        // aktif) — isian kolaborator tidak hilang saat halaman di-reload
        // dan indikator "Draft tersimpan" mencerminkan penulisan nyata.
        if ($this->autoSave) {
            $this->putWorkState($this->draftKey(), $this->draftPayload());
        }
    }

    protected function draftPayload(): array
    {
        return [
            'ship_id' => $this->ship_id,
            'is_new_ship' => $this->isNewShip,
            'edit_ship_data' => $this->editShipData,
            'shipData' => $this->shipData,
            'shipCertificates' => $this->shipCertificates,
            'survey_date' => $this->survey_date,
            'surveyor' => $this->surveyor,
            'location' => $this->location,
            'status' => $this->status,
            'notes' => $this->notes,
            'responses' => $this->responses,
            'groupNotes' => $this->groupNotes,
            'structure' => $this->structureTree,
            'activeCategory' => $this->activeCategory,
            'activeSubCategory' => $this->activeSubCategory,
            'user_name' => auth()->user()->name,
            'saved_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Snapshot datar field kolaboratif — key = path properti Livewire
     * sampai level FIELD (mis. `responses.12.scores.C`, `.date_issued`,
     * `groupNotes.5.0`), karena satu baris item bisa berisi beberapa
     * field yang diisi editor berbeda bersamaan. Struktur hanya di-hash:
     * perubahannya dikirim sebagai flag lalu remote memuat payload penuh.
     */
    protected function collabSnapshot(): array
    {
        $snapshot = [
            'ship_id' => $this->ship_id,
            'isNewShip' => $this->isNewShip,
            'editShipData' => $this->editShipData,
            'survey_date' => $this->survey_date,
            'surveyor' => $this->surveyor,
            'location' => $this->location,
            'status' => $this->status,
            'notes' => $this->notes,
            'shipCertificates' => $this->shipCertificates,
            'structureTree' => md5(json_encode($this->structureTree)),
        ];

        foreach ($this->shipData as $key => $value) {
            $snapshot["shipData.{$key}"] = $value;
        }
        $snapshot += Arr::dot($this->responses, 'responses.');

        // Catatan grup per baris catatan; grup kosong tetap punya key agar
        // penambahan catatan pertama terdeteksi sebagai perubahan daftar.
        foreach ($this->groupNotes as $groupId => $notes) {
            $notes = (array) $notes;
            if ($notes === []) {
                $snapshot["groupNotes.{$groupId}"] = [];
            } else {
                $snapshot += Arr::dot($notes, "groupNotes.{$groupId}.");
            }
        }

        return $snapshot;
    }

    /**
     * Path yang boleh diterapkan dari kolaborator: field yang ada di
     * snapshot, atau daftar/baris catatan pada grup yang sudah ada
     * (catatan bisa ditambah/dihapus sehingga index baru belum ada).
     */
    protected function isCollabPath(string $path, array $snapshot): bool
    {
        if ($path === 'structureTree') {
            return false;
        }

        if (array_key_exists($path, $snapshot)) {
            return true;
        }

        return preg_match('/^groupNotes\.(\d+)(?:\.\d+)?$/', $path, $m) === 1
            && array_key_exists((int) $m[1], $this->groupNotes);
    }

    /**
     * Baseline sinkron per editor (sesi) — diff berikutnya dihitung dari
     * sini, jadi hanya field yang benar-benar diubah editor ini yang terkirim.
     */
    protected function resetCollabBaseline(): void
    {
        $this->putWorkState($this->collabKey(), $this->collabSnapshot());
    }

    protected function collabKey(): string
    {
        return $this->draftKey().':collab';
    }

    /**
     * Broadcast field yang berubah sejak sinkron terakhir. Nilai ikut di
     * payload event (bukan sekadar ping) → remote langsung menerapkannya
     * per-field tanpa menimpa field lain yang sedang ia isi.
     */
    protected function syncCollaborators(): void
    {
        $baseline = Cache::get($this->collabKey());
        $current = $this->collabSnapshot();
        $this->putWorkState($this->collabKey(), $current);

        if (! is_array($baseline)) {
            return;
        }

        $structureChanged = ($baseline['structureTree'] ?? null) !== $current['structureTree'];
        unset($current['structureTree']);

        $changes = [];
        foreach ($current as $key => $value) {
            if (! array_key_exists($key, $baseline) || $baseline[$key] != $value) {
                $changes[$key] = $value;
            }
        }

        // Daftar catatan grup berubah panjang (tambah/hapus baris) → kirim
        // daftar utuh grup tsb agar baris yang hilang ikut terhapus di remote.
        $countsBefore = [];
        foreach (array_keys($baseline) as $key) {
            if (preg_match('/^groupNotes\.(\d+)\.\d+$/', $key, $m) === 1) {
                $countsBefore[(int) $m[1]] = ($countsBefore[(int) $m[1]] ?? 0) + 1;
            }
        }
        foreach ($this->groupNotes as $groupId => $notes) {
            if (($countsBefore[$groupId] ?? 0) === count((array) $notes)) {
                continue;
            }
            $group = "groupNotes.{$groupId}";
            $changes = array_filter($changes, fn ($k) => $k !== $group && ! str_starts_with($k, $group.'.'), ARRAY_FILTER_USE_KEY);
            $changes[$group] = array_values((array) $notes);
        }

        // Payload besar (mis. banyak field sekaligus) → kirim flag saja,
        // remote memuat payload penuh dari cache (batas pesan WebSocket).
        if ($structureChanged || strlen(json_encode($changes)) > 8000) {
            Cache::put($this->sharedDraftKey((int) auth()->id()), $this->draftPayload(), now()->addHours(2));
            $structureChanged = true;
            $changes = [];
        }

        if ($changes === [] && ! $structureChanged) {
            return;
        }

        try {
            broadcast(new SurveyContentUpdated(
                (int) $this->surveyId,
                (int) auth()->id(),
                auth()->user()->name,
                'draft',
                $changes,
                $structureChanged,
            ));
        } catch (\Throwable $e) {
            // WebSocket down tidak boleh mengganggu pengisian form.
            Log::debug('SurveyContentUpdated (draft) broadcast gagal: '.$e->getMessage());
        }
    }

    protected function sharedDraftKey(int $userId): string
    {
        return "survey_shared_draft:{$this->surveyId}:{$userId}";
    }

    /**
     * Muat payload penuh kolaborator dari cache — dipakai saat struktur
     * berubah atau perubahan terlalu besar untuk dikirim per-field.
     */
    public function applyRemoteDraft(int $userId): void
    {
        if (! $this->editMode) {
            return;
        }

        $survey = Survey::findOrFail($this->surveyId);
        $this->authorize('update', $survey);

        $draft = Cache::get($this->sharedDraftKey($userId));
        if (! is_array($draft) || $draft === []) {
            return;
        }

        $this->applyDraftPayload($draft);
        $this->fixActiveTabs();

        // Jadikan draft lokal juga — refresh halaman tetap memuat
        // isian yang sama, bukan versi DB yang lebih tua.
        $this->putWorkState($this->draftKey(), $draft);
        $this->hasDraft = true;
        $this->resetCollabBaseline();

        // Nama kolaborator ikut payload agar indikator live-sync hanya
        // tampil setelah draft benar-benar diterapkan (bukan no-op).
        $this->dispatch('remote-draft-applied', name: $draft['user_name'] ?? null, savedAt: $draft['saved_at'] ?? null);
    }

    /**
     * Restore draft from session (called in mount).
     */
    protected function loadDraft(): bool
    {
        $draft = Cache::get($this->draftKey());

        if (! $draft || empty($draft['responses'])) {
            return false;
        }

        $this->applyDraftPayload($draft);
        $this->restoreDraftTabs($draft);

        return true;
    }

    /**
     * Terapkan payload draft (sesi sendiri maupun draft bersama dari
     * kolaborator) ke state form. Tidak menyentuh tab aktif — navigasi
     * tab milik masing-masing editor (dipulihkan terpisah via
     * restoreDraftTabs untuk draft sesi).
     */
    protected function applyDraftPayload(array $draft): void
    {
        $this->ship_id = $draft['ship_id'] ?? $this->ship_id;
        $this->isNewShip = (bool) ($draft['is_new_ship'] ?? (($draft['ship_id'] ?? null) === self::NEW_SHIP_VALUE));
        $this->editShipData = (bool) ($draft['edit_ship_data'] ?? $this->isNewShip);
        $this->survey_date = $draft['survey_date'] ?? $this->survey_date;

        // Snapshot kapal ikut draft — override isian dari ship/master.
        if (is_array($draft['shipData'] ?? null)) {
            $this->shipData = array_merge($this->emptyShipData(), $draft['shipData']);
        }
        if (is_array($draft['shipCertificates'] ?? null)) {
            $this->shipCertificates = $this->certificatesWithKeys($draft['shipCertificates']);
        }
        $this->surveyor = $draft['surveyor'] ?? $this->surveyor;
        $this->location = $draft['location'] ?? $this->location;
        $this->status = $draft['status'] ?? $this->status;
        $this->notes = $draft['notes'] ?? $this->notes;

        // Restore working copy struktur bila draft menyimpan hasil pemangkasan,
        // lalu re-init responses/notes agar selaras dengan struktur itu.
        if (! empty($draft['structure'])) {
            $this->structureTree = $draft['structure'];
            $this->structureIndexCache = null;
            $this->editMode ? $this->loadResponses() : $this->initEmptyResponses();
        }

        // Merge draft responses with empty responses (in case template changed)
        foreach ($this->responses as $itemId => $empty) {
            if (isset($draft['responses'][$itemId])) {
                $this->responses[$itemId] = $draft['responses'][$itemId];
            }
        }

        // Merge draft group notes (strip obsolete per-item 'note' keys if any)
        foreach ($this->groupNotes as $groupId => $empty) {
            if (isset($draft['groupNotes'][$groupId])) {
                $this->groupNotes[$groupId] = $draft['groupNotes'][$groupId];
            }
        }
    }

    /**
     * Restore posisi tab dari payload draft sesi — hanya untuk draft
     * milik sendiri di mount, bukan untuk draft kolaborator.
     */
    protected function restoreDraftTabs(array $draft): void
    {
        $this->activeCategory = $draft['activeCategory'] ?? $this->activeCategory;

        // Restore sub-category tab, fall back ke sub pertama dari kategori aktif
        $activeCat = $this->categories->firstWhere('id', $this->activeCategory);
        $draftSubId = $draft['activeSubCategory'] ?? null;
        $this->activeSubCategory = $activeCat?->subCategories->contains('id', $draftSubId)
            ? $draftSubId
            : $activeCat?->subCategories->first()?->id;
    }

    /**
     * Clear draft after successful save.
     */
    protected function clearDraft(): void
    {
        Cache::forget($this->draftKey());
        Cache::forget($this->tabKey());
        Cache::forget($this->collabKey());

        if ($this->editMode) {
            Cache::forget($this->sharedDraftKey((int) auth()->id()));
        }
    }

    public function render()
    {
        return view('livewire.surveys.survey-form', [
            'categories' => $this->categories,
        ]);
    }
}
