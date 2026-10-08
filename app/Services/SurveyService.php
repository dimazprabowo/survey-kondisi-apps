<?php

namespace App\Services;

use App\Enums\SurveyItemType;
use App\Enums\SurveyStatus;
use App\Models\Ship;
use App\Models\Survey;
use App\Models\SurveyGroupNote;
use App\Models\SurveyResponse;
use App\Models\SurveyTemplate;
use App\Traits\HasDynamicLike;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SurveyService
{
    use HasDynamicLike;

    public function getFiltered(
        ?string $search = null,
        ?string $statusFilter = null,
        ?int $shipId = null,
        int $perPage = 15,
        ?string $sortField = null,
        string $sortDir = 'desc'
    ): LengthAwarePaginator {
        $query = Survey::with(['ship:id,name,code,year_built', 'creator:id,name', 'template:id,name,code']);

        if ($search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($search, $operator) {
                $q->where('survey_number', $operator, "%{$search}%")
                    ->orWhere('surveyor', $operator, "%{$search}%")
                    ->orWhere('location', $operator, "%{$search}%")
                    ->orWhereHas('ship', function ($sq) use ($search, $operator) {
                        $sq->where('name', $operator, "%{$search}%");
                    });
            });
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        if ($shipId) {
            $query->where('ship_id', $shipId);
        }

        $dir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';
        $sortable = [
            'survey_number' => 'surveys.survey_number',
            'ship' => '('.Ship::select('name')->whereColumn('ships.id', 'surveys.ship_id')->limit(1)->toSql().')',
            'template' => '('.SurveyTemplate::select('name')->whereColumn('survey_templates.id', 'surveys.survey_template_id')->limit(1)->toSql().')',
            'survey_date' => 'surveys.survey_date',
            'surveyor' => 'surveys.surveyor',
            'overall_cap_score' => 'surveys.overall_cap_score',
            'status' => 'CASE surveys.status '.collect(SurveyStatus::cases())
                ->map(fn ($s, $i) => "WHEN '{$s->value}' THEN {$i}")
                ->implode(' ').' ELSE '.count(SurveyStatus::cases()).' END',
        ];

        if (! isset($sortable[$sortField])) {
            $sortField = 'survey_date';
            $dir = 'desc';
        }

        $expr = $sortable[$sortField];

        // Nilai kosong (NULL, badge "-") dianggap nilai terkecil: paling atas
        // saat asc, paling bawah saat desc. CASE pada 'status' mengurutkan
        // mengikuti urutan workflow enum (draft → in_progress → completed →
        // cancelled), bukan alfabet nilai kolom.
        $nullDir = $dir === 'asc' ? 'DESC' : 'ASC';

        return $query
            ->orderByRaw("$expr IS NULL $nullDir")
            ->orderByRaw("$expr $dir")
            ->orderBy('surveys.id', 'desc')
            ->paginate($perPage);
    }

    public function generateSurveyNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'SVY-'.$year.'-';
        $last = Survey::withTrashed()
            ->where('survey_number', 'like', $prefix.'%')
            ->orderByRaw('CAST(SUBSTRING(survey_number, '.(strlen($prefix) + 1).') AS INTEGER) DESC')
            ->first();
        $next = $last ? ((int) substr($last->survey_number, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data): Survey
    {
        return DB::transaction(function () use ($data) {
            $data['survey_number'] = $data['survey_number'] ?? $this->generateSurveyNumber();
            $data['created_by'] = $data['created_by'] ?? auth()->id();
            $data['status'] = $data['status'] ?? SurveyStatus::Draft->value;

            $data['structure'] = $data['structure'] ?? $this->buildStructureSnapshot($data['survey_template_id'] ?? null);

            $survey = Survey::create($data);

            // Pre-create empty responses for all items
            $this->initResponses($survey);

            return $survey;
        });
    }

    /**
     * Snapshot struktur template (kategori -> sub -> grup -> item) ke array.
     * Disimpan sekali saat survey dibuat — perubahan template setelahnya
     * tidak memengaruhi survey yang sudah ada.
     */
    public function buildStructureSnapshot(?int $templateId): ?array
    {
        if (! $templateId) {
            return null;
        }

        $template = SurveyTemplate::with('categories.subCategories.itemGroups.items')->find($templateId);

        if (! $template) {
            return null;
        }

        return [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'snapshot_at' => now()->toIso8601String(),
            'categories' => $template->categories->sortBy('order_num')->map(fn ($cat) => [
                'id' => $cat->id,
                'label' => $cat->label,
                'order_num' => $cat->order_num,
                'subCategories' => $cat->subCategories->sortBy('order_num')->map(fn ($sc) => [
                    'id' => $sc->id,
                    'name' => $sc->name,
                    'order_num' => $sc->order_num,
                    'itemGroups' => $sc->itemGroups->sortBy('order_num')->map(fn ($ig) => [
                        'id' => $ig->id,
                        'name' => $ig->name,
                        'order_num' => $ig->order_num,
                        'items' => $ig->items->sortBy('order_num')->map(fn ($item) => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'order_num' => $item->order_num,
                            'item_type' => $item->item_type->value,
                            'score_labels' => $item->score_labels ?? ['C', 'V'],
                            'has_date_fields' => (bool) $item->has_date_fields,
                        ])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Hydrate snapshot JSON menjadi tree object dengan shape yang sama
     * seperti model template (dipakai komponen form/show).
     */
    public function hydrateStructure(?array $structure): Collection
    {
        return collect($structure['categories'] ?? [])->map(function ($cat) {
            $cat = (object) $cat;
            $cat->subCategories = collect($cat->subCategories ?? [])->map(function ($sc) {
                $sc = (object) $sc;
                $sc->itemGroups = collect($sc->itemGroups ?? [])->map(function ($ig) {
                    $ig = (object) $ig;
                    $ig->items = collect($ig->items ?? [])->map(function ($item) {
                        $item = (object) $item;
                        $item->item_type = SurveyItemType::from($item->item_type);
                        $item->score_labels = $item->score_labels ?? ['C', 'V'];
                        $item->has_date_fields = (bool) ($item->has_date_fields ?? false);

                        return $item;
                    });

                    return $ig;
                });

                return $sc;
            });

            return $cat;
        });
    }

    public function initResponses(Survey $survey): void
    {
        // Id item diambil dari snapshot — selalu konsisten dengan structure.
        $itemIds = collect($survey->structure['categories'] ?? [])
            ->flatMap(fn ($cat) => $cat['subCategories'] ?? [])
            ->flatMap(fn ($sc) => $sc['itemGroups'] ?? [])
            ->flatMap(fn ($ig) => collect($ig['items'] ?? [])->pluck('id'));
        $rows = $itemIds->map(fn ($id) => [
            'survey_id' => $survey->id,
            'survey_item_id' => $id,
            'scores' => null,
            'avg_score' => null,
            'date_issued' => null,
            'date_expired' => null,
        ])->toArray();

        // Chunk to avoid huge inserts
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('survey_responses')->insert($chunk);
        }
    }

    public function update(Survey $survey, array $data): Survey
    {
        DB::transaction(function () use ($survey, $data) {
            $survey->update($data);
        });

        return $survey->fresh();
    }

    public function delete(Survey $survey): void
    {
        $survey->delete();
    }

    public function saveResponse(int $surveyId, int $itemId, array $data): void
    {
        $scores = $data['scores'] ?? [];
        $avgScore = $scores ? $this->calculateItemAvg($scores) : null;

        SurveyResponse::updateOrCreate(
            ['survey_id' => $surveyId, 'survey_item_id' => $itemId],
            [
                'scores' => $scores ?: null,
                'avg_score' => $avgScore,
                'date_issued' => ($data['date_issued'] ?? null) ?: null,
                'date_expired' => ($data['date_expired'] ?? null) ?: null,
                'qty' => isset($data['qty']) && $data['qty'] !== '' ? (int) $data['qty'] : null,
                'specification' => ($data['specification'] ?? null) ?: null,
            ]
        );
    }

    public function saveResponses(int $surveyId, array $responses, array $groupNotes = []): void
    {
        DB::transaction(function () use ($surveyId, $responses, $groupNotes) {
            $this->pruneOrphanedData($surveyId);
            foreach ($responses as $itemId => $data) {
                $this->saveResponse($surveyId, (int) $itemId, $data);
            }
            $this->saveGroupNotes($surveyId, $groupNotes);
            $this->recalculateOverall($surveyId);
        });
    }

    /**
     * Hapus responses & group notes yang merujuk node di luar structure
     * tersimpan (mis. setelah node struktur dihapus pada edit survey).
     */
    protected function pruneOrphanedData(int $surveyId): void
    {
        $structure = Survey::findOrFail($surveyId)->structure ?? [];

        $itemIds = [];
        $groupIds = [];
        foreach ($structure['categories'] ?? [] as $cat) {
            foreach ($cat['subCategories'] ?? [] as $sc) {
                foreach ($sc['itemGroups'] ?? [] as $ig) {
                    $groupIds[] = $ig['id'];
                    foreach ($ig['items'] ?? [] as $item) {
                        $itemIds[] = $item['id'];
                    }
                }
            }
        }

        SurveyResponse::where('survey_id', $surveyId)->whereNotIn('survey_item_id', $itemIds)->delete();
        SurveyGroupNote::where('survey_id', $surveyId)->whereNotIn('survey_item_group_id', $groupIds)->delete();
    }

    /**
     * Save notes attached to item groups (dynamic list per item group).
     * Rows are anonymous — delete + re-insert keeps order_num consistent.
     * Empty notes are skipped so no dead rows remain.
     */
    public function saveGroupNotes(int $surveyId, array $groupNotes): void
    {
        foreach ($groupNotes as $itemGroupId => $notes) {
            $itemGroupId = (int) $itemGroupId;

            SurveyGroupNote::where('survey_id', $surveyId)
                ->where('survey_item_group_id', $itemGroupId)
                ->delete();

            $orderNum = 0;
            foreach ((array) $notes as $note) {
                $note = trim((string) $note);
                if ($note === '') {
                    continue;
                }

                SurveyGroupNote::create([
                    'survey_id' => $surveyId,
                    'survey_item_group_id' => $itemGroupId,
                    'note' => $note,
                    'order_num' => $orderNum++,
                ]);
            }
        }
    }

    /**
     * Rata-rata dari daftar nilai (null di-skip). Null jika list kosong.
     */
    protected function avgOfList(array $avgs): ?float
    {
        $avgs = array_values(array_filter($avgs, fn ($v) => $v !== null));

        return $avgs !== [] ? round(array_sum($avgs) / count($avgs), 2) : null;
    }

    /**
     * Item group average dari responses ter-keyed by survey_item_id.
     * Dipakai bersama oleh SurveyShow (UI) dan report builder (DOCX).
     */
    public function itemGroupAvg(object $itemGroup, Collection $responses): ?float
    {
        $itemAvgs = [];
        foreach ($itemGroup->items as $item) {
            $avg = $responses->get($item->id)?->avg_score;
            if ($avg !== null) {
                $itemAvgs[] = (float) $avg;
            }
        }

        return $this->avgOfList($itemAvgs);
    }

    /**
     * Sub-category average (average of item group averages).
     */
    public function subCategoryAvg(object $subCat, Collection $responses): ?float
    {
        return $this->avgOfList(
            $subCat->itemGroups->map(fn ($ig) => $this->itemGroupAvg($ig, $responses))->all()
        );
    }

    /**
     * Category average (average of sub-category averages).
     */
    public function categoryAvg(object $cat, Collection $responses): ?float
    {
        return $this->avgOfList(
            $cat->subCategories->map(fn ($sc) => $this->subCategoryAvg($sc, $responses))->all()
        );
    }

    /**
     * Overall average (average of category averages).
     */
    public function overallAvg(Collection $categories, Collection $responses): ?float
    {
        return $this->avgOfList(
            $categories->map(fn ($cat) => $this->categoryAvg($cat, $responses))->all()
        );
    }

    /**
     * Calculate item average from scores (mirrors Excel =AVERAGE(E:F))
     * Only count numeric scores, ignore null/empty/"-".
     */
    public function calculateItemAvg(?array $scores): ?float
    {
        if (! $scores) {
            return null;
        }
        $values = array_filter($scores, fn ($v) => is_numeric($v) && $v !== '' && $v !== '-');
        if (empty($values)) {
            return null;
        }

        return round(array_sum($values) / count($values), 2);
    }

    /**
     * Recalculate overall CAP score for a survey (mirrors Excel AVERAGE hierarchy).
     * Item avg -> ItemGroup avg -> SubCategory avg -> Category avg -> Overall
     */
    public function recalculateOverall(int $surveyId): ?float
    {
        $survey = Survey::findOrFail($surveyId);
        $structure = $survey->structure ?? [];

        // Rata-rata per item dari responses (id item merujuk snapshot, bukan tabel template)
        $itemAvgs = SurveyResponse::where('survey_id', $surveyId)
            ->whereNotNull('avg_score')
            ->pluck('avg_score', 'survey_item_id');

        if ($itemAvgs->isEmpty() || empty($structure['categories'])) {
            Survey::where('id', $surveyId)->update(['overall_cap_score' => null]);

            return null;
        }

        $catAvgs = [];
        foreach ($structure['categories'] as $cat) {
            $scAvgs = [];
            foreach ($cat['subCategories'] ?? [] as $sc) {
                $igAvgs = [];
                foreach ($sc['itemGroups'] ?? [] as $ig) {
                    $avgs = collect($ig['items'] ?? [])
                        ->map(fn ($item) => $itemAvgs->get($item['id']))
                        ->filter(fn ($v) => $v !== null)
                        ->map(fn ($v) => (float) $v)
                        ->all();
                    if ($avgs !== []) {
                        $igAvgs[] = round(array_sum($avgs) / count($avgs), 2);
                    }
                }
                if ($igAvgs !== []) {
                    $scAvgs[] = round(array_sum($igAvgs) / count($igAvgs), 2);
                }
            }
            if ($scAvgs !== []) {
                $catAvgs[] = round(array_sum($scAvgs) / count($scAvgs), 2);
            }
        }

        // Overall = average of category averages
        $overall = $catAvgs !== [] ? round(array_sum($catAvgs) / count($catAvgs), 2) : null;

        Survey::where('id', $surveyId)->update(['overall_cap_score' => $overall]);

        return $overall;
    }
}
