<?php

namespace App\Exports;

use App\Models\Survey;
use App\Services\SurveyService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SurveysExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected ?string $search;

    protected ?string $statusFilter;

    protected ?int $shipId;

    protected array $selectedIds;

    protected int|false|null $folderId;

    public function __construct(?string $search = null, ?string $statusFilter = null, ?int $shipId = null, array $selectedIds = [], int|false|null $folderId = null)
    {
        $this->search = $search;
        $this->statusFilter = $statusFilter;
        $this->shipId = $shipId;
        $this->selectedIds = $selectedIds;
        $this->folderId = $folderId;
    }

    public function query()
    {
        $query = Survey::with(['ship:id,name,code,year_built', 'creator:id,name', 'template:id,name,code', 'folder:id,name']);

        (new SurveyService)->applyFilters($query, $this->search, $this->statusFilter, $this->shipId);

        // false = hanya survey tanpa folder (root); int = survey dalam folder tsb
        if ($this->folderId === false) {
            $query->whereNull('survey_folder_id');
        } elseif (is_int($this->folderId)) {
            $query->where('survey_folder_id', $this->folderId);
        }

        if ($this->selectedIds !== []) {
            $query->whereIn('id', $this->selectedIds);
        }

        return $query->latest('survey_date');
    }

    public function headings(): array
    {
        return [
            'Nomor Survey',
            'Folder',
            'Kapal',
            'Kode Kapal',
            'Tahun Pembuatan',
            'Template',
            'Tanggal Survey',
            'Surveyor',
            'Lokasi',
            'CAP Score',
            'Kategori CAP',
            'Status',
            'Dibuat Oleh',
            'Dibuat Pada',
        ];
    }

    public function map($survey): array
    {
        return [
            $survey->survey_number,
            $survey->folder?->name ?? '-',
            $survey->ship?->name ?? '-',
            $survey->ship?->code ?? '-',
            $survey->ship?->year_built ?? '-',
            $survey->template?->name ?? ($survey->structure['template_name'] ?? '-'),
            $survey->survey_date?->format('d M Y') ?? '-',
            $survey->surveyor ?? '-',
            $survey->location ?? '-',
            $survey->overall_cap_score !== null ? number_format((float) $survey->overall_cap_score, 2) : '-',
            $survey->capRating()?->label() ?? '-',
            $survey->status->label(),
            $survey->creator?->name ?? '-',
            $survey->created_at?->format('d M Y H:i') ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
            ],
        ];
    }
}
