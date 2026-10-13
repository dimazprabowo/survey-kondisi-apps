<?php

namespace Database\Seeders;

use App\Enums\FileStatus;
use App\Models\Survey;
use App\Services\FileStorageService;
use App\Services\SurveyReportService;
use App\Services\SurveyService;
use Illuminate\Database\Seeder;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Lengkapi laporan untuk survey completed:
 *  - Report + default sections via SurveyReportService::getOrCreate()
 *  - Meta lembar pengesahan (approver, inspector, tempat/tanggal)
 *  - Foto dokumentasi placeholder per kategori (Intervention Image +
 *    FileStorageService::storeContent — konvensi path sama seperti Job).
 *
 * Idempotent: report yang sudah ada tidak dibuat ulang; meta & dokumentasi
 * hanya diisi bila masih kosong.
 */
class SurveyReportSeeder extends Seeder
{
    public function run(): void
    {
        $reportService = app(SurveyReportService::class);
        $surveyService = app(SurveyService::class);

        $surveys = Survey::completed()
            ->with('ship', 'report')
            ->orderBy('id')
            ->get();

        if ($surveys->isEmpty()) {
            $this->command->warn('SurveyReportSeeder dilewati: belum ada survey completed.');

            return;
        }

        $created = 0;
        $reports = [];
        foreach ($surveys as $survey) {
            $report = $reportService->getOrCreate($survey);
            $reports[$survey->id] = $report;
            if ($report->wasRecentlyCreated) {
                $created++;
            }
            $this->enrichMeta($report, $survey);
        }

        $this->command->info("Survey reports: {$created} dibuat, meta dilengkapi untuk {$surveys->count()} survey completed.");

        // Foto dokumentasi untuk beberapa report pertama saja (hemat waktu seed)
        $this->seedDocumentations($surveys->take(6), $reports, $surveyService);
    }

    /**
     * Isi meta lembar pengesahan bila masih kosong. Deterministik per survey.
     */
    protected function enrichMeta($report, Survey $survey): void
    {
        if ($report->approval_place !== null) {
            return;
        }

        $fake = fake();
        $fake->seed($survey->id * 15485863);

        $approvers = [
            'Ir. H. Darmawan, M.Mar.E.', 'Ir. Budiono, M.T.', 'Capt. H. Suyanto, M.Mar.',
            'Ir. Agus Riyanto', 'Ir. Hj. Ratna Sari, M.T.',
        ];
        $inspectors = [
            'Capt. Andi Prasetya', 'Ir. Budi Santoso', 'Capt. Dewi Lestari',
            'Ir. Hendra Wijaya', 'Capt. Rizal Fahmi', 'Ir. Sari Rahmawati',
        ];
        $places = ['Jakarta', 'Surabaya', 'Merak', 'Makassar', 'Batam'];

        $report->update([
            'approval_place' => $fake->randomElement($places),
            'approval_date' => ($survey->survey_date?->copy() ?? now())->addWeeks($fake->numberBetween(2, 6)),
            'approver_name' => $fake->randomElement($approvers),
            'inspector_1' => $fake->randomElement($inspectors),
            'inspector_2' => $fake->randomElement($inspectors),
        ]);
    }

    /**
     * Foto dokumentasi placeholder (JPEG polos berwarna per kategori) untuk
     * report yang belum punya dokumentasi — memakai konvensi path yang sama
     * dengan ProcessSurveyReportDocumentation.
     */
    protected function seedDocumentations($surveys, array $reports, SurveyService $surveyService): void
    {
        $storage = app(FileStorageService::class);
        $colors = ['#1F4E79', '#2E75B6', '#4472C4', '#5B9BD5', '#548235', '#BF9000'];
        $created = 0;

        try {
            $manager = new ImageManager(new Driver);
        } catch (\Throwable) {
            $this->command->warn('GD driver tidak tersedia — dokumentasi placeholder dilewati.');

            return;
        }

        foreach ($surveys as $survey) {
            $report = $reports[$survey->id] ?? null;
            if (! $report) {
                continue;
            }

            $categories = $surveyService->hydrateStructure($survey->structure)->take(3);

            foreach ($categories as $i => $cat) {
                if ($report->documentations()->where('survey_category_id', $cat->id)->exists()) {
                    continue;
                }

                $image = $manager->create(1200, 800)->fill($colors[$i % count($colors)]);
                $stored = $storage->storeContent(
                    (string) $image->toJpeg(82),
                    'survey-report-documentations',
                    [$survey->ship?->name ?? 'kapal', 'category-'.$cat->id],
                    'dokumentasi-'.$cat->id.'.jpg'
                );

                $report->documentations()->create([
                    'survey_category_id' => $cat->id,
                    'file_path' => $stored['path'],
                    'file_name' => $stored['name'],
                    'file_size' => $stored['size'],
                    'file_status' => FileStatus::Completed,
                    'file_processed_at' => now(),
                    'crop_data' => ['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 800],
                ]);
                $created++;
            }
        }

        $this->command->info("Report documentations seeded: {$created} placeholder images");
    }
}
