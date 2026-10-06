<?php

namespace App\Jobs;

use App\Enums\FileStatus;
use App\Models\SurveyReportDocumentation;
use App\Services\FileStorageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class ProcessSurveyReportDocumentation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $documentationId,
        public string $tempPath,
        public array $cropData,
    ) {}

    public function handle(FileStorageService $storage): void
    {
        $documentation = SurveyReportDocumentation::with('report.survey.ship')->find($this->documentationId);
        if (! $documentation) {
            $storage->deleteTemp($this->tempPath);

            return;
        }

        try {
            $sourcePath = Storage::disk($storage->tempDisk())->path($this->tempPath);
            $manager = new ImageManager(new Driver);
            $image = $manager->read($sourcePath)->orient();

            $x = max(0, (int) round($this->cropData['x'] ?? 0));
            $y = max(0, (int) round($this->cropData['y'] ?? 0));
            $width = max(1, min((int) round($this->cropData['width'] ?? $image->width()), $image->width() - $x));
            $height = max(1, min((int) round($this->cropData['height'] ?? $image->height()), $image->height() - $y));

            $encoded = $image
                ->crop($width, $height, $x, $y)
                ->scaleDown(width: 1200, height: 1200)
                ->toJpeg(82);

            $shipName = $documentation->report?->survey?->ship?->name ?? 'kapal';
            $stored = $storage->storeContent(
                (string) $encoded,
                'survey-report-documentations',
                [$shipName, 'category-'.$documentation->survey_category_id],
                'dokumentasi-'.$documentation->survey_category_id.'.jpg'
            );

            $oldPath = $documentation->file_path;
            $documentation->update([
                'file_path' => $stored['path'],
                'file_name' => $stored['name'],
                'file_size' => $stored['size'],
                'file_status' => FileStatus::Completed,
                'file_error' => null,
                'file_processed_at' => now(),
                'crop_data' => $this->cropData,
            ]);

            if ($oldPath && $oldPath !== $stored['path']) {
                $storage->delete($oldPath);
            }
            $storage->deleteTemp($this->tempPath);
        } catch (Throwable $e) {
            Log::error('Gagal memproses dokumentasi laporan survey', [
                'documentation_id' => $this->documentationId,
                'error' => $e->getMessage(),
            ]);
            $documentation->update([
                'file_status' => FileStatus::Failed,
                'file_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        app(FileStorageService::class)->deleteTemp($this->tempPath);
        SurveyReportDocumentation::whereKey($this->documentationId)->update([
            'file_status' => FileStatus::Failed,
            'file_error' => $e->getMessage(),
        ]);
    }
}
