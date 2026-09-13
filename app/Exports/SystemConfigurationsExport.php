<?php

namespace App\Exports;

use App\Models\SystemConfiguration;
use App\Traits\HasDynamicLike;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SystemConfigurationsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable, HasDynamicLike;

    protected ?string $search;

    protected ?string $isActive;

    public function __construct(?string $search = null, ?string $isActive = null)
    {
        $this->search = $search;
        $this->isActive = $isActive;
    }

    public function query()
    {
        $query = SystemConfiguration::query();

        if ($this->search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($operator) {
                $q->where('key', $operator, "%{$this->search}%")
                    ->orWhere('description', $operator, "%{$this->search}%")
                    ->orWhere('value', $operator, "%{$this->search}%");
            });
        }

        if ($this->isActive !== null && $this->isActive !== '') {
            $query->where('is_active', $this->isActive === '1');
        }

        return $query->orderBy('category')->orderBy('key');
    }

    public function headings(): array
    {
        return [
            'No',
            'Key',
            'Kategori',
            'Value',
            'Tipe Data',
            'Deskripsi',
            'Dapat Diedit',
            'Status',
        ];
    }

    public function map($config): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $config->key,
            $config->category->label(),
            $config->value,
            $config->data_type->label(),
            $config->description ?? '-',
            $config->is_editable ? 'Ya' : 'Tidak',
            $config->is_active ? 'Aktif' : 'Nonaktif',
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
