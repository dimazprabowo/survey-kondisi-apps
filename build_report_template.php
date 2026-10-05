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

/** Paragraf pertama dalam sebuah sel <w:tc>. */
$firstP = function (DOMElement $tc): ?DOMElement {
    foreach ($tc->childNodes as $c) {
        if ($c instanceof DOMElement && $c->localName === 'p') {
            return $c;
        }
    }

    return null;
};

/**
 * Ganti teks sebuah paragraf menjadi placeholder TANPA menghapus
 * w:pPr paragraf dan w:rPr run pertama — alignment, spacing, indent,
 * dan format font asli master tetap terjaga.
 */
$setTextKeepPr = function (DOMElement $p, string $text) use ($doc, $W): void {
    $rPr = null;
    foreach ($p->getElementsByTagNameNS($W, 'r') as $r) {
        foreach ($r->childNodes as $ch) {
            if ($ch instanceof DOMElement && $ch->localName === 'rPr') {
                $rPr = $ch->cloneNode(true);
                break 2;
            }
        }
    }
    foreach (iterator_to_array($p->childNodes) as $ch) {
        if ($ch instanceof DOMElement && $ch->localName === 'pPr') {
            continue;
        }
        $p->removeChild($ch);
    }
    $r = $doc->createElementNS($W, 'w:r');
    if ($rPr) {
        $r->appendChild($rPr);
    }
    $t = $doc->createElementNS($W, 'w:t', $text);
    $t->setAttribute('xml:space', 'preserve');
    $r->appendChild($t);
    $p->appendChild($r);
};

/** Terapkan line spacing (w:line + w:lineRule=auto) ke semua paragraf dalam node. */
$applyLine = function (DOMElement $node, int $line) use ($doc, $W): void {
    // Urutan valid w:pPr: spacing harus sebelum ind/jc/rPr
    $afterSpacing = ['ind', 'contextualSpacing', 'mirrorIndents', 'suppressOverlap',
        'jc', 'textDirection', 'textAlignment', 'textboxTightWrap', 'outlineLvl',
        'divId', 'cnfStyle', 'rPr', 'sectPr', 'pPrChange'];
    foreach ($node->getElementsByTagNameNS($W, 'p') as $p) {
        $pPr = null;
        foreach ($p->childNodes as $ch) {
            if ($ch instanceof DOMElement && $ch->localName === 'pPr') {
                $pPr = $ch;
                break;
            }
        }
        if (! $pPr) {
            $pPr = $doc->createElementNS($W, 'w:pPr');
            $p->insertBefore($pPr, $p->firstChild);
        }
        $spacing = null;
        foreach ($pPr->childNodes as $ch) {
            if ($ch instanceof DOMElement && $ch->localName === 'spacing') {
                $spacing = $ch;
                break;
            }
        }
        if (! $spacing) {
            $spacing = $doc->createElementNS($W, 'w:spacing');
            $anchor = null;
            foreach ($pPr->childNodes as $ch) {
                if ($ch instanceof DOMElement && in_array($ch->localName, $afterSpacing, true)) {
                    $anchor = $ch;
                    break;
                }
            }
            $pPr->insertBefore($spacing, $anchor);
        }
        $spacing->setAttributeNS($W, 'w:line', (string) $line);
        $spacing->setAttributeNS($W, 'w:lineRule', 'auto');
    }
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
    if (count($cells) >= 3 && isset($pengesahanMap[$cellText($cells[0])]) && ($vp = $firstP($cells[2]))) {
        $setTextKeepPr($vp, $pengesahanMap[$cellText($cells[0])]);
    }
}

