<?php

namespace App\Services;

use App\Models\SurveyCategory;
use App\Models\SurveyItem;
use App\Models\SurveyItemGroup;
use App\Models\SurveySubCategory;
use App\Models\SurveyTemplate;
use App\Traits\HasDynamicLike;
use App\Traits\SortsQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SurveyTemplateService
{
    use HasDynamicLike, SortsQuery;

    public function getFiltered(
        ?string $search = null,
        ?string $statusFilter = null,
        int $perPage = 15,
        ?string $sortField = null,
        string $sortDir = 'desc'
    ): LengthAwarePaginator {
        $query = SurveyTemplate::withCount(['surveys', 'categories']);

        if ($search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($search, $operator) {
                $q->where('name', $operator, "%{$search}%")
                    ->orWhere('code', $operator, "%{$search}%")
                    ->orWhere('description', $operator, "%{$search}%");
            });
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $sortable = [
            'name' => 'survey_templates.name',
            'code' => 'survey_templates.code',
            'categories_count' => 'categories_count',
            'surveys_count' => 'surveys_count',
            'is_active' => 'survey_templates.is_active',
            'created_at' => 'survey_templates.created_at',
        ];

        $this->applySorting($query, $sortable, $sortField, $sortDir, 'created_at', 'desc', 'survey_templates.id');

        return $query->paginate($perPage);
    }

    public function create(array $data): SurveyTemplate
    {
        return DB::transaction(function () use ($data) {
            $data['code'] = strtoupper($data['code'] ?? '');
            $data['created_by'] = $data['created_by'] ?? auth()->id();

            // Ensure only one default
            if (($data['is_default'] ?? false) === true) {
                SurveyTemplate::where('is_default', true)->update(['is_default' => false]);
            }

            return SurveyTemplate::create($data);
        });
    }

    public function update(SurveyTemplate $template, array $data): SurveyTemplate
    {
        DB::transaction(function () use ($template, $data) {
            if (isset($data['code'])) {
                $data['code'] = strtoupper($data['code']);
            }

            // Ensure only one default
            if (($data['is_default'] ?? false) === true && ! $template->is_default) {
                SurveyTemplate::where('is_default', true)
                    ->where('id', '!=', $template->id)
                    ->update(['is_default' => false]);
            }

            $template->update($data);
        });

        return $template->fresh();
    }

    public function delete(SurveyTemplate $template): void
    {
        if ($template->surveys()->exists()) {
            throw new \RuntimeException('Template tidak dapat dihapus karena masih digunakan oleh survey yang ada.');
        }

        if ($template->is_default) {
            throw new \RuntimeException('Template default tidak dapat dihapus.');
        }

        DB::transaction(function () use ($template) {
            // Cascade delete structure (FK cascadeOnDelete handles it)
            $template->delete();
        });
    }

    public function toggleStatus(SurveyTemplate $template): SurveyTemplate
    {
        $template->update(['is_active' => ! $template->is_active]);

        return $template;
    }

    /**
     * Duplicate a template with its full nested structure (deep copy).
     */
    public function duplicate(SurveyTemplate $source, array $overrides = []): SurveyTemplate
    {
        return DB::transaction(function () use ($source, $overrides) {
            $newTemplate = SurveyTemplate::create([
                'name' => $overrides['name'] ?? $source->name.' (Salinan)',
                'code' => strtoupper($overrides['code'] ?? $this->generateUniqueCode($source->code)),
                'description' => $overrides['description'] ?? $source->description,
                'is_active' => $overrides['is_active'] ?? true,
                'is_default' => false, // Copies are never default
                'created_by' => auth()->id(),
            ]);

            $source->load(['categories.subCategories.itemGroups.items']);

            foreach ($source->categories as $cat) {
                $newCat = SurveyCategory::create([
                    'survey_template_id' => $newTemplate->id,
                    'label' => $cat->label,
                    'order_num' => $cat->order_num,
                ]);

                foreach ($cat->subCategories as $sub) {
                    $newSub = SurveySubCategory::create([
                        'survey_category_id' => $newCat->id,
                        'order_num' => $sub->order_num,
                        'name' => $sub->name,
                    ]);

                    foreach ($sub->itemGroups as $ig) {
                        $newIg = SurveyItemGroup::create([
                            'survey_sub_category_id' => $newSub->id,
                            'name' => $ig->name,
                            'order_num' => $ig->order_num,
                        ]);

                        foreach ($ig->items as $item) {
                            SurveyItem::create([
                                'survey_item_group_id' => $newIg->id,
                                'name' => $item->name,
                                'item_type' => $item->item_type,
                                'score_labels' => $item->score_labels,
                                'has_date_fields' => $item->has_date_fields,
                                'order_num' => $item->order_num,
                            ]);
                        }
                    }
                }
            }

            return $newTemplate;
        });
    }

    /**
     * Save full nested structure from a form payload.
     * Payload format: [
     *   ['id' => null|int, 'code' => '', 'label' => '', 'order_num' => 1,
     *    'sub_categories' => [
     *      ['id' => null, 'name' => '', 'order_num' => 1, 'item_groups' => [
     *        ['id' => null, 'code' => '', 'name' => '', 'order_num' => 1, 'items' => [
     *          ['id' => null, 'code' => '', 'name' => '', 'score_labels' => [...], 'has_date_fields' => bool, 'order_num' => 1]
     *        ]]
     *      ]]
     *    ]]
     *   ]
     * ]
     */
    public function saveStructure(SurveyTemplate $template, array $categories): void
    {
        DB::transaction(function () use ($template, $categories) {
            // Track existing IDs to delete orphans
            $keepCatIds = [];
            $keepSubIds = [];
            $keepIgIds = [];
            $keepItemIds = [];

            $catOrder = 0;
            foreach ($categories as $catData) {
                $catOrder++;
                $catAttrs = [
                    'survey_template_id' => $template->id,
                    'label' => $catData['label'] ?? '',
                    'order_num' => $catData['order_num'] ?? $catOrder,
                ];

                if (! empty($catData['id'])) {
                    $cat = SurveyCategory::find($catData['id']);
                    if ($cat && $cat->survey_template_id === $template->id) {
                        $cat->update($catAttrs);
                        $keepCatIds[] = $cat->id;
                    } else {
                        $cat = SurveyCategory::create($catAttrs);
                        $keepCatIds[] = $cat->id;
                    }
                } else {
                    $cat = SurveyCategory::create($catAttrs);
                    $keepCatIds[] = $cat->id;
                }

                $subOrder = 0;
                foreach ($catData['sub_categories'] ?? [] as $subData) {
                    $subOrder++;
                    $subAttrs = [
                        'survey_category_id' => $cat->id,
                        'order_num' => $subData['order_num'] ?? $subOrder,
                        'name' => $subData['name'] ?? '',
                    ];

                    if (! empty($subData['id'])) {
                        $sub = SurveySubCategory::find($subData['id']);
                        if ($sub && $sub->survey_category_id === $cat->id) {
                            $sub->update($subAttrs);
                            $keepSubIds[] = $sub->id;
                        } else {
                            $sub = SurveySubCategory::create($subAttrs);
                            $keepSubIds[] = $sub->id;
                        }
                    } else {
                        $sub = SurveySubCategory::create($subAttrs);
                        $keepSubIds[] = $sub->id;
                    }

                    $igOrder = 0;
                    foreach ($subData['item_groups'] ?? [] as $igData) {
                        $igOrder++;
                        $igAttrs = [
                            'survey_sub_category_id' => $sub->id,
                            'name' => $igData['name'] ?? '',
                            'order_num' => $igData['order_num'] ?? $igOrder,
                        ];

                        if (! empty($igData['id'])) {
                            $ig = SurveyItemGroup::find($igData['id']);
                            if ($ig && $ig->survey_sub_category_id === $sub->id) {
                                $ig->update($igAttrs);
                                $keepIgIds[] = $ig->id;
                            } else {
                                $ig = SurveyItemGroup::create($igAttrs);
                                $keepIgIds[] = $ig->id;
                            }
                        } else {
                            $ig = SurveyItemGroup::create($igAttrs);
                            $keepIgIds[] = $ig->id;
                        }

                        $itemOrder = 0;
                        foreach ($igData['items'] ?? [] as $itemData) {
                            $itemOrder++;
                            $itemType = $itemData['item_type'] ?? 'score';
                            $itemAttrs = [
                                'survey_item_group_id' => $ig->id,
                                'name' => $itemData['name'] ?? '',
                                'item_type' => $itemType,
                                'score_labels' => $itemType === 'inventory' ? [] : ($itemData['score_labels'] ?? ['C', 'V']),
                                'has_date_fields' => $itemType === 'inventory' ? false : (bool) ($itemData['has_date_fields'] ?? false),
                                'order_num' => $itemData['order_num'] ?? $itemOrder,
                            ];

                            if (! empty($itemData['id'])) {
                                $item = SurveyItem::find($itemData['id']);
                                if ($item && $item->survey_item_group_id === $ig->id) {
                                    $item->update($itemAttrs);
                                    $keepItemIds[] = $item->id;
                                } else {
                                    $item = SurveyItem::create($itemAttrs);
                                    $keepItemIds[] = $item->id;
                                }
                            } else {
                                $item = SurveyItem::create($itemAttrs);
                                $keepItemIds[] = $item->id;
                            }
                        }
                    }
                }
            }

            // Delete orphans (items → item groups → sub categories → categories not in keep lists)
            SurveyItem::whereNotIn('id', $keepItemIds)
                ->whereHas('itemGroup.subCategory.category', fn ($q) => $q->where('survey_template_id', $template->id))
                ->delete();
            SurveyItemGroup::whereNotIn('id', $keepIgIds)
                ->whereHas('subCategory.category', fn ($q) => $q->where('survey_template_id', $template->id))
                ->delete();
            SurveySubCategory::whereNotIn('id', $keepSubIds)
                ->whereHas('category', fn ($q) => $q->where('survey_template_id', $template->id))
                ->delete();
            SurveyCategory::where('survey_template_id', $template->id)
                ->whereNotIn('id', $keepCatIds)
                ->delete();
        });
    }

    /**
     * Header kolom file Excel struktur template (single source of truth
     * untuk export & validasi import).
     */
    public static function structureHeadings(): array
    {
        return ['Kategori', 'Sub Kategori', 'Grup Item', 'Nama Item', 'Tipe Item', 'Label Skor', 'Field Tanggal'];
    }

    /**
     * Flatten hierarki template menjadi baris flat per kategori untuk
     * export Excel: [label kategori => [baris, ...]].
     */
    public function exportRowsByCategory(SurveyTemplate $template): array
    {
        $template->loadMissing('categories.subCategories.itemGroups.items');

        $grouped = [];
        foreach ($template->categories as $cat) {
            foreach ($cat->subCategories as $sub) {
                foreach ($sub->itemGroups as $ig) {
                    foreach ($ig->items as $item) {
                        $grouped[$cat->label][] = [
                            $cat->label,
                            $sub->name,
                            $ig->name,
                            $item->name,
                            $item->item_type instanceof \App\Enums\SurveyItemType ? $item->item_type->value : $item->item_type,
                            implode(',', $item->score_labels ?? []),
                            $item->has_date_fields ? 'Ya' : 'Tidak',
                        ];
                    }
                }
            }
        }

        return $grouped;
    }

    /**
     * Import struktur dari seluruh sheet workbook Excel. Sheet yang
     * baris pertamanya bukan header 'Kategori' (mis. Petunjuk) dilewati;
     * baris data semua sheet digabung lalu diparse seperti biasa.
     * $target null => buat template baru dari $templateData; selain itu
     * struktur template target ditimpa via saveStructure.
     */
    public function importSheets(array $sheets, ?SurveyTemplate $target = null, array $templateData = []): SurveyTemplate
    {
        $rows = [self::structureHeadings()];

        foreach ($sheets as $sheet) {
            if (mb_strtoupper(trim((string) ($sheet[0][0] ?? ''))) !== 'KATEGORI') {
                continue;
            }

            // Carry-forward kolom induk: sel kosong (hasil merge atau sengaja
            // dikosongkan) mewarisi nilai terakhir di atasnya; carry kolom anak
            // direset bila nilai induknya berubah. Reset penuh per sheet.
            $carry = [];
            foreach (array_slice($sheet, 1) as $row) {
                $row = array_pad(array_slice(array_values($row), 0, 7), 7, null);

                // Baris 100% kosong (mis. trailing buffer rows dari data
                // validation dropdown) di-skip sebelum carry-forward.
                if (implode('', array_map(fn ($v) => trim((string) ($v ?? '')), $row)) === '') {
                    continue;
                }

                foreach ([0, 1, 2] as $c) {
                    $v = trim((string) ($row[$c] ?? ''));
                    if ($v === '') {
                        $row[$c] = $carry[$c] ?? null;
                    } else {
                        if (($carry[$c] ?? null) !== $v) {
                            $carry = array_slice($carry, 0, $c, true); // nilai induk berubah -> carry anak hangus
                        }
                        $carry[$c] = $v;
                    }
                }
                $rows[] = $row;
            }
        }

        return $this->importRows($rows, $target, $templateData);
    }

    /**
     * Import struktur dari baris Excel (baris 1 = header).
     * $target null => buat template baru dari $templateData; selain itu
     * struktur template target ditimpa via saveStructure.
     */
    public function importRows(array $rows, ?SurveyTemplate $target = null, array $templateData = []): SurveyTemplate
    {
        $categories = $this->parseStructureRows($rows);

        return DB::transaction(function () use ($categories, $target, $templateData) {
            $template = $target ?? $this->create($templateData);
            $this->saveStructure($template, $categories);

            return $template->fresh();
        });
    }

    /**
     * Parse baris flat menjadi payload saveStructure. Hirarki ditentukan
     * oleh tuple (kategori, sub, grup) — baris dengan tuple sama masuk ke
     * grup yang sama; urutan kemunculan = order_num.
     */
    protected function parseStructureRows(array $rows): array
    {
        $typeAliases = [
            'score' => 'score', 'skor' => 'score',
            'inventory' => 'inventory', 'inventaris' => 'inventory',
        ];
        $yesValues = ['ya', 'y', 'yes', '1', 'true'];

        $errors = [];
        $categories = [];
        $catIndex = $subIndex = $igIndex = [];

        foreach ($rows as $i => $row) {
            if ($i === 0) {
                continue; // header
            }

            $cells = array_map(
                fn ($v) => trim((string) ($v ?? '')),
                array_pad(array_slice(array_values($row), 0, 7), 7, '')
            );

            if (implode('', $cells) === '') {
                continue; // baris kosong
            }

            [$catName, $subName, $igName, $itemName, $type, $labels, $date] = $cells;
            $line = $i + 1;

            $rowErrorCount = count($errors);
            foreach (['Kategori' => $catName, 'Sub Kategori' => $subName, 'Grup Item' => $igName, 'Nama Item' => $itemName, 'Tipe Item' => $type] as $col => $val) {
                if ($val === '') {
                    $errors[] = "Baris {$line}: kolom {$col} wajib diisi.";
                }
            }

            $itemType = $typeAliases[mb_strtolower($type)] ?? null;
            if ($type !== '' && ! $itemType) {
                $errors[] = "Baris {$line}: Tipe Item '{$type}' tidak dikenal (gunakan: score / inventory).";
            }

            if (count($errors) >= 10) {
                $errors[] = '...dan seterusnya. Perbaiki file lalu ulangi import.';
                break;
            }
            if (count($errors) > $rowErrorCount) {
                continue; // baris invalid — lewati, error sudah dicatat
            }

            $scoreLabels = $itemType === 'score'
                ? (array_values(array_filter(array_map('trim', explode(',', $labels)))) ?: ['C', 'V'])
                : [];
            $hasDate = $itemType === 'score' && in_array(mb_strtolower($date), $yesValues, true);

            // Bangun/temukan node hirarki berdasarkan tuple nama (case-insensitive).
            $k1 = mb_strtolower($catName);
            $k2 = $k1.'||'.mb_strtolower($subName);
            $k3 = $k2.'||'.mb_strtolower($igName);

            if (! isset($catIndex[$k1])) {
                $catIndex[$k1] = count($categories);
                $categories[] = ['label' => $catName, 'sub_categories' => []];
            }
            $cat = &$categories[$catIndex[$k1]];

            if (! isset($subIndex[$k2])) {
                $subIndex[$k2] = count($cat['sub_categories']);
                $cat['sub_categories'][] = ['name' => $subName, 'item_groups' => []];
            }
            $sub = &$cat['sub_categories'][$subIndex[$k2]];

            if (! isset($igIndex[$k3])) {
                $igIndex[$k3] = count($sub['item_groups']);
                $sub['item_groups'][] = ['name' => $igName, 'items' => []];
            }
            $ig = &$sub['item_groups'][$igIndex[$k3]];

            $ig['items'][] = [
                'name' => $itemName,
                'item_type' => $itemType,
                'score_labels' => $scoreLabels,
                'has_date_fields' => $hasDate,
            ];

            unset($ig, $sub, $cat);
        }

        if ($errors) {
            throw ValidationException::withMessages(['importFile' => $errors]);
        }
        if (! $categories) {
            throw ValidationException::withMessages([
                'importFile' => 'File tidak berisi data struktur yang valid. Unduh template untuk melihat formatnya.',
            ]);
        }

        return $categories;
    }

    /**
     * Get template with full nested hierarchy.
     */
    public function getWithHierarchy(int $templateId): ?SurveyTemplate
    {
        return SurveyTemplate::with([
            'categories.subCategories.itemGroups.items' => fn ($q) => $q->orderBy('order_num'),
            'categories' => fn ($q) => $q->orderBy('order_num'),
            'categories.subCategories' => fn ($q) => $q->orderBy('order_num'),
            'categories.subCategories.itemGroups' => fn ($q) => $q->orderBy('order_num'),
        ])->find($templateId);
    }

    public function getOptions(): array
    {
        return SurveyTemplate::active()
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'value' => (string) $t->id,
                'label' => $t->name.($t->is_default ? ' (Default)' : ''),
            ])
            ->toArray();
    }

    public function getDefault(): ?SurveyTemplate
    {
        return SurveyTemplate::default()->active()->first();
    }

    protected function generateUniqueCode(string $base): string
    {
        $code = strtoupper($base);
        $i = 1;
        while (SurveyTemplate::where('code', $code)->exists()) {
            $code = strtoupper($base).'-'.$i;
            $i++;
        }

        return $code;
    }
}
