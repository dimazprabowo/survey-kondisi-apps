<?php

namespace App\Livewire\Templates;

use App\Livewire\Traits\HasNotification;
use App\Models\SurveyTemplate;
use App\Services\SurveyTemplateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Component;

class TemplateForm extends Component
{
    use AuthorizesRequests, HasNotification;

    public ?SurveyTemplate $template = null;

    public bool $editMode = false;

    public $templateId;

    // Metadata fields
    public $name;

    public $code;

    public $description;

    public $is_active = true;

    public $is_default = false;

    // Nested structure: categories → sub_categories → item_groups → items
    public $categories = [];

    // Active category tab (index into $categories)
    public int $activeCategoryTab = 0;

    // Active sub-category tab within the active category panel
    public int $activeSubCategoryTab = 0;

    public function mount(?SurveyTemplate $template = null)
    {
        if ($template && $template->exists) {
            $this->authorize('update', $template);
            $this->template = $template;
            $this->editMode = true;
            $this->templateId = $template->id;
            $this->name = $template->name;
            $this->code = $template->code;
            $this->description = $template->description;
            $this->is_active = $template->is_active;
            $this->is_default = $template->is_default;
            $this->loadStructure();
        } else {
            $this->authorize('create', SurveyTemplate::class);
            // Start with one empty category
            $this->categories = [$this->emptyCategory()];
        }
    }

    protected function loadStructure(): void
    {
        $this->categories = [];
        $template = (new SurveyTemplateService)->getWithHierarchy($this->templateId);
        if (! $template) {
            return;
        }

        foreach ($template->categories as $cat) {
            $catData = [
                'id' => $cat->id,
                'row_key' => 'cat-'.$cat->id,
                'label' => $cat->label,
                'order_num' => $cat->order_num,
                'sub_categories' => [],
            ];
            foreach ($cat->subCategories as $sub) {
                $subData = [
                    'id' => $sub->id,
                    'row_key' => 'sub-'.$sub->id,
                    'name' => $sub->name,
                    'order_num' => $sub->order_num,
                    'item_groups' => [],
                ];
                foreach ($sub->itemGroups as $ig) {
                    $igData = [
                        'id' => $ig->id,
                        'row_key' => 'ig-'.$ig->id,
                        'name' => $ig->name,
                        'order_num' => $ig->order_num,
                        'items' => [],
                    ];
                    foreach ($ig->items as $item) {
                        $igData['items'][] = [
                            'id' => $item->id,
                            'row_key' => 'item-'.$item->id,
                            'name' => $item->name,
                            'item_type' => $item->item_type?->value ?? 'score',
                            'score_labels' => $item->score_labels ?? ['C', 'V'],
                            'has_date_fields' => (bool) $item->has_date_fields,
                            'order_num' => $item->order_num,
                        ];
                    }
                    $subData['item_groups'][] = $igData;
                }
                $catData['sub_categories'][] = $subData;
            }
            $this->categories[] = $catData;
        }
    }

    protected function emptyCategory(): array
    {
        return [
            'id' => null,
            'row_key' => (string) Str::uuid(),
            'label' => '',
            'order_num' => 1,
            'sub_categories' => [],
        ];
    }

    protected function emptySubCategory(): array
    {
        return [
            'id' => null,
            'row_key' => (string) Str::uuid(),
            'name' => '',
            'order_num' => 1,
            'item_groups' => [],
        ];
    }

    protected function emptyItemGroup(): array
    {
        return [
            'id' => null,
            'row_key' => (string) Str::uuid(),
            'name' => '',
            'order_num' => 1,
            'items' => [],
        ];
    }

