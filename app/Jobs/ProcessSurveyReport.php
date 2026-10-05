<?php

namespace App\Jobs;

use App\Enums\FileStatus;
use App\Models\SurveyReport;
use App\Services\FileStorageService;
use App\Services\SurveyReportDocxBuilder;
use App\Services\SurveyReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessSurveyReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $reportId) {}

    public function handle(
        SurveyReportDocxBuilder $builder,
        FileStorageService $storage,
    ): void {
        $report = SurveyReport::with('survey.ship')->find($this->reportId);
        if (! $report) {
            return;
        }

        $tmpPath = null;
        try {
            $tmpPath = $builder->build($report);

            $contents = file_get_contents($tmpPath);
            if ($contents === false) {
                throw new \RuntimeException('Gagal membaca file hasil generate.');
            }

            $fileName = ($report->report_number ?: 'laporan').'-'
                .Str::slug($report->survey?->ship?->name ?? 'kapal').'.docx';

            $stored = $storage->storeContent(
                $contents,
                'survey-reports',
                [$report->survey?->ship?->name ?? 'kapal'],
                $fileName
            );

            // Hapus file versi sebelumnya agar storage tidak menumpuk
            if ($report->file_path && $report->file_path !== $stored['path']) {
                $storage->delete($report->file_path);
            }

            $report->update([
                'file_path' => $stored['path'],
                'file_name' => $stored['name'],
                'file_size' => $stored['size'],
                'file_status' => FileStatus::Completed,
                'file_error' => null,
                'file_processed_at' => now(),
                'generator_version' => SurveyReportService::GENERATOR_VERSION,
            ]);
        } catch (Throwable $e) {
            Log::error('Gagal generate laporan survey', [
                'report_id' => $this->reportId,
                'error' => $e->getMessage(),
            ]);
            $report->update([
                'file_status' => FileStatus::Failed,
                'file_error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($tmpPath && is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    public function failed(Throwable $e): void
    {
        SurveyReport::where('id', $this->reportId)->update([
            'file_status' => FileStatus::Failed,
            'file_error' => $e->getMessage(),
        ]);
    }
}
