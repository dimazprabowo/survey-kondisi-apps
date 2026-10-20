<?php

namespace Tests\Feature;

use App\Livewire\Templates\TemplateForm;
use App\Models\SurveyTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Gate::before(fn () => true);
    }

    protected function minimalCategories(): array
    {
        return [
            [
                'row_key' => 'k1',
                'label' => 'Kategori 1',
                'order_num' => 1,
                'sub_categories' => [],
            ],
        ];
    }

    public function test_duplicate_code_returns_validation_error(): void
    {
        SurveyTemplate::create(['name' => 'Template A', 'code' => 'SK-A']);

        Livewire::test(TemplateForm::class)
            ->set('name', 'Template B')
            ->set('code', 'SK-A')
            ->set('categories', $this->minimalCategories())
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    }

    public function test_duplicate_code_different_case_returns_validation_error(): void
    {
        SurveyTemplate::create(['name' => 'Template A', 'code' => 'SK-A']);

        Livewire::test(TemplateForm::class)
            ->set('name', 'Template B')
            ->set('code', 'sk-a')
            ->set('categories', $this->minimalCategories())
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    }

    public function test_code_is_saved_uppercase(): void
    {
        Livewire::test(TemplateForm::class)
            ->set('name', 'Template B')
            ->set('code', 'sk-baru')
            ->set('categories', $this->minimalCategories())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('survey_templates', ['code' => 'SK-BARU']);
    }

    public function test_edit_keeps_own_code(): void
    {
        $template = SurveyTemplate::create(['name' => 'Template A', 'code' => 'SK-A'])->fresh();

        Livewire::test(TemplateForm::class, ['template' => $template])
            ->set('categories', $this->minimalCategories())
            ->call('save')
            ->assertHasNoErrors();
    }
}
