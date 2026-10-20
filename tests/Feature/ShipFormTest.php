<?php

namespace Tests\Feature;

use App\Livewire\MasterData\ShipForm;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ShipFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Gate::before(fn () => true);
    }

    public function test_duplicate_call_sign_returns_validation_error(): void
    {
        Ship::create(['name' => 'Kapal A', 'call_sign' => 'YB1A']);

        Livewire::test(ShipForm::class)
            ->set('name', 'Kapal B')
            ->set('call_sign', 'YB1A')
            ->call('save')
            ->assertHasErrors(['call_sign']);
    }

    public function test_duplicate_call_sign_different_case_returns_validation_error(): void
    {
        Ship::create(['name' => 'Kapal A', 'call_sign' => 'YB1A']);

        Livewire::test(ShipForm::class)
            ->set('name', 'Kapal B')
            ->set('call_sign', 'yb1a')
            ->call('save')
            ->assertHasErrors(['call_sign']);
    }

    public function test_duplicate_imo_number_returns_validation_error(): void
    {
        Ship::create(['name' => 'Kapal A', 'imo_number' => '9074729']);

        Livewire::test(ShipForm::class)
            ->set('name', 'Kapal B')
            ->set('imo_number', '9074729')
            ->call('save')
            ->assertHasErrors(['imo_number' => 'unique']);
    }

    public function test_empty_identifiers_saved_as_null_and_do_not_conflict(): void
    {
        Livewire::test(ShipForm::class)
            ->set('name', 'Kapal A')
            ->set('call_sign', '')
            ->set('imo_number', '')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(ShipForm::class)
            ->set('name', 'Kapal B')
            ->set('call_sign', '')
            ->set('imo_number', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Ship::whereNull('call_sign')->whereNull('imo_number')->count());
    }

    public function test_same_ship_name_is_allowed(): void
    {
        Ship::create(['name' => 'Kapal Sama']);

        Livewire::test(ShipForm::class)
            ->set('name', 'Kapal Sama')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Ship::where('name', 'Kapal Sama')->count());
    }

    public function test_edit_keeps_own_identifiers(): void
    {
        $ship = Ship::create([
            'name' => 'Kapal A',
            'call_sign' => 'YB1A',
            'imo_number' => '9074729',
            'status' => 'active',
            'class_status' => 'active',
        ])->fresh();

        Livewire::test(ShipForm::class, ['ship' => $ship])
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_remove_certificate_requires_confirmation(): void
    {
        Livewire::test(ShipForm::class)
            ->set('certificates', [['row_key' => 'cert-1', 'certificate_type' => 'Special Survey']])
            ->call('confirmRemoveCertificate', 0)
            ->assertDispatched('confirm-remove-cert');
    }
}
