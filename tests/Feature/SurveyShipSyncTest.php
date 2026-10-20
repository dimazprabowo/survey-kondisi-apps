<?php

namespace Tests\Feature;

use App\Enums\ShipClassStatus;
use App\Livewire\MasterData\ShipManagement;
use App\Livewire\Surveys\SurveyForm;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyCategory;
use App\Models\SurveyItem;
use App\Models\SurveyItemGroup;
use App\Models\SurveySubCategory;
use App\Models\SurveyTemplate;
use App\Models\User;
use App\Services\SurveyReportService;
use App\Services\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Snapshot data kapal pada survey + sinkron balik ke master saat status
 * survey menjadi Selesai (dicocokkan via Call Sign → fallback ship_id).
 */
class SurveyShipSyncTest extends TestCase
{
    use RefreshDatabase;

    protected SurveyTemplate $template;

    protected Ship $ship;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Gate::before(fn () => true);

        $this->template = SurveyTemplate::create([
            'name' => 'Template Test',
            'code' => 'TPL-SYNC',
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
        $group = SurveyItemGroup::create([
            'survey_sub_category_id' => $subCategory->id,
            'name' => 'Grup Test',
            'order_num' => 1,
        ]);
        SurveyItem::create([
            'survey_item_group_id' => $group->id,
            'name' => 'Item Test',
            'item_type' => 'score',
            'score_labels' => ['C', 'V'],
            'order_num' => 1,
        ]);

        $this->ship = Ship::create([
            'name' => 'Kapal Utama',
            'call_sign' => 'CS-UTAMA',
            'imo_number' => '1111111',
            'gross_tonnage' => '500',
            'status' => 'active',
            'class_status' => 'active',
        ]);
        $this->ship->certificates()->create([
            'certificate_type' => 'Special Survey',
            'last_date' => '2020-01-01',
            'next_1_date' => '2025-01-01',
            'order_num' => 1,
        ]);
    }

    protected function createSurvey(string $status = 'draft'): Survey
    {
        return (new SurveyService)->create([
            'ship_id' => $this->ship->id,
            'survey_template_id' => $this->template->id,
            'survey_date' => now()->format('Y-m-d'),
            'status' => $status,
        ]);
    }

    public function test_selecting_ship_fills_snapshot_fields(): void
    {
        Livewire::test(SurveyForm::class)
            ->set('ship_id', $this->ship->id)
            ->assertSet('shipData.name', 'Kapal Utama')
            ->assertSet('shipData.call_sign', 'CS-UTAMA')
            ->assertSet('shipData.gross_tonnage', '500')
            ->assertCount('shipCertificates', 1);
    }

    public function test_ship_snapshot_saved_with_survey(): void
    {
        Livewire::test(SurveyForm::class)
            ->set('ship_id', $this->ship->id)
            ->call('save');

        $survey = Survey::latest()->firstOrFail();

        $this->assertSame('Kapal Utama', $survey->ship_snapshot['attributes']['name']);
        $this->assertSame('CS-UTAMA', $survey->ship_snapshot['attributes']['call_sign']);
        $this->assertCount(1, $survey->ship_snapshot['certificates']);
    }

    public function test_completed_status_with_changes_opens_sync_modal_without_saving(): void
    {
        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('status', 'completed')
            ->set('shipData.gross_tonnage', '999')
            ->call('save')
            ->assertDispatched('open-modal', 'survey-ship-sync-modal');

        // Survey & master belum tersimpan — menunggu konfirmasi user.
        $this->assertSame('draft', $survey->fresh()->status->value);
        $this->assertSame('500', $this->ship->fresh()->gross_tonnage);
    }

    public function test_confirm_sync_saves_survey_and_overwrites_master(): void
    {
        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('status', 'completed')
            ->set('shipData.gross_tonnage', '999')
            ->call('save')
            ->call('confirmShipSync');

        $this->assertSame('completed', $survey->fresh()->status->value);
        $this->assertSame('999', $this->ship->fresh()->gross_tonnage);
        $this->assertSame('999', $survey->fresh()->ship_snapshot['attributes']['gross_tonnage']);
    }

    public function test_confirm_sync_matches_other_ship_by_call_sign(): void
    {
        $other = Ship::create([
            'name' => 'Kapal Lain',
            'call_sign' => 'CS-LAIN',
            'gross_tonnage' => '100',
            'status' => 'active',
            'class_status' => 'active',
        ]);

        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('status', 'completed')
            ->set('shipData.call_sign', 'CS-LAIN')
            // IMO ikut dikoreksi — IMO kapal terpilih (1111111) dilarang
            // ditimpa ke kapal lain (bentrok unik).
            ->set('shipData.imo_number', '2222222')
            ->set('shipData.gross_tonnage', '777')
            ->call('save')
            ->call('confirmShipSync');

        // Kapal yang cocok via Call Sign yang diperbarui — bukan pilihan form.
        $this->assertSame('777', $other->fresh()->gross_tonnage);
        $this->assertSame('500', $this->ship->fresh()->gross_tonnage);
    }

    public function test_confirm_sync_falls_back_to_ship_id_when_call_sign_empty(): void
    {
        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('status', 'completed')
            ->set('shipData.call_sign', '')
            ->set('shipData.gross_tonnage', '888')
            ->call('save')
            ->call('confirmShipSync');

        $ship = $this->ship->fresh();
        $this->assertSame('888', $ship->gross_tonnage);
        $this->assertNull($ship->call_sign);
    }

    public function test_corrected_call_sign_updates_selected_ship(): void
    {
        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('status', 'completed')
            ->set('shipData.call_sign', 'CS-BARU')
            ->call('save')
            ->call('confirmShipSync');

        // Call Sign baru belum ada di master → fallback ship_id →
        // kapal terpilih yang ikut dikoreksi call_sign-nya.
        $this->assertSame('CS-BARU', $this->ship->fresh()->call_sign);
    }

    public function test_cancel_sync_keeps_survey_and_master_unchanged(): void
    {
        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('status', 'completed')
            ->set('shipData.gross_tonnage', '999')
            ->call('save')
            ->call('closeShipSyncModal')
            ->assertDispatched('close-modal', 'survey-ship-sync-modal');

        $this->assertSame('draft', $survey->fresh()->status->value);
        $this->assertSame('500', $this->ship->fresh()->gross_tonnage);
    }

    public function test_empty_survey_field_wipes_master_value(): void
    {
        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('status', 'completed')
            ->set('shipData.gross_tonnage', '')
            ->call('save')
            ->call('confirmShipSync');

        $this->assertNull($this->ship->fresh()->gross_tonnage);
    }

    public function test_no_modal_when_snapshot_identical_to_master(): void
    {
        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('status', 'completed')
            ->call('save')
            ->assertNotDispatched('open-modal');

        $this->assertSame('completed', $survey->fresh()->status->value);
    }

    public function test_identifier_collision_with_other_ship_blocks_sync(): void
    {
        Ship::create([
            'name' => 'Kapal Lain',
            'imo_number' => '9999999',
            'status' => 'active',
            'class_status' => 'active',
        ]);

        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('status', 'completed')
            ->set('shipData.call_sign', '')
            ->set('shipData.imo_number', '9999999')
            ->call('save')
            ->assertHasErrors(['shipData.imo_number' => 'unique']);

        $this->assertSame('draft', $survey->fresh()->status->value);
        $this->assertSame('1111111', $this->ship->fresh()->imo_number);
    }

    public function test_certificates_overwritten_on_sync(): void
    {
        $survey = $this->createSurvey();

        $component = Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('status', 'completed')
            ->set('shipCertificates.0.certificate_type', 'Annual Survey')
            ->set('shipCertificates.0.next_2_date', '2030-01-01')
            ->call('save')
            ->call('confirmShipSync');

        $certs = $this->ship->fresh()->certificates;
        $this->assertCount(1, $certs);
        $this->assertSame('Annual Survey', $certs->first()->certificate_type);
        $this->assertSame('2030-01-01', $certs->first()->next_2_date?->format('Y-m-d'));
    }

    public function test_report_uses_ship_snapshot_not_live_master(): void
    {
        $survey = $this->createSurvey('completed');
        $survey->update([
            'ship_snapshot' => [
                'attributes' => ['name' => 'Kapal Saat Survey', 'year_built' => 2001, 'gross_tonnage' => '321'],
                'certificates' => [
                    ['certificate_type' => 'Docking', 'last_date' => '2020-06-01'],
                ],
            ],
        ]);

        // Master berubah setelah survey — laporan tetap pakai snapshot.
        $this->ship->update(['name' => 'Nama Master Baru', 'gross_tonnage' => '999']);
        $this->ship->certificates()->delete();

        $service = app(SurveyReportService::class);
        $report = $service->getOrCreate($survey->fresh());
        $ship = $service->buildReportData($report)['ship'];

        $this->assertSame('Kapal Saat Survey', $ship->name);
        $this->assertSame('321', $ship->gross_tonnage);
        $this->assertSame('Docking', $ship->certificates->first()->certificate_type);
    }

    public function test_edit_form_loads_snapshot_not_stale_master(): void
    {
        $survey = $this->createSurvey();
        $survey->update([
            'ship_snapshot' => [
                'attributes' => ['name' => 'Kapal Saat Survey', 'class_status' => 'active'],
                'certificates' => [],
            ],
        ]);
        $this->ship->update(['name' => 'Nama Master Baru']);

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->assertSet('shipData.name', 'Kapal Saat Survey');
    }

    // ------------------------------------------------------------------
    // Wizard langkah + opsi "Kapal Baru" + sinkron manual (Update Master)
    // ------------------------------------------------------------------

    public function test_wizard_next_step_validates_before_advancing(): void
    {
        Livewire::test(SurveyForm::class)
            ->assertSet('activeStep', 1)
            // ship_id kosong → validasi langkah 1 menahan user di tempat.
            ->call('nextStep')
            ->assertHasErrors(['ship_id'])
            ->assertSet('activeStep', 1)
            ->set('ship_id', $this->ship->id)
            ->call('nextStep')
            ->assertSet('activeStep', 2)
            ->call('nextStep')
            ->assertSet('activeStep', 3)
            ->call('prevStep')
            ->assertSet('activeStep', 2);
    }

    public function test_save_validation_error_jumps_to_owning_step(): void
    {
        Livewire::test(SurveyForm::class)
            ->set('ship_id', $this->ship->id)
            ->call('nextStep')
            ->call('nextStep')
            ->assertSet('activeStep', 3)
            ->set('shipData.name', '')
            ->call('save')
            ->assertHasErrors(['shipData.name'])
            ->assertSet('activeStep', 2);
    }

    public function test_edit_ship_data_toggle_locks_snapshot_fields(): void
    {
        Livewire::test(SurveyForm::class)
            ->assertSet('editShipData', false)
            ->call('toggleEditShipData')
            ->assertSet('editShipData', true);
    }

    public function test_new_ship_option_empties_snapshot_and_unlocks_edit(): void
    {
        Livewire::test(SurveyForm::class)
            ->set('ship_id', 'new')
            ->assertSet('isNewShip', true)
            ->assertSet('editShipData', true)
            ->assertSet('shipData.name', null)
            ->assertCount('shipCertificates', 0);
    }

    public function test_new_ship_survey_saves_without_master_link(): void
    {
        Livewire::test(SurveyForm::class)
            ->set('ship_id', 'new')
            ->set('shipData.name', 'Kapal Benar-benar Baru')
            ->set('shipData.call_sign', 'CS-NEW')
            ->call('save');

        $survey = Survey::latest()->firstOrFail();
        $this->assertNull($survey->ship_id);
        $this->assertSame('Kapal Benar-benar Baru', $survey->ship_snapshot['attributes']['name']);
        // Master belum dibuat — record baru dibuat saat sinkron.
        $this->assertNull(Ship::where('call_sign', 'CS-NEW')->first());
    }

    public function test_completed_new_ship_creates_master_and_links_survey(): void
    {
        Livewire::test(SurveyForm::class)
            ->set('ship_id', 'new')
            ->set('shipData.name', 'Kapal Baru Sekali')
            ->set('shipData.call_sign', 'CS-FRESH')
            ->set('shipData.gross_tonnage', '123')
            ->set('status', 'completed')
            ->call('save')
            ->assertDispatched('open-modal', 'survey-ship-sync-modal')
            ->call('confirmShipSync');

        $survey = Survey::latest()->firstOrFail();
        $ship = Ship::where('call_sign', 'CS-FRESH')->firstOrFail();
        $this->assertSame('Kapal Baru Sekali', $ship->name);
        $this->assertSame('123', $ship->gross_tonnage);
        $this->assertSame($ship->id, $survey->fresh()->ship_id);
    }

    public function test_update_master_button_pushes_changes_on_draft(): void
    {
        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('shipData.gross_tonnage', '999')
            ->call('updateMasterShip')
            ->assertDispatched('open-modal', 'survey-ship-sync-modal')
            ->call('confirmShipSync');

        // Master tertimpa; survey tetap draft & belum tersimpan.
        $this->assertSame('999', $this->ship->fresh()->gross_tonnage);
        $this->assertSame('draft', $survey->fresh()->status->value);
    }

    public function test_update_master_button_noops_when_no_changes(): void
    {
        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->call('updateMasterShip')
            ->assertNotDispatched('open-modal');

        $this->assertSame('500', $this->ship->fresh()->gross_tonnage);
    }

    public function test_update_master_button_creates_ship_for_new_ship(): void
    {
        Livewire::test(SurveyForm::class)
            ->set('ship_id', 'new')
            ->set('shipData.name', 'Kapal Via Tombol')
            ->set('shipData.call_sign', 'CS-BTN')
            ->call('updateMasterShip')
            ->assertDispatched('open-modal', 'survey-ship-sync-modal')
            ->call('confirmShipSync')
            ->assertSet('isNewShip', false);

        $ship = Ship::where('call_sign', 'CS-BTN')->firstOrFail();
        $this->assertSame('Kapal Via Tombol', $ship->name);
    }

    public function test_reload_ship_from_master_refreshes_snapshot(): void
    {
        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('shipData.gross_tonnage', '999')
            ->call('reloadShipFromMaster')
            ->assertSet('shipData.gross_tonnage', '500');

        // Master & survey tersimpan tidak berubah — hanya isian form.
        $this->assertSame('500', $this->ship->fresh()->gross_tonnage);
    }

    public function test_reload_ship_from_master_resolves_by_call_sign(): void
    {
        $other = Ship::create([
            'name' => 'Kapal Lain',
            'call_sign' => 'CS-LAIN',
            'gross_tonnage' => '100',
            'status' => 'active',
            'class_status' => 'active',
        ]);

        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->set('shipData.call_sign', 'CS-LAIN')
            ->call('reloadShipFromMaster')
            ->assertSet('shipData.name', 'Kapal Lain')
            ->assertSet('shipData.gross_tonnage', '100');
    }

    public function test_survey_without_ship_shows_snapshot_display_name(): void
    {
        $survey = $this->createSurvey();
        $survey->update([
            'ship_id' => null,
            'ship_snapshot' => [
                'attributes' => ['name' => 'Kapal Dari Snapshot'],
                'certificates' => [],
            ],
        ]);

        $this->assertSame('Kapal Dari Snapshot', $survey->fresh()->ship_display_name);
    }

    public function test_ship_with_surveys_can_be_deleted_and_snapshot_preserved(): void
    {
        $survey = $this->createSurvey();
        $survey->update([
            'ship_snapshot' => [
                'attributes' => ['name' => 'Kapal Utama'],
                'certificates' => [],
            ],
        ]);

        Livewire::test(ShipManagement::class)
            ->call('confirmDelete', $this->ship->id)
            ->assertSet('deletingShipSurveysCount', 1)
            ->call('delete');

        $this->assertTrue(Ship::withTrashed()->findOrFail($this->ship->id)->trashed());
        $survey->refresh();
        $this->assertNotNull($survey);
        $this->assertSame('Kapal Utama', $survey->ship_snapshot['attributes']['name']);
        $this->assertSame('Kapal Utama', $survey->ship_display_name);
    }

    public function test_inline_class_status_update_via_searchable_select(): void
    {
        Livewire::test(ShipManagement::class)
            ->set('classStatusEdits.'.$this->ship->id, ShipClassStatus::Suspended->value);

        $this->assertSame(ShipClassStatus::Suspended, $this->ship->fresh()->class_status);
    }

    public function test_active_step_restored_from_query_string(): void
    {
        Livewire::withQueryParams(['step' => '2'])
            ->test(SurveyForm::class)
            ->assertSet('activeStep', 2);
    }

    public function test_invalid_step_query_is_clamped(): void
    {
        Livewire::withQueryParams(['step' => '99'])
            ->test(SurveyForm::class)
            ->assertSet('activeStep', 3);
    }

    public function test_reload_from_master_requires_confirmation(): void
    {
        Livewire::test(SurveyForm::class)
            ->set('ship_id', $this->ship->id)
            ->call('confirmReloadShipFromMaster')
            ->assertDispatched('confirm-pull-master');
    }

    public function test_remove_certificate_requires_confirmation(): void
    {
        Livewire::test(SurveyForm::class)
            ->set('ship_id', $this->ship->id)
            ->call('confirmRemoveShipCertificate', 0)
            ->assertDispatched('confirm-remove-cert');
    }

    public function test_active_category_tab_persists_across_reload(): void
    {
        $category2 = SurveyCategory::create([
            'survey_template_id' => $this->template->id,
            'label' => 'Kategori Kedua',
            'order_num' => 2,
        ]);

        $survey = $this->createSurvey();

        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->call('setCategory', $category2->id)
            ->assertSet('activeCategory', $category2->id);

        // Simulasi hard refresh — mount ulang dengan session yang sama
        Livewire::test(SurveyForm::class, ['survey' => $survey])
            ->assertSet('activeCategory', $category2->id);
    }
}
