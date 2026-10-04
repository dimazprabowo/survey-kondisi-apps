<?php

namespace App\Livewire\Surveys;

use App\Enums\SurveyStatus;
use App\Livewire\Traits\HasNotification;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyTemplate;
use App\Services\SurveyService;
use App\Services\SurveyTemplateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Livewire\Component;

class SurveyForm extends Component
{
    use AuthorizesRequests, HasNotification;

    public ?Survey $survey = null;

    public bool $editMode = false;

    // Header fields
    public $surveyId;

    public $ship_id;

    public $survey_template_id;

    public $survey_date;

    public $surveyor;

    public $location;

    public $status = 'draft';

    public $notes;

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

    // Working copy struktur survey (snapshot) — sumber tunggal render & hapus node
    public array $structureTree = ['categories' => []];

    // Mode hapus struktur (kategori/sub/grup/item) — toggle di header
    public bool $editStructure = false;

    // Per-request cache index hierarki struktur (tidak diserialisasi Livewire)
    protected ?array $structureIndexCache = null;

    public function mount(?Survey $survey = null)
    {
        $this->autoSave = session()->get('survey_autosave', true);

        if ($survey && $survey->exists) {
            $this->authorize('update', $survey);
            $this->survey = $survey;
            $this->editMode = true;
            $this->surveyId = $survey->id;
            $this->ship_id = $survey->ship_id;
            $this->survey_template_id = $survey->survey_template_id;
            $this->survey_date = $survey->survey_date?->format('Y-m-d');
            $this->surveyor = $survey->surveyor;
            $this->location = $survey->location;
            $this->status = $survey->status->value;
            $this->notes = $survey->notes;

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
        } else {
            $this->authorize('create', Survey::class);
            $this->survey_date = now()->format('Y-m-d');
            $this->status = SurveyStatus::Draft->value;

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

            // Working copy struktur dari template terpilih (snapshot awal)
            $this->structureTree = app(SurveyService::class)->buildStructureSnapshot($this->survey_template_id)
                ?? ['categories' => []];

            $this->initEmptyResponses();
            $firstCat = $this->categories->first();
            $this->activeCategory = $firstCat?->id ?? 1;
            $this->activeSubCategory = $firstCat?->subCategories->first()?->id;

            // Restore draft if exists (auto-recovery after reload)
            $this->hasDraft = $this->loadDraft();
        }
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
            'ship_id' => ['required', 'exists:ships,id'],
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
        ];
    }

    public function validationAttributes()
    {
        return [
            'ship_id' => 'kapal',
            'survey_template_id' => 'template',
            'survey_date' => 'tanggal survey',
            'surveyor' => 'surveyor',
            'location' => 'lokasi',
            'status' => 'status',
            'notes' => 'catatan',
            'groupNotes.*.*' => 'catatan grup item',
        ];
    }

    public function getShipOptionsProperty(): array
    {
        return Ship::active()->orderBy('name')->get()->map(fn ($ship) => [
            'value' => (string) $ship->id,
            'label' => $ship->name.($ship->year_built ? ' ('.$ship->year_built.')' : ''),
        ])->toArray();
    }

    public function getStatusOptionsProperty(): array
    {
        return collect(SurveyStatus::cases())->map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
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
    }

    public function setSubCategory($subCategoryId): void
    {
        $this->activeSubCategory = $subCategoryId;
    }

    public function addGroupNote($itemGroupId): void
    {
        $this->groupNotes[$itemGroupId] = $this->groupNotes[$itemGroupId] ?? [];
        $this->groupNotes[$itemGroupId][] = '';
    }

    public function confirmRemoveGroupNote($itemGroupId, $index): void
    {
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
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'ship_id' => $this->ship_id,
                'survey_template_id' => $this->survey_template_id,
                'survey_date' => $this->survey_date,
                'surveyor' => $this->surveyor,
                'location' => $this->location,
                'status' => $this->status,
                'notes' => $this->notes,
                // Struktur survey ikut tersimpan — hasil snapshot (create) atau
                // working copy yang mungkin sudah dipangkas (edit).
                'structure' => $this->structureTree,
            ];

            if ($this->editMode) {
                $survey = Survey::findOrFail($this->surveyId);
                $this->authorize('update', $survey);
                $service->update($survey, $data);
                $service->saveResponses($survey->id, $this->responses, $this->groupNotes);
                $this->clearDraft();
                $this->notifySuccess('Survey berhasil diupdate!');
            } else {
                $this->authorize('create', Survey::class);
                $survey = $service->create($data);
                $service->saveResponses($survey->id, $this->responses, $this->groupNotes);
                $this->clearDraft();
                $this->notifySuccess('Survey berhasil dibuat!');
            }

            return $this->redirect(route('surveys.show', $survey), navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function cancel()
    {
        // Clear draft when user intentionally leaves the form
        $this->clearDraft();

        return $this->redirect(route('surveys.index'), navigate: true);
    }

    /**
     * Toggle auto-save draft on/off (preferensi disimpan di session).
     */
    public function toggleAutoSave()
    {
        $this->autoSave = ! $this->autoSave;
        session()->put('survey_autosave', $this->autoSave);
    }

    /**
     * Toggle mode hapus struktur (kategori/sub kategori/grup item/item).
     * Sengaja tidak persist di session — selalu mulai nonaktif demi keamanan.
     */
    public function toggleEditStructure()
    {
        $this->editStructure = ! $this->editStructure;
        $this->notifyInfo($this->editStructure
            ? 'Mode hapus struktur aktif. Hapus node via ikon tempat sampah.'
            : 'Mode hapus struktur nonaktif.');
    }

    /**
     * Minta konfirmasi sebelum menghapus node struktur.
     */
    public function confirmRemoveNode(string $level, $id): void
    {
        if (! $this->editStructure) {
            return;
        }

        $node = $this->findNode($this->structureTree['categories'] ?? [], (int) $id);
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
     * Cari node (kategori/sub/grup/item) di tree berdasarkan id.
     */
    protected function findNode(array $nodes, int $id): ?array
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
            foreach (['subCategories', 'itemGroups', 'items'] as $key) {
                if ($found = $this->findNode($node[$key] ?? [], $id)) {
                    return $found;
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
    }

    /**
     * Session key untuk draft — terpisah per mode agar draft create
     * tidak tabrakan dengan draft edit survey lain.
     */
    protected function draftKey(): string
    {
        return $this->editMode
            ? 'survey_draft_edit_'.$this->surveyId
            : 'survey_draft_create_'.$this->survey_template_id;
    }

    /**
     * Save draft to Livewire session (called from Alpine auto-save).
     */
    public function saveDraft()
    {
        if (! $this->autoSave) {
            return;
        }

        session()->put($this->draftKey(), [
            'ship_id' => $this->ship_id,
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
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Restore draft from session (called in mount).
     */
    protected function loadDraft(): bool
    {
        $draft = session()->get($this->draftKey());

        if (! $draft || empty($draft['responses'])) {
            return false;
        }

        $this->ship_id = $draft['ship_id'];
        $this->survey_date = $draft['survey_date'];
        $this->surveyor = $draft['surveyor'];
        $this->location = $draft['location'];
        $this->status = $draft['status'];
        $this->notes = $draft['notes'];

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

        $this->activeCategory = $draft['activeCategory'] ?? $this->activeCategory;

        // Restore sub-category tab, fall back ke sub pertama dari kategori aktif
        $activeCat = $this->categories->firstWhere('id', $this->activeCategory);
        $draftSubId = $draft['activeSubCategory'] ?? null;
        $this->activeSubCategory = $activeCat?->subCategories->contains('id', $draftSubId)
            ? $draftSubId
            : $activeCat?->subCategories->first()?->id;

        return true;
    }

    /**
     * Clear draft after successful save.
     */
    protected function clearDraft(): void
    {
        session()->forget($this->draftKey());
    }

    public function render()
    {
        return view('livewire.surveys.survey-form', [
            'categories' => $this->categories,
        ]);
    }
}
