<?php

namespace Tests\Feature;

use App\Livewire\Surveys\SurveyManagement;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\User;
use App\Services\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class SurveyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Ship $shipAlpha;

    protected Ship $shipBeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shipAlpha = Ship::create(['name' => 'Alpha', 'status' => 'active']);
        $this->shipBeta = Ship::create(['name' => 'Beta', 'status' => 'active']);

        $this->actingAs(User::factory()->create());
        Gate::before(fn () => true);
    }

    protected function makeSurvey(string $number, Ship $ship, string $date, ?string $surveyor = null, string $status = 'draft', ?float $capScore = null): Survey
    {
        return Survey::create([
            'survey_number' => $number,
            'ship_id' => $ship->id,
            'survey_date' => $date,
            'surveyor' => $surveyor,
            'status' => $status,
            'overall_cap_score' => $capScore,
        ]);
    }

    public function test_sort_by_survey_number_toggles_direction(): void
    {
        $this->makeSurvey('SVY-0002', $this->shipAlpha, '2026-01-01');
        $this->makeSurvey('SVY-0001', $this->shipBeta, '2026-01-02');
        $this->makeSurvey('SVY-0003', $this->shipAlpha, '2026-01-03');

        $component = Livewire::test(SurveyManagement::class)->call('sortBy', 'survey_number');

        $this->assertSame('survey_number', $component->get('sortField'));
        $this->assertSame('asc', $component->get('sortDir'));
        $this->assertSame(
            ['SVY-0001', 'SVY-0002', 'SVY-0003'],
            $component->viewData('surveys')->pluck('survey_number')->all()
        );

        // Klik kolom yang sama membalik arah sort
        $component->call('sortBy', 'survey_number');
        $this->assertSame('desc', $component->get('sortDir'));
        $this->assertSame(
            ['SVY-0003', 'SVY-0002', 'SVY-0001'],
            $component->viewData('surveys')->pluck('survey_number')->all()
        );
    }

    public function test_sort_by_ship_name_via_relation(): void
    {
        $this->makeSurvey('SVY-0001', $this->shipBeta, '2026-01-01');
        $this->makeSurvey('SVY-0002', $this->shipAlpha, '2026-01-02');
        $this->makeSurvey('SVY-0003', $this->shipBeta, '2026-01-03');

        $component = Livewire::test(SurveyManagement::class)->call('sortBy', 'ship');

        $this->assertSame(
            ['Alpha', 'Beta', 'Beta'],
            $component->viewData('surveys')->map(fn ($s) => $s->ship->name)->all()
        );
    }

    public function test_default_sort_is_survey_date_desc(): void
    {
        $this->makeSurvey('SVY-0001', $this->shipAlpha, '2026-01-01');
        $this->makeSurvey('SVY-0002', $this->shipAlpha, '2026-01-03');
        $this->makeSurvey('SVY-0003', $this->shipAlpha, '2026-01-02');

        $component = Livewire::test(SurveyManagement::class);

        $this->assertSame(
            ['SVY-0002', 'SVY-0003', 'SVY-0001'],
            $component->viewData('surveys')->pluck('survey_number')->all()
        );
    }

    public function test_cap_score_sort_treats_null_as_smallest(): void
    {
        $this->makeSurvey('SVY-0001', $this->shipAlpha, '2026-01-01', capScore: null);
        $this->makeSurvey('SVY-0002', $this->shipAlpha, '2026-01-02', capScore: 3.50);
        $this->makeSurvey('SVY-0003', $this->shipAlpha, '2026-01-03', capScore: 2.10);

        $service = new SurveyService;

        // asc: NULL (badge "-") dianggap terkecil → paling atas, diikuti 0 dst.
        $this->assertSame(
            ['SVY-0001', 'SVY-0003', 'SVY-0002'],
            $service->getFiltered(sortField: 'overall_cap_score', sortDir: 'asc')->pluck('survey_number')->all()
        );

        // desc: skor terbesar dulu, NULL paling akhir
        $this->assertSame(
            ['SVY-0002', 'SVY-0003', 'SVY-0001'],
            $service->getFiltered(sortField: 'overall_cap_score', sortDir: 'desc')->pluck('survey_number')->all()
        );
    }

    public function test_status_sort_follows_workflow_order(): void
    {
        $this->makeSurvey('SVY-0001', $this->shipAlpha, '2026-01-01', status: 'completed');
        $this->makeSurvey('SVY-0002', $this->shipAlpha, '2026-01-02', status: 'draft');
        $this->makeSurvey('SVY-0003', $this->shipAlpha, '2026-01-03', status: 'in_progress');
        $this->makeSurvey('SVY-0004', $this->shipAlpha, '2026-01-04', status: 'cancelled');

        // asc mengikuti urutan enum workflow: draft → in_progress → completed → cancelled
        $this->assertSame(
            ['SVY-0002', 'SVY-0003', 'SVY-0001', 'SVY-0004'],
            (new SurveyService)->getFiltered(sortField: 'status', sortDir: 'asc')->pluck('survey_number')->all()
        );
    }

    public function test_invalid_sort_field_falls_back_to_default(): void
    {
        $this->makeSurvey('SVY-0001', $this->shipAlpha, '2026-01-01');
        $this->makeSurvey('SVY-0002', $this->shipAlpha, '2026-01-03');

        $paginator = (new SurveyService)->getFiltered(sortField: 'malicious_col', sortDir: 'asc');

        $this->assertSame(
            ['SVY-0002', 'SVY-0001'],
            $paginator->pluck('survey_number')->all()
        );
    }
}
