<?php

namespace Tests\Feature;

use App\Livewire\Pages\Dashboard;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function signIn(array $permissions): void
    {
        foreach (['dashboard_view', 'surveys_view', 'users_view', 'companies_view', 'roles_view'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);
        $this->actingAs($user);
    }

    protected function survey(string $date, string $status, ?float $score = null): Survey
    {
        $ship = Ship::firstOrCreate(['name' => 'Dashboard Ship'], ['status' => 'active']);

        return Survey::create([
            'survey_number' => 'DASH-'.(Survey::withTrashed()->count() + 1),
            'ship_id' => $ship->id,
            'survey_date' => $date,
            'status' => $status,
            'overall_cap_score' => $score,
        ]);
    }

    public function test_analytics_counts_months_statuses_and_only_completed_cap_scores(): void
    {
        $this->signIn(['dashboard_view', 'surveys_view']);
        foreach ([3.5, 2.5, 1.5, 0] as $score) {
            $this->survey('2026-01-01', 'completed', $score);
        }
        $this->survey('2026-12-31', 'completed');
        $this->survey('2026-02-01', 'draft', 4);
        $this->survey('2026-03-01', 'in_progress', 4);
        $this->survey('2026-04-01', 'cancelled', 4);
        $this->survey('2025-12-31', 'completed', 4);
        $this->survey('2027-01-01', 'completed', 4);
        $this->survey('2026-01-01', 'completed', 4)->delete();

        $data = app(DashboardService::class)->surveyAnalytics(2026);

        $this->assertSame(8, $data['total']);
        $this->assertSame(5, $data['completed']);
        $this->assertSame(4, $data['rated']);
        $this->assertSame(1, $data['unrated']);
        $this->assertSame(1.88, $data['averageScore']);
        $this->assertCount(12, $data['months']);
        $this->assertSame([4, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 1], array_column($data['months'], 'count'));
        $this->assertSame([1, 1, 5, 1], array_column($data['statuses'], 'count'));
        $this->assertSame([1, 1, 1, 1], array_column($data['ratings'], 'count'));
    }

    public function test_empty_analytics_has_zero_months_and_no_fabricated_average(): void
    {
        $this->signIn(['dashboard_view', 'surveys_view']);
        $data = app(DashboardService::class)->surveyAnalytics(2026);

        $this->assertSame(0, $data['total']);
        $this->assertNull($data['averageScore']);
        $this->assertSame(0, array_sum(array_column($data['months'], 'count')));
        $this->assertSame(0, array_sum(array_column($data['ratings'], 'percentage')));
        Livewire::test(Dashboard::class)->assertSee('Belum ada survey pada tahun ini');
    }

    public function test_dashboard_permission_alone_does_not_expose_survey_data(): void
    {
        $this->signIn(['dashboard_view']);
        Livewire::test(Dashboard::class)->assertDontSee('Aktivitas survey bulanan');
        $this->expectException(AuthorizationException::class);
        app(DashboardService::class)->surveyAnalytics(2026);
    }

    public function test_survey_permission_alone_does_not_expose_dashboard_analytics(): void
    {
        $this->signIn(['surveys_view']);
        Livewire::test(Dashboard::class)->assertDontSee('Aktivitas survey bulanan');
        $this->expectException(AuthorizationException::class);
        app(DashboardService::class)->surveyAnalytics(2026);
    }

    public function test_year_options_include_current_year_and_exclude_deleted_only_years(): void
    {
        $this->signIn(['dashboard_view', 'surveys_view']);
        $this->survey('2024-01-01', 'draft');
        $this->survey('2024-12-31', 'completed', 4);
        $this->survey('2023-01-01', 'draft')->delete();

        $years = array_column(app(DashboardService::class)->surveyYears(), 'value');
        $this->assertContains('2024', $years);
        $this->assertContains((string) now()->year, $years);
        $this->assertNotContains('2023', $years);
        $this->assertSame($years, array_values(array_unique($years)));
    }

    public function test_unauthorized_year_changes_are_forbidden(): void
    {
        $this->signIn(['dashboard_view']);
        Livewire::test(Dashboard::class)->set('year', '2025')->assertForbidden();
    }

    public function test_average_is_weighted_by_surveys_not_distinct_scores(): void
    {
        $this->signIn(['dashboard_view', 'surveys_view']);
        $this->survey('2026-01-01', 'completed', 4);
        $this->survey('2026-02-01', 'completed', 4);
        $this->survey('2026-03-01', 'completed', 1);

        $data = app(DashboardService::class)->surveyAnalytics(2026);
        $this->assertSame(3.0, $data['averageScore']);
        $this->assertSame([2, 0, 0, 1], array_column($data['ratings'], 'count'));
    }

    public function test_livewire_root_contains_the_entire_dashboard_after_repeated_year_changes(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15));
        $this->signIn(['dashboard_view', 'surveys_view']);
        $this->survey('2025-01-01', 'draft');
        $this->survey('2026-01-01', 'completed', 3.5);
        $dashboard = Livewire::test(Dashboard::class);
        $componentId = $dashboard->snapshot['memo']['id'];

        foreach ([null, '2025', '2026', '1900', '2025', '2026'] as $year) {
            if ($year !== null) {
                $dashboard->set('year', $year)->assertHasNoErrors();
            }

            $document = new \DOMDocument;
            $document->loadHTML('<html><body>'.$dashboard->html().'</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
            $root = $document->getElementsByTagName('body')->item(0)->firstElementChild;
            $xpath = new \DOMXPath($document);

            $this->assertSame($componentId, $root->getAttribute('wire:id'));
            $this->assertSame(1, $xpath->query('.//*[@id="survey-analytics-title"]', $root)->length);
            $this->assertSame(1, $xpath->query('.//*[@x-key="dashboard-year"]', $root)->length);
            $this->assertSame(1, $xpath->query('//*[@*[name()="wire:id"]]')->length);
            $this->assertSame($year === '1900' ? 0 : 1, $xpath->query('.//*[@id="monthly-chart-title"]', $root)->length);
        }
    }

    public function test_welcome_dashboard_also_has_a_stable_livewire_root(): void
    {
        $this->signIn([]);
        $dashboard = Livewire::test(Dashboard::class);

        foreach ([false, true] as $refresh) {
            if ($refresh) {
                $dashboard->call('$refresh');
            }

            $document = new \DOMDocument;
            $document->loadHTML('<html><body>'.$dashboard->html().'</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
            $root = $document->getElementsByTagName('body')->item(0)->firstElementChild;

            $this->assertSame($dashboard->snapshot['memo']['id'], $root->getAttribute('wire:id'));
            $dashboard->assertDontSee('Kinerja Survey Kondisi');
        }
    }

    public function test_year_filter_changes_data_and_rejects_invalid_input(): void
    {
        $this->signIn(['dashboard_view', 'surveys_view']);
        $this->survey('2025-01-01', 'draft');
        $this->survey('2026-01-01', 'completed', 3.5);

        Livewire::test(Dashboard::class)
            ->set('year', '2025')
            ->assertViewHas('analytics', fn ($data) => $data['total'] === 1 && $data['completed'] === 0)
            ->set('year', '2026')
            ->assertViewHas('analytics', fn ($data) => $data['completed'] === 1)
            ->set('year', 'invalid')
            ->assertHasErrors('year');
    }
}
