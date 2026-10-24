<?php
// ==========================================================
// charts.php - dashboard charts drawn as plain SVG / CSS (no JS library)
// ==========================================================
if (!function_exists('e')) { http_response_code(403); exit('Forbidden'); }

/** Rows created per month this year, from a table's date column. */
function get_monthly_counts(mysqli $conn, string $table, string $dateCol, string $extraWhere = '1=1'): array {
    $counts = array_fill(1, 12, 0);
    $year = (int) date('Y');
    $res = db_run($conn,
        "SELECT MONTH(`$dateCol`) AS m, COUNT(*) AS c FROM `$table`
         WHERE YEAR(`$dateCol`) = ? AND ($extraWhere) GROUP BY MONTH(`$dateCol`)", 'i', [$year])->get_result();
    while ($row = $res->fetch_assoc()) $counts[(int) $row['m']] = (int) $row['c'];
    return $counts;
}

function render_line_chart(array $counts, string $color): string {
    $values = array_values($counts);
    $max = max(1, max($values));
    $w = 420; $h = 150; $padL = 28; $padB = 20; $plotW = $w - $padL - 12; $plotH = $h - $padB - 14;

    $points = [];
    foreach ($values as $i => $v) {
        $points[] = [round($padL + ($i / 11) * $plotW, 1), round(12 + $plotH - ($v / $max) * $plotH, 1)];
    }
    $poly = implode(' ', array_map(function ($p) { return "{$p[0]},{$p[1]}"; }, $points));
    $baseY = 12 + $plotH;
    $area = $poly . " {$points[11][0]},$baseY {$points[0][0]},$baseY";

    $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $svgLabels = '';
    foreach ($labels as $i => $l) {
        $svgLabels .= '<text x="' . $points[$i][0] . '" y="' . ($h - 4) . '" font-size="10" fill="#8a94a6" text-anchor="middle">' . $l . '</text>';
    }
    $grid = '';
    foreach ([0, 0.5, 1] as $frac) {
        $gy = 12 + $plotH - $frac * $plotH;
        $grid .= '<line x1="' . $padL . '" y1="' . $gy . '" x2="' . ($w - 12) . '" y2="' . $gy . '" stroke="#e8ecf3"/>'
              .  '<text x="2" y="' . ($gy + 3) . '" font-size="10" fill="#8a94a6">' . round($max * $frac) . '</text>';
    }
    $dots = '';
    foreach ($points as $i => $p) {
        if ($values[$i] > 0) $dots .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="3.5" fill="#fff" stroke="' . $color . '" stroke-width="2"/>';
    }
    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" role="img" aria-label="Monthly chart" xmlns="http://www.w3.org/2000/svg">'
        . $grid
        . '<polygon points="' . $area . '" fill="' . $color . '" fill-opacity="0.12"/>'
        . '<polyline points="' . $poly . '" fill="none" stroke="' . $color . '" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>'
        . $dots . $svgLabels . '</svg>';
}

/** Horizontal bar list. $items = [[label, value], ...] */
function render_bars(array $items, string $color = 'var(--primary)'): string {
    if (!$items) return '<p class="muted">No data yet.</p>';
    $max = 1;
    foreach ($items as $it) $max = max($max, (int) $it[1]);
    $html = '<div class="bars">';
    foreach ($items as $it) {
        $pct = round(((int) $it[1] / $max) * 100);
        $html .= '<div class="bar-row"><span class="bar-label">' . e($it[0]) . '</span>'
              .  '<span class="bar-track"><span class="bar-fill" style="width:' . $pct . '%;background:' . $color . '"></span></span>'
              .  '<span class="bar-val">' . (int) $it[1] . '</span></div>';
    }
    return $html . '</div>';
}

/** Donut + legend. $items = [[label, value, color], ...] */
function render_donut(array $items, string $centerLabel): string {
    $total = 0;
    foreach ($items as $it) $total += (int) $it[1];
    $parts = []; $cursor = 0.0; $legend = '';
    foreach ($items as $it) {
        $pct = $total > 0 ? ((int) $it[1] / $total) * 100 : 0;
        if ($pct > 0) $parts[] = $it[2] . ' ' . round($cursor, 2) . '% ' . round($cursor + $pct, 2) . '%';
        $cursor += $pct;
        $legend .= '<li><span class="dot" style="background:' . $it[2] . '"></span><span>' . e($it[0]) . '</span><strong>' . (int) $it[1] . '</strong></li>';
    }
    $css = $parts ? implode(', ', $parts) : '#e8ecf3 0% 100%';
    return '<div class="donut-wrap"><div class="donut" style="background:conic-gradient(' . $css . ')"><span>' . $total . '<small>' . e($centerLabel) . '</small></span></div>'
         . '<ul class="legend">' . $legend . '</ul></div>';
}
