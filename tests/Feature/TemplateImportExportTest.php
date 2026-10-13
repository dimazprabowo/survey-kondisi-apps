<?php

namespace Tests\Feature;

use App\Exports\SurveyTemplateStructureExport;
use App\Livewire\Templates\TemplateManagement;
use App\Models\SurveyTemplate;
use App\Models\User;
use App\Services\SurveyTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class TemplateImportExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    protected function allowAll(): void
    {
        Gate::before(fn () => true);
    }

    protected function validRows(): array
    {
        return [
            SurveyTemplateService::structureHeadings(),
            ['I. UMUM', 'Identitas Kapal', 'Dokumen', 'Sertifikat Klasifikasi', 'score', 'C,V', 'Ya'],
            ['I. UMUM', 'Identitas Kapal', 'Dokumen', 'Sertifikat Statutoria', 'score', 'C,V', 'Ya'],
            ['II. KONDISI', 'Lambung', 'Konstruksi', 'Plat lambung', 'score', 'C,V,F', 'Tidak'],
            ['II. KONDISI', 'Lambung', 'Peralatan', 'Jangkar', 'inventory', '', 'Tidak'],
        ];
    }

    public function test_import_rows_creates_new_template_with_hierarchy(): void
    {
        $this->allowAll();
        $template = (new SurveyTemplateService)->importRows(
            $this->validRows(),
            null,
            ['name' => 'Template Import', 'code' => 'TPL-IMP']
        );

        $this->assertSame('TPL-IMP', $template->code);
        $this->assertCount(2, $template->categories);

        $cat1 = $template->categories->firstWhere('label', 'I. UMUM');
        $this->assertSame(1, $cat1->order_num);
        $sub = $cat1->subCategories->firstWhere('name', 'Identitas Kapal');
        $group = $sub->itemGroups->firstWhere('name', 'Dokumen');
        $this->assertCount(2, $group->items);
        $this->assertTrue((bool) $group->items->first()->has_date_fields);

        $inventoryItem = $template->categories
            ->firstWhere('label', 'II. KONDISI')->subCategories
            ->firstWhere('name', 'Lambung')->itemGroups
            ->firstWhere('name', 'Peralatan')->items->first();
        $this->assertSame('inventory', $inventoryItem->item_type->value);
        $this->assertSame([], $inventoryItem->score_labels);
    }

    public function test_import_rows_replaces_existing_template_structure(): void
    {
        $this->allowAll();
        $existing = SurveyTemplate::create(['name' => 'Lama', 'code' => 'TPL-LAMA', 'is_active' => true]);

        $template = (new SurveyTemplateService)->importRows($this->validRows(), $existing);

        $this->assertSame($existing->id, $template->id);
        $this->assertCount(2, $template->categories);
        $this->assertSame('I. UMUM', $template->categories->first()->label);
    }

    public function test_import_rows_rejects_invalid_rows(): void
    {
        $this->allowAll();
        $rows = $this->validRows();
        $rows[] = ['', 'Sub', 'Grup', 'Item', 'score', '', ''];
        $rows[] = ['K', 'S', 'G', 'I', 'tipe-ngawur', '', ''];

        try {
            (new SurveyTemplateService)->importRows($rows, null, ['name' => 'X', 'code' => 'X']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $messages = $e->errors()['importFile'];
            $this->assertStringContainsString('Kategori', $messages[0]);
            $this->assertStringContainsString('tidak dikenal', implode(' ', $messages));
        }
    }

    public function test_import_rows_rejects_empty_file(): void
    {
        $this->expectException(ValidationException::class);
        (new SurveyTemplateService)->importRows([SurveyTemplateService::structureHeadings()]);
    }

    public function test_export_structure_roundtrip_via_component(): void
    {
        $this->allowAll();
        $template = (new SurveyTemplateService)->importRows(
            $this->validRows(), null, ['name' => 'Sumber', 'code' => 'TPL-SRC']
        );

        // Export struktur → konten xlsx nyata → import lagi ke template baru via komponen
        $content = Excel::raw(new SurveyTemplateStructureExport($template), \Maatwebsite\Excel\Excel::XLSX);
        $file = UploadedFile::fake()->createWithContent('struktur.xlsx', $content);

        Livewire::test(TemplateManagement::class)
            ->call('openImportModal')
            ->set('importMode', 'new')
            ->set('importName', 'Hasil Reimport')
            ->set('importCode', 'TPL-RE')
            ->set('importFile', $file)
            ->call('importStructure');

        $reimported = SurveyTemplate::firstWhere('code', 'TPL-RE');
        $this->assertNotNull($reimported);
        $this->assertCount(2, $reimported->categories);
        $totalItems = $reimported->categories->flatMap->subCategories->flatMap->itemGroups->flatMap->items;
        $this->assertCount(4, $totalItems);
    }

    public function test_import_modal_validates_required_fields(): void
    {
        $this->allowAll();
        Livewire::test(TemplateManagement::class)
            ->call('openImportModal')
            ->call('importStructure')
            ->assertHasErrors(['importFile', 'importName', 'importCode']);
    }

    public function test_import_export_denied_without_permission(): void
    {
        // User tanpa role/permission: ability 'import' & 'exportExcel' ditolak policy
        $this->assertFalse(Gate::forUser(auth()->user())->allows('import', SurveyTemplate::class));
        $this->assertFalse(Gate::forUser(auth()->user())->allows('exportExcel', SurveyTemplate::class));
        $this->assertFalse(Gate::forUser(auth()->user())->allows('viewAny', SurveyTemplate::class));
    }
}
