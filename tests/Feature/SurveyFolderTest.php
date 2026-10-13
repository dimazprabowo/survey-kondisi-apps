<?php

namespace Tests\Feature;

use App\Livewire\Surveys\SurveyManagement;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyFolder;
use App\Models\User;
use App\Services\SurveyFolderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class SurveyFolderTest extends TestCase
{
    use RefreshDatabase;

    protected Ship $ship;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ship = Ship::create(['name' => 'Alpha', 'status' => 'active']);
        $this->actingAs(User::factory()->create());
        Gate::before(fn () => true);
    }

    protected function makeSurvey(string $number, ?int $folderId = null): Survey
    {
        return Survey::create([
            'survey_number' => $number,
            'ship_id' => $this->ship->id,
            'survey_date' => '2026-01-01',
            'status' => 'draft',
            'survey_folder_id' => $folderId,
        ]);
    }

    public function test_folder_crud_via_component(): void
    {
        Livewire::test(SurveyManagement::class)
            ->call('createFolder')
            ->set('folderName', 'Survey 2026')
            ->call('saveFolder');

        $folder = SurveyFolder::firstWhere('name', 'Survey 2026');
        $this->assertNotNull($folder);

        // Rename
        Livewire::test(SurveyManagement::class)
            ->call('editFolder', $folder->id)
            ->set('folderName', 'Arsip 2026')
            ->call('saveFolder');
        $this->assertSame('Arsip 2026', $folder->fresh()->name);

        // Delete folder kosong
        Livewire::test(SurveyManagement::class)
            ->call('confirmDeleteFolder', $folder->id)
            ->call('deleteFolder');
        $this->assertNull(SurveyFolder::find($folder->id));
    }

    public function test_folder_name_must_be_unique(): void
    {
        SurveyFolder::create(['name' => 'Duplikat']);

        Livewire::test(SurveyManagement::class)
            ->call('createFolder')
            ->set('folderName', 'Duplikat')
            ->call('saveFolder')
            ->assertHasErrors('folderName');
    }

    public function test_root_list_only_shows_unfiled_surveys(): void
    {
        $folder = SurveyFolder::create(['name' => 'Grup A']);
        $this->makeSurvey('SVY-0001');            // root
        $this->makeSurvey('SVY-0002', $folder->id); // di dalam folder

        $component = Livewire::test(SurveyManagement::class);
        $this->assertSame(['SVY-0001'], $component->viewData('surveys')->pluck('survey_number')->all());
        $this->assertSame(['Grup A'], $component->viewData('folders')->pluck('name')->all());
    }

    public function test_open_folder_shows_only_its_surveys(): void
    {
        $folder = SurveyFolder::create(['name' => 'Grup A']);
        $this->makeSurvey('SVY-0001');
        $this->makeSurvey('SVY-0002', $folder->id);

        $component = Livewire::test(SurveyManagement::class)->call('openFolder', $folder->id);

        $this->assertSame($folder->id, $component->get('folderId'));
        $this->assertSame(['SVY-0002'], $component->viewData('surveys')->pluck('survey_number')->all());
        $this->assertSame($folder->id, $component->viewData('currentFolder')->id);

        // Kembali ke root
        $component->call('backToRoot');
        $this->assertNull($component->get('folderId'));
        $this->assertSame(['SVY-0001'], $component->viewData('surveys')->pluck('survey_number')->all());
    }

    public function test_active_folder_persists_via_url_query(): void
    {
        $folder = SurveyFolder::create(['name' => 'Grup A']);
        $this->makeSurvey('SVY-0001', $folder->id);

        // Simulasi refresh: mount dengan query ?folder=<ciphertext> (alias URL folderKey)
        $component = Livewire::withQueryParams(['folder' => $folder->getRouteKey()])
            ->test(SurveyManagement::class);

        $this->assertSame($folder->id, $component->get('folderId'));
        $this->assertSame(['SVY-0001'], $component->viewData('surveys')->pluck('survey_number')->all());
    }

    public function test_tampered_folder_key_falls_back_to_root(): void
    {
        $this->makeSurvey('SVY-0001');

        $component = Livewire::withQueryParams(['folder' => 'ciphertext-palsu'])
            ->test(SurveyManagement::class);

        $this->assertNull($component->get('folderId'));
        $this->assertNull($component->get('folderKey'));
        $this->assertSame(['SVY-0001'], $component->viewData('surveys')->pluck('survey_number')->all());
    }

    public function test_move_survey_to_folder_and_back_to_root(): void
    {
        $folder = SurveyFolder::create(['name' => 'Grup A']);
        $survey = $this->makeSurvey('SVY-0001');

        Livewire::test(SurveyManagement::class)
            ->call('openMoveModal', $survey->id)
            ->set('moveFolderId', (string) $folder->id)
            ->call('saveMove');
        $this->assertSame($folder->id, $survey->fresh()->survey_folder_id);

        // Keluarkan ke root
        Livewire::test(SurveyManagement::class)
            ->call('openFolder', $folder->id)
            ->call('openMoveModal', $survey->id)
            ->set('moveFolderId', '')
            ->call('saveMove');
        $this->assertNull($survey->fresh()->survey_folder_id);
    }

    public function test_bulk_move_selected_surveys_to_folder(): void
    {
        $folder = SurveyFolder::create(['name' => 'Grup A']);
        $s1 = $this->makeSurvey('SVY-0001');
        $s2 = $this->makeSurvey('SVY-0002');
        $s3 = $this->makeSurvey('SVY-0003');

        $component = Livewire::test(SurveyManagement::class)
            ->set('selected', [(string) $s1->id, (string) $s2->id])
            ->call('openBulkMoveModal')
            ->set('moveFolderId', (string) $folder->id)
            ->call('saveMove');

        $this->assertSame($folder->id, $s1->fresh()->survey_folder_id);
        $this->assertSame($folder->id, $s2->fresh()->survey_folder_id);
        $this->assertNull($s3->fresh()->survey_folder_id);
        $this->assertSame([], $component->get('selected'));

        // Bulk keluarkan ke root
        Livewire::test(SurveyManagement::class)
            ->call('openFolder', $folder->id)
            ->set('selected', [(string) $s1->id, (string) $s2->id])
            ->call('openBulkMoveModal')
            ->set('moveFolderId', '')
            ->call('saveMove');

        $this->assertNull($s1->fresh()->survey_folder_id);
        $this->assertNull($s2->fresh()->survey_folder_id);
    }

    public function test_duplicate_survey_without_values_into_folder(): void
    {
        $folder = SurveyFolder::create(['name' => 'Grup A']);
        $source = $this->makeSurvey('SVY-0001');
        $source->update(['status' => 'completed', 'overall_cap_score' => 4.50]);

        // Respon sumber diisi nilai — harus TIDAK ikut tersalin
        \DB::table('survey_responses')->insert([
            'survey_id' => $source->id,
            'survey_item_id' => 999,
            'scores' => json_encode(['C' => 5]),
            'avg_score' => 5,
        ]);

        Livewire::test(SurveyManagement::class)
            ->call('openDuplicateModal', $source->id)
            ->set('duplicateFolderId', (string) $folder->id)
            ->call('duplicateSurvey');

        $dup = Survey::where('id', '!=', $source->id)->latest('id')->first();
        $this->assertNotNull($dup);
        $this->assertNotSame($source->survey_number, $dup->survey_number);
        $this->assertSame($folder->id, $dup->survey_folder_id);
        $this->assertSame($source->ship_id, $dup->ship_id);
        $this->assertSame(\App\Enums\SurveyStatus::Draft, $dup->status);
        $this->assertNull($dup->overall_cap_score);
        // Semua respon duplikat kosong
        $this->assertTrue($dup->responses()->whereNotNull('avg_score')->doesntExist());
    }

    public function test_folder_with_surveys_cannot_be_deleted(): void
    {
        $folder = SurveyFolder::create(['name' => 'Grup A']);
        $this->makeSurvey('SVY-0001', $folder->id);

        $this->expectException(\RuntimeException::class);
        (new SurveyFolderService)->delete($folder);
    }

    public function test_create_survey_inside_folder_carries_folder_param(): void
    {
        $folder = SurveyFolder::create(['name' => 'Grup A']);
        $template = \App\Models\SurveyTemplate::create([
            'name' => 'Template A', 'code' => 'TPL-A', 'is_active' => true,
        ]);

        $component = Livewire::test(SurveyManagement::class)
            ->call('openFolder', $folder->id)
            ->call('proceedWithTemplate', $template->id);

        $redirect = $component->effects['redirect'] ?? '';
        $this->assertStringContainsString('folder=', $redirect);

        // Param folder harus bisa di-decrypt kembali ke id folder
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertSame(
            (string) $folder->id,
            \Illuminate\Support\Facades\Crypt::decryptString($query['folder'])
        );
    }
}
