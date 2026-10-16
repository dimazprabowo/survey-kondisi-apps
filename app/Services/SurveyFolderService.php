<?php

namespace App\Services;

use App\Models\Survey;
use App\Models\SurveyFolder;
use App\Traits\HasDynamicLike;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SurveyFolderService
{
    use HasDynamicLike;

    public function __construct(protected SurveyService $surveys = new SurveyService) {}

    /**
     * Daftar folder + jumlah survey di dalamnya, untuk strip folder di index.
     * Saat ada kriteria aktif (search/status/kapal): folder tampil bila
     * namanya cocok dengan search ATAU berisi survey yang memenuhi semua
     * kriteria (matched_surveys_count = jumlah hasil di dalam folder tsb,
     * pakai predicate filter yang sama dengan list survey). Tanpa search,
     * kecocokan nama folder tidak relevan — folder hanya tampil bila punya
     * survey yang cocok.
     */
    public function getFolders(?string $search = null, ?string $statusFilter = null, ?int $shipId = null): Collection
    {
        $hasCriteria = $search || ($statusFilter !== null && $statusFilter !== '') || $shipId;
        $query = SurveyFolder::withCount('surveys')->orderBy('name');

        if ($hasCriteria) {
            $operator = $this->getLikeOperator();
            $match = fn ($sq) => $this->surveys->applyFilters($sq, $search, $statusFilter, $shipId);
            $query
                ->where(function ($q) use ($search, $operator, $match) {
                    if ($search) {
                        $q->where('name', $operator, "%{$search}%")
                            ->orWhereHas('surveys', $match);
                    } else {
                        $q->whereHas('surveys', $match);
                    }
                })
                ->withCount(['surveys as matched_surveys_count' => $match]);
        }

        return $query->get();
    }

    public function create(array $data): SurveyFolder
    {
        return SurveyFolder::create([
            'name' => trim($data['name']),
            'created_by' => auth()->id(),
        ]);
    }

    public function update(SurveyFolder $folder, array $data): SurveyFolder
    {
        $folder->update(['name' => trim($data['name'])]);

        return $folder->fresh();
    }

    /**
     * Folder hanya bisa dihapus jika kosong — survey di dalamnya harus
     * dipindahkan keluar dulu (data survey tidak ikut terhapus diam-diam).
     */
    public function delete(SurveyFolder $folder): void
    {
        if ($folder->surveys()->exists()) {
            throw new \RuntimeException('Folder tidak dapat dihapus karena masih berisi survey. Pindahkan survey keluar folder terlebih dahulu.');
        }

        $folder->delete();
    }

    /**
     * Pindahkan survey ke folder (null = keluarkan ke root index).
     */
    public function moveSurvey(Survey $survey, ?SurveyFolder $folder): void
    {
        $this->moveSurveys([$survey->id], $folder);
    }

    /**
     * Pindahkan beberapa survey sekaligus (bulk) ke folder.
     * Mengembalikan jumlah survey yang ter-update.
     */
    public function moveSurveys(array $surveyIds, ?SurveyFolder $folder): int
    {
        return DB::transaction(
            fn () => Survey::whereIn('id', array_map('intval', $surveyIds))
                ->update(['survey_folder_id' => $folder?->id])
        );
    }
}
