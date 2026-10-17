<?php

namespace Tests\Feature;

use App\Livewire\MasterData\CompanyManagement;
use App\Livewire\MasterData\ShipManagement;
use App\Livewire\Profile\ImpersonateUser;
use App\Livewire\Settings\SystemConfiguration;
use App\Livewire\Settings\UserManagement;
use App\Livewire\Templates\TemplateManagement;
use App\Models\Company;
use App\Models\Ship;
use App\Models\SurveyTemplate;
use App\Models\SystemConfiguration as SystemConfigModel;
use App\Models\User;
use App\Services\CompanyService;
use App\Services\ImpersonateService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class TableSortingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'ZZ Admin', 'email' => 'admin@test.local']);
        $this->actingAs($this->admin);
        Gate::before(fn () => true);
    }

    public function test_company_sort_toggles_direction_and_respects_default(): void
    {
        Company::create(['code' => 'BB', 'name' => 'Beta', 'status' => 'active']);
        Company::create(['code' => 'AA', 'name' => 'Alpha', 'status' => 'active']);
        Company::create(['code' => 'CC', 'name' => 'Ceta', 'status' => 'active']);

        $component = Livewire::test(CompanyManagement::class);

        // Default: name asc
        $this->assertSame(['Alpha', 'Beta', 'Ceta'], $component->viewData('companies')->pluck('name')->all());

        // Klik kolom sama = balik arah
        $component->call('sortBy', 'name');
        $this->assertSame('desc', $component->get('sortDir'));
        $this->assertSame(['Ceta', 'Beta', 'Alpha'], $component->viewData('companies')->pluck('name')->all());
    }

    public function test_company_sort_by_users_count(): void
    {
        $one = Company::create(['code' => 'AA', 'name' => 'Alpha', 'status' => 'active']);
        $two = Company::create(['code' => 'BB', 'name' => 'Beta', 'status' => 'active']);
        User::factory()->create(['company_id' => $two->id]);
        User::factory()->create(['company_id' => $two->id]);

        $component = Livewire::test(CompanyManagement::class)->call('sortBy', 'users_count');

        // asc: 0 user dulu, lalu 2
        $this->assertSame(['Alpha', 'Beta'], $component->viewData('companies')->pluck('name')->all());
        $this->assertSame(2, $component->viewData('companies')->firstWhere('name', 'Beta')->users_count);
    }

    public function test_company_invalid_sort_field_falls_back_to_name(): void
    {
        Company::create(['code' => 'BB', 'name' => 'Beta', 'status' => 'active']);
        Company::create(['code' => 'AA', 'name' => 'Alpha', 'status' => 'active']);

        $names = (new CompanyService)
            ->getFiltered(sortField: 'evil; DROP TABLE companies', sortDir: 'asc')
            ->pluck('name')
            ->all();

        $this->assertSame(['Alpha', 'Beta'], $names);
    }

    public function test_ship_sort_by_year_built_places_null_last_on_desc(): void
    {
        Ship::create(['name' => 'Kapal A', 'status' => 'active', 'year_built' => 2000]);
        Ship::create(['name' => 'Kapal B', 'status' => 'active', 'year_built' => null]);
        Ship::create(['name' => 'Kapal C', 'status' => 'active', 'year_built' => 2010]);

        $component = Livewire::test(ShipManagement::class)->call('sortBy', 'year_built');

        // asc: NULL (kolom "-") dianggap terkecil → paling atas
        $this->assertSame(['Kapal B', 'Kapal A', 'Kapal C'], $component->viewData('ships')->pluck('name')->all());

        $component->call('sortBy', 'year_built');

        // desc: tahun terbaru dulu, NULL paling bawah
        $this->assertSame(['Kapal C', 'Kapal A', 'Kapal B'], $component->viewData('ships')->pluck('name')->all());
    }

    public function test_ship_sort_by_surveys_count(): void
    {
        Ship::create(['name' => 'Kosong', 'status' => 'active']);
        $busy = Ship::create(['name' => 'Sibuk', 'status' => 'active']);
        $busy->surveys()->create(['survey_number' => 'SVY-1', 'survey_date' => '2026-01-01', 'status' => 'draft']);

        $component = Livewire::test(ShipManagement::class)->call('sortBy', 'surveys_count');

        $this->assertSame(['Kosong', 'Sibuk'], $component->viewData('ships')->pluck('name')->all());
    }

    public function test_user_sort_by_company_name(): void
    {
        $zeta = Company::create(['code' => 'ZZ', 'name' => 'Zeta Corp', 'status' => 'active']);
        $beta = Company::create(['code' => 'BB', 'name' => 'Beta Corp', 'status' => 'active']);
        User::factory()->create(['name' => 'U Zeta', 'company_id' => $zeta->id]);
        User::factory()->create(['name' => 'U Beta', 'company_id' => $beta->id]);
        User::factory()->create(['name' => 'U None', 'company_id' => null]);

        $component = Livewire::test(UserManagement::class)->call('sortBy', 'company');

        // asc: NULL (tanpa company) dianggap terkecil → paling atas
        $this->assertSame(
            ['U None', 'U Beta', 'U Zeta'],
            $component->viewData('users')->where('id', '!=', $this->admin->id)->pluck('name')->values()->all()
        );
    }

    public function test_user_invalid_sort_field_falls_back_to_name(): void
    {
        User::factory()->create(['name' => 'Alpha']);
        User::factory()->create(['name' => 'Beta']);

        $names = (new UserService)
            ->getFilteredUsers(sortField: 'malicious', sortDir: 'asc')
            ->where('id', '!=', $this->admin->id)
            ->pluck('name')
            ->values()
            ->all();

        $this->assertSame(['Alpha', 'Beta'], $names);
    }

    public function test_system_config_default_sort_is_category_then_key(): void
    {
        SystemConfigModel::create(['key' => 'z_key', 'category' => 'general', 'value' => '1']);
        SystemConfigModel::create(['key' => 'a_key', 'category' => 'general', 'value' => '2']);
        SystemConfigModel::create(['key' => 'm_key', 'category' => 'sla', 'value' => '3']);

        $component = Livewire::test(SystemConfiguration::class);

        // Perilaku lama terjaga: category asc, key asc
        $this->assertSame(['a_key', 'z_key', 'm_key'], $component->viewData('configurations')->pluck('key')->all());
    }

    public function test_system_config_sort_by_key_toggles(): void
    {
        SystemConfigModel::create(['key' => 'b_key', 'category' => 'general', 'value' => '1']);
        SystemConfigModel::create(['key' => 'a_key', 'category' => 'sla', 'value' => '2']);

        $component = Livewire::test(SystemConfiguration::class)->call('sortBy', 'key');

        $this->assertSame(['a_key', 'b_key'], $component->viewData('configurations')->pluck('key')->all());

        $component->call('sortBy', 'key');
        $this->assertSame(['b_key', 'a_key'], $component->viewData('configurations')->pluck('key')->all());
    }

    public function test_impersonate_excludes_self_even_when_search_matches(): void
    {
        User::factory()->create(['name' => 'Other User']);

        // Search cocok dengan nama admin sendiri — tetap tidak boleh muncul
        $users = (new ImpersonateService)->getImpersonatableUsers(
            $this->admin,
            search: 'ZZ Admin'
        );

        $this->assertSame(0, $users->total());
        $this->assertFalse($users->pluck('id')->contains($this->admin->id));
    }

    public function test_impersonate_sort_by_name(): void
    {
        User::factory()->create(['name' => 'Beta']);
        User::factory()->create(['name' => 'Alpha']);

        $component = Livewire::test(ImpersonateUser::class);

        $this->assertSame(['Alpha', 'Beta'], $component->viewData('users')->pluck('name')->all());
    }

    public function test_template_default_sort_is_latest(): void
    {
        SurveyTemplate::create(['name' => 'Alpha', 'code' => 'AA']);
        SurveyTemplate::create(['name' => 'Beta', 'code' => 'BB']);

        $component = Livewire::test(TemplateManagement::class);

        // created_at desc + id desc tie-break → record terakhir dibuat di atas
        $this->assertSame(['Beta', 'Alpha'], $component->viewData('templates')->pluck('name')->all());
    }

    public function test_template_sort_by_name_and_counts(): void
    {
        SurveyTemplate::create(['name' => 'Beta', 'code' => 'BB']);
        SurveyTemplate::create(['name' => 'Alpha', 'code' => 'AA']);

        $component = Livewire::test(TemplateManagement::class)->call('sortBy', 'name');

        $this->assertSame('asc', $component->get('sortDir'));
        $this->assertSame(['Alpha', 'Beta'], $component->viewData('templates')->pluck('name')->all());
    }
}
