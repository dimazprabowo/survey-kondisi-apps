<?php

/**
 * Extract all survey structure from Excel to JSON.
 * Handles all variations: score labels in E/F/G, 1-3 score columns, direct items, sub-items.
 * Usage: php extract_excel.php
 */
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$path = 'storage/app/private/templates/FORM - CHARTER - SURVEY CONDITION.xlsx';
$reader = IOFactory::createReaderForFile($path);
$spreadsheet = $reader->load($path);

$sheetConfigs = [
    ['sheet' => 'Hull & Cons',                  'code' => 'I',   'label' => 'HULL & CONSTRUCTION'],
    ['sheet' => 'Ramp',                         'code' => 'II',  'label' => 'RAMP'],
    ['sheet' => 'Accomodation',                 'code' => 'III', 'label' => 'ACCOMODATION'],
    ['sheet' => 'Deck Machinery & Outfitting',  'code' => 'IV',  'label' => 'DECK MACHINERY & OUTFITTING'],
    ['sheet' => 'ER&Machinery-1',               'code' => 'V',   'label' => 'ENGINE ROOM & MACHINERY'],
    ['sheet' => 'ER&MAchinery-2',               'code' => 'V',   'label' => 'ENGINE ROOM & MACHINERY', 'is_continuation' => true],
    ['sheet' => 'PUMP&PIPE - xx',               'code' => 'VI',  'label' => 'PUMP & PIPE'],
    ['sheet' => 'BRIDGE & NAVIGATION',          'code' => 'VII', 'label' => 'BRIDGE & NAVIGATION'],
    ['sheet' => 'SHIP SAFETY OPERATION',        'code' => 'VIII', 'label' => 'SHIP SAFETY OPERATION'],
];

// Known score label values (compared uppercase)
$scoreLabels = ['C', 'V', 'F', 'M', 'MOTOR', 'PUMP', 'PANEL', 'PIPE', 'VALVE', 'FLANGE'];

function isScoreLabel($val)
{
    return in_array(strtoupper(trim((string) $val)), ['C', 'V', 'F', 'M', 'MOTOR', 'PUMP', 'PANEL', 'PIPE', 'VALVE', 'FLANGE']);
}

$result = [];

