<?php

namespace Tests\Feature;

use App\Livewire\Profile\DeleteUserForm;
use App\Livewire\Profile\UpdatePasswordForm;
use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSeeLivewire(UpdateProfileInformationForm::class)
            ->assertSeeLivewire(UpdatePasswordForm::class)
            ->assertSeeLivewire(DeleteUserForm::class);
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        // Perubahan email mengalir via pending_email (perlu verifikasi dulu).
        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->pending_email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->pending_email);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors('password')
            ->assertNoRedirect();

        $this->assertNotNull($user->fresh());
    }
}
