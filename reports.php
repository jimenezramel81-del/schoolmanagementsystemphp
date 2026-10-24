<?php
// ==========================================================
// reports.php - printable reports
//   ?report=masterlist  student masterlist (filter by course / year / section / status)
//   ?report=classlist   class list with signature column
//   ?report=summary     enrollment summary by course & year level
// ==========================================================
require "auth_check.php";
require "db.php";
require "config.php";
require "crud.php";
require "class_helpers.php";
require "layout.php";

require_access('reports');
$active = 'reports';

$report = $_GET['report'] ?? 'masterlist';
if (!in_array($report, ['masterlist', 'classlist', 'summary'], true)) $report = 'masterlist';

$tabs = ['masterlist' => 'Student Masterlist', 'classlist' => 'Class List', 'summary' => 'Enrollment Summary'];

$printBtn = '<button type="button" class="btn btn-primary" onclick="window.print()">' . icon('printer', 16) . ' Print report</button>';
render_header('Reports', 'reports', ['subtitle' => 'Generate and print school reports.', 'actions' => $printBtn]);
?>

<nav class="tabs no-print">
    <?php foreach ($tabs as $k => $label): ?>
        <a class="tab <?= $report === $k ? 'active' : '' ?>" href="reports.php?report=<?= $k ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<div class="print-only print-head">
    <h2><?= e(SCHOOL_NAME) ?> — <?= e($tabs[$report]) ?></h2>
    <p>Printed on <?= e(date('F j, Y')) ?></p>
</div>

<?php
// ======================================================= MASTERLIST
if ($report === 'masterlist'):
    $cfg = $modules['students'];
    $filterVals = read_filters($cfg);
    $qry = build_query($cfg, '', $filterVals);
    $res = db_run($conn, $qry['select'] . " ORDER BY m.LASTNAME, m.FIRSTNAME LIMIT 2000", $qry['types'], $qry['params'])->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    $exportParams = ['module' => 'students'];
    foreach ($filterVals as $k => $v) { if ($v !== '') $exportParams['f_' . $k] = $v; }
