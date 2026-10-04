<?php

/**
 * Transformasi template laporan DOCX asli menjadi template ber-placeholder
 * untuk SurveyReportDocxBuilder (TemplateProcessor + injeksi XML).
 *
 * - Teks dinamis -> ${placeholder} (diisi via TemplateProcessor::setValue)
 * - Region dinamis -> paragraf marker ${blok} (diinjeksi XML saat generate)
 * - Sel tabel Ship Particulars -> ${sp_*} berdasarkan label baris
 *
 * Usage: php build_report_template.php
 * Output: storage/app/private/templates/report-charter-condition.docx
 */
$source = 'storage/app/private/templates/Template Charter Condition Survey Report - ASDP.docx';
$target = 'storage/app/private/templates/report-charter-condition.docx';

if (! is_file($source)) {
    fwrite(STDERR, "Source template tidak ditemukan: {$source}\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($source) !== true) {
    fwrite(STDERR, "Gagal membuka {$source}\n");
    exit(1);
}

$documentXml = $zip->getFromName('word/document.xml');
$zip->close();

$W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

$doc = new DOMDocument;
$doc->preserveWhiteSpace = true;
$doc->loadXML($documentXml);
$xp = new DOMXPath($doc);
$xp->registerNamespace('w', $W);

$body = $xp->query('//w:body')->item(0);
$children = [];
foreach ($body->childNodes as $c) {
    if ($c instanceof DOMElement) {
        $children[] = $c;
    }
}

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------

/** Buat paragraf marker ${name}. */
$markerP = function (string $name) use ($doc, $W): DOMElement {
    $p = $doc->createElementNS($W, 'w:p');
    $r = $doc->createElementNS($W, 'w:r');
    $t = $doc->createElementNS($W, 'w:t', '${'.$name.'}');
    $t->setAttribute('xml:space', 'preserve');
    $r->appendChild($t);
    $p->appendChild($r);

    return $p;
};

/** Kosongkan seluruh isi node (pertahankan w:tcPr / w:pPr). */
$clearNode = function (DOMElement $node): void {
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMElement
            && in_array($child->localName, ['tcPr', 'pPr'], true)) {
            continue;
        }
        $node->removeChild($child);
    }
};

/** Buat paragraf berisi satu run teks. */
$makeP = function (string $text) use ($doc, $W): DOMElement {
    $p = $doc->createElementNS($W, 'w:p');
    $r = $doc->createElementNS($W, 'w:r');
    $t = $doc->createElementNS($W, 'w:t', $text);
    $t->setAttribute('xml:space', 'preserve');
    $r->appendChild($t);
    $p->appendChild($r);

    return $p;
};

/** Ganti seluruh isi sel dengan satu paragraf teks. */
$setCellText = function (DOMElement $tc, string $text) use ($clearNode, $makeP): void {
    $clearNode($tc);
    $tc->appendChild($makeP($text));
};

/** Ganti seluruh isi paragraf dengan satu run teks. */
$setParagraphText = function (DOMElement $p, string $text) use ($doc, $W, $clearNode): void {
    $clearNode($p);
    $r = $doc->createElementNS($W, 'w:r');
    $t = $doc->createElementNS($W, 'w:t', $text);
    $t->setAttribute('xml:space', 'preserve');
    $r->appendChild($t);
    $p->appendChild($r);
};

/** Daftar sel <w:tc> anak langsung sebuah baris. */
$rowCells = function (DOMElement $tr): array {
    $cells = [];
    foreach ($tr->childNodes as $c) {
        if ($c instanceof DOMElement && $c->localName === 'tc') {
            $cells[] = $c;
        }
    }

    return $cells;
};

$cellText = fn (DOMElement $tc): string => trim(preg_replace('/\s+/', ' ', $tc->textContent));

// ---------------------------------------------------------------------
// 1. Tabel "Data Kapal" lembar pengesahan (child 18): label -> sel ke-3
// ---------------------------------------------------------------------
$pengesahanMap = [
    'NAMA KAPAL' => '${ship_name}',
    'TIPE KAPAL' => '${ship_type}',
    'LOA' => '${loa} M',
    'BREADTH' => '${breadth} M',
    'DRAFT' => '${draft} M',
];