foreach ($sheetConfigs as $cfg) {
    $sheet = $spreadsheet->getSheetByName($cfg['sheet']);
    if (! $sheet) {
        continue;
    }

    $maxRow = $sheet->getHighestRow();

    $catIdx = -1;
    if (! empty($cfg['is_continuation'])) {
        foreach ($result as $idx => $c) {
            if ($c['category_code'] === $cfg['code']) {
                $catIdx = $idx;
                break;
            }
        }
    }
    if ($catIdx < 0) {
        $result[] = ['category_code' => $cfg['code'], 'category_label' => $cfg['label'], 'sub_categories' => []];
        $catIdx = count($result) - 1;
    }

    $rows = [];
    for ($r = 1; $r <= $maxRow; $r++) {
        $row = [];
        foreach (range('A', 'I') as $col) {
            $row[$col] = $sheet->getCell($col.$r)->getValue();
        }
        $rows[$r] = $row;
    }

    $currentScoreLabels = ['C', 'V'];
    $inSubItems = false;
    $subCatIdx = -1;
    $itemGroupIdx = -1;

    for ($r = 1; $r <= $maxRow; $r++) {
        $row = $rows[$r];
        $a = trim((string) ($row['A'] ?? ''));
        $b = trim((string) ($row['B'] ?? ''));
        $c = trim((string) ($row['C'] ?? ''));
        $d = trim((string) ($row['D'] ?? ''));
        $e = trim((string) ($row['E'] ?? ''));
        $f = trim((string) ($row['F'] ?? ''));
        $g = trim((string) ($row['G'] ?? ''));

        if ($a === '' && $b === '' && $c === '' && $d === '' && $e === '' && $f === '') {
            continue;
        }
        if ($a === 'No.' || $b === 'Item') {
            continue;
        }
        if (in_array($a, ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII']) && ! is_numeric($a) && $b !== '') {
            continue;
        }

        // Sub-category: A=numeric, B=name
        if ($a !== '' && is_numeric($a) && $b !== '') {
            $result[$catIdx]['sub_categories'][] = ['order_num' => (int) $a, 'name' => $b, 'item_groups' => []];
            $subCatIdx = count($result[$catIdx]['sub_categories']) - 1;
            $itemGroupIdx = -1;
            $inSubItems = false;

            continue;
        }

        if ($subCatIdx < 0) {
            continue;
        }

        // Skip note/date/qty rows
        if ($b === 'Note:' || $b === 'Note' || $b === '-' || $c === '(Lokasi temuan+kondisi kerusakan)') {
            $inSubItems = false;

            continue;
        }
        if ($d !== '' && (str_contains($d, 'Date issued') || str_contains($d, 'Exp') || str_contains($d, 'Next Renewal') || str_contains($d, 'Qty') || str_contains($d, 'Posisi Kerusakan'))) {
            continue;
        }

        // Score header: any of E/F/G/H is a known label, B and C are empty
        $isScoreHeader = false;
        if ($b === '' && $c === '') {
            foreach (['E', 'F', 'G', 'H'] as $col) {
                $val = trim((string) ($row[$col] ?? ''));
                if (isScoreLabel($val)) {
                    $isScoreHeader = true;
                    break;
                }
            }
        }
        if ($isScoreHeader) {
            $currentScoreLabels = [];
            foreach (['E', 'F', 'G', 'H'] as $col) {
                $val = trim((string) ($row[$col] ?? ''));
                if ($val !== '' && strtoupper($val) !== 'AVG') {
                    $currentScoreLabels[] = strtoupper($val);
                }
            }
            $inSubItems = true;

            continue;
        }

        // Sub-item: C=letter, D=name (after score header)
        if ($inSubItems && $c !== '' && $d !== '') {
            if ($itemGroupIdx < 0) {
                $result[$catIdx]['sub_categories'][$subCatIdx]['item_groups'][] = [
                    'code' => (string) $result[$catIdx]['sub_categories'][$subCatIdx]['order_num'],
                    'name' => $result[$catIdx]['sub_categories'][$subCatIdx]['name'],
                    'items' => [],
                ];
                $itemGroupIdx = count($result[$catIdx]['sub_categories'][$subCatIdx]['item_groups']) - 1;
            }

            $item = ['code' => $c, 'name' => $d, 'score_labels' => $currentScoreLabels, 'has_date_fields' => false];
            $nextD = trim((string) ($rows[$r + 1]['D'] ?? ''));
            if (str_contains($nextD, 'Date issued') || str_contains($nextD, 'Exp')) {
                $item['has_date_fields'] = true;
            }

            $result[$catIdx]['sub_categories'][$subCatIdx]['item_groups'][$itemGroupIdx]['items'][] = $item;

            continue;
        }

        // Item group header: B=code, D=name, next row is score header
        if ($b !== '' && $d !== '') {
            $nextRow = $rows[$r + 1] ?? [];
            $nextIsScoreHeader = false;
            foreach (['E', 'F', 'G', 'H'] as $col) {
                $val = trim((string) ($nextRow[$col] ?? ''));
                if (isScoreLabel($val)) {
                    $nextIsScoreHeader = true;
                    break;
                }
            }

            if ($nextIsScoreHeader) {
                $result[$catIdx]['sub_categories'][$subCatIdx]['item_groups'][] = ['code' => $b, 'name' => $d, 'items' => []];
                $itemGroupIdx = count($result[$catIdx]['sub_categories'][$subCatIdx]['item_groups']) - 1;
                $inSubItems = false;

                continue;
            }

            // Direct item: B=code, D=name, next row is NOT score header
            // Scores are user input - template just defines structure, don't require hasScores
            if (true) {
                $appended = false;
                if ($itemGroupIdx >= 0) {
                    $lastGroup = &$result[$catIdx]['sub_categories'][$subCatIdx]['item_groups'][$itemGroupIdx];
                    if (! empty($lastGroup['items'])) {
                        $lastItem = end($lastGroup['items']);
                        if (! ctype_alpha($lastItem['code']) && $lastItem['code'] !== '') {
                            $lastGroup['items'][] = ['code' => $b, 'name' => $d, 'score_labels' => $currentScoreLabels, 'has_date_fields' => false];
                            $appended = true;
                        }
                    }
                }
                if (! $appended) {
                    $result[$catIdx]['sub_categories'][$subCatIdx]['item_groups'][] = ['code' => $b, 'name' => $d, 'items' => []];
                    $itemGroupIdx = count($result[$catIdx]['sub_categories'][$subCatIdx]['item_groups']) - 1;
                    $result[$catIdx]['sub_categories'][$subCatIdx]['item_groups'][$itemGroupIdx]['items'][] = ['code' => $b, 'name' => $d, 'score_labels' => $currentScoreLabels, 'has_date_fields' => false];
                }
                $inSubItems = false;

                continue;
            }
        }

        // Continuation direct item: D=name, no B/C, has scores
        if ($d !== '' && $b === '' && $c === '' && ($e !== '' || $f !== '' || $g !== '')) {
            if ($itemGroupIdx >= 0) {
                $result[$catIdx]['sub_categories'][$subCatIdx]['item_groups'][$itemGroupIdx]['items'][] = ['code' => '', 'name' => $d, 'score_labels' => $currentScoreLabels, 'has_date_fields' => false];
            }

            continue;
        }
    }
}

// Benchmark data
$curveSheet = $spreadsheet->getSheetByName('curve');
$benchmarkData = [];
for ($r = 4; $r <= 60; $r++) {
    $name = $curveSheet->getCell('B'.$r)->getValue();
    $year = $curveSheet->getCell('C'.$r)->getValue();
    if ($name !== null && $name !== '') {
        $benchmarkData[] = ['name' => trim($name), 'year_built' => $year ? (int) $year : null];
    }
}

$output = ['categories' => $result, 'benchmark_ships' => $benchmarkData];
file_put_contents('storage/app/private/templates/survey_template.json', json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'Extracted '.count($result)." categories\n";
$totalAll = 0;
foreach ($result as $cat) {
    $totalItems = 0;
    foreach ($cat['sub_categories'] as $sub) {
        foreach ($sub['item_groups'] as $ig) {
            $totalItems += count($ig['items']);
        }
    }
    $totalAll += $totalItems;
    echo "  {$cat['category_code']}. {$cat['category_label']} - ".count($cat['sub_categories'])." sub-cats, $totalItems items\n";
    foreach ($cat['sub_categories'] as $sub) {
        $subItems = 0;
        foreach ($sub['item_groups'] as $ig) {
            $subItems += count($ig['items']);
        }
        echo "    {$sub['order_num']}. {$sub['name']} - ".count($sub['item_groups'])." groups, $subItems items\n";
    }
}
echo "TOTAL items: $totalAll\n";
echo 'Benchmark ships: '.count($benchmarkData)."\n";
