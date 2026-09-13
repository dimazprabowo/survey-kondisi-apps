<?php

namespace App\Services;

use App\Models\SurveyCategory;
use App\Models\SurveyItem;
use App\Models\SurveyItemGroup;
use App\Models\SurveySubCategory;
use App\Models\SurveyTemplate;
use App\Traits\HasDynamicLike;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SurveyTemplateService
{
    use HasDynamicLike;

    public function getFiltered(
        ?string $search = null,
        ?string $statusFilter = null,
        int $perPage = 15
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

        return $query->latest()->paginate($perPage);
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
                    'code' => $cat->code,
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
                            'code' => $ig->code,
                            'name' => $ig->name,
                            'order_num' => $ig->order_num,
                        ]);

                        foreach ($ig->items as $item) {
                            SurveyItem::create([
                                'survey_item_group_id' => $newIg->id,
                                'code' => $item->code,
                                'name' => $item->name,
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
                    'code' => $catData['code'] ?? '',
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
                            'code' => $igData['code'] ?? '',
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
                            $itemAttrs = [
                                'survey_item_group_id' => $ig->id,
                                'code' => $itemData['code'] ?? '',
                                'name' => $itemData['name'] ?? '',
                                'score_labels' => $itemData['score_labels'] ?? ['C', 'V'],
                                'has_date_fields' => (bool) ($itemData['has_date_fields'] ?? false),
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