// ---------------------------------------------------------------------
// 2. Tabel tanda tangan lembar pengesahan (child 20)
//    Diproses SEBELUM replace global "x..." agar x's nama approver
//    tidak tertukar dengan ${report_no}.
//    Teks diganti DI DALAM paragraf aslinya (setTextKeepPr) agar
//    alignment center, indent, dan format font master tetap terjaga —
//    serta paragraf spacer ruang tanda tangan tidak ikut terhapus.
// ---------------------------------------------------------------------
$placed = [];
foreach ($xp->query('.//w:p', $children[20]) as $p) {
    $text = trim(preg_replace('/\s+/', ' ', $p->textContent));
    $key = null;
    if (str_contains($text, 'dd/mm/')) {
        $key = 'approval_place_date';
    } elseif ($text === 'Inspector 1') {
        $key = 'inspector_1';
    } elseif ($text === 'Inspector 2') {
        $key = 'inspector_2';
    } elseif (preg_match('/^x+(\s+x+)*$/', $text)) {
        // Baris "xxxxxxx xxxxx" = nama penandatangan (Kepala Cabang)
        $key = 'approver_name';
    }
    if ($key === null) {
        continue;
    }
    // Placeholder hanya di paragraf pertama yang cocok; paragraf
    // x-cadangan berikutnya dikosongkan agar nama tidak terduplikasi.
    $setTextKeepPr($p, ($placed[$key] ?? false) ? '' : '${'.$key.'}');
    $placed[$key] = true;
}

