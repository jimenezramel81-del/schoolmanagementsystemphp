<?php
// ==========================================================
// layout.php - the page shell shared by every logged-in page
//   render_header($title, $activeKey, ['subtitle' => '', 'actions' => '<html>'])
//   ... page content ...
//   render_footer();
// ==========================================================
if (!function_exists('can_access')) { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/icons.php';

/** Sidebar menu. A link only appears if the user's role may open it (see access.php). */
function nav_groups(): array {
    return [
        'Overview' => [
            ['dashboard',   'Dashboard',     'dashboard',  'dashboard.php'],
        ],
        'Academics' => [
            ['course',      'Courses',       'book',       module_url('course')],
            ['subjects',    'Subjects',      'book-open',  module_url('subjects')],
            ['section',     'Sections',      'grid',       module_url('section')],
            ['yearlevel',   'Year Levels',   'layers',     module_url('yearlevel')],
            ['term',        'Terms',         'clock',      module_url('term')],
            ['schoolyear',  'School Years',  'calendar',   module_url('schoolyear')],
        ],
        'People' => [
            ['students',    'Students',      'users',      module_url('students')],
            ['instructors', 'Instructors',   'briefcase',  module_url('instructors')],
            ['my_record',   'My Record',     'user',       module_url('my_record')],
        ],
        'Classes' => [
            ['schedules',   'Class Schedules', 'calendar', module_url('schedules')],
            ['enrollments', 'Enrollments',   'user-plus',  module_url('enrollments')],
            ['grades',      'Grades',        'award',      module_url('grades')],
            ['attendance',  'Attendance',    'check-square', module_url('attendance')],
        ],
        'Reports' => [
            ['reports',     'Reports',       'bar-chart',  module_url('reports')],
        ],
        'Administration' => [
            ['users',       'Users',         'user',       module_url('users')],
            ['usertype',    'User Types',    'shield',     module_url('usertype')],
        ],
    ];
}

function render_header(string $title, string $active, array $opts = []): void {
    $fullname = $_SESSION['fullname'] ?? '';
    $role = current_role();
    $flash = flash_get();

    // The top search box jumps to students (staff) or subjects (everyone else)
    if (can_access('students')) { $searchModule = 'students'; $searchHint = 'Search students…'; }
    else                        { $searchModule = 'subjects'; $searchHint = 'Search subjects…'; }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(SCHOOL_NAME) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app">

    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <span class="brand-badge"><?= icon('book-open', 20) ?></span>
            <span class="brand-text">
                <span class="brand-name"><?= e(SCHOOL_NAME) ?></span>
                <span class="brand-sub"><?= e(SCHOOL_TAGLINE) ?></span>
            </span>
        </div>

        <nav class="nav">
            <?php foreach (nav_groups() as $group => $items):
                $visible = array_filter($items, function ($it) {
                    if ($it[0] === 'dashboard') return true;
                    if ($it[0] === 'my_record') return current_role() === 'Student';   // only students have a personal record
                    return can_access($it[0]);
                });
                if (!$visible) continue; ?>
                <div class="nav-group"><?= e($group) ?></div>
                <?php foreach ($visible as $it): ?>
                    <a href="<?= e($it[3]) ?>" class="nav-link <?= $active === $it[0] ? 'active' : '' ?>">
                        <?= icon($it[2], 18) ?><span><?= e($it[1]) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <div class="me">
                <span class="avatar"><?= e(initials($fullname)) ?></span>
                <span class="me-text">
                    <span class="me-name"><?= e($fullname) ?></span>
                    <span class="me-role"><?= e($role) ?></span>
                </span>
            </div>
            <a class="logout" href="logout.php" title="Log out"><?= icon('log-out', 18) ?></a>
        </div>
    </aside>
    <div class="scrim" id="scrim"></div>

    <div class="content">
        <header class="topbar no-print">
            <button type="button" class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu"><?= icon('menu', 20) ?></button>
            <form class="global-search" method="get" action="manage.php" role="search">
                <input type="hidden" name="module" value="<?= e($searchModule) ?>">
                <?= icon('search', 18) ?>
                <input type="search" name="q" placeholder="<?= e($searchHint) ?>" aria-label="Search">
            </form>
            <div class="topbar-user">
                <span class="today"><?= icon('calendar', 16) ?> <?= date('l, F j, Y') ?></span>
            </div>
        </header>

        <main class="page">
            <div class="page-head">
                <div>
                    <h1><?= e($title) ?></h1>
                    <?php if (!empty($opts['subtitle'])): ?><p class="page-sub"><?= e($opts['subtitle']) ?></p><?php endif; ?>
                </div>
                <?php if (!empty($opts['actions'])): ?><div class="page-actions no-print"><?= $opts['actions'] /* trusted HTML built by the page */ ?></div><?php endif; ?>
            </div>

            <?php if ($flash): ?>
                <div class="toast toast-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="status">
                    <?= icon($flash['type'] === 'success' ? 'check-circle' : 'alert', 18) ?>
                    <span><?= e($flash['msg']) ?></span>
                    <button type="button" class="toast-x" aria-label="Dismiss"><?= icon('x', 16) ?></button>
                </div>
            <?php endif; ?>
<?php
}

function render_footer(): void {
    ?>
        </main>
    </div>
</div>
<script src="app.js"></script>
</body>
</html>
<?php
}
