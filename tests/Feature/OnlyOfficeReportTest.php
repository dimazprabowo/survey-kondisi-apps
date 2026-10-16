<?php

namespace Tests\Feature;

use App\Enums\FileStatus;
use App\Livewire\Surveys\ReportWordEditor;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyReport;
use App\Models\User;
use App\Services\OnlyOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class OnlyOfficeReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected OnlyOfficeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.onlyoffice', [
            'enabled' => true,
            'url' => 'http://onlyoffice.test',
            'jwt_secret' => 'test-secret',
        ]);
        Storage::fake('local');

        $this->service = new OnlyOfficeService;
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function makeReport(): SurveyReport
    {
        $ship = Ship::create(['name' => 'Alpha', 'status' => 'active']);
        $survey = Survey::create([
            'survey_number' => 'SVY-0001',
            'ship_id' => $ship->id,
            'survey_date' => '2026-01-01',
            'status' => 'draft',
        ]);

        Storage::disk('local')->put('local/survey-reports/alpha/test.docx', 'DOCX-CONTENT');

        return SurveyReport::create([
            'survey_id' => $survey->id,
            'report_number' => 'RPT-001',
            'file_path' => 'local/survey-reports/alpha/test.docx',
            'file_name' => 'test.docx',
            'file_size' => 12,
            'file_status' => FileStatus::Completed,
            'file_processed_at' => now(),
            'generator_version' => \App\Services\SurveyReportService::GENERATOR_VERSION,
        ]);
    }

    public function test_jwt_roundtrip_and_tamper_detection(): void
    {
        $token = $this->service->jwtEncode(['foo' => 'bar']);
        $this->assertSame('bar', $this->service->jwtDecode($token)['foo']);

        $this->expectException(\RuntimeException::class);
        $this->service->jwtDecode($token.'x');
    }

    public function test_editor_config_contains_signed_url_and_valid_token(): void
    {
        $report = $this->makeReport();
        $config = $this->service->buildEditorConfig($report, $this->user);

        $this->assertSame('docx', $config['document']['fileType']);
        $this->assertStringContainsString('signature=', $config['document']['url']);
        $this->assertStringContainsString('onlyoffice/report', $config['editorConfig']['callbackUrl']);
        $this->assertSame((string) $this->user->id, $config['editorConfig']['user']['id']);

        // Token valid & payload-nya = config tanpa token.
        $decoded = $this->service->jwtDecode($config['token']);
        $this->assertSame($config['document']['url'], $decoded['document']['url']);

        // Editor berikutnya tanpa revisi tetap memakai file generate.
        $this->assertSame($report->file_path, $this->service->editableSourcePath($report));
    }

    public function test_edit_word_page_requires_permission(): void
    {
        $report = $this->makeReport();
        $survey = $report->survey;

        $this->get(route('surveys.report.word', $survey))->assertForbidden();

        Gate::before(fn () => true);
        $this->get(route('surveys.report.word', $survey))->assertOk();
    }

    public function test_word_editor_component_builds_config(): void
    {
        Gate::before(fn () => true);
        $report = $this->makeReport();

        Livewire::test(ReportWordEditor::class, ['survey' => $report->survey])
            ->assertSet('unavailableReason', null)
            ->assertSet('apiJsUrl', 'http://onlyoffice.test/web-apps/apps/api/documents/api.js')
            ->assertSet('editorConfig.document.key', $this->service->documentKey($report));
    }

    public function test_word_editor_blocked_when_file_not_generated(): void
    {
        Gate::before(fn () => true);
        $report = $this->makeReport();
        $report->update(['file_status' => null, 'file_path' => null]);

        Livewire::test(ReportWordEditor::class, ['survey' => $report->survey])
            ->assertSet('unavailableReason', 'File laporan belum tersedia. Generate DOCX terlebih dahulu.');
    }

    public function test_document_file_route_requires_signed_url(): void
    {
        $report = $this->makeReport();

        $this->get(route('onlyoffice.report.file', $report))->assertForbidden();

        $signedUrl = URL::temporarySignedRoute('onlyoffice.report.file', now()->addHour(), ['report' => $report->getRouteKey()]);
        $this->get($signedUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_callback_stores_edited_revision_without_touching_original(): void
    {
        $report = $this->makeReport();

        Http::fake(['*' => Http::response('EDITED-DOCX-BYTES')]);

        $token = $this->service->jwtEncode([
            'status' => 2,
            'url' => 'http://onlyoffice.test/cache/edited.docx',
            'users' => [(string) $this->user->id],
        ]);

        $this->postJson(route('onlyoffice.report.callback', $report), [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJson(['error' => 0]);

        $report->refresh();
        $this->assertSame('local/survey-reports/alpha/test.docx', $report->file_path);
        $this->assertNotNull($report->edited_file_path);
        $this->assertNotNull($report->edited_at);
        $this->assertSame($this->user->id, $report->edited_by);
        Storage::disk('local')->assertExists($report->edited_file_path);
        $this->assertSame('EDITED-DOCX-BYTES', Storage::disk('local')->get($report->edited_file_path));

        // Sesi edit berikutnya melanjutkan dari versi edit → key berubah.
        $this->assertSame($report->edited_file_path, $this->service->editableSourcePath($report));
    }

    public function test_callback_rejects_missing_or_invalid_token(): void
    {
        $report = $this->makeReport();

        $this->postJson(route('onlyoffice.report.callback', $report), ['status' => 2])
            ->assertForbidden();

        $bad = $this->service->jwtEncode(['status' => 2]).'tampered';
        $this->postJson(route('onlyoffice.report.callback', $report), [], ['Authorization' => "Bearer {$bad}"])
            ->assertForbidden();

        $this->assertNull($report->fresh()->edited_file_path);
    }

    public function test_callback_ignores_non_save_status(): void
    {
        $report = $this->makeReport();

        $token = $this->service->jwtEncode(['status' => 1]);
        $this->postJson(route('onlyoffice.report.callback', $report), [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJson(['error' => 0]);

        $this->assertNull($report->fresh()->edited_file_path);
    }
}
