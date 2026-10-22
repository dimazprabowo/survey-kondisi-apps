<?php

namespace Tests\Feature;

use App\Enums\FileStatus;
use App\Enums\SurveyItemType;
use App\Jobs\ProcessSurveyReport;
use App\Jobs\ProcessSurveyReportDocumentation;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyReport;
use App\Models\SurveyTemplate;
use App\Models\User;
use App\Services\SurveyReportDocxBuilder;
use App\Services\SurveyReportService;
use App\Services\SurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SurveyReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Ship $ship;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'surveys_view', 'surveys_create', 'surveys_update',
            'survey_reports_view', 'survey_reports_update', 'survey_reports_generate',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $this->user = User::factory()->create();
        $this->ship = Ship::create([
            'name' => 'KMP. TEST',
            'year_built' => 2000,
            'status' => 'active',
        ]);
    }

    protected function createSurvey(): Survey
    {
        $template = SurveyTemplate::create([
            'name' => 'Template Test',
            'code' => 'TPL-TEST',
            'is_active' => true,
        ]);

        return (new SurveyService)->create([
            'ship_id' => $this->ship->id,
            'survey_template_id' => $template->id,
            'survey_date' => now()->format('Y-m-d'),
            'surveyor' => 'Tester',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_guest_redirected_dari_halaman_laporan(): void
    {
        $survey = $this->createSurvey();

        $this->get(route('surveys.report', $survey))->assertRedirect();
    }

    public function test_user_tanpa_permission_ditolak(): void
    {
        $survey = $this->createSurvey();

        $this->actingAs($this->user)
            ->get(route('surveys.report', $survey))
            ->assertForbidden();
    }

    public function test_user_berpermission_membuka_laporan_dan_report_terbuat(): void
    {
        $this->user->givePermissionTo('survey_reports_view');
        $survey = $this->createSurvey();

        $this->actingAs($this->user)
            ->get(route('surveys.report', $survey))
            ->assertOk();

        $this->assertTrue(SurveyReport::where('survey_id', $survey->id)->exists());
    }

    public function test_referensi_cap_preview_lengkap_sesuai_master(): void
    {
        $reference = (new SurveyReportService(new SurveyService))->capReference();

        $this->assertSame(['BKI – CAP', 'ABS – CAP', 'BV – CAP', 'PM 62 (SPM)'], $reference['standards']);
        $this->assertStringContainsString('support/dudukan', $reference['introduction']);
        $this->assertStringContainsString('korosi, pitting', $reference['introduction']);
        $this->assertSame(['C', 'V', 'F', 'M'], array_column($reference['categories'], 'code'));
        foreach ($reference['categories'] as $category) {
            $this->assertNotEmpty($category['criteria']);
            foreach ($category['criteria'] as $criterion) {
                $this->assertCount(4, $criterion['scores']);
            }
        }
    }

    public function test_service_menyimpan_meta_dan_sections(): void
    {
        $survey = $this->createSurvey();
        $service = new SurveyReportService(new SurveyService);

        $report = $service->getOrCreate($survey);
        $service->updateMeta($report, [
            'report_number' => 'LAP-TEST-001',
            'report_title' => 'Judul Uji',
            'approver_name' => 'Kepala Cabang',
        ]);
        $service->saveSections($report, ['executive_summary' => 'Ringkasan uji']);

        $report->refresh();
        $this->assertSame('LAP-TEST-001', $report->report_number);
        $this->assertSame(
            'Ringkasan uji',
            $report->sections->firstWhere('key', 'executive_summary')->content
        );
    }

    public function test_versi_generator_lama_dinonaktifkan_saat_editor_dibuka(): void
    {
        $survey = $this->createSurvey();
        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);
        $report->update([
            'file_status' => FileStatus::Completed,
            'generator_version' => 'versi-lama',
            'file_path' => 'testing/survey-reports/versi-lama.docx',
            'file_processed_at' => now(),
        ]);

        $refreshedReport = $service->getOrCreate($survey);

        $this->assertNull($refreshedReport->file_status);
        $this->assertNull($refreshedReport->generator_version);
    }

    public function test_perubahan_report_menonaktifkan_file_generate_lama(): void
    {
        $survey = $this->createSurvey();
        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);
        $report->update([
            'file_status' => FileStatus::Completed,
            'file_path' => 'testing/survey-reports/report-lama.docx',
            'file_processed_at' => now(),
        ]);

        $service->updateMeta($report, ['report_title' => 'Judul Baru']);
        $this->assertNull($report->fresh()->file_status);

        $report->update(['file_status' => FileStatus::Completed, 'file_processed_at' => now()]);
        $service->saveSections($report, ['executive_summary' => 'Ringkasan yang berubah']);
        $this->assertNull($report->fresh()->file_status);
        $this->assertSame('testing/survey-reports/report-lama.docx', $report->fresh()->file_path);
    }

    public function test_benchmark_chart_endpoint_terauthorize_dan_mengembalikan_png(): void
    {
        $survey = $this->createSurvey();
        $url = route('surveys.report.benchmark', $survey);

        $this->actingAs($this->user)->get($url)->assertForbidden();

        $this->user->givePermissionTo('survey_reports_view');
        $response = $this->actingAs($this->user)->get($url);

        if (! extension_loaded('gd')) {
            $response->assertNotFound();

            return;
        }

        $response->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_bab_tiga_mengikuti_hierarki_nomor_template_word(): void
    {
        $item = (object) [
            'id' => 4001,
            'name' => 'Plat Starboard Side',
            'item_type' => SurveyItemType::Score,
            'score_labels' => ['C', 'V'],
            'has_date_fields' => false,
        ];
        $group = (object) [
            'id' => 3001,
            'name' => 'Bottom Top/Side Shell',
            'items' => collect([$item]),
        ];
        $pumpItem = (object) [
            'id' => 4002,
            'name' => 'FO Pump',
            'item_type' => SurveyItemType::Score,
            'score_labels' => ['MOTOR', 'PUMP', 'PANEL'],
            'has_date_fields' => false,
        ];
        $pumpGroup = (object) [
            'id' => 3002,
            'name' => 'Pump Group',
            'items' => collect([$pumpItem]),
        ];
        $subCategory = (object) [
            'id' => 2001,
            'name' => 'Hull',
            'itemGroups' => collect([$group, $pumpGroup]),
        ];
        $category = (object) [
            'id' => 1001,
            'label' => 'Hull and Construction',
            'subCategories' => collect([$subCategory]),
        ];

        $builder = app(SurveyReportDocxBuilder::class);
        $method = new \ReflectionMethod($builder, 'buildBab3');
        $xml = $method->invoke($builder, [
            'categories' => collect([$category]),
            'responses' => collect(),
            'groupNotes' => collect(),
        ]);
        $text = html_entity_decode(strip_tags($xml));

        $this->assertSame(1, substr_count($xml, '<w:tbl>'));
        $this->assertSame(8, substr_count($xml, '<w:gridCol'));
        $this->assertStringContainsString('<w:shd w:val="clear" w:color="auto" w:fill="4285F4"/>', $xml);
        $this->assertStringContainsString('<w:trHeight w:val="660"/><w:tblHeader/>', $xml);
        $this->assertStringContainsString('<w:trHeight w:val="405"/>', $xml);
        $this->assertStringContainsString('<w:trHeight w:val="300"/>', $xml);
        $this->assertStringContainsString('HULL AND CONSTRUCTION', $text);
        $firstGroupStart = strpos($xml, 'BOTTOM TOP/SIDE SHELL');
        $secondGroupStart = strpos($xml, 'PUMP GROUP');
        $firstGroupXml = substr($xml, $firstGroupStart, $secondGroupStart - $firstGroupStart);
        $this->assertStringContainsString('>C</w:t>', $firstGroupXml);
        $this->assertStringContainsString('>V</w:t>', $firstGroupXml);
        $this->assertStringNotContainsString('MOTOR', $firstGroupXml);
        $this->assertStringContainsString('MOTOR', substr($xml, $secondGroupStart));
        $romanPosition = strpos($xml, '>I</w:t>');
        $subCategoryPosition = strpos($xml, '>1</w:t>');
        $groupPosition = strpos($xml, '>1.1</w:t>');
        $itemPosition = strpos($xml, '>a</w:t>');

        $this->assertNotFalse($romanPosition);
        $this->assertNotFalse($subCategoryPosition);
        $this->assertNotFalse($groupPosition);
        $this->assertNotFalse($itemPosition);
        $this->assertTrue(
            $romanPosition < $subCategoryPosition
            && $subCategoryPosition < $groupPosition
            && $groupPosition < $itemPosition
        );
    }

    public function test_cap_breakdown_menomori_tujuh_kategori_secara_berurutan(): void
    {
        $categories = collect(range(1, 7))->map(fn ($number) => (object) [
            'id' => $number,
            'label' => 'Category '.$number,
            'subCategories' => collect(),
        ]);
        $builder = app(SurveyReportDocxBuilder::class);
        $method = new \ReflectionMethod($builder, 'buildCapBreakdown');
        $xml = $method->invoke($builder, [
            'categories' => $categories,
            'responses' => collect(),
            'ship' => (object) ['name' => 'KMP TEST'],
            'overallCap' => 3.5,
        ]);

        // Header 9 kolom + wrapper 3 kolom berisi 2 tabel nested @4 kolom
        $this->assertSame(4, substr_count($xml, '<w:tbl>'));
        $this->assertSame(20, substr_count($xml, '<w:gridCol'));
        foreach (range(1, 7) as $number) {
            $this->assertStringContainsString('>'.$number.'. Category '.$number.'</w:t>', $xml);
        }
        $this->assertStringNotContainsString('>9. Category', $xml);
        $this->assertStringNotContainsString('>10. Category', $xml);
        $this->assertStringNotContainsString('>11. Category', $xml);
    }

    public function test_upload_dokumentasi_disimpan_temp_dan_diproses_async(): void
    {
        Storage::fake('local');
        Queue::fake();
        $survey = $this->createSurvey();
        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);

        $documentation = $service->requestDocumentationUpload(
            $report,
            1001,
            UploadedFile::fake()->image('temuan.png', 1200, 800),
            ['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 800]
        );

        $this->assertSame(FileStatus::Processing, $documentation->file_status);
        $this->assertDatabaseHas('survey_report_documentations', [
            'survey_report_id' => $report->id,
            'survey_category_id' => 1001,
            'file_status' => FileStatus::Processing->value,
        ]);
        Queue::assertPushed(ProcessSurveyReportDocumentation::class, function ($job) {
            Storage::disk('local')->assertExists($job->tempPath);

            return true;
        });

        $tempPath = Storage::disk('local')->allFiles('temp/survey-report-documentations')[0];
        (new ProcessSurveyReportDocumentation(
            $documentation->id,
            $tempPath,
            ['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 800]
        ))->handle(app(\App\Services\FileStorageService::class));

        $documentation->refresh();
        $this->assertSame(FileStatus::Completed, $documentation->file_status);
        Storage::disk('local')->assertExists($documentation->file_path);
        $imageSize = getimagesize(Storage::disk('local')->path($documentation->file_path));
        $this->assertSame(1200, $imageSize[0]);
        $this->assertSame(800, $imageSize[1]);
        $this->assertSame('image/jpeg', $imageSize['mime']);
    }

    public function test_dokumentasi_menggunakan_filesystem_disk_s3_untuk_file_final(): void
    {
        Storage::fake('local');
        Storage::fake('s3');
        Queue::fake();
        config(['filesystems.default' => 's3']);

        $survey = $this->createSurvey();
        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);
        $cropData = ['x' => 0, 'y' => 0, 'width' => 900, 'height' => 600];
        $documentation = $service->requestDocumentationUpload(
            $report,
            1001,
            UploadedFile::fake()->image('temuan-s3.png', 900, 600),
            $cropData
        );
        $tempPath = Storage::disk('local')->allFiles('temp/survey-report-documentations')[0];

        (new ProcessSurveyReportDocumentation($documentation->id, $tempPath, $cropData))
            ->handle(app(\App\Services\FileStorageService::class));

        $documentation->refresh();
        $this->assertSame(FileStatus::Completed, $documentation->file_status);
        Storage::disk('s3')->assertExists($documentation->file_path);
        Storage::disk('local')->assertMissing($tempPath);
        $this->assertStringStartsWith('testing/survey-report-documentations/', $documentation->file_path);
    }

    public function test_xml_gambar_dokumentasi_mendeklarasikan_namespace_drawingml(): void
    {
        $builder = app(SurveyReportDocxBuilder::class);
        $method = new \ReflectionMethod($builder, 'imageDrawing');
        $drawing = $method->invoke($builder, 'rId999', 1001);
        $xml = '<root '
            .'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            .'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .$drawing.'</root>';

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($xml));
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertSame([], $errors);
        $this->assertStringContainsString('xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"', $drawing);
        $this->assertStringContainsString('xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"', $drawing);
    }

    public function test_docx_hasil_generate_lengkap_dan_tanpa_placeholder_tersisa(): void
    {
        $survey = $this->createSurvey();
        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);
        $path = app(SurveyReportDocxBuilder::class)->build($report);

        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path, \ZipArchive::CHECKCONS) === true);
            $xml = $zip->getFromName('word/document.xml');
            $settings = $zip->getFromName('word/settings.xml');
            $zip->close();

            $this->assertIsString($xml);
            $this->assertStringContainsString('<w:updateFields w:val="true"/>', $settings);
            $this->assertDoesNotMatchRegularExpression('/\$\{[^}]+\}/', $xml);
            foreach ([
                'LAPORAN AKHIR',
                'LEMBAR PENGESAHAN',
                'EXECUTIVE SUMMARY',
                'DAFTAR ISI',
                'BAB I.',
                'BAB II.',
                'BAB III.',
                'BAB IV',
                'LAMPIRAN',
            ] as $heading) {
                $this->assertStringContainsString($heading, $xml);
            }
            $this->assertStringContainsString('CATEGORY', $xml);
            $this->assertStringContainsString('CAP SCORE', $xml);
            $this->assertStringContainsString('Item Pemeriksaan', $xml);
            $this->assertStringContainsString('Keterangan', $xml);
            $this->assertStringContainsString('Dokumentasi', $xml);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_nilai_skalar_dengan_karakter_xml_di_escape_saat_generate(): void
    {
        $this->ship->update([
            'name' => 'KMP. A & B',
            'main_engine' => 'MAN B&W 9L27/38',
            'builder' => 'PT. Dock <Shipyard>',
            'loa' => '54.67 m',
        ]);
        $survey = $this->createSurvey();
        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);
        $path = app(SurveyReportDocxBuilder::class)->build($report);

        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertIsString($xml);
            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();
            $document = new \DOMDocument;
            $this->assertTrue($document->loadXML($xml));
            $this->assertSame([], libxml_get_errors());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $this->assertStringContainsString('MAN B&amp;W 9L27/38', $xml);
            $this->assertStringNotContainsString('MAN B&W 9L27/38', $xml);
            $this->assertStringContainsString('>54.67</w:t>', $xml);
            $this->assertStringNotContainsString('>54.67 m</w:t>', $xml);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_judul_kategori_bab3_memakai_numbering_tersendiri_agar_konsisten_di_onlyoffice(): void
    {
        $survey = $this->createSurvey();
        $survey->update(['structure' => [
            'categories' => [
                ['id' => 1, 'label' => 'Hull & Construction', 'order_num' => 1, 'subCategories' => []],
                ['id' => 2, 'label' => 'Engine Room', 'order_num' => 2, 'subCategories' => []],
            ],
        ]]);
        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);
        $path = app(SurveyReportDocxBuilder::class)->build($report);

        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path, \ZipArchive::CHECKCONS) === true);
            $document = $zip->getFromName('word/document.xml');
            $numbering = $zip->getFromName('word/numbering.xml');
            $zip->close();

            // List desimal satu level khusus judul kategori didaftarkan — bukan
            // warisan "%1%2" Heading2 yang dirender "11, 12" oleh OnlyOffice.
            preg_match_all('/<w:num w:numId="(\d+)"/', $numbering, $nums);
            preg_match_all('/w:abstractNumId="(\d+)"/', $numbering, $abstracts);
            $newNumId = max(array_map('intval', $nums[1]));
            $newAbstractId = max(array_map('intval', $abstracts[1]));
            $this->assertStringContainsString(
                '<w:num w:numId="'.$newNumId.'"><w:abstractNumId w:val="'.$newAbstractId.'"/></w:num>',
                $numbering
            );
            $this->assertMatchesRegularExpression(
                '/<w:abstractNum w:abstractNumId="'.$newAbstractId.'">.*?<w:numFmt w:val="decimal"\/>.*?<w:lvlText w:val="%1"\/>/s',
                $numbering
            );

            // Semua judul kategori Heading2 membawa numPr eksplisit ke numId baru.
            preg_match_all('/<w:p\b.*?<\/w:p>/s', $document, $paragraphs);
            $heading2 = array_filter(
                $paragraphs[0],
                fn ($paragraph) => str_contains($paragraph, 'w:pStyle w:val="Heading2"')
            );
            $this->assertNotEmpty($heading2);
            foreach ($heading2 as $paragraph) {
                $this->assertStringContainsString('<w:numId w:val="'.$newNumId.'"/>', $paragraph);
            }
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_kategori_bab3_masuk_cached_daftar_isi_sebagai_entri_toc2(): void
    {
        $survey = $this->createSurvey();
        $survey->update(['structure' => [
            'categories' => [
                ['id' => 1, 'label' => 'Hull & Construction', 'order_num' => 1, 'subCategories' => []],
                ['id' => 2, 'label' => 'Engine Room', 'order_num' => 2, 'subCategories' => []],
            ],
        ]]);
        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);
        $path = app(SurveyReportDocxBuilder::class)->build($report);

        try {
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path, \ZipArchive::CHECKCONS) === true);
            $document = $zip->getFromName('word/document.xml');
            $zip->close();

            // OnlyOffice menampilkan cached result TOC apa adanya — entri
            // kategori harus tertulis eksplisit, bukan menunggu field update.
            preg_match_all('/<w:p\b.*?<\/w:p>/s', $document, $paragraphs);
            $toc2 = array_values(array_filter(
                $paragraphs[0],
                fn ($paragraph) => str_contains($paragraph, 'w:pStyle w:val="TOC2"')
            ));

            $this->assertCount(2, $toc2);
            foreach (['HULL &amp; CONSTRUCTION', 'ENGINE ROOM'] as $index => $label) {
                $this->assertStringContainsString('_TocBkiCat'.$index, $toc2[$index]);
                $this->assertStringContainsString('PAGEREF _TocBkiCat'.$index.' \h', $toc2[$index]);
                $this->assertStringContainsString($label, $toc2[$index]);
            }

            // Entri TOC2 berada di antara entri BAB III dan BAB IV.
            $bab3 = strpos($document, 'BAB III. PEMERIKSAAN');
            $bab4 = strpos($document, 'BAB IV');
            $firstToc2 = strpos($document, '_TocBkiCat0');
            $this->assertNotFalse($bab3);
            $this->assertNotFalse($bab4);
            $this->assertGreaterThan($bab3, $firstToc2);
            $this->assertLessThan($bab4, $firstToc2);

            // Heading kategori membawa bookmark yang dituju hyperlink TOC,
            // dan field TOC ditandai dirty agar nomor halaman di-refresh.
            $this->assertMatchesRegularExpression(
                '/<w:bookmarkStart w:id="\d+" w:name="_TocBkiCat0"\/>/',
                $document
            );
            $this->assertMatchesRegularExpression(
                '/<w:fldChar w:fldCharType="begin" w:dirty="true"\/>(?:(?!<w:fldChar).)*TOC \\\\o/s',
                $document
            );
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_generate_sukses_menghapus_revisi_edit_lama(): void
    {
        Storage::fake('local');
        $survey = $this->createSurvey();
        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);
        $editedPath = 'testing/survey-reports/kmp-test/edited.docx';
        Storage::disk('local')->put($editedPath, 'EDITED-OLD');
        $report->update([
            'edited_file_path' => $editedPath,
            'edited_file_name' => 'edited.docx',
            'edited_file_size' => 10,
            'edited_at' => now(),
            'edited_by' => $this->user->id,
        ]);

        (new ProcessSurveyReport($report->id))->handle(
            app(SurveyReportDocxBuilder::class),
            app(\App\Services\FileStorageService::class),
        );

        $report->refresh();
        $this->assertSame(FileStatus::Completed, $report->file_status);
        $this->assertNull($report->edited_file_path);
        $this->assertNull($report->edited_at);
        Storage::disk('local')->assertMissing($editedPath);
        Storage::disk('local')->assertExists($report->file_path);
    }

    public function test_generate_menandai_processing_dan_dispatch_job(): void
    {
        Queue::fake();
        $survey = $this->createSurvey();
        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);

        $service->requestGenerate($report);

        $report->refresh();
        $this->assertSame(FileStatus::Processing, $report->file_status);
        Queue::assertPushed(ProcessSurveyReport::class);
    }

    public function test_generate_notifikasi_owner_dan_generator_bukan_kolaborator(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $generator = User::factory()->create();
        $collaborator = User::factory()->create();
        $survey = $this->createSurvey();
        $survey->update(['created_by' => $owner->id]);
        \App\Models\SurveyAccessRequest::create([
            'survey_id' => $survey->id,
            'user_id' => $collaborator->id,
            'status' => 'approved',
        ]);

        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);

        (new ProcessSurveyReport($report->id, $generator->id))->handle(
            app(SurveyReportDocxBuilder::class),
            app(\App\Services\FileStorageService::class),
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $generator->id,
            'title' => 'Laporan Survey Selesai',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'title' => 'Laporan Survey Selesai',
        ]);
        // Kolaborator akses edit tidak ikut dinotifikasi
        $this->assertDatabaseMissing('notifications', ['user_id' => $collaborator->id]);
    }

    public function test_generate_oleh_owner_hanya_notifikasi_sekali(): void
    {
        Storage::fake('local');
        $survey = $this->createSurvey(); // created_by = $this->user

        $service = new SurveyReportService(new SurveyService);
        $report = $service->getOrCreate($survey);

        (new ProcessSurveyReport($report->id, $this->user->id))->handle(
            app(SurveyReportDocxBuilder::class),
            app(\App\Services\FileStorageService::class),
        );

        $this->assertSame(1, \App\Models\Notification::where('user_id', $this->user->id)
            ->where('title', 'Laporan Survey Selesai')->count());
    }
}