foreach ($xp->query('./w:tr', $children[18]) as $tr) {
    $cells = $rowCells($tr);
    if (count($cells) >= 3 && isset($pengesahanMap[$cellText($cells[0])])) {
        $setCellText($cells[2], $pengesahanMap[$cellText($cells[0])]);
    }
}

// ---------------------------------------------------------------------
// 2. Tabel tanda tangan lembar pengesahan (child 20)
//    Diproses SEBELUM replace global "x..." agar x's nama approver
//    tidak tertukar dengan ${report_no}.
// ---------------------------------------------------------------------
foreach ($xp->query('.//w:tc', $children[20]) as $tc) {
    $text = $cellText($tc);
    if (str_contains($text, 'dd/mm/')) {
        $setCellText($tc, '${approval_place_date}');
    } elseif ($text === 'Inspector 1') {
        $setCellText($tc, '${inspector_1}');
    } elseif ($text === 'Inspector 2') {
        $setCellText($tc, '${inspector_2}');
    } elseif (preg_match('/^x+(\s+x+)*$/', $text)) {
        // Baris "xxxxxxx xxxxx" = nama penandatangan (Kepala Cabang)
        $setCellText($tc, '${approver_name}');
    }
}

// ---------------------------------------------------------------------
// 3. Scalar placeholders — teks di dalam w:t (duplikat Choice/Fallback ikut)
// ---------------------------------------------------------------------
foreach ($xp->query('//w:t') as $t) {
    $text = $t->textContent;

    if (preg_match('/^x{10,}$/', $text)) {
        // NO. LAP di cover (xxxxxxxx...) -> ${report_no}
        $t->textContent = '${report_no}';
    } elseif ($text === 'EXAMPLE') {
        $t->textContent = '${ship_name}';
    } elseif ($text === 'xxxxxx') {
        // "No: xxxxxx" pada lembar pengesahan
        $t->textContent = '${report_no}';
    } elseif (str_contains($text, 'JASA KONSULTAN INDEPENDENT SURVEY KONDISI')) {
        // Baris pertama judul proyek di cover -> ${report_title}
        $t->textContent = '${report_title}';
    } elseif (preg_match('/^PT\. ASDP INDONESIA FERRY - \d{4}$/', $text)) {
        // Baris kedua judul cover: dikosongkan (digabung ke report_title)
        $t->textContent = '';
    }
}

// ---------------------------------------------------------------------
// 4. Executive Summary — narasi + daftar kategori + lead-in + tabel CAP
//    (child 23..36) diganti marker ${exec_summary} lalu ${cap_breakdown}
// ---------------------------------------------------------------------
$body->insertBefore($markerP('exec_summary'), $children[23]);
$body->insertBefore($markerP('cap_breakdown'), $children[23]);
foreach (range(23, 36) as $i) {
    $body->removeChild($children[$i]);
}

// ---------------------------------------------------------------------
// 5. Paragraf gambar grafik benchmark (child 40) -> ${benchmark_chart}
// ---------------------------------------------------------------------
$setParagraphText($children[40], '${benchmark_chart}');

// ---------------------------------------------------------------------
// 6. Tabel ringkasan temuan (child 47) -> marker ${temuan_table}
// ---------------------------------------------------------------------
$body->insertBefore($markerP('temuan_table'), $children[47]);
$body->removeChild($children[47]);

// ---------------------------------------------------------------------
// 7. BAB I — paragraf kontrak (54) + narasi umum (55-56)
// ---------------------------------------------------------------------
$body->insertBefore($markerP('bab1_contract'), $children[54]);
$body->insertBefore($markerP('bab1_general'), $children[54]);
foreach ([54, 55, 56] as $i) {
    $body->removeChild($children[$i]);
}

