<?php

namespace App\Exports;

use App\Models\Company;
use App\Traits\HasDynamicLike;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompaniesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
        $query = Company::withCount('users');

        if ($this->search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($operator) {
                $q->where('code', $operator, "%{$this->search}%")
                    ->orWhere('name', $operator, "%{$this->search}%")
                    ->orWhere('email', $operator, "%{$this->search}%")
                    ->orWhere('phone', $operator, "%{$this->search}%")
                    ->orWhere('pic_name', $operator, "%{$this->search}%");
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
            'No',
            'Kode',
            'Nama Perusahaan',
            'Email',
            'Telepon',
            'Alamat',
            'Nama PIC',
            'Email PIC',
            'Telepon PIC',
            'Jumlah User',
            'Status',
            'Tanggal Dibuat',
        ];
    }

    public function map($company): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $company->code,
            $company->name,
            $company->email ?? '-',
            $company->phone ?? '-',
            $company->address ?? '-',
            $company->pic_name ?? '-',
            $company->pic_email ?? '-',
            $company->pic_phone ?? '-',
            $company->users_count,
            $company->status->label(),
            $company->created_at->format('d/m/Y H:i'),
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
