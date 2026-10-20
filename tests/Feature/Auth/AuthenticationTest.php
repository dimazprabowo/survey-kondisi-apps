<?php

namespace Tests\Feature\Auth;

use App\Livewire\Layout\Navigation;
use App\Livewire\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSeeLivewire(Login::class);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password')
            ->call('login')
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        // Dashboard butuh permission — pakai super admin (Gate::before bypass).
        $role = Role::firstOrCreate(['name' => 'super admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSeeLivewire(Navigation::class);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Navigation::class)
            ->call('logout')
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
