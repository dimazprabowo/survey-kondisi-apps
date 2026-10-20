<?php

namespace Tests\Feature\Auth;

use App\Livewire\Pages\Auth\ForgotPassword;
use App\Livewire\Pages\Auth\ResetPassword as ResetPasswordComponent;
use App\Models\User;
use App\Notifications\CustomResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSeeLivewire(ForgotPassword::class);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('sendResetLink')
            ->assertHasNoErrors();

        // Notifikasi dikirim on-demand ke email user (bukan ke model).
        Notification::assertSentOnDemand(
            CustomResetPassword::class,
            fn ($notification, $channels, $notifiable) => ($notifiable->routes['mail'] ?? null) === $user->email
        );
    }

    public function test_reset_password_link_unknown_email_fails_validation(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'tidak-ada@example.com')
            ->call('sendResetLink')
            ->assertHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $token = app('auth.password.broker')->createToken($user);

        $this->get('/reset-password/'.$token.'?email='.$user->email)
            ->assertOk()
            ->assertSeeLivewire(ResetPasswordComponent::class);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = app('auth.password.broker')->createToken($user);

        Livewire::withQueryParams(['email' => $user->email])
            ->test(ResetPasswordComponent::class, ['token' => $token])
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check('new-password-123', $user->fresh()->password)
        );
    }
}
