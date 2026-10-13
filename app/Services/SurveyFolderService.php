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
     * Saat search aktif: folder tampil bila namanya cocok ATAU berisi survey
     * yang cocok (matched_surveys_count = jumlah hasil di dalam folder tsb,
     * pakai predicate search yang sama dengan list survey).
     */
    public function getFolders(?string $search = null): Collection
    {
        $query = SurveyFolder::withCount('surveys')->orderBy('name');

        if ($search) {
            $operator = $this->getLikeOperator();
            $query
                ->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                        ->orWhereHas('surveys', fn ($sq) => $this->surveys->applySearch($sq, $search));
                })
                ->withCount([
                    'surveys as matched_surveys_count' => fn ($sq) => $this->surveys->applySearch($sq, $search),
                ]);
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