// ---------------------------------------------------------------------
// 2b. Lembar pengesahan (child 11..20) — jarak antar baris single (1.0)
// ---------------------------------------------------------------------
foreach (range(11, 20) as $i) {
    $applyLine($children[$i], 240);
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
    } elseif (str_contains($text, 'posisi EXAMPLE terhadap')) {
        // Kalimat caption grafik benchmark mengandung nama kapal contoh
        $t->textContent = str_replace('EXAMPLE', '${ship_name}', $text);
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
//    (child 23..36) diganti marker ${exec_summary} lalu ${cap_breakdown}.
//    PENTING: child 25 memuat w:sectPr section ASDP (headerReference
//    rId14-17 = header/footer berlogo ASDP + footer2). SectPr dipindah ke
//    paragraf baru setelah marker agar header/footer ASDP tetap dipakai
//    mulai EXECUTIVE SUMMARY sampai akhir dokumen (body-end sectPr
//    mewarisi header section ini).
// ---------------------------------------------------------------------
$sectPrExec = $xp->query('./w:pPr/w:sectPr', $children[25])->item(0);

$body->insertBefore($markerP('exec_summary'), $children[23]);
$body->insertBefore($markerP('cap_breakdown'), $children[23]);
if ($sectPrExec) {
    $pBreak = $doc->createElementNS($W, 'w:p');
    $pBreakPr = $doc->createElementNS($W, 'w:pPr');
    $pBreakPr->appendChild($sectPrExec);
    $pBreak->appendChild($pBreakPr);
    $body->insertBefore($pBreak, $children[23]);
}
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
// 7. BAB I — paragraf kontrak (54), narasi umum (55), dan CAP intro (56)
//    adalah teks tetap master — dipertahankan verbatim. Daftar standar
//    CAP (57-60) diganti marker ${bab1_standards}.
// ---------------------------------------------------------------------
$body->insertBefore($markerP('bab1_standards'), $children[57]);
foreach (range(57, 60) as $i) {
    $body->removeChild($children[$i]);
}

// ---------------------------------------------------------------------
// 7b. Perbaiki border "nil" pada tabel CAP (child 61 & 64):
//     - right=nil pada sel terakhir baris (tepi kanan tabel kosong)
//     - bottom=nil pada sel penutup vMerge / baris terakhir (garis
//       bawah sel merged hilang — mis. bawah kolom CATEGORY "F")
// ---------------------------------------------------------------------
$fixCapTableBorders = function (DOMElement $tbl) use ($W, $rowCells): void {
    $gridCount = $tbl->getElementsByTagNameNS($W, 'gridCol')->length;
    $rows = [];
    foreach ($tbl->childNodes as $c) {
        if ($c instanceof DOMElement && $c->localName === 'tr') {
            $rows[] = $c;
        }
    }
    $fixBorder = function (?DOMElement $b) use ($W): void {
        $b->setAttributeNS($W, 'w:val', 'single');
        $b->setAttributeNS($W, 'w:sz', '4');
        $b->setAttributeNS($W, 'w:space', '0');
        $b->setAttributeNS($W, 'w:color', '000000');
    };
    foreach ($rows as $ri => $tr) {
        $col = 0;
        foreach ($rowCells($tr) as $tc) {
            $tcPr = $tc->getElementsByTagNameNS($W, 'tcPr')->item(0);
            $gs = $tcPr?->getElementsByTagNameNS($W, 'gridSpan')->item(0);
            $span = $gs ? (int) $gs->getAttributeNS($W, 'val') : 1;
            $tb = $tcPr?->getElementsByTagNameNS($W, 'tcBorders')->item(0);
            if ($tb) {
                $right = $tb->getElementsByTagNameNS($W, 'right')->item(0);
                if ($right?->getAttributeNS($W, 'val') === 'nil' && $col + $span === $gridCount) {
                    $fixBorder($right);
                }
                $bottom = $tb->getElementsByTagNameNS($W, 'bottom')->item(0);
                if ($bottom?->getAttributeNS($W, 'val') === 'nil') {
                    // Merge lanjut bila sel di kolom sama pada baris
                    // berikutnya adalah vMerge continuation.
                    $mergeContinues = false;
                    if (isset($rows[$ri + 1])) {
                        $ncol = 0;
                        foreach ($rowCells($rows[$ri + 1]) as $ntc) {
                            $npr = $ntc->getElementsByTagNameNS($W, 'tcPr')->item(0);
                            $ngs = $npr?->getElementsByTagNameNS($W, 'gridSpan')->item(0);
                            $nspan = $ngs ? (int) $ngs->getAttributeNS($W, 'val') : 1;
                            if ($ncol === $col) {
                                $nvm = $npr?->getElementsByTagNameNS($W, 'vMerge')->item(0);
                                $mergeContinues = $nvm && $nvm->getAttributeNS($W, 'val') !== 'restart';
                                break;
                            }
                            $ncol += $nspan;
                        }
                    }
                    if (! $mergeContinues) {
                        $fixBorder($bottom);
                    }
                }
            }
            $col += $span;
        }
    }
};
$fixCapTableBorders($children[61]);
$fixCapTableBorders($children[64]);

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
    if (count($cells) >= 3 && isset($particularsMap[$cellText($cells[0])]) && ($vp = $firstP($cells[2]))) {
        $setTextKeepPr($vp, $particularsMap[$cellText($cells[0])]);
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
    $content = $name === 'word/document.xml' ? $newXml : $in->getFromName($name);

    // Daftar isi adalah TOC field — minta Word update field saat dokumen
    // dibuka agar nomor halaman tidak basi (semua "1" hasil generate).
    // w:updateFields wajib di akhir sequence CT_Settings — sebelum
    // hdrShapeDefaults/footnotePr/endnotePr/compat/docVars/rsids.
    if ($name === 'word/settings.xml' && ! str_contains($content, 'updateFields')) {
        foreach (['hdrShapeDefaults', 'footnotePr', 'endnotePr', 'compat', 'docVars', 'rsids'] as $anchor) {
            if (str_contains($content, '<w:'.$anchor)) {
                $content = preg_replace(
                    '/<w:'.$anchor.'\b/',
                    '<w:updateFields w:val="true"/>$0',
                    $content, 1
                );
                break;
            }
        }
        if (! str_contains($content, 'updateFields')) {
            $content = str_replace('</w:settings>', '<w:updateFields w:val="true"/></w:settings>', $content);
        }
    }

    $out->addFromString($name, $content);
}
$in->close();
$out->close();
rename($tmpTarget, $target);

// Verifikasi marker terpasang
$check = file_get_contents('zip://'.realpath($target).'#word/document.xml');
$markers = ['report_no', 'ship_name', 'report_title', 'approval_place_date', 'approver_name',
    'inspector_1', 'inspector_2', 'exec_summary', 'cap_breakdown', 'benchmark_chart',
    'temuan_table', 'bab1_standards', 'status_class_table', 'memoranda',
    'bab3', 'bab4', 'sp_name', 'sp_main_engine', 'sp_aux_power'];
$missing = array_filter($markers, fn ($m) => ! str_contains($check, '${'.$m.'}'));

if ($missing) {
    fwrite(STDERR, 'MARKER HILANG: '.implode(', ', $missing)."\n");
    exit(1);
}

echo "OK: {$target}\n";
echo 'Ukuran: '.number_format(filesize($target) / 1024, 1)." KB\n";
