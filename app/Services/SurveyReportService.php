<?php

namespace App\Services;

use App\Enums\FileStatus;
use App\Jobs\ProcessSurveyReport;
use App\Jobs\ProcessSurveyReportDocumentation;
use App\Models\Survey;
use App\Models\SurveyGroupNote;
use App\Models\SurveyReport;
use App\Models\SurveyReportDocumentation;
use App\Models\SurveyResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SurveyReportService
{
    public const GENERATOR_VERSION = '2026-10-report-v28';

    public function __construct(protected SurveyService $surveyService) {}

    /**
     * Ambil report untuk survey; buat otomatis (lazy) dengan konten default
     * bila belum ada — report selalu 1:1 dengan survey.
     */
    public function getOrCreate(Survey $survey): SurveyReport
    {
        return DB::transaction(function () use ($survey) {
            $report = SurveyReport::firstOrCreate(
                ['survey_id' => $survey->id],
                [
                    'report_number' => $this->generateReportNumber(),
                    'report_title' => 'JASA KONSULTAN INDEPENDENT SURVEY KONDISI',
                ]
            );

            if ($report->wasRecentlyCreated) {
                $this->seedDefaultSections($report, $survey);
            } elseif ($report->file_status === FileStatus::Completed
                && $report->generator_version !== self::GENERATOR_VERSION) {
                $this->markGeneratedFileOutdated($report);
                $report->save();
            }

            return $report;
        });
    }

    public function updateMeta(SurveyReport $report, array $data): SurveyReport
    {
        $report->fill($data);
        if ($report->isDirty()) {
            $this->markGeneratedFileOutdated($report);
        }
        $report->save();

        return $report;
    }

    /**
     * Upsert konten narasi per section (key => content).
     */
    public function saveSections(SurveyReport $report, array $sections): void
    {
        DB::transaction(function () use ($report, $sections) {
            $changed = false;
            foreach ($sections as $key => $content) {
                $section = $report->sections()->firstOrNew(['key' => $key]);
                $section->content = $content;
                if ($section->isDirty()) {
                    $section->save();
                    $changed = true;
                }
            }

            if ($changed) {
                $this->markGeneratedFileOutdated($report);
                $report->save();
            }
        });
    }

    /**
     * Tandai report sedang diproses lalu dispatch job generate DOCX.
     */
    public function requestGenerate(SurveyReport $report): SurveyReport
    {
        $report->update([
            'file_status' => FileStatus::Processing,
            'file_error' => null,
        ]);

        ProcessSurveyReport::dispatch($report->id);

        return $report;
    }

    public function requestDocumentationUpload(
        SurveyReport $report,
        int $categoryId,
        UploadedFile $photo,
        array $cropData
    ): SurveyReportDocumentation {
        $storage = app(FileStorageService::class);
        $temp = $storage->storeTemp($photo, 'survey-report-documentations');

        try {
            $documentation = DB::transaction(function () use ($report, $categoryId, $cropData) {
                $documentation = $report->documentations()->updateOrCreate(
                    ['survey_category_id' => $categoryId],
                    [
                        'file_status' => FileStatus::Processing,
                        'file_error' => null,
                        'crop_data' => $cropData,
                    ]
                );
                $this->markGeneratedFileOutdated($report);
                $report->save();

                return $documentation;
            });

            ProcessSurveyReportDocumentation::dispatch($documentation->id, $temp['path'], $cropData);

            return $documentation;
        } catch (\Throwable $e) {
            $storage->deleteTemp($temp['path']);
            throw $e;
        }
    }

    public function deleteDocumentation(SurveyReportDocumentation $documentation): void
    {
        DB::transaction(function () use ($documentation) {
            $report = $documentation->report;
            app(FileStorageService::class)->delete($documentation->file_path);
            $documentation->delete();
            $this->markGeneratedFileOutdated($report);
            $report->save();
        });
    }

    protected function markGeneratedFileOutdated(SurveyReport $report): void
    {
        $report->file_status = null;
        $report->file_error = null;
        $report->file_processed_at = null;
        $report->generator_version = null;
    }

    /**
     * Download file DOCX hasil generate terakhir.
     */
    public function download(SurveyReport $report)
    {
        return app(FileStorageService::class)->download($report->file_path, $report->file_name);
    }

    public function generateReportNumber(): string
    {
        $year = now()->format('Y');
        $prefix = 'LAP-'.$year.'-';
        $last = SurveyReport::where('report_number', 'like', $prefix.'%')
            ->orderByRaw('CAST(SUBSTRING(report_number, '.(strlen($prefix) + 1).') AS INTEGER) DESC')
            ->first();
        $next = $last ? ((int) substr($last->report_number, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function capReference(): array
    {
        return [
            'introduction' => 'CAP adalah program penilaian kondisi kapal, baik lambung, permesinan maupun sistem operasinya dimana hasil penilaian dari kondisi kapal tersebut akan disajikan dalam bentuk peringkat (CAP rating). Pemberian rating didasarkan pada pemeriksaan sertifikat dan dokumen kapal, dan juga inspeksi fisik/visual konstruksi, permesinan, sistem kelistrikan, perlengkapan, dan alat keselamatan kapal. Inspeksi visual yang dilakukan bukan hanya melihat kondisi fisik dari peralatan namun juga kondisi support/dudukan. Item yang diperiksa (tergantung pada jenis unit) dengan bukti belum terpasang, kerusakan, deformasi, retakan, kebocoran, korosi, pitting, dan lainnya.',
            'standards' => ['BKI – CAP', 'ABS – CAP', 'BV – CAP', 'PM 62 (SPM)'],
            'categories' => [
                [
                    'code' => 'C',
                    'criteria' => [[
                        'name' => 'Kondisi Pelapisan/Cat pada Konstruksi, Plat, Perpipaan dan Peralatan deck lainnya',
                        'scores' => [
                            'Tidak terdapat lapisan pelindung',
                            'Lapisan pelindung tidak terawat',
                            'Lapisan pelindung terawat',
                            'Lapisan pelindung dalam kondisi baru',
                        ],
                    ]],
                ],
                [
                    'code' => 'V',
                    'criteria' => [
                        [
                            'name' => 'Kondisi Retak/Crack dan Deformasi pada Konstruksi, Perpipaan, Plat dan Peralatan deck lainnya',
                            'scores' => [
                                'Terdapat kerusakan peralatan,perpipaan, crack dan deformasi yang signifikan',
                                'Terdapat spot crack, deformasi serta kerusakan peralatan dan perpipaan',
                                'Terdapat area crack, deformasi dan kerusakan peralatan, perpipaan tingkat rendah',
                                'Tidak terdapat kerusakan peralatan, perpipaan ataupun crack dan deformasi pada spot area sesuai dengan kondisi baru',
                            ],
                        ],
                        [
                            'name' => 'Kondisi Ruang-ruangan Kapal',
                            'scores' => ['', '', '', ''],
                        ],
                        [
                            'name' => 'Kondisi Sistim Permesinan Kapal & Permesinan Geladak',
                            'scores' => [
                                'Terdapat kerusakan secara signifikan',
                                'Terdapat spot kerusakan pada item dan komponen',
                                'Terdapat kerusakan pada item dan komponen tingkat rendah',
                                'Tidak terdapat kerusakan item dan komponen serta dalam kondisi baru',
                            ],
                        ],
                        [
                            'name' => 'Kondisi Alat Navigasi dan Komunikasi',
                            'scores' => [
                                'Terdapat kerusakan fisik secara signifikan',
                                'Terdapat kerusakan fisik dan komponen peralatan',
                                'Tidak terdapat kerusakan fisik dan terawat',
                                'Kondisi fisik baik dan dalam kondisi baru',
                            ],
                        ],
                        [
                            'name' => 'Kondisi Life-Saving Appliances (LSA) & Fire-Fighting Appliances (FFA)',
                            'scores' => [
                                'Terdapat kerusakan secara signifikan',
                                'Terdapat spot kerusakan fisik dan komponen peralatan',
                                'Tidak terdapat kerusakan dan terawat',
                                'Kondisi fisik baik dan dalam kondisi baru',
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'F',
                    'criteria' => [
                        [
                            'name' => 'Sistim Permesinan Kapal dan Permesinan Geladak',
                            'scores' => [
                                'Terdapat kerusakan komponen secara signifikan dan tidak berfungsi',
                                'Terdapat spot kerusakan pada komponen permesinan',
                                'Terdapat penurunan peforma pada komponen permesinan dan berfungsi',
                                'Berfungsi dengan baik sesuai dengan kondisi baru',
                            ],
                        ],
                        [
                            'name' => 'Sistim Kumpa-Kumpa (Pompa)',
                            'scores' => [
                                'Terdapat kerusakan pada item dan komponen secara signifikan dan tidak berfungsi',
                                'Terdapat kerusakan pada item dan komponen',
                                'Berfungsi dan terdapat penurunan peforma',
                                'Berfungsi dengan baik sesuai dengan kondisi baru',
                            ],
                        ],
                        [
                            'name' => 'Alat Navigasi dan Komunikasi',
                            'scores' => [
                                'Tidak berfungsi dan terdapat kerusakan secara signifikan',
                                'Tidak berfungsi secara optimal dan terdapat kerusakan komponen peralatan',
                                'Berfungsi secara optimal dan terdapat penurunan peforma',
                                'Berfungsi dengan baik sesuai dengan kondisi baru',
                            ],
                        ],
                    ],
                ],
                [
                    'code' => 'M',
                    'criteria' => [
                        [
                            'name' => 'Dokumen Kapal',
                            'scores' => [
                                'Sertifikat telah melewati masa berlaku',
                                'Masa berlaku sertifikat sisa 1 bulan mendekati tanggal masa berlaku',
                                'Masa berlaku sertifikat sisa 3 bulan mendekati tanggal masa berlaku',
                                'Masa berlaku sertifikat masih aktif',
                            ],
                            // Baris kedua "Dokumen Kapal" di master (vMerge):
                            // score 1 teks sendiri, score 2-4 digabung satu sel (colspan 3).
                            'extra_rows' => [
                                [
                                    'Semua sertifikat fisik dan dokumen dalam bentuk hard copy / soft copy tidak ada.',
                                    ['text' => 'Semua sertifikat fisik dan dokumen dalam hard / soft copy wajib ada di atas kapal', 'colspan' => 3],
                                ],
                            ],
                        ],
                        [
                            'name' => 'Life-Saving Appliances (LSA) & Fire-Fighting Appliances (FFA)',
                            'scores' => [
                                'Memiliki masa berlaku sudah expired',
                                'Memiliki masa berlaku mendekati 1 bulan dari tanggal expired',
                                'Memiliki masa berlaku mendekati 3 bulan dari tanggal expired',
                                'Memiliki masa berlaku lebih dari 3 bulan dari tanggal expired',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    // -----------------------------------------------------------------
    //  Data aggregation untuk builder DOCX
    // -----------------------------------------------------------------

    /**
     * Kumpulkan seluruh data yang dibutuhkan WordReportBuilder.
     * Semua query eager-loaded di sini (tanpa query di loop builder).
     */
    public function buildReportData(SurveyReport $report): array
    {
        $survey = $report->survey()->with(['ship.certificates', 'template'])->firstOrFail();
        $categories = $this->surveyService->hydrateStructure($survey->structure);

        $responses = SurveyResponse::where('survey_id', $survey->id)
            ->get()
            ->keyBy('survey_item_id');

        $groupNotes = SurveyGroupNote::where('survey_id', $survey->id)
            ->orderBy('order_num')
            ->get()
            ->groupBy('survey_item_group_id');

        $sections = $report->sections->keyBy('key');

        // Report yang di-seed dengan format narasi lama dinormalisasi ke
        // format master — hanya di memori (konten hasil edit user tidak tersentuh).
        $execSection = $sections->get('executive_summary');
        if ($execSection && trim((string) $execSection->content) === $this->executiveSummaryLegacyDefault($survey)) {
            $execSection->content = $this->executiveSummaryDefault($survey);
        }

        $documentations = $report->documentations()
            ->where('file_status', FileStatus::Completed)
            ->get()
            ->keyBy('survey_category_id');

        // Overall CAP: pakai kolom tersimpan (di-recalc saat saveResponses),
        // fallback hitung ulang dari responses bila kosong.
        $overall = $survey->overall_cap_score !== null
            ? (float) $survey->overall_cap_score
            : $this->surveyService->overallAvg($categories, $responses);

        return [
            'report' => $report,
            'survey' => $survey,
            'ship' => $survey->ship,
            'categories' => $categories,
            'responses' => $responses,
            'groupNotes' => $groupNotes,
            'sections' => $sections,
            'documentations' => $documentations,
            'overallCap' => $overall,
            'benchmark' => $this->benchmarkData($survey),
        ];
    }

    /**
     * Titik-titik grafik benchmark: umur kapal vs overall CAP rating
     * dari seluruh survey completed (populasi) + posisi survey ini.
     *
     * @return array{points: array<array{x: float, y: float}>, current: ?array{x: float, y: float}}
     */
    public function benchmarkData(Survey $survey): array
    {
        $points = Survey::completed()
            ->whereNotNull('overall_cap_score')
            ->where('id', '!=', $survey->id)
            ->with('ship:id,year_built')
            ->get()
            ->map(function ($s) {
                $yearBuilt = $s->ship?->year_built;
                if (! $yearBuilt || ! $s->survey_date) {
                    return null;
                }

                return [
                    'x' => (float) ($s->survey_date->format('Y') - $yearBuilt),
                    'y' => (float) $s->overall_cap_score,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $current = null;
        if ($survey->overall_cap_score !== null && $survey->ship?->year_built && $survey->survey_date) {
            $current = [
                'x' => (float) ($survey->survey_date->format('Y') - $survey->ship->year_built),
                'y' => (float) $survey->overall_cap_score,
            ];
        }

        return ['points' => $points, 'current' => $current];
    }

    /**
     * Narasi executive summary sesuai template Word master ASDP.
     */
    protected function executiveSummaryDefault(Survey $survey): string
    {
        $shipName = $survey->ship?->name ?? 'kapal';
        $dateStr = $survey->survey_date?->translatedFormat('d F Y') ?? '-';
        $location = $survey->location;

        return implode("\n", [
            "Pemeriksaan dan penilaian pada kapal {$shipName} dilaksanakan pada tanggal {$dateStr}".($location ? " di {$location}" : '').' dengan kondisi kapal operasional. Survei kondisi ini dilaksanakan untuk mengevaluasi aspek legalitas, konstruksi, sistem permesinan, navigasi dan komunikasi, serta sistem keselamatan kapal. Hasil survei disusun dalam satu laporan yang akan dijadikan sebagai bahan pertimbangan teknis bagi PT ASDP Indonesia Ferry.',
            'Penilaian kondisi kapal dilakukan menggunakan metodologi Condition Assessment Program (CAP) dengan parameter mengacu pada standar CAP milik BKI (Biro Klasifikasi Indonesia). Hasil penilaian disajikan dalam bentuk peringkat kondisi (CAP rating) dengan skala 1 hingga 4, di mana nilai 1 menunjukkan kondisi terendah dan nilai 4 menunjukkan kondisi tertinggi. Cakupan pemeriksaan dalam survei ini meliputi tujuh kelompok besar, yaitu:',
        ]);
    }

    /**
     * Format narasi lama — dipakai hanya untuk mendeteksi section yang masih
     * berisi default lama agar dinormalisasi saat generate DOCX.
     */
    protected function executiveSummaryLegacyDefault(Survey $survey): string
    {
        $shipName = $survey->ship?->name ?? 'kapal';
        $dateStr = $survey->survey_date?->translatedFormat('d F Y');
        $location = $survey->location;

        return implode("\n", array_filter([
            "Pemeriksaan dan penilaian pada kapal {$shipName} dilaksanakan pada tanggal {$dateStr}".($location ? " di {$location}" : '').'. Survei kondisi ini dilaksanakan untuk mengevaluasi aspek legalitas, konstruksi, sistem permesinan, navigasi dan komunikasi, serta sistem keselamatan kapal. Hasil survei disusun dalam satu laporan yang akan dijadikan sebagai bahan pertimbangan teknis.',
            'Penilaian kondisi kapal dilakukan menggunakan metodologi Condition Assessment Program (CAP) dengan parameter mengacu pada standar CAP milik BKI (Biro Klasifikasi Indonesia). Hasil penilaian disajikan dalam bentuk peringkat kondisi (CAP rating) dengan skala 1 hingga 4, di mana nilai 1 menunjukkan kondisi terendah dan nilai 4 menunjukkan kondisi tertinggi.',
        ]));
    }

    /**
     * Daftar standar CAP BAB I — satu item per baris dengan nomor,
     * sesuai tampilan list bernomor di template Word master.
     */
    protected function capStandardsDefault(): string
    {
        return collect($this->capReference()['standards'])
            ->map(fn ($standard, $i) => ($i + 1).'. '.$standard)
            ->implode("\n");
    }

    /**
     * Konten default per section saat report pertama dibuat.
     * finding_{catId} diisi gabungan group notes kategori tsb sebagai draf awal.
     */
    protected function seedDefaultSections(SurveyReport $report, Survey $survey): void
    {
        $categories = $this->surveyService->hydrateStructure($survey->structure);
        $groupNotes = SurveyGroupNote::where('survey_id', $survey->id)
            ->orderBy('order_num')
            ->get()
            ->groupBy('survey_item_group_id');

        $defaults = [
            'executive_summary' => $this->executiveSummaryDefault($survey),
            'cap_standards' => $this->capStandardsDefault(),
            'memoranda' => 'N/A',
        ];

        $order = 0;
        foreach ($defaults as $key => $content) {
            $report->sections()->create(['key' => $key, 'content' => $content, 'order_num' => $order++]);
        }

        foreach ($categories as $cat) {
            // Draf keterangan temuan = gabungan catatan item group pada kategori
            $findingLines = $cat->subCategories
                ->flatMap(fn ($sc) => $sc->itemGroups)
                ->flatMap(fn ($ig) => $groupNotes->get($ig->id)?->pluck('note') ?? collect())
                ->filter()
                ->values();

            $report->sections()->create([
                'key' => 'finding_'.$cat->id,
                'content' => $findingLines->isNotEmpty()
                    ? $findingLines->map(fn ($n) => '- '.$n)->implode("\n")
                    : null,
                'order_num' => $order++,
            ]);

            $report->sections()->create([
                'key' => 'saran_'.$cat->id,
                'content' => null,
                'order_num' => $order++,
            ]);
        }
    }
}
