<?php
// ==========================================================
// helpers.php - small shared functions used by every page
// ==========================================================
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'helpers.php') { http_response_code(403); exit('Forbidden'); }

const SCHOOL_NAME = 'MSTIP';
const SCHOOL_TAGLINE = 'School Management System';

/** Escape for HTML output. */
function e($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// ---------- CSRF protection ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_valid(): bool {
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

// ---------- One-time messages (shown after a redirect) ----------
function flash_set(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function flash_get(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Display helpers ----------
function initials(string $name): string {
    $out = '';
    foreach (preg_split('/\s+/', trim($name)) as $part) {
        if ($part !== '') $out .= strtoupper(substr($part, 0, 1));
    }
    return substr($out, 0, 2) ?: '?';
}
function fmt_time($t): string {
    return $t ? date('g:i A', strtotime((string) $t)) : '';
}
function fmt_date($d): string {
    return ($d && $d !== '0000-00-00') ? date('M j, Y', strtotime((string) $d)) : '';
}
function fmt_datetime($d): string {
    return $d ? date('M j, Y g:i A', strtotime((string) $d)) : '';
}
function full_name(array $r, string $prefix = ''): string {
    $parts = [];
    foreach (['SALUTATION', 'FIRSTNAME', 'MIDDLENAME', 'LASTNAME'] as $k) {
        if (!empty($r[$prefix . $k])) $parts[] = $r[$prefix . $k];
    }
    return implode(' ', $parts);
}

/** Colour class for a status word (Active, Present, Passed ...). */
function badge_class($v): string {
    switch (strtolower((string) $v)) {
        case 'active': case 'present': case 'passed': return 'badge-green';
        case 'inactive': return 'badge-gray';
        case 'graduated': case 'excused': return 'badge-blue';
        case 'late': case 'in progress': return 'badge-amber';
        case 'dropped': case 'absent': case 'failed': return 'badge-red';
    }
    return 'badge-gray';
}
function badge($v): string {
    return '<span class="badge ' . badge_class($v) . '">' . e($v) . '</span>';
}

// ---------- Grading (Philippine 1.00 - 5.00 scale, passing = 75) ----------
const PASSING_GRADE = 75;

/** Final grade (percent) = average of prelim, midterm, final. Null until all three are in. */
function grade_percent($prelim, $midterm, $final): ?float {
    if ($prelim === null || $midterm === null || $final === null || $prelim === '' || $midterm === '' || $final === '') return null;
    return round(((float) $prelim + (float) $midterm + (float) $final) / 3, 2);
}
/** Percent -> 1.00 .. 5.00 equivalent. */
function grade_equivalent(?float $pct): ?float {
    if ($pct === null) return null;
    if ($pct >= 96) return 1.00;
    if ($pct >= 94) return 1.25;
    if ($pct >= 91) return 1.50;
    if ($pct >= 88) return 1.75;
    if ($pct >= 85) return 2.00;
    if ($pct >= 82) return 2.25;
    if ($pct >= 79) return 2.50;
    if ($pct >= 76) return 2.75;
    if ($pct >= PASSING_GRADE) return 3.00;
    return 5.00;
}
function grade_remarks(?float $pct): string {
    if ($pct === null) return 'In Progress';
    return $pct >= PASSING_GRADE ? 'Passed' : 'Failed';
}
function fmt_num($v, int $dec = 2): string {
    return ($v === null || $v === '') ? '—' : number_format((float) $v, $dec);
}

// ---------- Link the logged-in user to an instructor / student record ----------
function my_instructor_id(mysqli $conn): ?int {
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $s = $conn->prepare("SELECT ID FROM tbl_instructors WHERE USER_ID = ? LIMIT 1");
    $s->bind_param('i', $uid);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? (int) $r['ID'] : null;
}
function my_student_id(mysqli $conn): ?int {
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $s = $conn->prepare("SELECT ID FROM tbl_students WHERE USER_ID = ? LIMIT 1");
    $s->bind_param('i', $uid);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? (int) $r['ID'] : null;
}

/** Build a URL for the current page, keeping the current query string and overriding some keys. */
function url_with(array $override = []): string {
    $p = array_merge($_GET, $override);
    foreach ($p as $k => $v) {
        if ($v === '' || $v === null) unset($p[$k]);
    }
    return '?' . http_build_query($p);
}
