<?php
// ==========================================================
// xlsx_writer.php
// Builds a real .xlsx workbook using only PHP's built-in
// ZipArchive extension (enabled by default in XAMPP).
// No PhpSpreadsheet / Composer / internet needed.
// ==========================================================

function xml_escape($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function col_letter($index) {            // 0 => A, 25 => Z, 26 => AA ...
    $letter = '';
    $index++;
    while ($index > 0) {
        $rem = ($index - 1) % 26;
        $letter = chr(65 + $rem) . $letter;
        $index = intdiv($index - 1, 26);
    }
    return $letter;
}

/**
 * Streams a one-sheet .xlsx workbook to the browser and exits.
 * $numericCols = 0-based columns written as real numbers; everything else is
 * text, so values like "user00001" never lose their leading zeros.
 */
function download_xlsx($filename, $sheetName, $headers, $rows, $numericCols = []) {
    $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmpPath, ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
        '</Types>');

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>');

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
        '</Relationships>');

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="' . xml_escape(substr($sheetName, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets>' .
        '</workbook>');

    $zip->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>' .
        '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
        '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>' .
        '</styleSheet>');

    $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    // Column widths sized to the longest value; header row frozen
    $widths = [];
    foreach ($headers as $i => $h) $widths[$i] = mb_strlen((string) $h);
    foreach ($rows as $row) {
        foreach ($row as $i => $val) {
            $len = mb_strlen((string) $val);
            if (!isset($widths[$i]) || $len > $widths[$i]) $widths[$i] = $len;
        }
    }
    $cols = '<cols>';
    foreach ($widths as $i => $w) {
        $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . min(50, max(8, $w + 3)) . '" customWidth="1"/>';
    }
    $cols .= '</cols>';

    $x .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . $cols . '<sheetData><row r="1">';
    foreach ($headers as $i => $h) {
        $x .= '<c r="' . col_letter($i) . '1" t="inlineStr" s="1"><is><t>' . xml_escape($h) . '</t></is></c>';
    }
    $x .= '</row>';

    $r = 2;
    foreach ($rows as $row) {
        $x .= '<row r="' . $r . '">';
        foreach ($row as $i => $val) {
            $ref = col_letter($i) . $r;
            if (in_array($i, $numericCols, true) && is_numeric($val)) {
                $x .= '<c r="' . $ref . '"><v>' . xml_escape($val) . '</v></c>';
            } else {
                $x .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . xml_escape($val) . '</t></is></c>';
            }
        }
        $x .= '</row>';
        $r++;
    }
    $x .= '</sheetData></worksheet>';
    $zip->addFromString('xl/worksheets/sheet1.xml', $x);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpPath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($tmpPath);
    unlink($tmpPath);
    exit;
}
