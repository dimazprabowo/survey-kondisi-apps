<?php

namespace Database\Seeders;

use App\Enums\SurveyAccessStatus;
use App\Models\Survey;
use App\Models\User;
use App\Services\SurveyService;
use Illuminate\Database\Seeder;

/**
 * Contoh data permintaan akses edit survey:
 * - satu request PENDING (inspektur2) untuk demo alur approve/tolak,
 * - satu request APPROVED (inspektur3) untuk demo daftar kolaborator.
 * Lewat SurveyService agar notifikasi ke pemilik/requester ikut terkirim.
 */
class SurveyAccessRequestSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(SurveyService::class);

        $requesterPending = User::where('email', 'inspektur2@app.com')->first();
        $requesterApproved = User::where('email', 'inspektur3@app.com')->first();

        $surveys = Survey::whereNotNull('created_by')->orderBy('id')->take(2)->get();
        if ($surveys->count() < 2 || ! $requesterPending || ! $requesterApproved) {
            return;
        }

        $service->requestEditAccess($surveys[0], $requesterPending);

        $approvedRequest = $service->requestEditAccess($surveys[1], $requesterApproved);
        if ($approvedRequest->status === SurveyAccessStatus::Pending) {
            $owner = $surveys[1]->creator;
            if ($owner) {
                $service->decideAccessRequest($approvedRequest, true, $owner);
            }
        }

        $this->command?->info('Survey access requests seeded: 1 pending, 1 approved.');
    }
}
