<?php
// ==========================================================
// access.php
// Role-based access control - the ONE place that decides which
// usertype may open which page, and whether it can change data.
//
//   'rw' = can view, add, edit, delete / save
//   'r'  = view only
//   (missing) = no access (page hidden and blocked server-side)
//
// Keys match the module keys in config.php, plus the special pages:
//   grades, attendance, reports, my_record
// Role names must match tbl_usertype.USERTYPE exactly.
// ==========================================================
if (!function_exists('e')) { http_response_code(403); exit('Forbidden'); }

$rolePermissions = [
    'Admin' => ['*' => 'rw'],

    'Registrar' => [
        'students' => 'rw', 'enrollments' => 'rw', 'subjects' => 'rw', 'term' => 'rw',
        'schoolyear' => 'rw', 'instructors' => 'rw',
        'course' => 'r', 'section' => 'r', 'yearlevel' => 'r', 'schedules' => 'r',
        'grades' => 'r', 'attendance' => 'r', 'reports' => 'r',
    ],

    'Dean' => [
        'subjects' => 'rw', 'section' => 'rw', 'instructors' => 'rw', 'yearlevel' => 'rw', 'schedules' => 'rw',
        'course' => 'r', 'students' => 'r', 'enrollments' => 'r',
        'grades' => 'r', 'attendance' => 'r', 'reports' => 'r',
    ],

    'Instructor' => [
        'subjects' => 'rw', 'section' => 'rw',
        'schedules' => 'r', 'students' => 'r', 'enrollments' => 'r',
        'grades' => 'rw', 'attendance' => 'rw', 'reports' => 'r',
    ],

    'Student' => [
        'subjects' => 'r',
        'my_record' => 'r',
    ],
];

function current_role(): string {
    return $_SESSION['usertype'] ?? '';
}

function perm_level(string $module): string {
    global $rolePermissions;
    $perms = $rolePermissions[current_role()] ?? [];
    if (isset($perms[$module])) return $perms[$module];
    if (isset($perms['*'])) return $perms['*'];
    return '';
}

function can_access(string $module): bool {
    return perm_level($module) !== '';
}

function can_modify(string $module): bool {
    return perm_level($module) === 'rw';
}

// Server-side gate: call at the top of every page.
// Hiding a menu link is not security - this is.
function require_access(string $module): void {
    if (!can_access($module)) {
        flash_set('error', "Access denied: your user type (" . current_role() . ") cannot open that page.");
        header("Location: dashboard.php");
        exit;
    }
}

function module_url(string $module): string {
    switch ($module) {
        case 'grades':     return 'grades.php';
        case 'attendance': return 'attendance.php';
        case 'reports':    return 'reports.php';
        case 'my_record':  return 'student_view.php?me=1';
    }
    return 'manage.php?module=' . urlencode($module);
}
