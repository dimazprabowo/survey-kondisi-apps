<?php

namespace Tests\Feature;

use App\Events\SurveyContentUpdated;
use App\Livewire\Surveys\SurveyForm;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyCategory;
use App\Models\SurveyItem;
use App\Models\SurveyItemGroup;
use App\Models\SurveySubCategory;
use App\Models\SurveyTemplate;
use App\Models\User;
use App\Services\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kolaborasi realtime SurveyForm: cek versi konten (loadedVersion) agar
 * simpan tidak menimpa perubahan editor lain secara diam-diam, reload
 * state saat remote save, dan broadcast posisi tab.
 */
class SurveyCollaborationTest extends TestCase
{
    use RefreshDatabase;

    protected Survey $survey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Gate::before(fn () => true);

        $template = SurveyTemplate::create([
            'name' => 'Template Test',
            'code' => 'TPL-COLLAB',
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

        $ship = Ship::create([
            'name' => 'Kapal Utama',
            'call_sign' => 'CS-UTAMA',
            'status' => 'active',
        ]);

        $this->survey = (new SurveyService)->create([
            'ship_id' => $ship->id,
            'survey_template_id' => $template->id,
            'survey_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'notes' => 'catatan awal',
        ]);
    }

    /**
     * Simulasi simpan oleh editor lain — updated_at lebih baru dari
     * loadedVersion dan updated_by menunjuk user berbeda.
     */
    protected function remoteSave(): User
    {
        $other = User::factory()->create();
        Survey::whereKey($this->survey->id)->update([
            'notes' => 'diubah editor lain',
            'updated_by' => $other->id,
            'updated_at' => now()->addSecond(),
        ]);

        return $other;
    }

    public function test_save_intercepted_when_remote_editor_saved_first(): void
    {
        $this->remoteSave();

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->set('notes', 'isian saya')
            ->call('save')
            ->assertDispatched('confirm-overwrite-survey');

        // Perubahan remote belum tertimpa — persist menunggu konfirmasi.
        $this->assertSame('diubah editor lain', $this->survey->fresh()->notes);
    }

    public function test_confirm_overwrite_save_persists_after_confirmation(): void
    {
        $this->remoteSave();

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->set('notes', 'isian saya')
            ->call('save')
            ->assertDispatched('confirm-overwrite-survey')
            ->call('confirmOverwriteSave');

        $this->assertSame('isian saya', $this->survey->fresh()->notes);
    }

    public function test_save_without_remote_change_proceeds_normally(): void
    {
        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->set('notes', 'isian saya')
            ->call('save')
            ->assertNotDispatched('confirm-overwrite-survey');

        $this->assertSame('isian saya', $this->survey->fresh()->notes);
    }

    public function test_own_save_does_not_trigger_conflict(): void
    {
        // Remote save tapi updated_by = user yang sama (mis. tab lain miliknya)
        Survey::whereKey($this->survey->id)->update([
            'updated_by' => auth()->id(),
            'updated_at' => now()->addSecond(),
        ]);

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->set('notes', 'isian saya')
            ->call('save')
            ->assertNotDispatched('confirm-overwrite-survey');
    }

    public function test_refresh_collaborative_state_reloads_remote_changes(): void
    {
        $this->remoteSave();

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->assertSet('notes', 'catatan awal')
            ->call('refreshCollaborativeState', 'Editor Lain')
            ->assertSet('notes', 'diubah editor lain');
    }

    public function test_save_draft_broadcasts_only_changed_fields(): void
    {
        Event::fake([SurveyContentUpdated::class]);
        $item = SurveyItem::first();

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->set('notes', 'draft kolaboratif')
            ->set("responses.{$item->id}.scores.C", 3)
            ->call('saveDraft');

        Event::assertDispatched(SurveyContentUpdated::class, function ($e) use ($item) {
            return $e->kind === 'draft'
                && ! $e->structureChanged
                && array_keys($e->changes) == ['notes', "responses.{$item->id}.scores.C"]
                && $e->changes['notes'] === 'draft kolaboratif';
        });
    }

    public function test_two_fields_in_same_row_merge_without_overwrite(): void
    {
        Event::fake([SurveyContentUpdated::class]);
        $item = SurveyItem::first();

        // Lokal mengisi skor C; kolaborator mengisi skor V di baris yang sama
        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->set("responses.{$item->id}.scores.C", 3)
            ->call('applyRemoteChanges', ["responses.{$item->id}.scores.V" => 4])
            ->assertSet("responses.{$item->id}.scores.C", 3)
            ->assertSet("responses.{$item->id}.scores.V", 4)
            ->call('saveDraft');

        // Hanya field lokal yang dikirim — skor V kolaborator tidak terpantul
        Event::assertDispatched(SurveyContentUpdated::class, fn ($e) => $e->changes === ["responses.{$item->id}.scores.C" => 3]);
    }

    public function test_changes_from_multiple_collaborators_merge(): void
    {
        Event::fake([SurveyContentUpdated::class]);
        $item = SurveyItem::first();

        // Dua kolaborator berbeda mengirim field berbeda secara beruntun
        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->call('applyRemoteChanges', ["responses.{$item->id}.scores.C" => 2])
            ->call('applyRemoteChanges', ["responses.{$item->id}.scores.V" => 3, 'surveyor' => 'Kolaborator B'])
            ->assertSet("responses.{$item->id}.scores.C", 2)
            ->assertSet("responses.{$item->id}.scores.V", 3)
            ->assertSet('surveyor', 'Kolaborator B')
            ->call('saveDraft');

        // Tidak ada yang dipantulkan balik ke para kolaborator
        Event::assertNotDispatched(SurveyContentUpdated::class);
    }

    public function test_adding_group_note_sends_whole_note_list(): void
    {
        Event::fake([SurveyContentUpdated::class]);
        $groupId = SurveyItemGroup::first()->id;

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->call('addGroupNote', $groupId)
            ->set("groupNotes.{$groupId}.0", 'catatan baru')
            ->call('saveDraft');

        Event::assertDispatched(SurveyContentUpdated::class, fn ($e) => $e->changes === ["groupNotes.{$groupId}" => ['catatan baru']]);
    }

    public function test_save_draft_syncs_even_when_auto_save_disabled(): void
    {
        Event::fake([SurveyContentUpdated::class]);

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->set('autoSave', false)
            ->set('notes', 'tanpa autosave')
            ->call('saveDraft');

        Event::assertDispatched(SurveyContentUpdated::class, fn ($e) => $e->changes === ['notes' => 'tanpa autosave']);
    }

    public function test_apply_remote_changes_sets_fields_without_echo(): void
    {
        Event::fake([SurveyContentUpdated::class]);
        $item = SurveyItem::first();

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->call('applyRemoteChanges', [
                'notes' => 'dari kolaborator',
                "responses.{$item->id}.scores.C" => 2,
            ])
            ->assertSet('notes', 'dari kolaborator')
            ->assertSet("responses.{$item->id}.scores.C", 2)
            // Nilai remote sudah masuk baseline → tidak dipantulkan balik
            ->call('saveDraft');

        Event::assertNotDispatched(SurveyContentUpdated::class);
    }

    public function test_apply_remote_changes_ignores_non_whitelisted_paths(): void
    {
        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->call('applyRemoteChanges', [
                'surveyId' => 999,
                'editMode' => false,
                'structureTree' => ['categories' => []],
                'groupNotes.999999' => ['grup tidak ada'],
            ])
            ->assertSet('surveyId', $this->survey->id)
            ->assertSet('editMode', true);
    }

    public function test_remote_changes_do_not_swallow_local_edits(): void
    {
        Event::fake([SurveyContentUpdated::class]);

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->set('surveyor', 'isian lokal')
            ->call('applyRemoteChanges', ['notes' => 'dari kolaborator'])
            ->call('saveDraft');

        // Perubahan lokal tetap terkirim; nilai remote tidak ikut terpantul
        Event::assertDispatched(SurveyContentUpdated::class, fn ($e) => $e->changes === ['surveyor' => 'isian lokal']);
    }

    public function test_work_state_survives_session_loss(): void
    {
        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->call('toggleAutoSave')
            ->call('toggleAutoSave')
            ->set('notes', 'draft tahan sesi')
            ->call('saveDraft');

        // Simulasi session ditimpa request paralel (wire:poll) — state
        // form ada di cache per-key, jadi tetap pulih.
        session()->flush();

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->assertSet('autoSave', true)
            ->assertSet('hasDraft', true)
            ->assertSet('notes', 'draft tahan sesi');
    }

    public function test_save_draft_without_changes_does_not_broadcast(): void
    {
        Event::fake([SurveyContentUpdated::class]);

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->call('saveDraft');

        Event::assertNotDispatched(SurveyContentUpdated::class);
    }

    public function test_apply_remote_draft_loads_collaborator_working_copy(): void
    {
        $item = SurveyItem::first();
        $other = User::factory()->create();

        Cache::put("survey_shared_draft:{$this->survey->id}:{$other->id}", [
            'ship_id' => $this->survey->ship_id,
            'is_new_ship' => false,
            'edit_ship_data' => false,
            'shipData' => [],
            'shipCertificates' => [],
            'survey_date' => now()->format('Y-m-d'),
            'surveyor' => null,
            'location' => null,
            'status' => 'draft',
            'notes' => 'isian remote',
            'responses' => [$item->id => ['scores' => ['C' => 4]]],
            'groupNotes' => [],
            'structure' => null,
            'activeCategory' => null,
            'activeSubCategory' => null,
            'saved_at' => now()->toIso8601String(),
        ], now()->addHour());

        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->assertSet('notes', 'catatan awal')
            ->call('applyRemoteDraft', $other->id)
            ->assertSet('notes', 'isian remote')
            ->assertSet('hasDraft', true)
            ->assertSet("responses.{$item->id}.scores.C", 4)
            ->assertDispatched('remote-draft-applied');
    }

    public function test_apply_remote_draft_noop_outside_edit_mode(): void
    {
        Livewire::test(SurveyForm::class)
            ->call('applyRemoteDraft', 1)
            ->assertOk();
    }

    public function test_broadcast_focus_requires_edit_mode(): void
    {
        // Create mode (tanpa survey) — no-op, tidak broadcast
        Livewire::test(SurveyForm::class)
            ->call('broadcastFocus', 1)
            ->assertOk();

        // Edit mode — lolos gate dan jalan tanpa error (broadcast log driver)
        Livewire::test(SurveyForm::class, ['survey' => $this->survey])
            ->call('broadcastFocus', 1)
            ->assertOk();
    }
}
