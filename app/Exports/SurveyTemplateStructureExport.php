<?php

namespace App\Exports;

use App\Models\SurveyTemplate;
use App\Services\SurveyTemplateService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export struktur template ke format Excel yang bisa di-import kembali.
 * Sheet pertama selalu "Petunjuk", lalu satu sheet per kategori dengan
 * visual terstruktur: merge cell per blok induk, warna per level, dan
 * indentasi. Kolom Kategori tetap ditulis (single source of truth —
 * sel merge terbaca kosong dan diisi ulang via carry-forward saat import).
 * $template null => file template kosong berisi sheet contoh.
 */
class SurveyTemplateStructureExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(protected ?SurveyTemplate $template = null) {}

    public function sheets(): array
    {
        $sheets = [$this->instructionsSheet()];
        $usedTitles = ['Petunjuk'];

        $grouped = $this->template
            ? app(SurveyTemplateService::class)->exportRowsByCategory($this->template)
            : self::exampleRowsByCategory();

        foreach ($grouped as $categoryLabel => $rows) {
            $sheets[] = $this->dataSheet(
                $rows,
                self::sheetTitle($categoryLabel, $usedTitles)
            );
        }

        return $sheets;
    }

    protected function dataSheet(array $rows, string $title): object
    {
        return new class(SurveyTemplateService::structureHeadings(), $rows, $title) implements FromArray, WithEvents, WithHeadings, WithTitle
        {
            public function __construct(
                protected array $headings,
                protected array $rows,
                protected string $sheetTitle
            ) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }

            public function title(): string
            {
                return $this->sheetTitle;
            }

            public function registerEvents(): array
            {
                return [
                    AfterSheet::class => fn (AfterSheet $e) => SurveyTemplateStructureExport::styleCategorySheet($e->sheet->getDelegate(), $this->rows),
                ];
            }
        };
    }

    protected function instructionsSheet(): object
    {
        return new class(self::instructions()) implements FromArray, ShouldAutoSize, WithStyles, WithTitle
        {
            public function __construct(protected array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function title(): string
            {
                return 'Petunjuk';
            }

            public function styles(Worksheet $sheet)
            {
                return [1 => ['font' => ['bold' => true, 'size' => 14]]];
            }
        };
    }

    /**
     * Styling visual sheet kategori: header gelap, merge blok induk,
     * warna berbeda per level, indentasi, border, dan freeze pane.
     */
    public static function styleCategorySheet(Worksheet $sheet, array $rows): void
    {
        $last = count($rows) + 1;
        if ($last < 2) {
            return;
        }

        $sheet->freezePane('A2');

        // Header
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Lebar kolom (autosize tidak akurat untuk sel merge)
        foreach (['A' => 26, 'B' => 28, 'C' => 26, 'D' => 55, 'E' => 13, 'F' => 13, 'G' => 13] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Border + alignment dasar semua sel data
        $sheet->getStyle("A2:G{$last}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("E2:G{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D2:D{$last}")->getAlignment()->setWrapText(true)->setIndent(1);

        // Level induk: warna berbeda + merge blok nilai yang sama
        $sheet->mergeCells("A2:A{$last}");
        $sheet->getStyle("A2:A{$last}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E75B6']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        self::mergeColumnRuns($sheet, $rows, 1, 'B', ['fill' => 'BDD7EE', 'bold' => true]);
        self::mergeColumnRuns($sheet, $rows, 2, 'C', ['fill' => 'DDEBF7', 'bold' => false]);

        // Dropdown di baris data + buffer baris kosong agar baris baru tetap tervalidasi
        self::addListValidation($sheet, 'E', $last, 'score,inventory', 'Tipe Item',
            'score = penilaian C/V/F/M; inventory = qty + spesifikasi.');
        self::addListValidation($sheet, 'G', $last, 'Ya,Tidak', 'Field Tanggal',
            'Pilih "Ya" bila item menampilkan tanggal issued/expired.');
    }

    /**
     * Pasang dropdown list pada satu kolom (baris data + buffer untuk baris baru).
     * Catatan: setShowDropDown(true) menulis showDropDown="0" di XML —
     * nilai "1" justru menyembunyikan panah dropdown (quirky OOXML).
     */
    protected static function addListValidation(Worksheet $sheet, string $col, int $lastDataRow, string $list, string $title, string $prompt): void
    {
        $last = $lastDataRow + 50;
        for ($r = 2; $r <= $last; $r++) {
            $sheet->getCell("{$col}{$r}")->getDataValidation()
                ->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle("{$title} tidak valid")
                ->setError("Pilih salah satu nilai dari dropdown: {$list}.")
                ->setPromptTitle($title)
                ->setPrompt($prompt)
                ->setFormula1('"'.$list.'"');
        }
    }

    /**
     * Merge rentang baris berurutan bernilai sama pada satu kolom,
     * lalu beri warna levelnya.
     */
    protected static function mergeColumnRuns(Worksheet $sheet, array $rows, int $colIdx, string $col, array $style): void
    {
        $total = count($rows);
        $sheet->getStyle("{$col}2:{$col}".($total + 1))->applyFromArray([
            'font' => ['bold' => $style['bold']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $style['fill']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true, 'indent' => $colIdx],
        ]);

        $start = 0;
        for ($i = 1; $i <= $total; $i++) {
            $same = $i < $total
                && mb_strtolower(trim((string) ($rows[$i][$colIdx] ?? ''))) === mb_strtolower(trim((string) ($rows[$start][$colIdx] ?? '')));
            if ($same) {
                continue;
            }
            if ($i - $start > 1) {
                $sheet->mergeCells("{$col}".($start + 2).":{$col}".($i + 1));
            }
            $start = $i;
        }
    }

    /**
     * Judul sheet aman Excel: tanpa karakter : \ / ? * [ ] dan maks 31
     * karakter; dibuat unik dengan suffix bila hasil sanitasi bertabrakan.
     */
    protected static function sheetTitle(string $name, array &$used): string
    {
        $title = trim(preg_replace('/[:\\\\\/\?\*\[\]]/', '-', $name)) ?: 'Kategori';
        $title = mb_substr($title, 0, 31);

        $base = $title;
        $i = 2;
        while (in_array($title, $used, true)) {
            $title = mb_substr($base, 0, 28).'-'.$i++;
        }
        $used[] = $title;

        return $title;
    }

    protected static function exampleRowsByCategory(): array
    {
        return [
            'I. UMUM' => [
                ['I. UMUM', 'Identitas Kapal', 'Dokumen', 'Sertifikat Klasifikasi', 'score', 'C,V', 'Ya'],
                ['I. UMUM', 'Identitas Kapal', 'Dokumen', 'Sertifikat Statutoria', 'score', 'C,V', 'Ya'],
            ],
            'II. KONDISI' => [
                ['II. KONDISI', 'Lambung', 'Konstruksi', 'Plat lambung sisi kiri', 'score', 'C,V,F', 'Tidak'],
                ['II. KONDISI', 'Lambung', 'Peralatan', 'Jangkar & rantai jangkar', 'inventory', '', 'Tidak'],
            ],
        ];
    }

    protected static function instructions(): array
    {
        return [
            ['PETUNJUK PENGISIAN STRUKTUR TEMPLATE'],
            [''],
            ['1. Setiap kategori memiliki sheet (tab) sendiri — satu baris = satu item survey.'],
            ['2. Kolom induk (Kategori/Sub Kategori/Grup Item) ditampilkan sebagai blok merged agar mudah dibaca. Sel kosong pada kolom induk akan mewarisi nilai di atasnya saat diimport.'],
            ['3. Urutan baris menentukan urutan (order_num) kategori, sub kategori, grup, dan item.'],
            ['4. Baris dengan Kategori/Sub Kategori/Grup Item yang sama digabung ke satu node yang sama (tidak peka huruf besar/kecil).'],
            ['5. Kolom wajib: Nama Item dan Tipe Item (kolom induk boleh dikosongkan bila melanjutkan nilai di atasnya).'],
            ['6. Tipe Item: pilih dari dropdown — "score" (penilaian C/V/F/M) atau "inventory" (qty + spesifikasi).'],
            ['7. Label Skor: khusus tipe score — daftar label dipisah koma, contoh "C,V" atau "C,V,F,M". Kosongkan untuk default C,V.'],
            ['8. Field Tanggal: pilih dari dropdown — "Ya" bila item menampilkan tanggal issued/expired, selain itu "Tidak".'],
            ['9. Baris kosong akan diabaikan; baris pertama (header) tidak boleh diubah. Sheet ini ("Petunjuk") tidak ikut diimport.'],
            [''],
            ['CONTOH: lihat baris pada sheet kategori.'],
        ];
    }
}
