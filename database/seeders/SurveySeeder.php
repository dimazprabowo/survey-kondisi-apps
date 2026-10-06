<?php

namespace Database\Seeders;

use App\Enums\SurveyItemType;
use App\Enums\SurveyStatus;
use App\Models\Ship;
use App\Models\Survey;
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

        $defaultTemplateId = $templates->firstWhere('is_default', true)?->id ?? $templates->first()->id;

        // 2 survey kurasi hanya dibuat saat tabel masih kosong (fresh seed)
        if (Survey::count() === 0) {
            // Survey 1: template default, semua item terisi, status completed
            $this->seedSurvey($service, [
                'ship_id' => $ships[0]->id,
                'survey_template_id' => $defaultTemplateId,
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
                'survey_template_id' => $defaultTemplateId,
                'survey_date' => now()->format('Y-m-d'),
                'surveyor' => $user?->name ?? 'Surveyor Contoh',
                'location' => 'Belawan, Medan',
                'status' => SurveyStatus::InProgress->value,
                'notes' => 'Survey sedang berjalan, sebagian section belum diisi.',
                'created_by' => $user?->id,
            ], fillRatio: 0.5, withGroupNotes: true);
        }

        // Lengkapi sampai total 25 survey dummy — aman dijalankan ulang
        // (hanya menambah sampai target tercapai, tidak menduplikasi).
        $this->seedRandomSurveys($service, $defaultTemplateId, $user?->id, target: 25);
    }

    /**
     * Buat survey dummy acak hingga total $target baris — kapal acak,
     * tanggal 3 tahun terakhir, status berbobot (mayoritas completed).
     */
    protected function seedRandomSurveys(SurveyService $service, int $templateId, ?int $userId, int $target): void
    {
        $toCreate = max(0, $target - Survey::count());

        if ($toCreate === 0) {
            $this->command->info("Surveys seeded: sudah ada {$target} survey, tidak ada yang ditambah.");

            return;
        }

        $shipIds = Ship::active()->pluck('id')->all();
        $surveyors = [
            'Capt. Andi Prasetya', 'Ir. Budi Santoso', 'Capt. Dewi Lestari',
            'Ir. Hendra Wijaya', 'Capt. Rizal Fahmi', 'Ir. Sari Rahmawati',
        ];
        $locations = [
            'Pelabuhan Merak, Banten', 'Pelabuhan Bakauheni, Lampung',
            'Ketapang, Banyuwangi', 'Gilimanuk, Bali', 'Tanjung Priok, Jakarta',
            'Pelabuhan Lembar, Lombok', 'Balikpapan, Kalimantan Timur',
            'Makassar, Sulawesi Selatan', 'Bitung, Sulawesi Utara',
            'Banjarmasin, Kalimantan Selatan',
        ];
        $notes = [
            SurveyStatus::Completed->value => 'Survey kondisi selesai. CAP rating sudah dihitung.',
            SurveyStatus::InProgress->value => 'Survey dalam proses pengisian data lapangan.',
            SurveyStatus::Draft->value => 'Draft survey awal, belum ada pengisian.',
        ];

        for ($i = 0; $i < $toCreate; $i++) {
            $fake = fake();
            // Distribusi status: ~60% completed, ~25% in_progress, ~15% draft
            $status = $fake->randomElement([
                SurveyStatus::Completed, SurveyStatus::Completed, SurveyStatus::Completed,
                SurveyStatus::Completed, SurveyStatus::Completed, SurveyStatus::Completed,
                SurveyStatus::InProgress, SurveyStatus::InProgress, SurveyStatus::InProgress,
                SurveyStatus::Draft,
            ]);

            $fillRatio = match ($status) {
                SurveyStatus::Completed => 1.0,
                SurveyStatus::InProgress => $fake->randomFloat(1, 3, 7) / 10,
                default => $fake->randomFloat(1, 0, 2) / 10,
            };

            $this->seedSurvey($service, [
                'ship_id' => $fake->randomElement($shipIds),
                'survey_template_id' => $templateId,
                'survey_date' => $fake->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
                'surveyor' => $fake->randomElement($surveyors),
                'location' => $fake->randomElement($locations),
                'status' => $status->value,
                'notes' => $notes[$status->value],
                'created_by' => $userId,
            ], fillRatio: $fillRatio, withGroupNotes: $status === SurveyStatus::Completed);
        }

        $this->command->info("Surveys seeded: {$toCreate} survey dummy ditambah (total {$target}).");
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
