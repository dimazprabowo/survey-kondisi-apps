<?php

namespace App\Livewire\Pages;

use App\Models\Survey;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public string $year = '';

    public function mount(): void
    {
        $this->year = (string) now()->year;
    }

    protected function rules(): array
    {
        return ['year' => ['required', 'integer', 'between:1900,2100']];
    }

    protected function validationAttributes(): array
    {
        return ['year' => 'tahun survey'];
    }

    public function updatedYear(): void
    {
        $this->authorize('viewStats');
        $this->authorize('viewAny', Survey::class);
        if ($this->year === '') {
            $this->year = (string) now()->year;
        }
        $this->validate();
    }

    public function render()
    {
        $user = auth()->user();
        $canViewStats = Gate::allows('viewStats');

        $data = [
            'authUser' => $user,
            'authUserRole' => $user->getRoleNames()->join(', ') ?: 'User',
            'canViewStats' => $canViewStats,
            'appJoinedAt' => $user->created_at,
        ];

        if ($canViewStats) {
            // Only query counts the user is permitted to see (avoid information leak + unnecessary queries).
            $service = app(DashboardService::class);
            $data = array_merge($data, $service->administrationCounts());
            if (Gate::allows('viewAny', Survey::class)) {
                $data['analyticsYear'] = validator(['year' => $this->year], $this->rules())->passes()
                    ? (int) $this->year : now()->year;
                $data['yearOptions'] = $service->surveyYears();
                $data['analytics'] = $service->surveyAnalytics($data['analyticsYear']);
            }
        }

        return view('livewire.pages.dashboard', $data);
    }
}
