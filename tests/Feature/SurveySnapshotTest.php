<?php

namespace Tests\Feature;

use App\Enums\SurveyItemType;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyCategory;
use App\Models\SurveyItem;
use App\Models\SurveyItemGroup;
use App\Models\SurveySubCategory;
use App\Models\SurveyTemplate;
use App\Services\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveySnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected SurveyTemplate $template;

    protected SurveyItem $item;

    protected SurveyItemGroup $itemGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->template = SurveyTemplate::create([
            'name' => 'Template Test',
            'code' => 'TPL-TEST',
            'is_active' => true,
            'is_default' => true,
        ]);
        $category = SurveyCategory::create([
            'survey_template_id' => $this->template->id,
            'label' => 'Kategori Test',
            'order_num' => 1,
        ]);
        $subCategory = SurveySubCategory::create([
            'survey_category_id' => $category->id,
            'name' => 'Sub Test',
            'order_num' => 1,
        ]);
        $this->itemGroup = SurveyItemGroup::create([
            'survey_sub_category_id' => $subCategory->id,
            'name' => 'Grup Test',
            'order_num' => 1,
        ]);
        $this->item = SurveyItem::create([
            'survey_item_group_id' => $this->itemGroup->id,
            'name' => 'Item Test',
            'item_type' => 'score',
            'score_labels' => ['C', 'V'],
            'order_num' => 1,
        ]);

        Ship::create(['name' => 'Kapal Test', 'status' => 'active']);
    }

    protected function createSurvey(): Survey
    {
        return (new SurveyService)->create([
            'ship_id' => Ship::first()->id,
            'survey_template_id' => $this->template->id,
            'survey_date' => now()->format('Y-m-d'),
            'status' => 'draft',
        ]);
    }

    public function test_survey_create_snapshots_template_structure(): void
    {
        $survey = $this->createSurvey();

        $this->assertNotNull($survey->structure);
        $this->assertSame('Template Test', $survey->structure['template_name']);

        $cat = $survey->structure['categories'][0];
        $this->assertSame('Kategori Test', $cat['label']);

        $item = $cat['subCategories'][0]['itemGroups'][0]['items'][0];
        $this->assertSame($this->item->id, $item['id']);
        $this->assertSame('Item Test', $item['name']);
        $this->assertSame(['C', 'V'], $item['score_labels']);
    }

    public function test_template_changes_do_not_affect_existing_survey(): void
    {
        $survey = $this->createSurvey();
        $originalItemName = $survey->structure['categories'][0]['subCategories'][0]['itemGroups'][0]['items'][0]['name'];

        // Simulasi edit + tambah struktur di master template
        $this->item->update(['name' => 'Item Diganti', 'score_labels' => ['X', 'Y', 'Z']]);
        $this->itemGroup->update(['name' => 'Grup Diganti']);

        $survey->refresh();
        $item = $survey->structure['categories'][0]['subCategories'][0]['itemGroups'][0]['items'][0];

        $this->assertSame($originalItemName, $item['name']);
        $this->assertSame(['C', 'V'], $item['score_labels']);
        $this->assertSame(
            'Grup Test',
            $survey->structure['categories'][0]['subCategories'][0]['itemGroups'][0]['name']
        );
    }

    public function test_responses_and_cap_survive_template_item_deletion(): void
    {
        $survey = $this->createSurvey();
        $service = new SurveyService;

        $service->saveResponses($survey->id, [
            $this->item->id => ['scores' => ['C' => '3', 'V' => '4']],
        ]);
        $this->assertSame(3.5, (float) $survey->fresh()->overall_cap_score);

        // Hapus item di master template — response & struktur survey harus utuh
        $this->item->delete();

        $this->assertDatabaseHas('survey_responses', [
            'survey_id' => $survey->id,
            'survey_item_id' => $this->item->id,
        ]);

        $service->recalculateOverall($survey->id);
        $this->assertSame(3.5, (float) $survey->fresh()->overall_cap_score);
    }

    public function test_hydrate_structure_restores_shape_and_enums(): void
    {
        $survey = $this->createSurvey();
        $categories = (new SurveyService)->hydrateStructure($survey->structure);

        $item = $categories->first()->subCategories->first()->itemGroups->first()->items->first();

        $this->assertSame($this->item->id, $item->id);
        $this->assertSame(SurveyItemType::Score, $item->item_type);
        $this->assertSame(['C', 'V'], $item->score_labels);
    }

    public function test_new_survey_uses_updated_template(): void
    {
        $this->item->update(['name' => 'Item Versi Baru']);

        $survey = $this->createSurvey();
        $item = $survey->structure['categories'][0]['subCategories'][0]['itemGroups'][0]['items'][0];

        $this->assertSame('Item Versi Baru', $item['name']);
    }
}
