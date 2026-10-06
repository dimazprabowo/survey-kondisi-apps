<?php

namespace App\Services;

use App\Enums\SurveyItemType;
use App\Models\SurveyReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Bangun dokumen laporan survey (DOCX) dari template ber-placeholder.
 *
 * Dua lapis pengisian:
 *  1. TemplateProcessor  -> placeholder skalar ${field} + gambar ${benchmark_chart}
 *  2. Injeksi XML        -> blok dinamis (tabel BAB III, breakdown CAP, dll.)
 *     menggantikan paragraf marker ${blok} dengan XML WordprocessingML
 *     yang dibangun programatis.
 *
 * Layout (cover, header/footer, TOC, tabel kriteria CAP, dll.) tetap utuh
 * dari template — hanya konten dinamis yang diisi/diinjeksi.
 */
class SurveyReportDocxBuilder
{
    public const TEMPLATE_PATH = 'app/private/templates/report-charter-condition.docx';

    public function __construct(
        protected SurveyService $surveyService,
        protected SurveyReportService $reportService,
    ) {}

    /**
     * Generate DOCX ke file temporary; return absolute path.
     *
     * @throws \RuntimeException
     */
    public function build(SurveyReport $report): string
    {
        $templatePath = storage_path(self::TEMPLATE_PATH);
        if (! is_file($templatePath)) {
            throw new \RuntimeException('Template laporan tidak ditemukan: '.$templatePath);
        }

        $data = $this->reportService->buildReportData($report);

        $tp = new TemplateProcessor($templatePath);
        $this->fillScalars($tp, $data);
        $this->fillBenchmarkChart($tp, $data);

        $tmpFile = tempnam(sys_get_temp_dir(), 'report_').'.docx';
        $tp->saveAs($tmpFile);

        $this->injectBlocks($tmpFile, $data);

        return $tmpFile;
    }

    // =================================================================
    //  Layer 1 — placeholder skalar via TemplateProcessor
    // =================================================================

    protected function fillScalars(TemplateProcessor $tp, array $data): void
    {
        $report = $data['report'];
        $ship = $data['ship'];

        $tp->setValue('report_no', $report->report_number ?? '-');
        $tp->setValue('report_title', $report->report_title ?? '');
        $tp->setValue('ship_name', $ship?->name ?? '-');
        $tp->setValue('ship_type', $ship?->ship_type ?? '-');

        // Lembar pengesahan — ringkasan dimensi kapal + tanda tangan
        $tp->setValue('loa', $ship?->loa ?? '-');
        $tp->setValue('breadth', $ship?->breadth ?? '-');
        $tp->setValue('draft', $ship?->draft ?? '-');
        $tp->setValue('approval_place_date', $this->approvalPlaceDate($report));
        $tp->setValue('approver_name', $report->approver_name ?? '-');
        $tp->setValue('inspector_1', $report->inspector_1 ?? '-');
        $tp->setValue('inspector_2', $report->inspector_2 ?? '-');

        // BAB II — ship particulars
        $tp->setValue('sp_name', $ship?->name ?? '-');
        $tp->setValue('sp_type', $ship?->ship_type ?? '-');
        $tp->setValue('sp_imo', $ship?->imo_number ?? '-');
        $tp->setValue('sp_call_sign', $ship?->call_sign ?? '-');
        $tp->setValue('sp_gt_nt', trim(($ship?->gross_tonnage ?? '-').'/'.($ship?->net_tonnage ?? '-'), '/'));
        $tp->setValue('sp_year_built', (string) ($ship?->year_built ?? '-'));
        $tp->setValue('sp_builder', $ship?->builder ?? '-');
        $tp->setValue('sp_port_registry', $ship?->port_of_registry ?? '-');
        $tp->setValue('sp_hull_material', $ship?->hull_material ?? '-');
        $tp->setValue('sp_loa', $ship?->loa ?? '-');
        $tp->setValue('sp_lpp', $ship?->lpp ?? '-');
        $tp->setValue('sp_breadth', $ship?->breadth ?? '-');
        $tp->setValue('sp_depth', $ship?->depth ?? '-');
        $tp->setValue('sp_draft', $ship?->draft ?? '-');
        $tp->setValue('sp_dwt', $ship?->dwt ?? '-');
        $tp->setValue('sp_class', $ship?->class_name ?? '-');
        $tp->setValue('sp_class_notations', $ship?->class_notations ?? '-');
        $tp->setValue('sp_main_engine', $ship?->main_engine ?? '-');
        $tp->setValue('sp_me_power', $ship?->main_engine_power ?? '-');
        $tp->setValue('sp_aux_engine', $ship?->aux_engine ?? '-');
        $tp->setValue('sp_aux_power', $ship?->aux_engine_power ?? '-');
    }

    protected function approvalPlaceDate(SurveyReport $report): string
    {
        $place = $report->approval_place ?: '';
        $date = $report->approval_date?->translatedFormat('d F Y') ?? '';

        return trim($place.($place && $date ? ', ' : '').$date) ?: '-';
    }

    protected function fillBenchmarkChart(TemplateProcessor $tp, array $data): void
    {
        $chartPath = $this->renderBenchmarkChart($data['benchmark']);
        if ($chartPath) {
            $tp->setImageValue('benchmark_chart', [
                'path' => $chartPath,
                'width' => 480,
                'height' => 300,
                'ratio' => false,
            ]);
        }
    }

    // =================================================================
    //  Layer 2 — injeksi blok XML ke marker ${...}
    // =================================================================

    protected function injectBlocks(string $docxPath, array $data): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException('Gagal membuka dokumen hasil generate.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $data['documentationRelationships'] = $this->injectDocumentationImages($zip, $data);
        $blocks = $this->buildBlocks($data);

        foreach ($blocks as $marker => $blockXml) {
            $xml = $this->replaceMarkerParagraph($xml, $marker, $blockXml);
        }

        $this->validateXml($xml, 'word/document.xml');
        $zip->addFromString('word/document.xml', $xml);
        $this->enableFieldUpdates($zip);
        $zip->close();
    }

