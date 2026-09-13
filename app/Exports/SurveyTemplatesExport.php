<?php

namespace App\Exports;

use App\Models\SurveyTemplate;
use App\Traits\HasDynamicLike;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SurveyTemplatesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable, HasDynamicLike;

    protected ?string $search;

    protected ?string $statusFilter;

    public function __construct(?string $search = null, ?string $statusFilter = null)
    {
        $this->search = $search;
        $this->statusFilter = $statusFilter;
    }

    public function query()
    {
        $query = SurveyTemplate::withCount(['surveys', 'categories']);

        if ($this->search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($operator) {
                $q->where('name', $operator, "%{$this->search}%")
                    ->orWhere('code', $operator, "%{$this->search}%")
                    ->orWhere('description', $operator, "%{$this->search}%");
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        return $query->orderBy('is_default', 'desc')->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Nama Template',
            'Deskripsi',
            'Jumlah Kategori',
            'Jumlah Survey',
            'Default',
            'Status',
        ];
    }

    public function map($template): array
    {
        return [
            $template->code ?? '-',
            $template->name,
            $template->description ?? '-',
            $template->categories_count,
            $template->surveys_count,
            $template->is_default ? 'Ya' : 'Tidak',
            $template->is_active ? 'Aktif' : 'Nonaktif',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
