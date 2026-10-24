<?php
// ==========================================================
// export.php?module=<key>[&q=...&f_FIELD=...]
// Downloads the current list (with the same search + filters,
// ALL pages) as a real .xlsx workbook.
// ==========================================================
require "auth_check.php";
require "db.php";
require "config.php";
require "crud.php";
require "xlsx_writer.php";

$module = $_GET['module'] ?? '';
if (!is_string($module) || !isset($modules[$module])) {
    header("Location: dashboard.php");
    exit;
}
require_access($module);          // same role rules as the page itself

$cfg = $modules[$module];
$idField = $cfg['id_field'] ?? 'ID';
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';

$qry = build_query($cfg, $q, read_filters($cfg));
$result = db_run($conn, $qry['select'] . " ORDER BY m.`$idField` ASC", $qry['types'], $qry['params'])->get_result();

// Columns: never export password hashes or photo file names
$headers = ['ID'];
$exportFields = [];
$numericCols = [0];
foreach ($cfg['fields'] as $f) {
    if ($f['type'] === 'password' || $f['type'] === 'image') continue;
    $headers[] = $f['label'];
    $exportFields[] = $f;
    if ($f['type'] === 'number') $numericCols[] = count($headers) - 1;
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $line = [$row[$idField]];
    foreach ($exportFields as $f) {
        $v = $row[$f['name']] ?? '';
        switch ($f['type']) {
            case 'select': $v = $row[$f['name'] . '_LABEL'] ?? ''; break;
            case 'time':   $v = $v ? fmt_time($v) : ''; break;
        }
        $line[] = $v;
    }
    $rows[] = $line;
}

download_xlsx($module . '_export_' . date('Y-m-d') . '.xlsx', $cfg['label'], $headers, $rows, $numericCols);
