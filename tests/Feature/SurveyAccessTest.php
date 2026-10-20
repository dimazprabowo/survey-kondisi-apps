<?php

namespace Tests\Feature;

use App\Enums\SurveyAccessStatus;
use App\Livewire\Surveys\SurveyShow;
use App\Models\Survey;
use App\Models\User;
use App\Services\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Kepemilikan survey: delete/edit hanya pemilik (super admin bypass),
 * akses edit non-owner lewat permintaan yang disetujui pemilik.
 */
class SurveyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $name) {
            $user->givePermissionTo(Permission::findOrCreate($name));
        }

        return $user;
    }

    protected function survey(?User $owner = null): Survey
    {
        return Survey::create([
            'survey_number' => 'SV-'.fake()->unique()->numberBetween(1000, 9999),
            'survey_date' => '2026-01-01',
            'status' => 'draft',
            'created_by' => $owner?->id,
        ]);
    }

    public function test_owner_can_update_and_delete_with_permission(): void
    {
        $owner = $this->userWith('surveys_update', 'surveys_delete');
        $survey = $this->survey($owner);

        $this->assertTrue($owner->can('update', $survey));
        $this->assertTrue($owner->can('delete', $survey));
    }

    public function test_non_owner_cannot_update_or_delete_despite_permission(): void
    {
        $owner = $this->userWith();
        $other = $this->userWith('surveys_update', 'surveys_delete');
        $survey = $this->survey($owner);

        $this->assertFalse($other->can('update', $survey));
        $this->assertFalse($other->can('delete', $survey));
    }

    public function test_super_admin_bypasses_ownership(): void
    {
        $owner = $this->userWith();
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate('super admin'));
        $survey = $this->survey($owner);

        $this->assertTrue($admin->can('update', $survey));
        $this->assertTrue($admin->can('delete', $survey));
    }

    public function test_survey_without_owner_is_editable_by_any_permission_holder(): void
    {
        $user = $this->userWith('surveys_update', 'surveys_delete');
        $survey = $this->survey(); // created_by null — data lama/seeder

        $this->assertTrue($user->can('update', $survey));
        $this->assertTrue($user->can('delete', $survey));
    }

    public function test_request_creates_pending_record_and_notifies_owner(): void
    {
        $owner = $this->userWith();
        $requester = $this->userWith('surveys_update');
        $survey = $this->survey($owner);
        $this->actingAs($requester);

        $request = app(SurveyService::class)->requestEditAccess($survey, $requester);

        $this->assertSame(SurveyAccessStatus::Pending, $request->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => 'warning',
        ]);
    }

    public function test_approved_request_grants_edit_access(): void
    {
        $owner = $this->userWith();
        $requester = $this->userWith('surveys_update');
        $survey = $this->survey($owner);
        $service = app(SurveyService::class);

        $request = $service->requestEditAccess($survey, $requester);
        $service->decideAccessRequest($request, true, $owner);

        $this->assertTrue($requester->can('update', $survey->fresh()));
        $this->assertDatabaseHas('notifications', [
            'user_id' => $requester->id,
            'type' => 'success',
        ]);
    }

    public function test_rejected_request_denies_edit_access(): void
    {
        $owner = $this->userWith();
        $requester = $this->userWith('surveys_update');
        $survey = $this->survey($owner);
        $service = app(SurveyService::class);

        $request = $service->requestEditAccess($survey, $requester);
        $service->decideAccessRequest($request, false, $owner);

        $this->assertFalse($requester->can('update', $survey->fresh()));
    }

    public function test_revoked_access_removes_edit_permission(): void
    {
        $owner = $this->userWith();
        $requester = $this->userWith('surveys_update');
        $survey = $this->survey($owner);
        $service = app(SurveyService::class);

        $request = $service->requestEditAccess($survey, $requester);
        $service->decideAccessRequest($request, true, $owner);
        $this->assertTrue($requester->can('update', $survey->fresh()));

        $service->revokeAccess($request->fresh());
        $this->assertFalse($requester->can('update', $survey->fresh()));
    }

    public function test_collaborator_still_cannot_delete_or_manage_access(): void
    {
        $owner = $this->userWith();
        $requester = $this->userWith('surveys_update', 'surveys_delete');
        $survey = $this->survey($owner);
        $service = app(SurveyService::class);

        $request = $service->requestEditAccess($survey, $requester);
        $service->decideAccessRequest($request, true, $owner);
        $survey = $survey->fresh();

        $this->assertTrue($requester->can('update', $survey));
        $this->assertFalse($requester->can('delete', $survey));
        $this->assertFalse($requester->can('manageAccess', $survey));
    }

    public function test_livewire_request_access_flow(): void
    {
        $owner = $this->userWith();
        $requester = $this->userWith('surveys_view', 'surveys_update');
        $survey = $this->survey($owner);
        $this->actingAs($requester);

        Livewire::test(SurveyShow::class, ['survey' => $survey])
            ->call('requestAccess');

        $this->assertDatabaseHas('survey_access_requests', [
            'survey_id' => $survey->id,
            'user_id' => $requester->id,
            'status' => 'pending',
        ]);
    }

    public function test_only_owner_can_approve_access(): void
    {
        $owner = $this->userWith('surveys_view', 'surveys_update');
        $requester = $this->userWith('surveys_view', 'surveys_update');
        $outsider = $this->userWith('surveys_view', 'surveys_update');
        $survey = $this->survey($owner);

        $request = app(SurveyService::class)->requestEditAccess($survey, $requester);

        // Non-owner tidak bisa approve
        $this->actingAs($outsider);
        Livewire::test(SurveyShow::class, ['survey' => $survey])
            ->call('approveAccess', $request->id)
            ->assertForbidden();

        $this->assertSame('pending', $request->fresh()->status->value);

        // Owner approve
        $this->actingAs($owner);
        Livewire::test(SurveyShow::class, ['survey' => $survey])
            ->call('approveAccess', $request->id);

        $this->assertSame('approved', $request->fresh()->status->value);
    }
}
