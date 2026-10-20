<?php

namespace Tests\Feature\Auth;

use App\Livewire\Pages\Auth\Register;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        // Pendaftaran dibuka (deadline aktif di masa depan)
        SystemConfiguration::firstOrCreate(
            ['key' => 'registration.deadline'],
            [
                'category' => 'general',
                'value' => now()->addDay()->toDateTimeString(),
                'data_type' => 'datetime',
                'is_editable' => true,
                'is_active' => true,
            ]
        );
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSeeLivewire(Register::class);
    }

    public function test_registration_redirects_to_login_when_closed(): void
    {
        $config = SystemConfiguration::where('key', 'registration.deadline')->first();
        $config->update(['is_active' => false]);

        $this->get('/register')->assertRedirect(route('login'));
    }

    public function test_new_users_can_register(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertTrue(User::where('email', 'test@example.com')->first()->hasRole('user'));
    }

    public function test_registration_still_succeeds_when_verification_email_fails(): void
    {
        // Simulasi kegagalan pengiriman email verifikasi (SMTP down): akun
        // harus tetap dibuat & user tetap login — TIDAK boleh menyisakan
        // akun "orphan" yang memblokir daftar ulang via unique email.
        Event::listen(Registered::class, function () {
            throw new \RuntimeException('SMTP down');
        });

        Livewire::test(Register::class)
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_user_can_register_again_after_account_deleted(): void
    {
        $user = User::factory()->create(['email' => 'gone@example.com']);

        app(UserService::class)->delete($user);
        $this->assertDatabaseMissing('users', ['email' => 'gone@example.com']);

        Livewire::test(Register::class)
            ->set('name', 'Daftar Ulang')
            ->set('email', 'gone@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'gone@example.com']);
        $this->assertAuthenticated();
    }

    public function test_delete_cleans_auth_artifacts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        \DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => 'abc',
            'created_at' => now(),
        ]);
        \DB::table('sessions')->insert([
            'id' => 'sess-1',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        app(UserService::class)->delete($user);

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $user->id]);
    }
}