?>
    <section class="card no-print">
        <form class="toolbar" method="get" action="reports.php">
            <input type="hidden" name="report" value="masterlist">
            <?php foreach (['COURSE_ID', 'YEARLEVEL_ID', 'SECTION_ID', 'STATUS'] as $fname):
                $ff = find_field($cfg, $fname); ?>
                <select class="filter" name="f_<?= e($fname) ?>" data-autosubmit aria-label="<?= e($ff['label']) ?>">
                    <option value=""><?= e($ff['filter_label']) ?></option>
                    <?php foreach (field_choices($conn, $ff) as $c): ?>
                        <option value="<?= e($c[0]) ?>" <?= (string) $filterVals[$fname] === (string) $c[0] ? 'selected' : '' ?>><?= e($c[1]) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endforeach; ?>
            <a class="btn btn-ghost" href="reports.php?report=masterlist"><?= icon('x', 16) ?> Reset</a>
            <?php if (can_access('students')): ?>
                <a class="btn btn-outline push-right" href="export.php?<?= e(http_build_query($exportParams)) ?>"><?= icon('download', 16) ?> Export to Excel</a>
            <?php endif; ?>
        </form>
    </section>

    <section class="card">
        <div class="card-head"><h2>Student masterlist</h2><span class="muted"><?= count($rows) ?> student<?= count($rows) === 1 ? '' : 's' ?></span></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th class="col-id">#</th><th>Student No.</th><th>Name</th><th>Gender</th><th>Course</th><th>Year Level</th><th>Section</th><th>Status</th><th>Contact</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="9"><div class="empty"><?= icon('users', 28) ?><strong>No students match these filters</strong></div></td></tr><?php endif; ?>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td class="col-id muted"><?= $i + 1 ?></td>
                        <td><?= e($r['STUDENT_NO']) ?></td>
                        <td><strong><?= e(roster_name($r)) ?></strong></td>
                        <td><?= e($r['GENDER']) ?></td>
                        <td><?= e($r['COURSE_ID_LABEL'] ?? '—') ?></td>
                        <td><?= e($r['YEARLEVEL_ID_LABEL'] ?? '—') ?></td>
                        <td><?= e($r['SECTION_ID_LABEL'] ?? '—') ?></td>
                        <td><?= badge($r['STATUS']) ?></td>
                        <td><?= e($r['CONTACT'] ?: '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php
// ======================================================= CLASS LIST
elseif ($report === 'classlist'):
    $schedules = accessible_schedules($conn);
    $selectedId = (int) ($_GET['schedule'] ?? 0);
    $class = $schedules[$selectedId] ?? null;
    render_class_picker($schedules, $selectedId, 'reports.php', ['report' => 'classlist']);

    if ($class):
        render_class_info($class);
        $roster = class_roster($conn, $selectedId);
?>
    <section class="card">
        <div class="card-head"><h2>Class list</h2><span class="muted"><?= count($roster) ?> student<?= count($roster) === 1 ? '' : 's' ?></span></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th class="col-id">#</th><th>Student No.</th><th>Name</th><th>Gender</th><th>Status</th><th class="sign-col">Signature</th></tr></thead>
                <tbody>
                <?php if (!$roster): ?><tr><td colspan="6"><div class="empty"><?= icon('users', 28) ?><strong>No students enrolled in this class</strong></div></td></tr><?php endif; ?>
                <?php foreach ($roster as $i => $r): ?>
                    <tr>
                        <td class="col-id muted"><?= $i + 1 ?></td>
                        <td><?= e($r['STUDENT_NO']) ?></td>
                        <td><strong><?= e(roster_name($r)) ?></strong></td>
                        <td><?= e($r['GENDER']) ?></td>
                        <td><?= badge($r['STATUS']) ?></td>
                        <td class="sign-col"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php
    endif;

// ======================================================= SUMMARY
else:
    $courses = $conn->query("SELECT ID, COURSE, DESCRIPTION FROM tbl_course ORDER BY COURSE")->fetch_all(MYSQLI_ASSOC);
    $years   = $conn->query("SELECT ID, YEARLEVEL FROM tbl_yearlevel ORDER BY ID")->fetch_all(MYSQLI_ASSOC);

    $matrix = [];
    $res = $conn->query("SELECT COURSE_ID, YEARLEVEL_ID, COUNT(*) AS c FROM tbl_students WHERE STATUS = 'Active' GROUP BY COURSE_ID, YEARLEVEL_ID");
    while ($r = $res->fetch_assoc()) $matrix[(int) $r['COURSE_ID']][(int) $r['YEARLEVEL_ID']] = (int) $r['c'];

    $statusCounts = [];
    $res = $conn->query("SELECT STATUS, COUNT(*) AS c FROM tbl_students GROUP BY STATUS");
    while ($r = $res->fetch_assoc()) $statusCounts[$r['STATUS']] = (int) $r['c'];

    $schedules = accessible_schedules($conn);
    $colTotals = array_fill_keys(array_column($years, 'ID'), 0);
    $grand = 0;
?>
    <section class="card">
        <div class="card-head"><h2>Active students by course &amp; year level</h2></div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Course</th><?php foreach ($years as $y): ?><th class="num"><?= e($y['YEARLEVEL']) ?></th><?php endforeach; ?><th class="num">Total</th></tr>
                </thead>
                <tbody>
                <?php foreach ($courses as $c):
                    $rowTotal = 0; ?>
                    <tr>
                        <td><strong><?= e($c['COURSE']) ?></strong><div class="muted small"><?= e($c['DESCRIPTION']) ?></div></td>
                        <?php foreach ($years as $y):
                            $n = $matrix[(int) $c['ID']][(int) $y['ID']] ?? 0;
                            $rowTotal += $n; $colTotals[$y['ID']] += $n; ?>
                            <td class="num"><?= $n ?: '<span class="muted">0</span>' ?></td>
                        <?php endforeach; $grand += $rowTotal; ?>
                        <td class="num"><strong><?= $rowTotal ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td><strong>Total</strong></td>
                        <?php foreach ($years as $y): ?><td class="num"><strong><?= $colTotals[$y['ID']] ?></strong></td><?php endforeach; ?>
                        <td class="num"><strong><?= $grand ?></strong></td></tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2>All students by status</h2></div>
        <div class="chips big">
            <?php foreach (['Active', 'Inactive', 'Graduated', 'Dropped'] as $st): ?>
                <span class="status-count"><?= badge($st) ?> <strong><?= (int) ($statusCounts[$st] ?? 0) ?></strong></span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><h2>Enrollment per class</h2><span class="muted"><?= count($schedules) ?> classes</span></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Subject</th><th>Section</th><th>Term</th><th>Schedule</th><th>Instructor</th><th class="num">Enrolled</th></tr></thead>
                <tbody>
                <?php if (!$schedules): ?><tr><td colspan="6"><div class="empty"><?= icon('calendar', 28) ?><strong>No classes scheduled yet</strong></div></td></tr><?php endif; ?>
                <?php foreach ($schedules as $s): ?>
                    <tr>
                        <td><strong><?= e($s['SUBJECTCODE']) ?></strong><div class="muted small"><?= e($s['SUBJECTNAME']) ?></div></td>
                        <td><?= e($s['SECTION']) ?></td>
                        <td><?= e($s['SCHOOLYEAR']) ?> · <?= e($s['TERM']) ?></td>
                        <td><?= e(schedule_when($s)) ?></td>
                        <td><?= e($s['INSTRUCTOR']) ?></td>
                        <td class="num"><strong><?= (int) $s['ENROLLED'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif;

render_footer();
