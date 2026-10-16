<?php

namespace App\Services;

use App\Enums\CapRating;
use App\Enums\SurveyStatus;
use App\Models\Company;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class DashboardService
{
    private function datePartExpression(string $part): string
    {
        [$unit, $format] = match ($part) {
            'year' => ['YEAR', '%Y'],
            'month' => ['MONTH', '%m'],
        };

        return match (Survey::query()->getConnection()->getDriverName()) {
            'sqlite' => "CAST(strftime('{$format}', survey_date) AS INTEGER)",
            'sqlsrv' => "DATEPART({$unit}, survey_date)",
            default => "EXTRACT({$unit} FROM survey_date)",
        };
    }

    public function administrationCounts(): array
    {
        Gate::authorize('viewStats');
        $counts = [];
        foreach (['users_view' => [User::class, 'totalUsers'], 'companies_view' => [Company::class, 'totalCompanies'], 'roles_view' => [Role::class, 'totalRoles']] as $permission => [$model, $key]) {
            if (Gate::allows($permission)) {
                $counts[$key] = $model::count();
            }
        }

        return $counts;
    }

    public function surveyYears(): array
    {
        Gate::authorize('viewStats');
        Gate::authorize('viewAny', Survey::class);

        return Survey::query()->selectRaw($this->datePartExpression('year').' as year')
            ->distinct()->toBase()->pluck('year')->push((string) now()->year)
            ->filter()->unique()->sortDesc()->values()
            ->map(fn ($year) => ['value' => (string) $year, 'label' => (string) $year])->all();
    }

    public function surveyAnalytics(int $year): array
    {
        Gate::authorize('viewStats');
        Gate::authorize('viewAny', Survey::class);

        $query = Survey::query()->where('survey_date', '>=', sprintf('%04d-01-01', $year))
            ->where('survey_date', '<', sprintf('%04d-01-01', $year + 1));
        $monthExpression = $this->datePartExpression('month');
        $groups = (clone $query)->selectRaw($monthExpression.' as month, status, COUNT(*) as total')
            ->groupByRaw($monthExpression.', status')->toBase()->get();
        $total = (int) $groups->sum('total');
        $statusCounts = $groups->groupBy('status')->map(fn ($rows) => (int) $rows->sum('total'));
        $completed = $statusCounts->get(SurveyStatus::Completed->value, 0);
        $months = [];
        foreach (['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'] as $index => $label) {
            $count = (int) $groups->where('month', $index + 1)->sum('total');
            $months[] = ['label' => $label, 'count' => $count];
        }
        $axisMax = max(4, (int) (ceil(max(array_column($months, 'count')) / 4) * 4));
        foreach ($months as $index => &$month) {
            $month['x'] = 52 + $index * 56;
            $month['height'] = round($month['count'] / $axisMax * 192, 2);
            $month['y'] = 220 - $month['height'];
        }
        unset($month);

        $statuses = array_map(fn (SurveyStatus $status) => [
            'label' => $status->label(),
            'class' => $status->badgeClass(),
            'count' => $count = $statusCounts->get($status->value, 0),
            'percentage' => $total ? round($count / $total * 100, 1) : 0,
        ], SurveyStatus::cases());

        $scoreGroups = (clone $query)->where('status', SurveyStatus::Completed)
            ->whereNotNull('overall_cap_score')->selectRaw('overall_cap_score, COUNT(*) as total')
            ->groupBy('overall_cap_score')->toBase()->get();
        $rated = (int) $scoreGroups->sum('total');
        $ratingCounts = array_fill_keys(array_column(CapRating::cases(), 'value'), 0);
        $scoreSum = 0;
        foreach ($scoreGroups as $group) {
            $score = (float) $group->overall_cap_score;
            $ratingCounts[CapRating::fromScore($score)->value] += (int) $group->total;
            $scoreSum += $score * (int) $group->total;
        }
        $ratings = array_map(fn (CapRating $rating) => [
            'label' => $rating->label(),
            'description' => $rating->description(),
            'class' => $rating->badgeClass(),
            'count' => $count = $ratingCounts[$rating->value],
            'percentage' => $rated ? round($count / $rated * 100, 1) : 0,
        ], CapRating::cases());

        return [
            'total' => $total,
            'completed' => $completed,
            'completionRate' => $total ? round($completed / $total * 100, 1) : 0,
            'rated' => $rated,
            'unrated' => $completed - $rated,
            'averageScore' => $rated ? round($scoreSum / $rated, 2) : null,
            'months' => $months,
            'axisMax' => $axisMax,
            'statuses' => $statuses,
            'ratings' => $ratings,
        ];
    }
}
