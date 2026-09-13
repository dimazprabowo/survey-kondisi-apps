<?php

namespace App\Exports;

use App\Models\Survey;
use App\Traits\HasDynamicLike;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SurveysExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable, HasDynamicLike;

    protected ?string $search;

    protected ?string $statusFilter;

    protected ?int $shipId;

    public function __construct(?string $search = null, ?string $statusFilter = null, ?int $shipId = null)
    {
        $this->search = $search;
        $this->statusFilter = $statusFilter;
        $this->shipId = $shipId;
    }

    public function query()
    {
        $query = Survey::with(['ship:id,name,code,year_built', 'creator:id,name', 'template:id,name,code']);

        if ($this->search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($operator) {
                $q->where('survey_number', $operator, "%{$this->search}%")
                    ->orWhere('surveyor', $operator, "%{$this->search}%")
                    ->orWhere('location', $operator, "%{$this->search}%")
                    ->orWhereHas('ship', function ($sq) use ($operator) {
                        $sq->where('name', $operator, "%{$this->search}%");
                    });
            });
        }

        if ($this->statusFilter !== null && $this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->shipId) {
            $query->where('ship_id', $this->shipId);
        }

        return $query->latest('survey_date');
    }

    public function headings(): array
    {
        return [
            'Nomor Survey',
            'Kapal',
            'Kode Kapal',
            'Tahun Pembuatan',
            'Template',
            'Tanggal Survey',
            'Surveyor',
            'Lokasi',
            'CAP Score',
            'Status',
            'Dibuat Oleh',
            'Dibuat Pada',
        ];
    }

    public function map($survey): array
    {
        return [
            $survey->survey_number,
            $survey->ship?->name ?? '-',
            $survey->ship?->code ?? '-',
            $survey->ship?->year_built ?? '-',
            $survey->template?->name ?? '-',
            $survey->survey_date?->format('d M Y') ?? '-',
            $survey->surveyor ?? '-',
            $survey->location ?? '-',
            $survey->overall_cap_score !== null ? number_format((float) $survey->overall_cap_score, 2) : '-',
            $survey->status->label(),
            $survey->creator?->name ?? '-',
            $survey->created_at?->format('d M Y H:i') ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