    protected function emptyItem(): array
    {
        return [
            'id' => null,
            'row_key' => (string) Str::uuid(),
            'name' => '',
            'item_type' => 'score',
            'score_labels' => ['C', 'V'],
            'has_date_fields' => false,
            'order_num' => 1,
        ];
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.label' => ['required', 'string', 'max:255'],
            'categories.*.sub_categories' => ['nullable', 'array'],
            'categories.*.sub_categories.*.name' => ['required', 'string', 'max:255'],
            'categories.*.sub_categories.*.item_groups' => ['nullable', 'array'],
            'categories.*.sub_categories.*.item_groups.*.name' => ['required', 'string', 'max:255'],
            'categories.*.sub_categories.*.item_groups.*.items' => ['nullable', 'array'],
            'categories.*.sub_categories.*.item_groups.*.items.*.name' => ['required', 'string', 'max:500'],
            'categories.*.sub_categories.*.item_groups.*.items.*.item_type' => ['required', 'in:'.implode(',', \App\Enums\SurveyItemType::values())],
            'categories.*.sub_categories.*.item_groups.*.items.*.score_labels' => ['nullable', 'array'],
            'categories.*.sub_categories.*.item_groups.*.items.*.has_date_fields' => ['boolean'],
        ];
    }

    public function validationAttributes()
    {
        return [
            'name' => 'nama template',
            'code' => 'kode template',
            'description' => 'deskripsi',
            'categories' => 'kategori',
            'categories.*.label' => 'nama kategori',
            'categories.*.sub_categories.*.name' => 'nama sub kategori',
            'categories.*.sub_categories.*.item_groups.*.name' => 'nama item group',
            'categories.*.sub_categories.*.item_groups.*.items.*.name' => 'nama item',
        ];
    }

    // Category actions
    public function setActiveCategoryTab($index)
    {
        $this->activeCategoryTab = (int) $index;
        // Sub-category tabs belong to the active category panel — reset when switching categories
        $this->activeSubCategoryTab = 0;
    }

    public function setActiveSubCategoryTab($index)
    {
        $this->activeSubCategoryTab = (int) $index;
    }

    public function addCategory()
    {
        $this->categories[] = $this->emptyCategory();
        $this->reorderCategories();
        $this->activeCategoryTab = count($this->categories) - 1;
    }

    public function confirmRemoveCategory($index)
    {
        $index = (int) $index;
        if (! isset($this->categories[$index])) {
            return;
        }

        $label = trim($this->categories[$index]['label'] ?? '') ?: 'Tab Baru';

        $this->dispatch('confirm-remove-category',
            action: 'removeCategory',
            actionParams: $index,
            title: 'Hapus Kategori',
            message: "Kategori \"{$label}\" beserta seluruh sub kategori, item group, dan item di dalamnya akan dihapus.",
            confirmText: 'Ya, Hapus',
            cancelText: 'Batal',
            type: 'danger',
        );
    }

    public function removeCategory($index)
    {
        $index = (int) $index;
        unset($this->categories[$index]);
        $this->categories = array_values($this->categories);
        $this->reorderCategories();

        // Keep the active tab pointing at the same (or nearest) category
        if ($index < $this->activeCategoryTab) {
            $this->activeCategoryTab--;
        }
        $this->activeCategoryTab = min($this->activeCategoryTab, max(count($this->categories) - 1, 0));

        $this->dispatch('confirm-remove-category-close');
        $this->notifyInfo('Kategori berhasil dihapus.');
    }

    public function moveCategoryUp($index)
    {
        if ($index <= 0) {
            return;
        }
        $temp = $this->categories[$index - 1];
        $this->categories[$index - 1] = $this->categories[$index];
        $this->categories[$index] = $temp;
        $this->reorderCategories();

        // Keep the active tab on the same category after the swap
        if ($this->activeCategoryTab === (int) $index) {
            $this->activeCategoryTab = $index - 1;
        } elseif ($this->activeCategoryTab === $index - 1) {
            $this->activeCategoryTab = (int) $index;
        }
    }

    public function moveCategoryDown($index)
    {
        if ($index >= count($this->categories) - 1) {
            return;
        }
        $temp = $this->categories[$index + 1];
        $this->categories[$index + 1] = $this->categories[$index];
        $this->categories[$index] = $temp;
        $this->reorderCategories();

        if ($this->activeCategoryTab === (int) $index) {
            $this->activeCategoryTab = $index + 1;
        } elseif ($this->activeCategoryTab === $index + 1) {
            $this->activeCategoryTab = (int) $index;
        }
    }

    protected function reorderCategories(): void
    {
        foreach ($this->categories as $i => &$cat) {
            $cat['order_num'] = $i + 1;
        }
        unset($cat);
    }

    // Sub-category actions
    public function addSubCategory($catIndex)
    {
        $this->categories[$catIndex]['sub_categories'][] = $this->emptySubCategory();
        $this->reorderSubCategories($catIndex);
        $this->activeSubCategoryTab = count($this->categories[$catIndex]['sub_categories']) - 1;
    }

    public function confirmRemoveSubCategory($catIndex, $subIndex)
    {
        $catIndex = (int) $catIndex;
        $subIndex = (int) $subIndex;
        if (! isset($this->categories[$catIndex]['sub_categories'][$subIndex])) {
            return;
        }

        $name = trim($this->categories[$catIndex]['sub_categories'][$subIndex]['name'] ?? '') ?: 'Sub Baru';

        $this->dispatch('confirm-remove-sub',
            action: 'removeSubCategory',
            actionParams: [$catIndex, $subIndex],
            title: 'Hapus Sub Kategori',
            message: "Sub kategori \"{$name}\" beserta seluruh item group dan item di dalamnya akan dihapus.",
            confirmText: 'Ya, Hapus',
            cancelText: 'Batal',
            type: 'danger',
        );
    }

    public function removeSubCategory($catIndex, $subIndex)
    {
        $catIndex = (int) $catIndex;
        $subIndex = (int) $subIndex;
        unset($this->categories[$catIndex]['sub_categories'][$subIndex]);
        $this->categories[$catIndex]['sub_categories'] = array_values($this->categories[$catIndex]['sub_categories']);
        $this->reorderSubCategories($catIndex);

        // Keep the active sub tab pointing at the same (or nearest) sub-category
        if ($subIndex < $this->activeSubCategoryTab) {
            $this->activeSubCategoryTab--;
        }
        $this->activeSubCategoryTab = min($this->activeSubCategoryTab, max(count($this->categories[$catIndex]['sub_categories']) - 1, 0));

        $this->dispatch('confirm-remove-sub-close');
        $this->notifyInfo('Sub kategori berhasil dihapus.');
    }

    public function moveSubCategoryUp($catIndex, $subIndex)
    {
        if ($subIndex <= 0) {
            return;
        }
        $temp = $this->categories[$catIndex]['sub_categories'][$subIndex - 1];
        $this->categories[$catIndex]['sub_categories'][$subIndex - 1] = $this->categories[$catIndex]['sub_categories'][$subIndex];
        $this->categories[$catIndex]['sub_categories'][$subIndex] = $temp;
        $this->reorderSubCategories($catIndex);

        if ($this->activeSubCategoryTab === (int) $subIndex) {
            $this->activeSubCategoryTab = $subIndex - 1;
        } elseif ($this->activeSubCategoryTab === $subIndex - 1) {
            $this->activeSubCategoryTab = (int) $subIndex;
        }
    }

    public function moveSubCategoryDown($catIndex, $subIndex)
    {
        $subs = $this->categories[$catIndex]['sub_categories'];
        if ($subIndex >= count($subs) - 1) {
            return;
        }
        $temp = $this->categories[$catIndex]['sub_categories'][$subIndex + 1];
        $this->categories[$catIndex]['sub_categories'][$subIndex + 1] = $this->categories[$catIndex]['sub_categories'][$subIndex];
        $this->categories[$catIndex]['sub_categories'][$subIndex] = $temp;
        $this->reorderSubCategories($catIndex);

        if ($this->activeSubCategoryTab === (int) $subIndex) {
            $this->activeSubCategoryTab = $subIndex + 1;
        } elseif ($this->activeSubCategoryTab === $subIndex + 1) {
            $this->activeSubCategoryTab = (int) $subIndex;
        }
    }

    protected function reorderSubCategories($catIndex): void
    {
        foreach ($this->categories[$catIndex]['sub_categories'] as $i => &$sub) {
            $sub['order_num'] = $i + 1;
        }
        unset($sub);
    }

    // Item group actions
    public function addItemGroup($catIndex, $subIndex)
    {
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][] = $this->emptyItemGroup();
        $this->reorderItemGroups($catIndex, $subIndex);
    }

    public function removeItemGroup($catIndex, $subIndex, $igIndex)
    {
        unset($this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]);
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'] = array_values($this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups']);
        $this->reorderItemGroups($catIndex, $subIndex);
    }

    public function moveItemGroupUp($catIndex, $subIndex, $igIndex)
    {
        if ($igIndex <= 0) {
            return;
        }
        $igs = $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'];
        $temp = $igs[$igIndex - 1];
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex - 1] = $igs[$igIndex];
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex] = $temp;
        $this->reorderItemGroups($catIndex, $subIndex);
    }

    public function moveItemGroupDown($catIndex, $subIndex, $igIndex)
    {
        $igs = $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'];
        if ($igIndex >= count($igs) - 1) {
            return;
        }
        $temp = $igs[$igIndex + 1];
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex + 1] = $igs[$igIndex];
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex] = $temp;
        $this->reorderItemGroups($catIndex, $subIndex);
    }

    protected function reorderItemGroups($catIndex, $subIndex): void
    {
        foreach ($this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'] as $i => &$ig) {
            $ig['order_num'] = $i + 1;
        }
        unset($ig);
    }

    // Item actions
    public function addItem($catIndex, $subIndex, $igIndex)
    {
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][] = $this->emptyItem();
        $this->reorderItems($catIndex, $subIndex, $igIndex);
    }

    public function removeItem($catIndex, $subIndex, $igIndex, $itemIndex)
    {
        unset($this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex]);
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'] = array_values($this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items']);
        $this->reorderItems($catIndex, $subIndex, $igIndex);
    }

    public function moveItemUp($catIndex, $subIndex, $igIndex, $itemIndex)
    {
        if ($itemIndex <= 0) {
            return;
        }
        $items = $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'];
        $temp = $items[$itemIndex - 1];
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex - 1] = $items[$itemIndex];
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex] = $temp;
        $this->reorderItems($catIndex, $subIndex, $igIndex);
    }

    public function moveItemDown($catIndex, $subIndex, $igIndex, $itemIndex)
    {
        $items = $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'];
        if ($itemIndex >= count($items) - 1) {
            return;
        }
        $temp = $items[$itemIndex + 1];
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex + 1] = $items[$itemIndex];
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex] = $temp;
        $this->reorderItems($catIndex, $subIndex, $igIndex);
    }

    protected function reorderItems($catIndex, $subIndex, $igIndex): void
    {
        foreach ($this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'] as $i => &$item) {
            $item['order_num'] = $i + 1;
        }
        unset($item);
    }

    // Score labels management (comma-separated input)
    public function updateScoreLabels($catIndex, $subIndex, $igIndex, $itemIndex, $value)
    {
        $labels = array_filter(array_map('trim', explode(',', $value)));
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex]['score_labels'] = $labels ?: ['C', 'V'];
    }

    /**
     * Switch item type between 'score' and 'inventory'.
     * Inventory items don't have score_labels/has_date_fields (filled with qty & specification at survey time).
     */
    public function updateItemType($catIndex, $subIndex, $igIndex, $itemIndex, $value)
    {
        $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex]['item_type'] = $value;
        if ($value === 'inventory') {
            $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex]['score_labels'] = [];
            $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex]['has_date_fields'] = false;
        } elseif (empty($this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex]['score_labels'])) {
            $this->categories[$catIndex]['sub_categories'][$subIndex]['item_groups'][$igIndex]['items'][$itemIndex]['score_labels'] = ['C', 'V'];
        }
    }

    public function save(SurveyTemplateService $service)
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Jump to the first category (and sub-category) tab that has validation errors so the user can see them
            foreach ($e->validator->errors()->keys() as $key) {
                if (preg_match('/^categories\.(\d+)(?:\.sub_categories\.(\d+))?/', $key, $m)) {
                    $this->activeCategoryTab = (int) $m[1];
                    $this->activeSubCategoryTab = isset($m[2]) ? (int) $m[2] : 0;
                    break;
                }
            }
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'name' => $this->name,
                'code' => $this->code,
                'description' => $this->description,
                'is_active' => (bool) $this->is_active,
                'is_default' => (bool) $this->is_default,
            ];

            if ($this->editMode) {
                $template = SurveyTemplate::findOrFail($this->templateId);
                $this->authorize('update', $template);
                $service->update($template, $data);
                $service->saveStructure($template, $this->categories);
                $this->notifySuccess('Template berhasil diupdate!');
            } else {
                $this->authorize('create', SurveyTemplate::class);
                $template = $service->create($data);
                $service->saveStructure($template, $this->categories);
                $this->notifySuccess('Template berhasil dibuat!');
            }

            return $this->redirect(route('master-data.survey-templates.index'), navigate: true);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function cancel()
    {
        return $this->redirect(route('master-data.survey-templates.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.templates.template-form');
    }
}
