<?php

namespace Tests\Feature;

use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyCategory;
use App\Models\SurveyGroupNote;
use App\Models\SurveyItem;
use App\Models\SurveyItemGroup;
use App\Models\SurveySubCategory;
use App\Models\SurveyTemplate;
use App\Services\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SurveyGroupNoteTest extends TestCase
{
    use RefreshDatabase;

    protected SurveyItemGroup $itemGroup;

    protected SurveyItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $template = SurveyTemplate::create([
            'name' => 'Template Test',
            'code' => 'TPL-TEST',
            'is_active' => true,
            'is_default' => true,
        ]);
        $category = SurveyCategory::create([
            'survey_template_id' => $template->id,
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
            'survey_template_id' => SurveyTemplate::first()->id,
            'survey_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'surveyor' => 'Surveyor Test',
        ]);
    }

    public function test_survey_responses_table_has_no_note_column(): void
    {
        $this->assertFalse(
            Schema::hasColumn('survey_responses', 'note'),
            'Kolom note per-item harus sudah dihapus dari survey_responses'
        );
    }

    public function test_group_notes_saved_in_order(): void
    {
        $survey = $this->createSurvey();
        $service = new SurveyService;

        $service->saveResponses($survey->id, [
            $this->item->id => ['scores' => ['C' => '3', 'V' => '4']],
        ], [
            $this->itemGroup->id => ['  Catatan pertama  ', 'Catatan kedua'],
        ]);

        $notes = SurveyGroupNote::where('survey_id', $survey->id)
            ->where('survey_item_group_id', $this->itemGroup->id)
            ->orderBy('order_num')
            ->get();

        $this->assertCount(2, $notes);
        $this->assertSame('Catatan pertama', $notes[0]->note);
        $this->assertSame('Catatan kedua', $notes[1]->note);
        $this->assertSame([0, 1], $notes->pluck('order_num')->all());
        $this->assertSame(3.5, (float) $survey->fresh()->overall_cap_score);
    }

    public function test_empty_group_notes_remove_existing_rows(): void
    {
        $survey = $this->createSurvey();
        $service = new SurveyService;

        $service->saveResponses($survey->id, [], [$this->itemGroup->id => ['Catatan awal']]);
        $this->assertSame(1, SurveyGroupNote::where('survey_id', $survey->id)->count());

        $service->saveResponses($survey->id, [], [$this->itemGroup->id => ['   ', '']]);
        $this->assertSame(0, SurveyGroupNote::where('survey_id', $survey->id)->count());
    }

    public function test_group_notes_replaced_not_duplicated(): void
    {
        $survey = $this->createSurvey();
        $service = new SurveyService;

        $service->saveResponses($survey->id, [], [$this->itemGroup->id => ['Catatan A', 'Catatan B']]);
        $service->saveResponses($survey->id, [], [$this->itemGroup->id => ['Catatan baru']]);

        $notes = SurveyGroupNote::where('survey_id', $survey->id)->orderBy('order_num')->get();
        $this->assertCount(1, $notes);
        $this->assertSame('Catatan baru', $notes->first()->note);
    }
}
