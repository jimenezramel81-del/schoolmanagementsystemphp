<?php
// ==========================================================
// dashboard.php - overview. The content depends on the user type:
//   Admin / Registrar / Dean : school-wide statistics
//   Instructor               : own classes
//   Student                  : own classes, units and GWA
// ==========================================================
require "auth_check.php";
require "db.php";
require "crud.php";
require "charts.php";
require "class_helpers.php";
require "layout.php";

$role = current_role();
$active = 'dashboard';
$dayAbbr = date('D');          // Mon, Tue ...

function stat_card(string $icon, string $tone, string $label, $value, string $link = '', string $note = ''): void {
    $tag = $link !== '' ? 'a href="' . e($link) . '"' : 'div';
    $end = $link !== '' ? 'a' : 'div';
    echo '<' . $tag . ' class="stat-card"><span class="stat-icon tone-' . $tone . '">' . icon($icon, 22) . '</span>'
       . '<span class="stat-body"><span class="stat-label">' . e($label) . '</span>'
       . '<span class="stat-value">' . e($value) . '</span>' . ($note !== '' ? '<span class="stat-note">' . $note . '</span>' : '')
       . '</span></' . $end . '>';
}

/** Classes happening today in the latest school year. */
function todays_classes(mysqli $conn, string $dayAbbr, string $extraJoin = '', string $extraWhere = '', string $types = '', array $params = []): array {
    $sql = "SELECT sc.ID, sub.SUBJECTCODE, sub.SUBJECTNAME, sec.SECTION, sc.DAYS, sc.TIME_START, sc.TIME_END, sc.ROOM,
                   CONCAT_WS(' ', i.SALUTATION, i.FIRSTNAME, i.LASTNAME) AS INSTRUCTOR
            FROM tbl_schedules sc
            JOIN tbl_subjects sub  ON sub.ID = sc.SUBJECT_ID
            JOIN tbl_section sec   ON sec.ID = sc.SECTION_ID
            JOIN tbl_instructors i ON i.ID = sc.INSTRUCTOR_ID
            $extraJoin
            WHERE sc.DAYS LIKE ? AND sc.SCHOOLYEAR_ID = (SELECT MAX(ID) FROM tbl_schoolyear) $extraWhere
            ORDER BY sc.TIME_START";
    $res = db_run($conn, $sql, 's' . $types, array_merge(['%' . $dayAbbr . '%'], $params))->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    return $out;
}

function render_today(array $list, string $emptyMsg): void { ?>
    <section class="card">
        <div class="card-head"><h2>Today’s classes</h2><span class="muted"><?= e(date('l')) ?></span></div>
        <?php if (!$list): ?>
            <div class="empty slim"><?= icon('calendar', 24) ?><span><?= e($emptyMsg) ?></span></div>
        <?php else: ?>
            <ul class="agenda">
                <?php foreach ($list as $c): ?>
                    <li>
                        <span class="agenda-time"><?= e(fmt_time($c['TIME_START'])) ?><small><?= e(fmt_time($c['TIME_END'])) ?></small></span>
                        <span class="agenda-body">
                            <strong><?= e($c['SUBJECTCODE']) ?> · <?= e($c['SUBJECTNAME']) ?></strong>
                            <small><?= e($c['SECTION']) ?> · <?= e($c['ROOM'] ?: 'No room') ?> · <?= e($c['INSTRUCTOR']) ?></small>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
<?php }

$firstName = explode(' ', trim($_SESSION['fullname']))[0] ?? '';
$hour = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

render_header('Dashboard', 'dashboard', ['subtitle' => "$greet, $firstName. Here’s what’s happening at " . SCHOOL_NAME . '.']);