// ---------------------------------------------------------------------
// 8. BAB II — tabel Ship Particulars (child 68): label -> ${sp_*}
// ---------------------------------------------------------------------
$particularsMap = [
    'Name of Vessel' => '${sp_name}',
    'Type of Ship' => '${sp_type}',
    'IMO Number' => '${sp_imo}',
    'Call Sign' => '${sp_call_sign}',
    'GT/NT' => '${sp_gt_nt}',
    'Year Build' => '${sp_year_built}',
    'Builder' => '${sp_builder}',
    'Port Register' => '${sp_port_registry}',
    'Material of Hull' => '${sp_hull_material}',
    'Length (LoA)' => '${sp_loa}',
    'Lenght (LPP)' => '${sp_lpp}',
    'Breadth (B)' => '${sp_breadth}',
    'Tinggi (Height)' => '${sp_depth}',
    'Draft' => '${sp_draft}',
    'DWT' => '${sp_dwt}',
    'Class' => '${sp_class}',
    'Class Notations' => '${sp_class_notations}',
    'Main Engine' => '${sp_main_engine}',
    'Main Engine Power Requier/Installed' => '${sp_me_power}',
    'Auxiliary Engine' => '${sp_aux_engine}',
    'Auxiliary Cap./Power' => '${sp_aux_power}',
];

foreach ($xp->query('./w:tr', $children[68]) as $tr) {
    $cells = $rowCells($tr);
    if (count($cells) >= 3 && isset($particularsMap[$cellText($cells[0])])) {
        $setCellText($cells[2], $particularsMap[$cellText($cells[0])]);
    }
}

// ---------------------------------------------------------------------
// 9. BAB II — tabel Status Class (child 71) -> marker ${status_class_table}
// ---------------------------------------------------------------------
$body->insertBefore($markerP('status_class_table'), $children[71]);
$body->removeChild($children[71]);

// ---------------------------------------------------------------------
// 10. BAB II — sel "N/A" pada tabel Class Memoranda (child 74)
// ---------------------------------------------------------------------
foreach ($xp->query('.//w:tc', $children[74]) as $tc) {
    if ($cellText($tc) === 'N/A') {
        $clearNode($tc);
        $tc->appendChild($markerP('memoranda'));
    }
}

// ---------------------------------------------------------------------
// 11. BAB III — ganti seluruh konten (child 78..130) dengan ${bab3}
//     Heading "BAB III." (child 77) dipertahankan (target TOC).
// ---------------------------------------------------------------------
$body->insertBefore($markerP('bab3'), $children[78]);
foreach (range(78, 130) as $i) {
    $body->removeChild($children[$i]);
}

// ---------------------------------------------------------------------
// 12. BAB IV — ganti konten saran (child 132..188) dengan ${bab4}
//     Heading "BAB IV." (child 131) dipertahankan.
// ---------------------------------------------------------------------
$body->insertBefore($markerP('bab4'), $children[132]);
foreach (range(132, 188) as $i) {
    $body->removeChild($children[$i]);
}

// ---------------------------------------------------------------------
// Tulis ulang DOCX dengan document.xml hasil transformasi
// ---------------------------------------------------------------------
$newXml = $doc->saveXML();

$tmpTarget = $target.'.tmp';
$out = new ZipArchive;
if ($out->open($tmpTarget, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Gagal membuat {$tmpTarget}\n");
    exit(1);
}

$in = new ZipArchive;
$in->open($source);
for ($i = 0; $i < $in->numFiles; $i++) {
    $name = $in->getNameIndex($i);
    $out->addFromString($name, $name === 'word/document.xml'
        ? $newXml
        : $in->getFromName($name));
}
$in->close();
$out->close();
rename($tmpTarget, $target);

// Verifikasi marker terpasang
$check = file_get_contents('zip://'.realpath($target).'#word/document.xml');
$markers = ['report_no', 'ship_name', 'report_title', 'approval_place_date', 'approver_name',
    'inspector_1', 'inspector_2', 'exec_summary', 'cap_breakdown', 'benchmark_chart',
    'temuan_table', 'bab1_contract', 'bab1_general', 'status_class_table', 'memoranda',
    'bab3', 'bab4', 'sp_name', 'sp_main_engine', 'sp_aux_power'];
$missing = array_filter($markers, fn ($m) => ! str_contains($check, '${'.$m.'}'));

if ($missing) {
    fwrite(STDERR, 'MARKER HILANG: '.implode(', ', $missing)."\n");
    exit(1);
}

echo "OK: {$target}\n";
echo 'Ukuran: '.number_format(filesize($target) / 1024, 1)." KB\n";
