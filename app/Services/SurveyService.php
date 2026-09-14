<?php

namespace App\Services;

use App\Enums\SurveyStatus;
use App\Models\Survey;
use App\Models\SurveyCategory;
use App\Models\SurveyItem;
use App\Models\SurveyResponse;
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
        int $perPage = 15
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

        return $query->latest('survey_date')->paginate($perPage);
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

            $survey = Survey::create($data);

            // Pre-create empty responses for all items
            $this->initResponses($survey);

            return $survey;
        });
    }

    public function initResponses(Survey $survey): void
    {
        $itemIds = SurveyItem::whereHas('itemGroup.subCategory.category', function ($q) use ($survey) {
            $q->where('survey_template_id', $survey->survey_template_id);
        })->pluck('id');
        $rows = $itemIds->map(fn ($id) => [
            'survey_id' => $survey->id,
            'survey_item_id' => $id,
            'scores' => null,
            'avg_score' => null,
            'date_issued' => null,
            'date_expired' => null,
            'note' => null,
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
                'note' => ($data['note'] ?? null) ?: null,
            ]
        );
    }

    public function saveResponses(int $surveyId, array $responses): void
    {
        DB::transaction(function () use ($surveyId, $responses) {
            foreach ($responses as $itemId => $data) {
                $this->saveResponse($surveyId, (int) $itemId, $data);
            }
            $this->recalculateOverall($surveyId);
        });
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
        // Fetch all responses with full hierarchy in one query
        $rows = DB::table('survey_responses')
            ->join('survey_items', 'survey_responses.survey_item_id', '=', 'survey_items.id')
            ->join('survey_item_groups', 'survey_items.survey_item_group_id', '=', 'survey_item_groups.id')
            ->join('survey_sub_categories', 'survey_item_groups.survey_sub_category_id', '=', 'survey_sub_categories.id')
            ->join('survey_categories', 'survey_sub_categories.survey_category_id', '=', 'survey_categories.id')
            ->where('survey_responses.survey_id', $surveyId)
            ->whereNotNull('survey_responses.avg_score')
            ->select(
                'survey_responses.avg_score',
                'survey_item_groups.id as ig_id',
                'survey_sub_categories.id as sc_id',
                'survey_categories.id as cat_id'
            )
            ->get();

        if ($rows->isEmpty()) {
            Survey::where('id', $surveyId)->update(['overall_cap_score' => null]);

            return null;
        }

        // Group item averages by item group
        $igAvgs = [];
        foreach ($rows as $row) {
            $igAvgs[$row->ig_id][] = (float) $row->avg_score;
        }
        $igAvgMap = [];
        foreach ($igAvgs as $igId => $avgs) {
            $igAvgMap[$igId] = round(array_sum($avgs) / count($avgs), 2);
        }

        // Map sub-category -> item groups (with their averages)
        $scToIg = [];
        foreach ($rows as $row) {
            $scToIg[$row->sc_id][$row->ig_id] = $igAvgMap[$row->ig_id];
        }
        $scAvgMap = [];
        foreach ($scToIg as $scId => $igMap) {
            $vals = array_filter($igMap, fn ($v) => $v !== null);
            if (! empty($vals)) {
                $scAvgMap[$scId] = round(array_sum($vals) / count($vals), 2);
            }
        }

        // Map category -> sub-categories (with their averages)
        $catToSc = [];
        foreach ($rows as $row) {
            $catToSc[$row->cat_id][$row->sc_id] = $scAvgMap[$row->sc_id] ?? null;
        }
        $catAvgMap = [];
        foreach ($catToSc as $catId => $scMap) {
            $vals = array_filter($scMap, fn ($v) => $v !== null);
            if (! empty($vals)) {
                $catAvgMap[$catId] = round(array_sum($vals) / count($vals), 2);
            }
        }

        // Overall = average of category averages
        $overall = null;
        if (! empty($catAvgMap)) {
            $overall = round(array_sum($catAvgMap) / count($catAvgMap), 2);
        }

        Survey::where('id', $surveyId)->update(['overall_cap_score' => $overall]);

        return $overall;
    }

    /**
     * Get survey with full hierarchy for display (eager loaded).
     */
    public function getSurveyWithHierarchy(int $surveyId): ?Survey
    {
        return Survey::with([
            'ship',
            'creator',
            'template',
            'responses.item.itemGroup.subCategory.category',
        ])->find($surveyId);
    }

    /**
     * Get template structure with responses mapped for a survey.
     */
    public function getTemplateWithResponses(int $surveyId): array
    {
        $survey = $this->getSurveyWithHierarchy($surveyId);
        if (! $survey) {
            return [];
        }

        $responseMap = $survey->responses->keyBy('survey_item_id');

        $categories = SurveyCategory::with([
            'subCategories.itemGroups.items',
        ])
            ->where('survey_template_id', $survey->survey_template_id)
            ->orderBy('order_num')
            ->get();

        $result = [];
        foreach ($categories as $cat) {
            $catData = [
                'id' => $cat->id,
                'code' => $cat->code,
                'label' => $cat->label,
                'sub_categories' => [],
            ];
            foreach ($cat->subCategories as $sc) {
                $scData = [
                    'id' => $sc->id,
                    'order_num' => $sc->order_num,
                    'name' => $sc->name,
                    'item_groups' => [],
                ];
                foreach ($sc->itemGroups as $ig) {
                    $igData = [
                        'id' => $ig->id,
                        'code' => $ig->code,
                        'name' => $ig->name,
                        'items' => [],
                    ];
                    foreach ($ig->items as $item) {
                        $response = $responseMap->get($item->id);
                        $igData['items'][] = [
                            'id' => $item->id,
                            'code' => $item->code,
                            'name' => $item->name,
                            'score_labels' => $item->score_labels,
                            'has_date_fields' => $item->has_date_fields,
                            'scores' => $response?->scores ?? [],
                            'avg_score' => $response?->avg_score,
                            'date_issued' => $response?->date_issued,
                            'date_expired' => $response?->date_expired,
                            'note' => $response?->note,
                        ];
                    }
                    $scData['item_groups'][] = $igData;
                }
                $catData['sub_categories'][] = $scData;
            }
            $result[] = $catData;
        }

        return $result;
    }

    /**
     * Calculate live averages for a set of responses (for frontend display).
     * Returns hierarchy of averages without saving.
     */
    public function calculateLiveAverages(Collection $responses): array
    {
        $itemAvgs = [];
        $igAvgs = [];
        $scAvgs = [];
        $catAvgs = [];

        foreach ($responses as $r) {
            $item = $r->item ?? $r['item'] ?? null;
            if (! $item) {
                continue;
            }
            $avg = $r->avg_score ?? ($r['avg_score'] ?? null);
            if ($avg !== null) {
                $igId = $item->itemGroup->id ?? $item['item_group_id'];
                $scId = $item->itemGroup->subCategory->id ?? $item['sub_category_id'];
                $catId = $item->itemGroup->subCategory->category->id ?? $item['category_id'];
                $itemAvgs[$item->id ?? $item['id']] = (float) $avg;
                $igAvgs[$igId][] = (float) $avg;
            }
        }

        return [
            'items' => $itemAvgs,
            'item_groups' => $igAvgs,
            'sub_categories' => $scAvgs,
            'categories' => $catAvgs,
        ];
    }
}