// =====================================================================
// STUDENT
// =====================================================================
if ($role === 'Student'):
    $sid = my_student_id($conn);
    $classes = []; $units = 0; $weighted = 0.0; $gradedUnits = 0;
    if ($sid) {
        $res = db_run($conn,
            "SELECT sub.SUBJECTCODE, sub.SUBJECTNAME, sub.UNITS, sc.DAYS, sc.TIME_START, sc.TIME_END, sc.ROOM,
                    CONCAT_WS(' ', i.SALUTATION, i.FIRSTNAME, i.LASTNAME) AS INSTRUCTOR,
                    g.PRELIM, g.MIDTERM, g.FINALTERM
             FROM tbl_enrollments e
             JOIN tbl_schedules sc  ON sc.ID = e.SCHEDULE_ID
             JOIN tbl_subjects sub  ON sub.ID = sc.SUBJECT_ID
             JOIN tbl_instructors i ON i.ID = sc.INSTRUCTOR_ID
             LEFT JOIN tbl_grades g ON g.ENROLLMENT_ID = e.ID
             WHERE e.STUDENT_ID = ? AND sc.SCHOOLYEAR_ID = (SELECT MAX(ID) FROM tbl_schoolyear)
             ORDER BY sc.TIME_START", 'i', [$sid])->get_result();
        while ($r = $res->fetch_assoc()) {
            $pct = grade_percent($r['PRELIM'], $r['MIDTERM'], $r['FINALTERM']);
            $r['PCT'] = $pct; $r['EQ'] = grade_equivalent($pct);
            $units += (int) $r['UNITS'];
            if ($pct !== null) { $weighted += $r['EQ'] * (int) $r['UNITS']; $gradedUnits += (int) $r['UNITS']; }
            $classes[] = $r;
        }
    }
    $today = array_values(array_filter($classes, function ($c) use ($dayAbbr) { return strpos($c['DAYS'], $dayAbbr) !== false; }));
?>
    <?php if (!$sid): ?>
        <section class="card"><div class="empty"><?= icon('user', 28) ?><strong>Your account is not linked to a student record yet</strong><span>Please contact the Registrar.</span></div></section>
    <?php else: ?>
    <div class="stat-grid">
        <?php
        stat_card('book-open', 'blue', 'Classes this term', count($classes), 'student_view.php?me=1');
        stat_card('layers', 'purple', 'Units enrolled', $units);
        stat_card('award', 'green', 'Current GWA', $gradedUnits ? number_format($weighted / $gradedUnits, 2) : '—', 'student_view.php?me=1', '1.00 is the highest');
        stat_card('calendar', 'amber', 'Classes today', count($today));
        ?>
    </div>
    <div class="grid-2">
        <section class="card">
            <div class="card-head"><h2>My schedule</h2><a class="link" href="student_view.php?me=1">Full record →</a></div>
            <div class="table-wrap"><table class="data">
                <thead><tr><th>Subject</th><th>Days</th><th>Time</th><th>Room</th><th>Instructor</th></tr></thead>
                <tbody>
                <?php if (!$classes): ?><tr><td colspan="5"><div class="empty slim"><span>You are not enrolled in any class yet.</span></div></td></tr><?php endif; ?>
                <?php foreach ($classes as $c): ?>
                    <tr>
                        <td><strong><?= e($c['SUBJECTCODE']) ?></strong><div class="muted small"><?= e($c['SUBJECTNAME']) ?></div></td>
                        <td><?= e($c['DAYS']) ?></td>
                        <td><?= e(fmt_time($c['TIME_START'])) ?> – <?= e(fmt_time($c['TIME_END'])) ?></td>
                        <td><?= e($c['ROOM'] ?: '—') ?></td>
                        <td><?= e($c['INSTRUCTOR']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
        <?php render_today(array_map(function ($c) { return $c + ['SECTION' => '']; }, $today), 'No classes today. Enjoy your day!'); ?>
    </div>
    <?php endif; ?>

<?php
// =====================================================================
// INSTRUCTOR
// =====================================================================
elseif ($role === 'Instructor'):
    $iid = my_instructor_id($conn);
    $mine = $iid ? array_values(accessible_schedules($conn)) : [];
?>
    <?php if (!$iid): ?>
        <section class="card"><div class="empty"><?= icon('briefcase', 28) ?><strong>Your account is not linked to an instructor record yet</strong><span>Ask the Dean or an Admin to link your login to your instructor profile.</span></div></section>
    <?php else:
        $studentCount = 0; foreach ($mine as $m) $studentCount += (int) $m['ENROLLED'];
        $todayList = todays_classes($conn, $dayAbbr, '', ' AND sc.INSTRUCTOR_ID = ?', 'i', [$iid]);
    ?>
    <div class="stat-grid">
        <?php
        stat_card('calendar', 'blue', 'My classes', count($mine), 'grades.php');
        stat_card('users', 'purple', 'Student enrollments', $studentCount);
        stat_card('clock', 'amber', 'Classes today', count($todayList));
        ?>
    </div>
    <div class="grid-2">
        <section class="card">
            <div class="card-head"><h2>My classes</h2></div>
            <div class="table-wrap"><table class="data">
                <thead><tr><th>Subject</th><th>Section</th><th>Schedule</th><th class="num">Students</th><th class="col-actions">Open</th></tr></thead>
                <tbody>
                <?php if (!$mine): ?><tr><td colspan="5"><div class="empty slim"><span>No classes assigned yet.</span></div></td></tr><?php endif; ?>
                <?php foreach ($mine as $m): ?>
                    <tr>
                        <td><strong><?= e($m['SUBJECTCODE']) ?></strong><div class="muted small"><?= e($m['SUBJECTNAME']) ?></div></td>
                        <td><?= e($m['SECTION']) ?></td>
                        <td><?= e($m['DAYS']) ?> <?= e(fmt_time($m['TIME_START'])) ?></td>
                        <td class="num"><?= (int) $m['ENROLLED'] ?></td>
                        <td class="col-actions"><div class="row-actions">
                            <a class="act act-edit" href="grades.php?schedule=<?= (int) $m['ID'] ?>"><?= icon('award', 15) ?><span>Grades</span></a>
                            <a class="act act-view" href="attendance.php?schedule=<?= (int) $m['ID'] ?>"><?= icon('check-square', 15) ?><span>Attendance</span></a>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
        <?php render_today($todayList, 'You have no classes today.'); ?>
    </div>
    <?php endif; ?>

<?php
// =====================================================================
// ADMIN / REGISTRAR / DEAN
// =====================================================================
else:
    $one = function (string $sql) use ($conn) { return (int) $conn->query($sql)->fetch_assoc()['c']; };
    $totalStudents = $one("SELECT COUNT(*) c FROM tbl_students WHERE STATUS = 'Active'");
    $totalInstr    = $one("SELECT COUNT(*) c FROM tbl_instructors");
    $totalCourses  = $one("SELECT COUNT(*) c FROM tbl_course");
    $totalSubjects = $one("SELECT COUNT(*) c FROM tbl_subjects");
    $totalClasses  = $one("SELECT COUNT(*) c FROM tbl_schedules");
    $totalEnroll   = $one("SELECT COUNT(*) c FROM tbl_enrollments");

    $monthly = get_monthly_counts($conn, 'tbl_students', 'CREATED_AT');
    $vals = array_values($monthly);
    $idx = (int) date('n') - 1;
    $cur = $vals[$idx]; $prev = $idx > 0 ? $vals[$idx - 1] : 0;
    $growth = $prev > 0 ? round((($cur - $prev) / $prev) * 100) : ($cur > 0 ? 100 : 0);

    $byCourse = []; $res = $conn->query("SELECT c.COURSE, COUNT(s.ID) n FROM tbl_course c LEFT JOIN tbl_students s ON s.COURSE_ID = c.ID AND s.STATUS = 'Active' GROUP BY c.ID, c.COURSE ORDER BY c.COURSE");
    while ($r = $res->fetch_assoc()) $byCourse[] = [$r['COURSE'], $r['n']];

    $byYear = []; $res = $conn->query("SELECT y.YEARLEVEL, COUNT(s.ID) n FROM tbl_yearlevel y LEFT JOIN tbl_students s ON s.YEARLEVEL_ID = y.ID AND s.STATUS = 'Active' GROUP BY y.ID, y.YEARLEVEL ORDER BY y.ID");
    while ($r = $res->fetch_assoc()) $byYear[] = [$r['YEARLEVEL'], $r['n']];

    $statusColors = ['Active' => '#16a34a', 'Inactive' => '#94a3b8', 'Graduated' => '#2563eb', 'Dropped' => '#dc2626'];
    $statusItems = [];
    foreach ($statusColors as $st => $col) {
        $n = (int) db_run($conn, "SELECT COUNT(*) c FROM tbl_students WHERE STATUS = ?", 's', [$st])->get_result()->fetch_assoc()['c'];
        $statusItems[] = [$st, $n, $col];
    }

    $recent = $conn->query("SELECT s.ID, s.STUDENT_NO, s.FIRSTNAME, s.LASTNAME, s.PHOTO, s.STATUS, s.CREATED_AT, c.COURSE
                            FROM tbl_students s LEFT JOIN tbl_course c ON c.ID = s.COURSE_ID ORDER BY s.CREATED_AT DESC, s.ID DESC LIMIT 6");
    $todayList = todays_classes($conn, $dayAbbr);
?>
    <div class="stat-grid">
        <?php
        $note = '<span class="trend ' . ($growth >= 0 ? 'up' : 'down') . '">' . ($growth >= 0 ? '▲ ' : '▼ ') . abs($growth) . '% vs last month</span>';
        stat_card('users', 'blue', 'Active students', number_format($totalStudents), can_access('students') ? module_url('students') : '', $note);
        stat_card('briefcase', 'purple', 'Instructors', number_format($totalInstr), can_access('instructors') ? module_url('instructors') : '');
        stat_card('book', 'green', 'Courses', number_format($totalCourses), can_access('course') ? module_url('course') : '');
        stat_card('book-open', 'amber', 'Subjects', number_format($totalSubjects), can_access('subjects') ? module_url('subjects') : '');
        stat_card('calendar', 'pink', 'Class schedules', number_format($totalClasses), can_access('schedules') ? module_url('schedules') : '');
        stat_card('user-plus', 'teal', 'Enrollments', number_format($totalEnroll), can_access('enrollments') ? module_url('enrollments') : '');
        ?>
    </div>

    <div class="grid-3">
        <section class="card"><div class="card-head"><h2>Students by course</h2></div><?= render_bars($byCourse) ?></section>
        <section class="card"><div class="card-head"><h2>Students by year level</h2></div><?= render_bars($byYear, '#7c3aed') ?></section>
        <section class="card"><div class="card-head"><h2>Student status</h2></div><?= render_donut($statusItems, 'Students') ?></section>
    </div>

    <div class="grid-2">
        <section class="card">
            <div class="card-head"><h2>Recently added students</h2><?php if (can_access('students')): ?><a class="link" href="manage.php?module=students">View all →</a><?php endif; ?></div>
            <div class="table-wrap"><table class="data">
                <thead><tr><th>Student</th><th>Student No.</th><th>Course</th><th>Status</th><th>Added</th></tr></thead>
                <tbody>
                <?php if ($recent->num_rows === 0): ?><tr><td colspan="5"><div class="empty slim"><span>No students yet.</span></div></td></tr><?php endif; ?>
                <?php while ($r = $recent->fetch_assoc()): ?>
                    <tr>
                        <td><div class="name-cell">
                            <?php if ($r['PHOTO']): ?><img class="thumb" src="uploads/students/<?= e($r['PHOTO']) ?>" alt=""><?php else: ?><span class="mini-avatar"><?= e(initials($r['FIRSTNAME'] . ' ' . $r['LASTNAME'])) ?></span><?php endif; ?>
                            <strong><?= e($r['FIRSTNAME'] . ' ' . $r['LASTNAME']) ?></strong>
                        </div></td>
                        <td><?= e($r['STUDENT_NO']) ?></td>
                        <td><?= e($r['COURSE'] ?? '—') ?></td>
                        <td><?= badge($r['STATUS']) ?></td>
                        <td class="muted"><?= e(fmt_date($r['CREATED_AT'])) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table></div>
        </section>
        <?php render_today($todayList, 'No classes are scheduled for today.'); ?>
    </div>

    <div class="grid-2">
        <section class="card"><div class="card-head"><h2>New students this year</h2></div><?= render_line_chart($monthly, '#2563eb') ?></section>
        <?php if ($role === 'Admin'):
            $typeColors = ['Admin' => '#dc2626', 'Registrar' => '#d97706', 'Dean' => '#7c3aed', 'Instructor' => '#2563eb', 'Student' => '#16a34a'];
            $dist = [];
            $res = $conn->query("SELECT t.USERTYPE, COUNT(u.ID) c FROM tbl_usertype t LEFT JOIN tbl_users u ON u.USERTYPE_ID = t.ID GROUP BY t.ID, t.USERTYPE ORDER BY t.ID");
            while ($r = $res->fetch_assoc()) $dist[] = [$r['USERTYPE'], $r['c'], $typeColors[$r['USERTYPE']] ?? '#94a3b8'];
        ?>
        <section class="card"><div class="card-head"><h2>System users by type</h2><a class="link" href="manage.php?module=users">Manage →</a></div><?= render_donut($dist, 'Users') ?></section>
        <?php else: ?>
        <section class="card"><div class="card-head"><h2>Quick links</h2></div>
            <div class="quick">
                <?php foreach (['students' => ['users', 'Students'], 'schedules' => ['calendar', 'Class Schedules'], 'enrollments' => ['user-plus', 'Enrollments'], 'reports' => ['bar-chart', 'Reports']] as $k => $q):
                    if (!can_access($k)) continue; ?>
                    <a href="<?= e(module_url($k)) ?>"><?= icon($q[0], 20) ?><span><?= e($q[1]) ?></span></a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
<?php endif;

render_footer();
