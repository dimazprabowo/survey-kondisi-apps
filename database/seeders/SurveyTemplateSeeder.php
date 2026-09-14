<?php

namespace Database\Seeders;

use App\Models\SurveyCategory;
use App\Models\SurveyItem;
use App\Models\SurveyItemGroup;
use App\Models\SurveySubCategory;
use App\Models\SurveyTemplate;
use Illuminate\Database\Seeder;

class SurveyTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = storage_path('app/private/templates/survey_template.json');

        if (! file_exists($jsonPath)) {
            $this->command->error('Survey template JSON not found: '.$jsonPath);
            $this->command->warn('Run: php extract_excel.php first');

            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        // Clear existing template data
        SurveyItem::query()->delete();
        SurveyItemGroup::query()->delete();
        SurveySubCategory::query()->delete();
        SurveyCategory::query()->delete();
        SurveyTemplate::query()->delete();

        // 1. Default template — full structure dari Excel
        $this->createTemplate(
            'Survey Kondisi Kapal (Default)',
            'SK-DEFAULT',
            'Template default hasil ekstraksi dari Excel survey kondisi kapal. Berisi seluruh 8 kategori lengkap.',
            true,
            true,
            $data['categories']
        );

        // 2. Template Ringkas — hanya kategori I, II, III (Hull, Ramp, Accomodation)
        $ringkasCats = array_filter($data['categories'], fn ($c) => in_array($c['category_code'], ['I', 'II', 'III']));
        $this->createTemplate(
            'Survey Kondisi Kapal (Ringkas)',
            'SK-RINGKAS',
            'Template ringkas berisi 3 kategori utama: Hull & Construction, Ramp, dan Accomodation. Cocok untuk survey cepat.',
            true,
            false,
            array_values($ringkasCats)
        );

        // 3. Template Machinery — hanya kategori IV, V (Deck Machinery, Engine Room & Machinery termasuk Pump/Pipe)
        $machineryCats = array_filter($data['categories'], fn ($c) => in_array($c['category_code'], ['IV', 'V']));
        $this->createTemplate(
            'Survey Kondisi Kapal (Machinery)',
            'SK-MACHINERY',
            'Template khusus machinery berisi 2 kategori: Deck Machinery & Outfitting dan Engine Room & Machinery (termasuk Pump & Pipe).',
            true,
            false,
            array_values($machineryCats)
        );

        // 4. Template Safety & Navigation — hanya kategori VI, VII (Bridge & Navigation, Ship Safety Operation)
        $safetyCats = array_filter($data['categories'], fn ($c) => in_array($c['category_code'], ['VI', 'VII']));
        $this->createTemplate(
            'Survey Kondisi Kapal (Safety & Navigation)',
            'SK-SAFETY',
            'Template khusus safety & navigation berisi 2 kategori: Bridge & Navigation dan Ship Safety Operation.',
            true,
            false,
            array_values($safetyCats)
        );

        $this->command->info('Survey templates seeded: 4 templates (1 default + 3 varian)');
    }

    /**
     * Create a template with its full nested structure from category data.
     *
     * @param  array  $categoriesData  Category data from JSON
     */
    protected function createTemplate(
        string $name,
        string $code,
        string $description,
        bool $isActive,
        bool $isDefault,
        array $categoriesData
    ): SurveyTemplate {
        $template = SurveyTemplate::create([
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'is_active' => $isActive,
            'is_default' => $isDefault,
        ]);

        $catOrder = 0;
        foreach ($categoriesData as $catData) {
            $catOrder++;
            $category = SurveyCategory::create([
                'survey_template_id' => $template->id,
                'code' => $catData['category_code'],
                'label' => $catData['category_label'],
                'order_num' => $catOrder,
            ]);

            $subOrder = 0;
            foreach ($catData['sub_categories'] as $subData) {
                $subOrder++;
                $subCategory = SurveySubCategory::create([
                    'survey_category_id' => $category->id,
                    'order_num' => $subData['order_num'],
                    'name' => $subData['name'],
                ]);

                $igOrder = 0;
                foreach ($subData['item_groups'] as $igData) {
                    $igOrder++;
                    $itemGroup = SurveyItemGroup::create([
                        'survey_sub_category_id' => $subCategory->id,
                        'code' => $igData['code'] ?? '',
                        'name' => $igData['name'],
                        'order_num' => $igOrder,
                    ]);

                    $itemOrder = 0;
                    foreach ($igData['items'] as $itemData) {
                        $itemOrder++;
                        SurveyItem::create([
                            'survey_item_group_id' => $itemGroup->id,
                            'code' => $itemData['code'] ?? '',
                            'name' => $itemData['name'],
                            'item_type' => $itemData['item_type'] ?? 'score',
                            'score_labels' => $itemData['score_labels'] ?? ['C', 'V'],
                            'has_date_fields' => $itemData['has_date_fields'] ?? false,
                            'order_num' => $itemOrder,
                        ]);
                    }
                }
            }
        }

        $this->command->line("  - {$name}: {$catOrder} categories");

        return $template;
    }
}
