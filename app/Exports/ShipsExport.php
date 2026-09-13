<?php

namespace App\Exports;

use App\Models\Ship;
use App\Traits\HasDynamicLike;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShipsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
        $query = Ship::withCount('surveys');

        if ($this->search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($operator) {
                $q->where('name', $operator, "%{$this->search}%")
                    ->orWhere('code', $operator, "%{$this->search}%")
                    ->orWhere('imo_number', $operator, "%{$this->search}%")
                    ->orWhere('owner', $operator, "%{$this->search}%")
                    ->orWhere('operator', $operator, "%{$this->search}%");
            });
        }

        if ($this->statusFilter !== null && $this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'Nama Kapal',
            'Kode',
            'Tahun Pembuatan',
            'Nomor IMO',
            'Jenis Kapal',
            'Bendera',
            'Gross Tonnage',
            'Pemilik',
            'Operator',
            'Jumlah Survey',
            'Status',
        ];
    }

    public function map($ship): array
    {
        return [
            $ship->name,
            $ship->code ?? '-',
            $ship->year_built ?? '-',
            $ship->imo_number ?? '-',
            $ship->ship_type ?? '-',
            $ship->flag ?? '-',
            $ship->gross_tonnage ?? '-',
            $ship->owner ?? '-',
            $ship->operator ?? '-',
            $ship->surveys_count,
            $ship->status->label(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
