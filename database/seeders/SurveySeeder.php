<?php

namespace Database\Seeders;

use App\Enums\SurveyItemType;
use App\Enums\SurveyStatus;
use App\Models\Ship;
use App\Models\SurveyCategory;
use App\Models\SurveyItem;
use App\Models\SurveyTemplate;
use App\Models\User;
use App\Services\SurveyService;
use Illuminate\Database\Seeder;

class SurveySeeder extends Seeder
{
    public function run(): void
    {
        $service = new SurveyService;
        $user = User::first();
        $ships = Ship::active()->limit(3)->get();
        $templates = SurveyTemplate::active()->get();

        if ($ships->isEmpty() || $templates->isEmpty()) {
            $this->command->warn('SurveySeeder dilewati: butuh ships & templates lebih dulu.');

            return;
        }

        // Survey 1: template default, semua item terisi, status completed
        $this->seedSurvey($service, [
            'ship_id' => $ships[0]->id,
            'survey_template_id' => $templates->firstWhere('is_default', true)?->id ?? $templates->first()->id,
            'survey_date' => now()->subDays(7)->format('Y-m-d'),
            'surveyor' => $user?->name ?? 'Surveyor Contoh',
            'location' => 'Tanjung Priok, Jakarta',
            'status' => SurveyStatus::Completed->value,
            'notes' => 'Survey kondisi rutin tahunan. Kondisi kapal secara umum baik.',
            'created_by' => $user?->id,
        ], fillRatio: 1.0, withGroupNotes: true);

        // Survey 2: template default, pengisian parsial, status in_progress
        $this->seedSurvey($service, [
            'ship_id' => $ships[1]->id,
            'survey_template_id' => $templates->firstWhere('is_default', true)?->id ?? $templates->first()->id,
            'survey_date' => now()->format('Y-m-d'),
            'surveyor' => $user?->name ?? 'Surveyor Contoh',
            'location' => 'Belawan, Medan',
            'status' => SurveyStatus::InProgress->value,
            'notes' => 'Survey sedang berjalan, sebagian section belum diisi.',
            'created_by' => $user?->id,
        ], fillRatio: 0.5, withGroupNotes: true);

        $this->command->info('Surveys seeded: 2 sample surveys (1 completed penuh, 1 in_progress parsial)');
    }

    /**
     * Buat survey + responses konsisten via service (avg & overall dihitung otomatis).
     * fillRatio: 0.0-1.0 proporsi item yang diisi.
     */
    protected function seedSurvey(SurveyService $service, array $surveyData, float $fillRatio, bool $withGroupNotes): void
    {
        $survey = $service->create($surveyData);

        $items = SurveyItem::whereHas('itemGroup.subCategory.category', function ($q) use ($survey) {
            $q->where('survey_template_id', $survey->survey_template_id);
        })->with('itemGroup')->orderBy('order_num')->get();

        $responses = [];
        $i = 0;
        foreach ($items as $item) {
            $i++;
            // Isi hanya sebagian item sesuai fillRatio (pola selang-seling agar parsial merata)
            $fill = $fillRatio >= 1.0 || ($i % (int) max(1, round(1 / $fillRatio))) !== 0;

            if (! $fill) {
                continue;
            }

            if ($item->item_type === SurveyItemType::Inventory) {
                $responses[$item->id] = [
                    'qty' => rand(1, 10),
                    'specification' => 'Sesuai standar',
                ];

                continue;
            }

            $scores = [];
            foreach ((array) $item->score_labels as $label) {
                $scores[$label] = (string) rand(2, 4);
            }

            $responses[$item->id] = [
                'scores' => $scores,
                'date_issued' => $item->has_date_fields ? now()->subMonths(rand(1, 12))->format('Y-m-d') : null,
                'date_expired' => $item->has_date_fields ? now()->addMonths(rand(6, 36))->format('Y-m-d') : null,
            ];
        }

        // Catatan grup: 1 catatan untuk tiap grup ke-N pada survey completed
        $groupNotes = [];
        if ($withGroupNotes) {
            $groupIds = SurveyCategory::where('survey_template_id', $survey->survey_template_id)
                ->with('subCategories.itemGroups')
                ->get()
                ->flatMap(fn ($cat) => $cat->subCategories)
                ->flatMap(fn ($sub) => $sub->itemGroups)
                ->pluck('id');

            $j = 0;
            foreach ($groupIds as $gid) {
                $j++;
                if ($j % 5 === 0) {
                    $groupNotes[$gid] = ['Kondisi perlu monitoring berkala'];
                }
            }
        }

        $service->saveResponses($survey->id, $responses, $groupNotes);
    }
}
