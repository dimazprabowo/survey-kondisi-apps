<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Spatie\Permission\Models\Role;

class RolesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function query()
    {
        return Role::with('permissions');
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Role',
            'Jumlah Permission',
            'Permissions',
            'Jumlah User',
        ];
    }

    public function map($role): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            ucfirst($role->name),
            $role->permissions->count(),
            $role->permissions->pluck('name')->map(fn ($p) => str_replace('_', ' ', ucfirst($p)))->implode(', '),
            $role->users()->count(),
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