    protected function validateXml(string $xml, string $part): void
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $document = new \DOMDocument;
        $loaded = $document->loadXML($xml);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $errors !== []) {
            $details = implode('; ', array_map(
                fn (\LibXMLError $error) => trim($error->message).' (baris '.$error->line.')',
                $errors
            ));

            throw new \RuntimeException('XML DOCX tidak valid pada '.$part.($details ? ': '.$details : '.'));
        }
    }

    protected function enableFieldUpdates(\ZipArchive $zip): void
    {
        $settingsPath = 'word/settings.xml';
        $settings = $zip->getFromName($settingsPath);
        if ($settings === false || str_contains($settings, '<w:updateFields')) {
            return;
        }

        $settings = str_replace('</w:settings>', '<w:updateFields w:val="true"/></w:settings>', $settings);
        $zip->addFromString($settingsPath, $settings);
    }

    protected function injectDocumentationImages(\ZipArchive $zip, array $data): array
    {
        $relationshipsPath = 'word/_rels/document.xml.rels';
        $relationships = $zip->getFromName($relationshipsPath);
        if ($relationships === false) {
            return [];
        }

        preg_match_all('/Id="rId(\d+)"/', $relationships, $matches);
        $nextId = $matches[1] ? max(array_map('intval', $matches[1])) + 1 : 1;
        $injected = [];

        foreach ($data['documentations'] as $categoryId => $documentation) {
            if (! $documentation->file_path || ! Storage::disk(file_disk())->exists($documentation->file_path)) {
                continue;
            }

            $relationshipId = 'rId'.$nextId++;
            $mediaName = 'report-documentation-'.$documentation->id.'.jpg';
            $zip->addFromString('word/media/'.$mediaName, Storage::disk(file_disk())->get($documentation->file_path));
            $relationship = '<Relationship Id="'.$relationshipId.'" '
                .'Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" '
                .'Target="media/'.$mediaName.'"/>';
            $relationships = str_replace('</Relationships>', $relationship.'</Relationships>', $relationships);
            $injected[(int) $categoryId] = $relationshipId;
        }

        $zip->addFromString($relationshipsPath, $relationships);

        if ($injected !== []) {
            $contentTypes = $zip->getFromName('[Content_Types].xml');
            if ($contentTypes !== false && ! str_contains($contentTypes, 'Extension="jpg"')) {
                $contentTypes = str_replace(
                    '</Types>',
                    '<Default Extension="jpg" ContentType="image/jpeg"/></Types>',
                    $contentTypes
                );
                $zip->addFromString('[Content_Types].xml', $contentTypes);
            }
        }

        return $injected;
    }

    /**
     * Ganti paragraf <w:p> yang berisi teks marker ${name} dengan XML blok.
     * Marker sengaja berada di paragraf kosong tersendiri pada template.
     */
    protected function replaceMarkerParagraph(string $xml, string $marker, string $blockXml): string
    {
        $quoted = preg_quote('${'.$marker.'}', '/');
        $pattern = '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*?'.$quoted.'(?:(?!<\/w:p>).)*?<\/w:p>/s';

        $result = preg_replace($pattern, $blockXml, $xml, 1);

        return $result ?? $xml;
    }

    /**
     * Bangun seluruh blok XML dinamis. Key = nama marker di template.
     */
    protected function buildBlocks(array $data): array
    {
        return [
            'exec_summary' => $this->buildExecSummary($data),
            'cap_breakdown' => $this->buildCapBreakdown($data),
            'temuan_table' => $this->buildFindingsTable($data),
            'bab1_standards' => $this->buildBab1Standards($data),
            'status_class_table' => $this->buildStatusClassTable($data),
            'memoranda' => $this->buildParagraphs($this->section($data, 'memoranda')),
            'bab3' => $this->buildBab3($data),
            'bab4' => $this->buildBab4($data),
        ];
    }

    // -----------------------------------------------------------------
    //  Blok: Executive Summary
    // -----------------------------------------------------------------

    protected function buildExecSummary(array $data): string
    {
        // Halaman executive summary: jarak antar baris 1.5, Garamond 12pt
        $xml = $this->buildParagraphs($this->section($data, 'executive_summary'), [
            'line' => 360, 'size' => 24, 'italicPhrases' => ['Condition Assessment Program'],
        ]);

        // Daftar kategori: bullet rata kiri (numId 11 = Symbol dot di template)
        // dalam 2 kolom tak terlihat, seperti layout section 2-kolom pada master.
        $categories = $data['categories'];
        $half = (int) ceil($categories->count() / 2);
        $bullet = fn ($cat) => $this->p($cat->label, ['numId' => 11, 'line' => 360, 'size' => 24, 'jc' => 'left']);
        $xml .= $this->tbl([50, 50], [[
            $this->tc($categories->slice(0, $half)->map($bullet)->implode(''), []),
            $this->tc($categories->slice($half)->map($bullet)->implode(''), []),
        ]], [], ['borders' => false]);

        // Kalimat pembuka tabel breakdown — mulai halaman baru seperti master;
        // "Overall CAP Rating" dan "Overall CAP Rating sebesar X,XX" bold sesuai master.
        $boldPhrases = ['Overall CAP Rating'];
        if ($data['overallCap'] !== null) {
            array_unshift($boldPhrases, 'Overall CAP Rating sebesar '.number_format($data['overallCap'], 2));
        }
        $xml .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
        $xml .= $this->p(
            'Tabel berikut menyajikan rincian Overall CAP Rating hasil survei kondisi kapal '
            .($data['ship']?->name ?? '-').', yang diperoleh dari rata-rata penilaian pada komponen pemeriksaan utama.'
            .($data['overallCap'] !== null
                ? ' Berdasarkan hasil penilaian, kapal ini memperoleh Overall CAP Rating sebesar '
                    .number_format($data['overallCap'], 2).'. Adapun rincian penilaian sebagai berikut:'
                : ''),
            ['line' => 360, 'size' => 24, 'boldPhrases' => $boldPhrases]
        );

        return $xml;
    }

    protected function buildCapBreakdown(array $data): string
    {
        $categories = $data['categories'];
        $responses = $data['responses'];
        $shipName = $data['ship']?->name ?? '-';
        $overall = $data['overallCap'];
        $half = (int) ceil($categories->count() / 2);
        $leftLines = $this->breakdownLines($categories->slice(0, $half)->values(), $responses, 0);
        $rightLines = $this->breakdownLines($categories->slice($half)->values(), $responses, $half);
        // Skema border master: tanpa tblBorders — hanya frame luar (per-cell
        // tcBorders) + garis bawah double di bawah baris kategori.
        // Header (3 baris) tetap satu tabel 9 kolom seperti master.
        $headerRows = [
            [$this->tc($this->bp('Overall CAP Rating Breakdown', ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']), ['span' => 9, 'shade' => '4472C4', 'vAlign' => 'center', 'borders' => ['top' => 'single', 'left' => 'single', 'right' => 'single']])],
            [
                $this->tc($this->bp('Overall CAP Rating Condition '.$shipName, ['bold' => true]), ['span' => 7, 'vAlign' => 'center', 'borders' => ['left' => 'single']]),
                $this->tc($this->bp(':', ['bold' => true, 'jc' => 'center']), ['vAlign' => 'center']),
                $this->tc($this->bp($overall !== null ? number_format($overall, 2) : '-', ['bold' => true, 'jc' => 'right']), ['vAlign' => 'center', 'borders' => ['right' => 'single']]),
            ],
            [$this->tc($this->bp(''), ['span' => 9, 'shade' => '888888', 'borders' => ['left' => 'single', 'right' => 'single']])],
        ];
        $xml = $this->tbl([4, 30, 3, 11, 4, 4, 30, 3, 11], $headerRows, [0], [
            'borders' => false,
            'rowHeights' => [0 => 460, 1 => 460, 2 => 300],
        ]);

        // Dua kolom sebagai tabel independen (nested): tinggi baris tiap kolom
        // tidak saling memengaruhi — menghindari jarak palsu antar sub-kategori
        // saat salah satu kolom lebih panjang. Frame luar digambar di sel wrapper.
        // (9638*0.48 - margin sel ~216) => 4400 twips untuk tabel nested.
        $columnTable = fn ($lines) => $this->tbl(
            [4, 30, 3, 11],
            array_map(fn ($line) => $this->breakdownCells($line), $lines),
            [],
            ['borders' => false, 'totalWidth' => 4400]
        ).$this->bp('');

        return $xml.$this->tbl([48, 4, 48], [[
            $this->tc($columnTable($leftLines), ['borders' => ['left' => 'single', 'bottom' => 'single']]),
            $this->tc($this->bp(''), ['borders' => ['bottom' => 'single']]),
            $this->tc($columnTable($rightLines), ['borders' => ['right' => 'single', 'bottom' => 'single']]),
        ]], [], ['borders' => false]);
    }

    protected function breakdownLines(Collection $categories, Collection $responses, int $startIndex): array
    {
        $lines = [];
        foreach ($categories as $index => $category) {
            $categoryAvg = $this->surveyService->categoryAvg($category, $responses);
            $lines[] = [
                'type' => 'category',
                'label' => ($startIndex + $index + 1).'. '.$category->label,
                'value' => $categoryAvg !== null ? number_format($categoryAvg, 2) : '-',
                // Spasi atas untuk kategori kedua dst per kolom (mis. "2. RAMP")
                // agar tidak mepet sub-kategori sebelumnya.
                'spaced' => $index > 0,
            ];
            foreach ($category->subCategories as $subCategory) {
                $subCategoryAvg = $this->surveyService->subCategoryAvg($subCategory, $responses);
                $lines[] = [
                    'type' => 'sub_category',
                    'label' => $subCategory->name,
                    'value' => $subCategoryAvg !== null ? number_format($subCategoryAvg, 2) : '-',
                ];
            }
        }

        return $lines;
    }

    /**
     * Sel untuk satu baris breakdown di dalam kolom independen. Border per
     * master: hanya garis bawah double di baris kategori — frame luar
     * digambar oleh sel wrapper di buildCapBreakdown().
     */
    protected function breakdownCells(array $line): array
    {
        $opt = fn (string $bottom = 'nil') => $bottom === 'nil' ? [] : ['borders' => ['bottom' => $bottom]];

        if ($line['type'] === 'category') {
            $before = ! empty($line['spaced']) ? 240 : 0;

            return [
                $this->tc($this->bp($line['label'], ['bold' => true, 'jc' => 'left', 'spacingBefore' => $before]), ['span' => 2] + $opt('double')),
                $this->tc($this->bp(':', ['bold' => true, 'jc' => 'center', 'spacingBefore' => $before]), $opt('double')),
                $this->tc($this->bp($line['value'], ['jc' => 'right', 'spacingBefore' => $before]), $opt('double')),
            ];
        }

        return [
            $this->tc($this->bp('-', ['jc' => 'center']), []),
            $this->tc($this->bp($line['label'], ['jc' => 'left']), []),
            $this->tc($this->bp(''), []),
            $this->tc($this->bp($line['value'], ['jc' => 'right']), []),
        ];
    }

    protected function buildFindingsTable(array $data): string
    {
        $rows = [
            [
                $this->tc($this->p('No.', ['bold' => true, 'jc' => 'center']), ['shade' => 'D9E2F3']),
                $this->tc($this->p('Item Pemeriksaan', ['bold' => true, 'jc' => 'center']), ['shade' => 'D9E2F3']),
                $this->tc($this->p('Keterangan', ['bold' => true, 'jc' => 'center']), ['shade' => 'D9E2F3']),
                $this->tc($this->p('Dokumentasi', ['bold' => true, 'jc' => 'center']), ['shade' => 'D9E2F3']),
            ],
        ];

        $i = 0;
        foreach ($data['categories'] as $cat) {
            $i++;
            $content = $this->section($data, 'finding_'.$cat->id);
            $relationshipId = $data['documentationRelationships'][$cat->id] ?? null;
            $cropData = ($data['documentations'][$cat->id] ?? null)?->crop_data;
            $rows[] = [
                $this->tc($this->p((string) $i, ['jc' => 'center']), []),
                $this->tc($this->p($cat->label, ['jc' => 'left']), []),
                $this->tc($this->buildParagraphs($content ?: '-'), []),
                $this->tc($relationshipId ? $this->imageDrawing($relationshipId, $cat->id, $cropData) : $this->p(''), ['vAlign' => 'center']),
            ];
        }

        return $this->tbl([6, 16, 43, 35], $rows, [0]);
    }

    // -----------------------------------------------------------------
    //  Blok: BAB I & BAB II
    // -----------------------------------------------------------------

    /**
     * Daftar standar CAP BAB I — satu item per baris pada section
     * 'cap_standards', dirender sebagai list bernomor (ListParagraph +
     * numId 10 + spacing before=0) mengikuti gaya paragraf master.
     * Prefix nomor manual ("1. ", "2) ") di-strip agar tidak dobel dengan
     * auto-numbering Word.
     */
    protected function buildBab1Standards(array $data): string
    {
        $content = preg_replace('/^\s*\d+[.)]\s*/m', '', $this->section($data, 'cap_standards'));

        return $this->buildParagraphs($content, [
            'style' => 'ListParagraph',
            'numId' => 10,
            'rawSpacing' => '<w:spacing w:before="0"/>',
        ]);
    }

    protected function buildStatusClassTable(array $data): string
    {
        $rows = [
            [
                $this->tc($this->p('CERTIFICATE TYPE', ['bold' => true, 'jc' => 'center']), ['shade' => 'D9E2F3']),
                $this->tc($this->p('LAST', ['bold' => true, 'jc' => 'center']), ['shade' => 'D9E2F3']),
                $this->tc($this->p('NEXT 1', ['bold' => true, 'jc' => 'center']), ['shade' => 'D9E2F3']),
                $this->tc($this->p('NEXT 2', ['bold' => true, 'jc' => 'center']), ['shade' => 'D9E2F3']),
                $this->tc($this->p('POSTPONE', ['bold' => true, 'jc' => 'center']), ['shade' => 'D9E2F3']),
            ],
        ];

        $certs = $data['ship']?->certificates ?? collect();
        if ($certs->isEmpty()) {
            $rows[] = [
                $this->tc($this->p('Belum ada data sertifikat status class', ['jc' => 'center', 'italic' => true, 'color' => '595959']), ['span' => 5]),
            ];
        }

        foreach ($certs as $cert) {
            $rows[] = [
                $this->tc($this->p($cert->certificate_type), []),
                $this->tc($this->p($cert->last_date?->format('d/m/Y') ?? '', ['jc' => 'center']), []),
                $this->tc($this->p($cert->next_1_date?->format('d/m/Y') ?? '', ['jc' => 'center']), []),
                $this->tc($this->p($cert->next_2_date?->format('d/m/Y') ?? '', ['jc' => 'center']), []),
                $this->tc($this->p($cert->postpone_date?->format('d/m/Y') ?? '', ['jc' => 'center']), []),
            ];
        }

        return $this->tbl([36, 16, 16, 16, 16], $rows);
    }

    // -----------------------------------------------------------------
    //  Blok: BAB III — tabel pemeriksaan per kategori
    // -----------------------------------------------------------------

    protected function buildBab3(array $data): string
    {
        $responses = $data['responses'];
        $groupNotes = $data['groupNotes'];
        $xml = '';

        // Master BAB III memakai font 11pt (w:sz=22) di seluruh sel tabel.
        $tp = fn (string $text, array $opts = []): string => $this->p($text, $opts + ['size' => 24]);

        foreach ($data['categories'] as $catIndex => $cat) {
            $maxLabels = 3;
            $hasLongLabels = $cat->subCategories
                ->flatMap(fn ($sc) => $sc->itemGroups)
                ->flatMap(fn ($group) => $group->items)
                ->flatMap(fn ($item) => $item->score_labels ?? [])
                ->contains(fn ($label) => mb_strlen((string) $label) > 3);
            $widths = $hasLongLabels
                ? [7.75, 8.77, 3.76, 44.15, 8.50, 8.50, 9.50, 8.50]
                : [7.75, 8.77, 3.76, 41.15, 6.38, 6.67, 13.26, 11.69];
            $columnCount = count($widths);
            $catAvg = $this->surveyService->categoryAvg($cat, $responses);
            $rows = [[
                $this->tc($tp('No.', ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']), ['shade' => '4285F4', 'noWrap' => true]),
                $this->tc($tp('Item', ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']), ['span' => $columnCount - 2, 'shade' => '4285F4', 'noWrap' => true]),
                $this->tc($tp('Overall CAP Rating', ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']), ['shade' => '4285F4']),
            ], [
                $this->tc($tp(to_roman($catIndex + 1), ['bold' => true, 'jc' => 'center']), ['noWrap' => true]),
                $this->tc($tp(strtoupper($cat->label).' OVERALL CAP RATING', ['bold' => true]), ['span' => $columnCount - 2]),
                $this->tc($tp($catAvg !== null ? number_format($catAvg, 2) : '-', ['bold' => true, 'jc' => 'center']), ['noWrap' => true]),
            ]];

            $grayRow = [
                $this->tc($tp(''), ['span' => 2, 'shade' => '808080']),
                $this->tc($tp(''), ['span' => 2, 'shade' => '808080']),
                $this->tc($tp(''), ['span' => 2, 'shade' => '808080']),
                $this->tc($tp(''), ['span' => 2, 'shade' => '808080']),
            ];
            $grayRowIndexes = [];

            foreach ($cat->subCategories as $scIndex => $sc) {
                $grayRowIndexes[] = count($rows);
                $rows[] = $grayRow;
                $scAvg = $this->surveyService->subCategoryAvg($sc, $responses);
                $rows[] = [
                    $this->tc($tp((string) ($scIndex + 1), ['bold' => true, 'jc' => 'center']), ['noWrap' => true]),
                    $this->tc($tp($sc->name, ['bold' => true]), ['span' => $columnCount - 2]),
                    $this->tc($tp($scAvg !== null ? number_format($scAvg, 2) : '-', ['jc' => 'center']), ['noWrap' => true]),
                ];

                foreach ($sc->itemGroups as $igIndex => $group) {
                    $isInventory = $this->isInventoryGroup($group);
                    $labels = $isInventory ? ['Qty', 'Specification'] : array_slice($this->groupScoreLabels($group), 0, $maxLabels);
                    $unusedSlots = $maxLabels - count($labels);
                    $groupAvg = $this->surveyService->itemGroupAvg($group, $responses);
                    $rows[] = [
                        $this->tc($tp(''), []),
                        $this->tc($tp(($scIndex + 1).'.'.($igIndex + 1), ['bold' => true, 'jc' => 'center']), ['noWrap' => true]),
                        $this->tc($tp(''), []),
                        $this->tc($tp(strtoupper($group->name), ['bold' => true]), []),
                        $this->tc($tp(''), ['span' => 2]),
                        $this->tc($tp(''), []),
                        $this->tc($tp($groupAvg !== null ? number_format($groupAvg, 2) : '-', ['jc' => 'center']), ['noWrap' => true]),
                    ];

                    $labelCells = [
                        $this->tc($tp(''), ['span' => 2]),
                        $this->tc($tp(''), ['span' => 2]),
                    ];
                    if ($isInventory) {
                        $labelCells[] = $this->tc($tp($labels[0], ['bold' => true, 'jc' => 'center']), ['noWrap' => true]);
                        $labelCells[] = $this->tc($tp($labels[1] ?? '', ['bold' => true, 'jc' => 'center']), ['span' => $maxLabels, 'noWrap' => true]);
                    } else {
                        foreach ($labels as $label) {
                            $labelCells[] = $this->tc($tp($label, ['bold' => true, 'jc' => 'center']), ['noWrap' => true]);
                        }
                        $labelCells[] = $this->tc($tp('Avg', ['bold' => true, 'jc' => 'center']), ['noWrap' => true]);
                        for ($unused = 0; $unused < $unusedSlots; $unused++) {
                            $labelCells[] = $this->tc($tp(''), []);
                        }
                    }
                    $rows[] = $labelCells;

                    foreach ($group->items as $itemIndex => $item) {
                        $response = $responses->get($item->id);
                        $cells = [
                            $this->tc($tp(''), ['span' => 2, 'vAlign' => 'top']),
                            $this->tc($tp(to_letter($itemIndex + 1), ['jc' => 'center']), ['noWrap' => true, 'vAlign' => 'top']),
                            $this->tc($tp($item->name).$this->dateLines($item, $response), ['vAlign' => 'top']),
                        ];
                        if ($isInventory) {
                            $qty = $response?->qty;
                            $specification = $response?->specification;
                            $cells[] = $this->tc($tp($qty !== null && $qty !== '' ? (string) $qty : '-', ['jc' => 'center']), ['noWrap' => true, 'vAlign' => 'top']);
                            $cells[] = $this->tc($tp($specification !== null && $specification !== '' ? (string) $specification : '-', ['jc' => 'center']), ['span' => $maxLabels, 'vAlign' => 'top']);
                        } else {
                            foreach ($labels as $label) {
                                $value = $response?->scores[$label] ?? null;
                                $cells[] = $this->tc($tp($value !== null && $value !== '' ? (string) $value : '-', ['jc' => 'center']), ['noWrap' => true, 'vAlign' => 'top']);
                            }
                            $cells[] = $this->tc($tp(
                                $response?->avg_score !== null ? number_format((float) $response->avg_score, 2) : '-',
                                ['jc' => 'center']
                            ), ['noWrap' => true, 'vAlign' => 'top']);
                            for ($unused = 0; $unused < $unusedSlots; $unused++) {
                                $cells[] = $this->tc($tp(''), ['vAlign' => 'top']);
                            }
                        }
                        $rows[] = $cells;
                    }

                    $remainingSpan = $columnCount - 2;
                    $rows[] = [
                        $this->tc($tp(''), []),
                        $this->tc($tp('Note:', ['bold' => true]), []),
                        $this->tc($tp(''), ['span' => $remainingSpan]),
                    ];
                    $notes = $groupNotes->get($group->id)?->values()->all() ?? [];
                    while (count($notes) < 2) {
                        $notes[] = null;
                    }
                    foreach ($notes as $note) {
                        $rows[] = [
                            $this->tc($tp(''), []),
                            $this->tc($tp('-', ['jc' => 'right']), ['noWrap' => true]),
                            $this->tc($tp($note?->note ?? ''), ['span' => $remainingSpan]),
                        ];
                    }
                    $rows[] = [
                        $this->tc($tp(''), ['span' => 2]),
                        $this->tc($tp(''), ['span' => 2]),
                        $this->tc($tp(''), []),
                        $this->tc($tp(''), []),
                        $this->tc($tp(''), []),
                        $this->tc($tp(''), []),
                    ];
                }
            }

            // Kategori ke-2 dst mulai di halaman baru; kategori pertama tetap
            // menyambung heading "BAB III." (yang sudah pageBreakBefore sendiri).
            $xml .= $tp(strtoupper($cat->label), ['style' => 'Heading2', 'line' => 360, 'pageBreakBefore' => $catIndex > 0]);
            $specTables = $this->bab3SpecTables($cat, 'before');
            $xml .= $specTables;
            $legend = $this->scoreLegend($cat);
            // Master memberi page break setelah tabel spesifikasi, sehingga
            // legend "V= Visual F= Function" + tabel CAP mulai di halaman baru.
            if ($legend !== '') {
                $xml .= $tp($legend, ['bold' => true, 'jc' => 'center', 'line' => 360, 'pageBreakBefore' => $specTables !== '']);
            } elseif ($specTables !== '') {
                $xml .= $tp('', ['pageBreakBefore' => true]);
            }
            $xml .= $this->tbl($widths, $rows, [0], [
                'rowHeights' => [0 => 660, 1 => 405] + array_fill_keys($grayRowIndexes, 300),
                'defaultRowHeight' => 315,
                'frameOnly' => true,
            ]).$tp('');
            $xml .= $this->bab3SpecTables($cat, 'after');
        }

        return $xml;
    }

    protected function isInventoryGroup(object $group): bool
    {
        return $group->items->every(fn ($item) => $item->item_type === SurveyItemType::Inventory);
    }

    /**
     * Tabel isian statis BAB III yang ada di master tapi tidak berasal dari
     * struktur survey — dicocokkan via keyword pada label kategori. Sel data
     * sengaja kosong karena diisi manual di Word. $position: 'before' =
     * sebelum legend + tabel CAP, 'after' = setelah tabel CAP.
     */
    protected function bab3SpecTables(object $cat, string $position): string
    {
        $label = strtoupper((string) $cat->label);
        $key = match (true) {
            str_contains($label, 'ENGINE') => 'engine',
            str_contains($label, 'BRIDGE') => 'bridge',
            str_contains($label, 'SAFETY') => 'safety',
            default => null,
        };

        return match ([$key, $position]) {
            ['engine', 'before'] => $this->engineGeneralSpecTable().$this->engineFocTable(),
            ['engine', 'after'] => $this->enginePumpsTable(),
            ['bridge', 'before'] => $this->navigationSpecTable(),
            ['safety', 'before'] => $this->safetyEquipmentTable(),
            default => '',
        };
    }

    /**
     * Engine Room — "General Specification": spesifikasi ME P/S + AE 1-3.
     * Master: header biru 5B9BD5, font 11pt.
     */
    protected function engineGeneralSpecTable(): string
    {
        $tp = fn (string $text, array $opts = []): string => $this->p($text, $opts + ['size' => 22]);
        $hdr = fn (string $text): string => $this->tc(
            $tp($text, ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']),
            ['shade' => '5B9BD5', 'noWrap' => true]
        );

        $rows = [
            [$this->tc($tp('General Specification', ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']), ['span' => 7, 'shade' => '5B9BD5'])],
            [$hdr('No'), $hdr('Specification'), $hdr('Main Engine (P)'), $hdr('Main Engine (S)'), $hdr('Auxiliary Engine 1'), $hdr('Auxiliary Engine 2'), $hdr('Auxiliary Engine 3')],
        ];

        $specs = ['Merk', 'Manufacture', 'Type', 'Model', 'Serial Number', 'Speed (Rpm) Max', 'Speed (Rpm) Applicable', 'Maximum Continuous Rating (MCR)', 'Number of Cylinder', 'Year Build', 'Total Running Hours'];
        foreach ($specs as $i => $spec) {
            $rows[] = [
                $this->tc($tp((string) ($i + 1), ['jc' => 'center']), ['noWrap' => true]),
                $this->tc($tp($spec), []),
                $this->tc($tp(''), []),
                $this->tc($tp(''), []),
                $this->tc($tp(''), []),
                $this->tc($tp(''), []),
                $this->tc($tp(''), []),
            ];
        }

        return $this->tbl([5.41, 14.84, 15.88, 15.98, 15.98, 15.98, 15.94], $rows, [0, 1]).$this->p('');
    }

    /**
     * Engine Room — "Fuel Oil Consumption (FOC)": Speed/FOC per kondisi
     * Idle/Slow/Service/Full untuk ME PS/SB + AE 1-3. Font 11pt.
     */
    protected function engineFocTable(): string
    {
        $tp = fn (string $text, array $opts = []): string => $this->p($text, $opts + ['size' => 22]);
        $hdr = fn (string $text, array $tcOpts = []): string => $this->tc(
            $tp($text, ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']),
            $tcOpts + ['shade' => '5B9BD5', 'noWrap' => true]
        );

        $rows = [
            [$this->tc($tp('Fuel Oil Consumption (FOC)', ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']), ['span' => 10, 'shade' => '5B9BD5'])],
            [
                $hdr('No', ['vMerge' => 'restart']),
                $hdr('Item', ['vMerge' => 'restart']),
                $hdr('Idle', ['span' => 2]),
                $hdr('Slow', ['span' => 2]),
                $hdr('Service', ['span' => 2]),
                $hdr('Full', ['span' => 2]),
            ],
            [
                $this->tc($tp(''), ['vMerge' => 'continue', 'shade' => '5B9BD5']),
                $this->tc($tp(''), ['vMerge' => 'continue', 'shade' => '5B9BD5']),
                $hdr('Speed'), $hdr('FOC'), $hdr('Speed'), $hdr('FOC'), $hdr('Speed'), $hdr('FOC'), $hdr('Speed'), $hdr('FOC'),
            ],
        ];

        foreach (['ME PS', 'ME SB', 'AE 1', 'AE 2', 'AE 3'] as $i => $item) {
            $rows[] = array_merge([
                $this->tc($tp((string) ($i + 1), ['jc' => 'center']), ['noWrap' => true]),
                $this->tc($tp($item), []),
            ], array_map(fn () => $this->tc($tp(''), []), range(1, 8)));
        }

        return $this->tbl([9.57, 17, 8.86, 9.5, 9.74, 8.62, 9.14, 9.22, 10.02, 8.34], $rows, [0, 1, 2]).$this->p('');
    }

    /**
     * Engine Room — daftar Pumps: lembar isian Merk/Type/Capacity/Qty/Remark.
     * Master: tanpa shading, header bold, font 11pt. Posisi setelah tabel CAP.
     */
    protected function enginePumpsTable(): string
    {
        $tp = fn (string $text, array $opts = []): string => $this->p($text, $opts + ['size' => 22]);
        $hdr = fn (string $text): string => $this->tc(
            $tp($text, ['bold' => true, 'jc' => 'center']),
            ['noWrap' => true]
        );

        $rows = [
            [$hdr('No'), $hdr('Pumps'), $hdr('Merk'), $hdr('Type'), $hdr('Capacity(Kw)'), $hdr('Quantity'), $hdr('Remark')],
        ];

        $pumps = ['FO Pump', 'Fire Pump', 'SW Air Cond. Pump', 'Sewage Pump', 'Emergency Bilge Pump', 'Bilge Pump', 'FW Press. Set. Pump', 'GS / Fire Pump', 'LO Priming Pump', 'SW Pump', 'LO Stbd Gear Box Pump', 'Emgcy SW Cooling Pump', 'FO Transfer Pump', 'LO Transfer Pump', 'ME FW Cooling Pump', 'Dirty Oil Pump', 'Ballast Pump', 'SW Press Set Pump', 'Oily Water Separator', 'Steering Gear', 'Air Compressor', 'Air reservoir', 'Bow Thruster'];
        foreach ($pumps as $i => $pump) {
            $rows[] = [
                $this->tc($tp((string) ($i + 1), ['jc' => 'center']), ['noWrap' => true]),
                $this->tc($tp($pump), []),
                $this->tc($tp(''), []),
                $this->tc($tp(''), []),
                $this->tc($tp(''), []),
                $this->tc($tp(''), []),
                $this->tc($tp(''), []),
            ];
        }

        return $this->tbl([7.02, 29.44, 15.56, 10.44, 11.48, 12.52, 13.54], $rows, [0]).$this->p('');
    }

    /**
     * Bridge — "Navigation & Communication Specification": isian Qty +
     * Specification per item, digrup Navigation / Communication Equipment.
     * Master: header biru 4285F4, font 10pt.
     */
    protected function navigationSpecTable(): string
    {
        $tp = fn (string $text, array $opts = []): string => $this->p($text, $opts + ['size' => 20]);
        $hdr = fn (string $text, array $tcOpts = []): string => $this->tc(
            $tp($text, ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']),
            $tcOpts + ['shade' => '4285F4', 'noWrap' => true]
        );

        $rows = [
            [$this->tc($tp('Navigation & Communication Specification', ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']), ['span' => 4, 'shade' => '4285F4'])],
            [$hdr('No.'), $hdr('Item'), $hdr('Qty'), $hdr('Specification')],
        ];

        $groups = [
            'Navigation Equipment' => ['Radar', 'GPS', 'Echosounder', 'Anemometer', 'AIS', 'Gyro Compass', 'Magnetic Compass', 'ECDIS', 'Autopilot', 'Steering Wheel', 'Bow Thruster Control Stand', 'Throttle', 'Navigation Light Control Panel', 'BNWAS'],
            'Communication Equipment' => ['Navtex Receiver', 'GMDSS', 'MF/HF', 'VHF', 'Handy Talky', 'Public Addresser', 'Sound Power Telephone', 'Electrical/Air Horn', 'Weather Fax', 'Inmarsat'],
        ];
        foreach ($groups as $group => $items) {
            $rows[] = [
                $this->tc($tp(''), []),
                $this->tc($tp($group, ['bold' => true, 'jc' => 'center']), []),
                $this->tc($tp(''), []),
                $this->tc($tp(''), []),
            ];
            foreach ($items as $i => $item) {
                $rows[] = [
                    $this->tc($tp((string) ($i + 1), ['jc' => 'center']), ['noWrap' => true]),
                    $this->tc($tp($item), []),
                    $this->tc($tp(''), []),
                    $this->tc($tp(''), []),
                ];
            }
        }

        return $this->tbl([6.96, 54.46, 11.84, 26.73], $rows, [0, 1]).$this->p('');
    }

    /**
     * Ship Safety — "Life Saving Appliance & Fire Fighting Appliance
     * Equipment": isian Qty per item, digrup LSA / FFA. Font 10pt.
     */
    protected function safetyEquipmentTable(): string
    {
        $tp = fn (string $text, array $opts = []): string => $this->p($text, $opts + ['size' => 20]);
        $hdr = fn (string $text, array $tcOpts = []): string => $this->tc(
            $tp($text, ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']),
            $tcOpts + ['shade' => '4285F4', 'noWrap' => true]
        );

        $rows = [
            [$this->tc($tp('Life Saving Appliance & Fire Fighting Appliance Equipment', ['bold' => true, 'jc' => 'center', 'color' => 'FFFFFF']), ['span' => 3, 'shade' => '4285F4'])],
            [$hdr('No.'), $hdr('Item'), $hdr('Qty')],
        ];

        $groups = [
            'Life Saving Appliance Equipment' => ['HRU', 'ILR', 'Life line Throwing', 'Rocket Parachute', 'Smoke Signal', 'Hand Flare', 'EEBD (Emergency Escape Breathing Device)', 'EPIRB (Emergency Position-Indicating Radio Beacon)', 'SART (Search and Rescue Transponder)', 'Life Buoy with Line or Light', 'Life Jacket', 'Rescue Boat', 'Davit Rescue Boat', 'Escape Route', 'Muster Station'],
            'Fire Fighting Appliance Equipment' => ['Portable CO2 Fire Extinguisher', 'Portable Foam Fire Extinguisher', 'Fire Box, Hose, and Nozzle', 'Fire Blanket', 'Fireman Outfit', 'SOPEP', 'SCBA (Self-Contained Breathing Apparatus)', 'Smoke Detector', 'Heat Detector', 'Sprinkler', 'Emergency Fire Pump', 'Hydrant', 'Fixed CO2 System', 'International Shore Connection'],
        ];
        foreach ($groups as $group => $items) {
            $rows[] = [
                $this->tc($tp(''), []),
                $this->tc($tp($group, ['bold' => true, 'jc' => 'center']), []),
                $this->tc($tp(''), []),
            ];
            foreach ($items as $i => $item) {
                $rows[] = [
                    $this->tc($tp((string) ($i + 1), ['jc' => 'center']), ['noWrap' => true]),
                    $this->tc($tp($item), []),
                    $this->tc($tp(''), []),
                ];
            }
        }

        return $this->tbl([6.96, 66.22, 26.82], $rows, [0, 1]).$this->p('');
    }

    protected function groupScoreLabels(object $group): array
    {
        $labels = $group->items
            ->filter(fn ($item) => $item->item_type === SurveyItemType::Score)
            ->flatMap(fn ($item) => $item->score_labels ?? [])
            ->unique()
            ->values()
            ->all();

        return $labels !== [] ? $labels : ['C', 'V'];
    }

    /**
     * Tanggal issued/expired untuk item sertifikat (has_date_fields).
     */
    protected function dateLines($item, $response): string
    {
        if (! $item->has_date_fields || ! $response || (! $response->date_issued && ! $response->date_expired)) {
            return '';
        }

        $xml = '';
        if ($response->date_issued) {
            $xml .= $this->p('Date issued : '.$response->date_issued->format('d/m/Y'), ['size' => 20]);
        }
        if ($response->date_expired) {
            $xml .= $this->p('Exp : '.$response->date_expired->format('d/m/Y'), ['size' => 20]);
        }

        return $xml;
    }

    /**
     * Legend label skor di atas tabel kategori, mis. "C= Coating   V= Visual".
     */
    protected function scoreLegend(object $cat): string
    {
        $map = ['C' => 'Coating', 'V' => 'Visual', 'F' => 'Function', 'M' => 'Maintenance'];

        $usedLabels = $cat->subCategories
            ->flatMap(fn ($sc) => $sc->itemGroups)
            ->flatMap(fn ($ig) => $ig->items)
            ->filter(fn ($item) => $item->item_type === SurveyItemType::Score)
            ->flatMap(fn ($item) => $item->score_labels ?? [])
            ->unique();

        return collect(array_keys($map))
            ->filter(fn ($label) => $usedLabels->contains($label))
            ->map(fn ($label) => $label.'= '.$map[$label])
            ->implode('    ');
    }

    // -----------------------------------------------------------------
    //  Blok: BAB IV — Saran
    // -----------------------------------------------------------------

    protected function buildBab4(array $data): string
    {
        $xml = $this->p('Adapun saran dari hasil pemeriksaan kondisi kapal yaitu sebagai berikut:', ['size' => 24]);

        $num = 0;
        foreach ($data['categories'] as $cat) {
            $num++;
            $content = trim((string) $this->section($data, 'saran_'.$cat->id));

            $xml .= $this->p($num.'. '.$cat->label.':', ['bold' => true, 'size' => 24, 'spacingBefore' => 240]);

            if ($content === '') {
                $xml .= $this->p('-', ['size' => 22]);

                continue;
            }

            foreach (preg_split('/\r?\n/', $content) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if (str_starts_with($line, '- ')) {
                    $xml .= $this->p('• '.substr($line, 2), ['indent' => 360, 'size' => 22]);
                } else {
                    $xml .= $this->p($line, ['size' => 22]);
                }
            }
        }

        return $xml;
    }

    // -----------------------------------------------------------------
    //  Grafik benchmark (scatter: umur kapal vs CAP rating)
    // -----------------------------------------------------------------

    /**
     * Render scatter chart ke PNG via GD; return binary PNG atau null.
     * Dipakai oleh DOCX (ditulis ke temp file) dan preview live di editor.
     */
    public function renderBenchmarkChartPng(array $benchmark): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $w = 960;
        $h = 600;
        $padL = 80;
        $padR = 30;
        $padT = 50;
        $padB = 70;

        $img = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 40, 40, 40);
        $gray = imagecolorallocate($img, 200, 200, 200);
        $blue = imagecolorallocate($img, 37, 99, 235);
        $red = imagecolorallocate($img, 220, 38, 38);
        imagefill($img, 0, 0, $white);

        $allX = array_map(fn ($p) => $p['x'], $benchmark['points']);
        if ($benchmark['current']) {
            $allX[] = $benchmark['current']['x'];
        }
        $maxX = max(10, (int) ceil(($allX ? max($allX) : 40) / 10) * 10);
        $maxY = 4.0;

        $px = fn ($x) => (int) round($padL + ($x / $maxX) * ($w - $padL - $padR));
        $py = fn ($y) => (int) round($h - $padB - ($y / $maxY) * ($h - $padT - $padB));

        // Grid + axis labels
        for ($y = 0; $y <= 4; $y++) {
            $yy = $py($y);
            imageline($img, $padL, $yy, $w - $padR, $yy, $y === 0 ? $black : $gray);
            imagestring($img, 4, $padL - 30, $yy - 8, (string) $y, $black);
        }
        $stepX = $maxX > 50 ? 10 : 5;
        for ($x = 0; $x <= $maxX; $x += $stepX) {
            $xx = $px($x);
            imageline($img, $xx, $h - $padB, $xx, $h - $padB + 5, $black);
            imagestring($img, 3, $xx - 8, $h - $padB + 10, (string) $x, $black);
        }
        imageline($img, $padL, $h - $padB, $w - $padR, $h - $padB, $black);
        imageline($img, $padL, $h - $padB, $padL, $padT, $black);

        imagestring($img, 4, (int) ($w / 2 - 60), $h - 30, 'Ship Age (years)', $black);
        imagestringup($img, 4, 15, (int) ($h / 2 + 60), 'Overall CAP Rating', $black);
        imagestring($img, 4, $padL, 15, 'Benchmark: Umur Kapal vs CAP Rating', $black);

        // Titik populasi (biru)
        foreach ($benchmark['points'] as $p) {
            imagefilledellipse($img, (int) $px($p['x']), (int) $py($p['y']), 12, 12, $blue);
        }

        // Titik kapal ini (merah)
        if ($benchmark['current']) {
            imagefilledellipse(
                $img,
                (int) $px($benchmark['current']['x']),
                (int) $py($benchmark['current']['y']),
                18,
                18,
                $red
            );
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return $png !== false ? $png : null;
    }

    /**
     * Render scatter chart ke PNG temporary; return path atau null.
     */
    protected function renderBenchmarkChart(array $benchmark): ?string
    {
        $png = $this->renderBenchmarkChartPng($benchmark);
        if ($png === null) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'chart_').'.png';
        file_put_contents($path, $png);

        return $path;
    }

    // =================================================================
    //  WordprocessingML helpers
    // =================================================================

    protected function section(array $data, string $key): string
    {
        return (string) ($data['sections']->get($key)?->content ?? '');
    }

    /**
     * Paragraf multi-baris: tiap baris -> satu <w:p>. Kosong -> satu <w:p> kosong.
     */
    protected function buildParagraphs(string $content, array $opts = []): string
    {
        $lines = preg_split('/\r?\n/', trim($content));
        if ($lines === false || $lines === ['']) {
            return $this->p('', $opts);
        }

        return implode('', array_map(fn ($line) => $this->p(trim($line), $opts), $lines));
    }

    protected function imageDrawing(string $relationshipId, int $categoryId, ?array $cropData = null): string
    {
        // Muat di kolom Dokumentasi: frame persegi 1:1 (~5.3 cm).
        $width = 1905000;
        $height = 1905000;
        $name = 'Dokumentasi kategori '.$categoryId;
        $srcRect = $this->squareSrcRect($cropData);

        return '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:drawing>'
            .'<wp:inline xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            .'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" '
            .'distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$width.'" cy="'.$height.'"/>'
            .'<wp:docPr id="'.(10000 + $categoryId).'" name="'.$this->esc($name).'"/>'
            .'<wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            .'<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic><pic:nvPicPr><pic:cNvPr id="0" name="'.$this->esc($name).'"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="'.$relationshipId.'"/>'.$srcRect.'<a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$width.'" cy="'.$height.'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
            .'</a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    /**
     * Hitung <a:srcRect> center-crop ke rasio 1:1 dari dimensi crop tersimpan,
     * supaya foto lama 3:2 tetap proporsional di frame persegi tanpa upload
     * ulang. Nilai srcRect dalam 1/1000 persen dari dimensi sumber; foto yang
     * sudah square tidak di-crop.
     */
    protected function squareSrcRect(?array $cropData): string
    {
        $w = (float) ($cropData['width'] ?? 0);
        $h = (float) ($cropData['height'] ?? 0);
        if ($w <= 0 || $h <= 0 || abs($w - $h) <= 0.01 * $w) {
            return '';
        }
        // Potong sisi yang lebih panjang secara simetris (center crop).
        $trim = (int) round((1 - min($w, $h) / max($w, $h)) / 2 * 100000);

        return $w > $h
            ? '<a:srcRect l="'.$trim.'" r="'.$trim.'"/>'
            : '<a:srcRect t="'.$trim.'" b="'.$trim.'"/>';
    }

    /**
     * Paragraf khusus tabel Overall CAP Rating Breakdown — font 11pt.
     */
    protected function bp(string $text, array $opts = []): string
    {
        return $this->p($text, ['size' => 22] + $opts);
    }

    /**
     * Satu paragraf <w:p>.
     * opts: bold, italic, color, jc (center/right/both), style (pStyle), indent (twips),
     *       line (w:line twips, 240=single 360=1.5), spacingBefore (twips),
     *       size (w:sz half-points, 20=10pt 24=12pt), numId (list numbering),
     *       pageBreakBefore (paragraf mulai di halaman baru),
     *       italicPhrases/boldPhrases (array frasa yang di-render sebagai run
     *       italic/bold tersendiri).
     */
    protected function p(string $text, array $opts = []): string
    {
        $pPrInner = '';
        if (! empty($opts['style'])) {
            $pPrInner .= '<w:pStyle w:val="'.$opts['style'].'"/>';
        }
        if (! empty($opts['pageBreakBefore'])) {
            $pPrInner .= '<w:pageBreakBefore/>';
        }
        if (! empty($opts['numId'])) {
            $pPrInner .= '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="'.(int) $opts['numId'].'"/></w:numPr>';
        }
        if (isset($opts['rawSpacing'])) {
            $pPrInner .= $opts['rawSpacing'];
        } else {
            $pPrInner .= '<w:spacing w:after="0" w:line="'.(int) ($opts['line'] ?? 240).'" w:lineRule="auto"'
                .(! empty($opts['spacingBefore']) ? ' w:before="'.$opts['spacingBefore'].'"' : '')
                .'/>';
        }
        if (isset($opts['indent'])) {
            $pPrInner .= '<w:ind w:left="'.$opts['indent'].'"/>';
        }
        if (! empty($opts['jc'])) {
            $pPrInner .= '<w:jc w:val="'.$opts['jc'].'"/>';
        }
        $pPr = $pPrInner !== '' ? '<w:pPr>'.$pPrInner.'</w:pPr>' : '';

        $runs = '';
        foreach ($this->runs($text, $opts) as [$runText, $runOpts]) {
            $runs .= '<w:r>'.$this->rPr($runOpts).'<w:t xml:space="preserve">'.$this->esc($runText).'</w:t></w:r>';
        }

        return '<w:p>'.$pPr.$runs.'</w:p>';
    }

    /**
     * Pecah teks menjadi [teks, opts] per run — frasa dalam `italicPhrases`/
     * `boldPhrases` menjadi run tersendiri dengan format aktif. Frasa terpanjang
     * didahulukan agar frasa yang menjadi prefix frasa lain tidak bentrok.
     */
    protected function runs(string $text, array $opts): array
    {
        $phrases = [];
        foreach ((array) ($opts['italicPhrases'] ?? []) as $phrase) {
            $phrases[$phrase]['italic'] = true;
        }
        foreach ((array) ($opts['boldPhrases'] ?? []) as $phrase) {
            $phrases[$phrase]['bold'] = true;
        }
        if ($phrases === [] || $text === '') {
            return [[$text, $opts]];
        }

        $keys = array_keys($phrases);
        usort($keys, fn ($a, $b) => strlen($b) <=> strlen($a));
        $pattern = '/('.implode('|', array_map(fn ($phrase) => preg_quote($phrase, '/'), $keys)).')/';
        $runs = [];
        foreach (preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            $runs[] = [$part, isset($phrases[$part]) ? $phrases[$part] + $opts : $opts];
        }

        return $runs ?: [[$text, $opts]];
    }

    /**
     * Run properties <w:rPr> dari opts paragraf.
     */
    protected function rPr(array $opts): string
    {
        $rPr = '<w:rFonts w:ascii="Garamond" w:hAnsi="Garamond" w:eastAsia="Garamond" w:cs="Garamond"/>';
        if (empty($opts['style'])) {
            $size = (int) ($opts['size'] ?? 20);
            $rPr .= '<w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/>';
        }
        if (! empty($opts['bold'])) {
            $rPr .= '<w:b/><w:bCs/>';
        }
        if (! empty($opts['italic'])) {
            $rPr .= '<w:i/><w:iCs/>';
        }
        if (! empty($opts['color'])) {
            $rPr .= '<w:color w:val="'.$opts['color'].'"/>';
        }

        return '<w:rPr>'.$rPr.'</w:rPr>';
    }

    /**
     * Sel tabel <w:tc>. opts: span (gridSpan), vMerge ('restart'|'continue'),
     * shade (hex fill), vAlign (default 'center' — teks selalu di tengah
     * vertikal), borders (array side => 'single'|'double'; sisi tak disebut = nil).
     */
    protected function tc(string $innerXml, array $opts = []): string
    {
        $tcPr = '';
        if (! empty($opts['span']) && $opts['span'] > 1) {
            $tcPr .= '<w:gridSpan w:val="'.$opts['span'].'"/>';
        }
        if (! empty($opts['vMerge'])) {
            $tcPr .= $opts['vMerge'] === 'restart' ? '<w:vMerge w:val="restart"/>' : '<w:vMerge/>';
        }
        if (! empty($opts['borders'])) {
            $bordersXml = '';
            foreach (['top', 'left', 'bottom', 'right'] as $side) {
                $val = $opts['borders'][$side] ?? 'nil';
                $bordersXml .= $val === 'nil'
                    ? '<w:'.$side.' w:val="nil"/>'
                    : '<w:'.$side.' w:val="'.$val.'" w:sz="'.($val === 'double' ? 6 : 4).'" w:space="0" w:color="000000"/>';
            }
            $tcPr .= '<w:tcBorders>'.$bordersXml.'</w:tcBorders>';
        }
        if (! empty($opts['shade'])) {
            $tcPr .= '<w:shd w:val="clear" w:color="auto" w:fill="'.$opts['shade'].'"/>';
        }
        if (! empty($opts['noWrap'])) {
            $tcPr .= '<w:noWrap/>';
        }
        $tcPr .= '<w:vAlign w:val="'.($opts['vAlign'] ?? 'center').'"/>';
        $tcPr = $tcPr !== '' ? '<w:tcPr>'.$tcPr.'</w:tcPr>' : '';

        // Sel wajib berisi minimal satu paragraf
        if (! str_contains($innerXml, '<w:p')) {
            $innerXml = $this->p($innerXml);
        }

        return '<w:tc>'.$tcPr.$innerXml.'</w:tc>';
    }

    /**
     * Tabel <w:tbl> dengan border single.
     *
     * @param  array<int>  $widthsPct  Lebar kolom dalam persen (total ~100)
     * @param  array<array<string>>  $rows  Tiap baris = daftar string <w:tc>
     * @param  array<int>  $headerRowIdx  Index baris yang ditandai header (repeat on page break)
     * @param  array{borders?: bool, rowHeights?: array<int, int>, defaultRowHeight?: int, totalWidth?: int}  $opts
     */
    protected function tbl(array $widthsPct, array $rows, array $headerRowIdx = [], array $opts = []): string
    {
        $total = array_sum($widthsPct);
        $totalWidth = (int) ($opts['totalWidth'] ?? 9638);
        $gridXml = implode('', array_map(
            fn ($w) => '<w:gridCol w:w="'.(int) round($w / max(1, $total) * $totalWidth).'"/>',
            $widthsPct
        ));

        $rowsXml = '';
        foreach ($rows as $i => $cells) {
            $trPr = '';
            $height = $opts['rowHeights'][$i] ?? $opts['defaultRowHeight'] ?? null;
            if ($height || in_array($i, $headerRowIdx, true)) {
                $trPr = '<w:trPr>'
                    .($height ? '<w:trHeight w:val="'.$height.'"/>' : '')
                    .(in_array($i, $headerRowIdx, true) ? '<w:tblHeader/>' : '')
                    .'</w:trPr>';
            }
            $rowsXml .= '<w:tr>'.$trPr.implode('', $cells).'</w:tr>';
        }

        $borderStyle = ($opts['borders'] ?? true) ? 'single' : 'nil';
        $insideStyle = ($opts['frameOnly'] ?? false) ? 'nil' : $borderStyle;
        $borders = '<w:tblBorders>'
            .'<w:top w:val="'.$borderStyle.'" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:left w:val="'.$borderStyle.'" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:bottom w:val="'.$borderStyle.'" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:right w:val="'.$borderStyle.'" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:insideH w:val="'.$insideStyle.'" w:sz="4" w:space="0" w:color="000000"/>'
            .'<w:insideV w:val="'.$insideStyle.'" w:sz="4" w:space="0" w:color="000000"/>'
            .'</w:tblBorders>';

        return '<w:tbl>'
            .'<w:tblPr><w:tblW w:w="5000" w:type="pct"/>'
            .$borders
            .'<w:tblLayout w:type="fixed"/>'
            .'<w:tblCellMar><w:top w:w="15" w:type="dxa"/><w:bottom w:w="15" w:type="dxa"/></w:tblCellMar>'
            .'<w:tblLook w:val="04A0" w:firstRow="1" w:lastRow="0" w:firstColumn="1" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/>'
            .'</w:tblPr>'
            .'<w:tblGrid>'.$gridXml.'</w:tblGrid>'
            .$rowsXml
            .'</w:tbl>';
    }

    protected function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
