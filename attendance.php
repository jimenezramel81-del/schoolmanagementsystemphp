<?php
// ==========================================================
// attendance.php - take attendance per class and date, and see
// each student's attendance summary for the class.
// ==========================================================
require "auth_check.php";
require "db.php";
require "crud.php";
require "class_helpers.php";
require "layout.php";

require_access('attendance');
$active = 'attendance';
$canEdit = can_modify('attendance');
$statuses = ['Present', 'Absent', 'Late', 'Excused'];

$schedules = accessible_schedules($conn);
$selectedId = (int) ($_GET['schedule'] ?? $_POST['schedule'] ?? 0);
$class = $schedules[$selectedId] ?? null;
if ($selectedId !== 0 && !$class) {
    flash_set('error', 'That class was not found or is not assigned to you.');
    header("Location: attendance.php");
    exit;
}

// The date being recorded (defaults to today)
$dateRaw = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
$dt = is_string($dateRaw) ? DateTime::createFromFormat('Y-m-d', $dateRaw) : false;
$date = ($dt && $dt->format('Y-m-d') === $dateRaw) ? $dateRaw : date('Y-m-d');

// ---------- Save (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_attendance') {
    $back = "attendance.php?schedule=$selectedId&date=" . urlencode($date);
    if (!$canEdit || !$class) {
        flash_set('error', 'You do not have permission to record attendance for this class.');
        header("Location: attendance.php");
        exit;
    }
    if (!csrf_valid()) {
        flash_set('error', 'Your session expired. Please try again.');
        header("Location: $back");
        exit;
    }
    $roster = class_roster($conn, $selectedId);
    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO tbl_attendance (ENROLLMENT_ID, ATT_DATE, STATUS) VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE STATUS = VALUES(STATUS)");
        $n = 0;
        foreach ($roster as $r) {
            $eid = (int) $r['EID'];
            $st = $_POST['status'][$eid] ?? '';
            if (!is_string($st) || !in_array($st, $statuses, true)) continue;
            $stmt->bind_param('iss', $eid, $date, $st);
            $stmt->execute();
            $n++;
        }
        $conn->commit();
        flash_set('success', "Attendance for " . fmt_date($date) . " saved ($n students).");
    } catch (mysqli_sql_exception $ex) {
        $conn->rollback();
        flash_set('error', 'Attendance could not be saved. Please try again.');
    }
    header("Location: $back");
    exit;
}

// ---------- Load data ----------
$roster = []; $today = []; $summary = []; $recordedDates = [];
if ($class) {
    $roster = class_roster($conn, $selectedId);

    $res = db_run($conn,
        "SELECT a.ENROLLMENT_ID, a.STATUS FROM tbl_attendance a
         JOIN tbl_enrollments e ON e.ID = a.ENROLLMENT_ID
         WHERE e.SCHEDULE_ID = ? AND a.ATT_DATE = ?", 'is', [$selectedId, $date])->get_result();
    while ($r = $res->fetch_assoc()) $today[(int) $r['ENROLLMENT_ID']] = $r['STATUS'];

    $res = db_run($conn,
        "SELECT a.ENROLLMENT_ID,
                SUM(a.STATUS = 'Present') AS P, SUM(a.STATUS = 'Absent') AS A,
                SUM(a.STATUS = 'Late') AS L, SUM(a.STATUS = 'Excused') AS X, COUNT(*) AS T
         FROM tbl_attendance a JOIN tbl_enrollments e ON e.ID = a.ENROLLMENT_ID
         WHERE e.SCHEDULE_ID = ? GROUP BY a.ENROLLMENT_ID", 'i', [$selectedId])->get_result();
    while ($r = $res->fetch_assoc()) $summary[(int) $r['ENROLLMENT_ID']] = $r;

    $res = db_run($conn,
        "SELECT DISTINCT a.ATT_DATE FROM tbl_attendance a JOIN tbl_enrollments e ON e.ID = a.ENROLLMENT_ID
         WHERE e.SCHEDULE_ID = ? ORDER BY a.ATT_DATE DESC LIMIT 8", 'i', [$selectedId])->get_result();
    while ($r = $res->fetch_assoc()) $recordedDates[] = $r['ATT_DATE'];
}

// ---------- Page ----------
render_header('Attendance', 'attendance', [
    'subtitle' => $class ? 'Attendance — ' . schedule_label($class) : 'Choose a class to take or review attendance.' . ($canEdit ? '' : ' · View only'),
]);

render_class_picker($schedules, $selectedId, 'attendance.php');

if ($class):
    render_class_info($class); ?>

    <section class="card no-print">
        <form class="toolbar" method="get" action="attendance.php">
            <input type="hidden" name="schedule" value="<?= $selectedId ?>">
            <div class="picker-field narrow">
                <label for="attDate">Date</label>
                <input type="date" id="attDate" name="date" value="<?= e($date) ?>" data-autosubmit>
            </div>
            <a class="btn btn-outline" href="attendance.php?schedule=<?= $selectedId ?>">Today</a>
            <?php if ($recordedDates): ?>
                <div class="chips">
                    <span class="muted small">Recorded:</span>
                    <?php foreach ($recordedDates as $d): ?>
                        <a class="chip <?= $d === $date ? 'active' : '' ?>" href="attendance.php?schedule=<?= $selectedId ?>&date=<?= e($d) ?>"><?= e(date('M j', strtotime($d))) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </form>
    </section>

    <section class="card">
        <form method="post" action="attendance.php?schedule=<?= $selectedId ?>&date=<?= e($date) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_attendance">
            <input type="hidden" name="schedule" value="<?= $selectedId ?>">
            <input type="hidden" name="date" value="<?= e($date) ?>">

            <div class="card-head">
                <h2>Roll call — <?= e(date('l, F j, Y', strtotime($date))) ?></h2>
                <?php if ($canEdit && $roster): ?>
                <div class="head-actions no-print">
                    <button type="button" class="btn btn-outline" data-mark-all="Present"><?= icon('check', 16) ?> Mark all present</button>
                    <button type="submit" class="btn btn-primary"><?= icon('save', 16) ?> Save attendance</button>
                </div>
                <?php endif; ?>
            </div>

            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th class="col-id">#</th><th>Student No.</th><th>Student</th>
                            <th><?= $canEdit ? 'Status for this date' : 'Status' ?></th>
                            <th class="num" title="Present">P</th><th class="num" title="Absent">A</th><th class="num" title="Late">L</th><th class="num" title="Excused">E</th>
                            <th class="num" title="(Present + Late) / sessions recorded">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$roster): ?>
                        <tr><td colspan="9"><div class="empty"><?= icon('users', 28) ?><strong>No students enrolled</strong><span>Enroll students in this class first (Enrollments page).</span></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($roster as $i => $r):
                        $eid = (int) $r['EID'];
                        $cur = $today[$eid] ?? null;
                        $sm = $summary[$eid] ?? ['P' => 0, 'A' => 0, 'L' => 0, 'X' => 0, 'T' => 0];
                        $rate = $sm['T'] > 0 ? round((($sm['P'] + $sm['L']) / $sm['T']) * 100) : null;
                    ?>
                        <tr>
                            <td class="col-id muted"><?= $i + 1 ?></td>
                            <td><?= e($r['STUDENT_NO']) ?></td>
                            <td><strong><?= e(roster_name($r)) ?></strong></td>
                            <td>
                                <?php if ($canEdit): ?>
                                    <div class="seg-group" role="radiogroup">
                                        <?php foreach ($statuses as $st): ?>
                                            <label class="seg seg-<?= strtolower($st) ?>">
                                                <input type="radio" name="status[<?= $eid ?>]" value="<?= $st ?>" <?= ($cur ?? 'Present') === $st ? 'checked' : '' ?>>
                                                <span><?= $st ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if ($cur === null): ?><span class="muted small">Not yet recorded</span><?php endif; ?>
                                <?php else: ?>
                                    <?= $cur ? badge($cur) : '<span class="muted">Not recorded</span>' ?>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= (int) $sm['P'] ?></td>
                            <td class="num"><?= (int) $sm['A'] ?></td>
                            <td class="num"><?= (int) $sm['L'] ?></td>
                            <td class="num"><?= (int) $sm['X'] ?></td>
                            <td class="num"><?= $rate === null ? '<span class="muted">—</span>' : '<strong class="' . ($rate < 80 ? 'text-red' : 'text-green') . '">' . $rate . '%</strong>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="hint">P = Present, A = Absent, L = Late, E = Excused. Rate = (Present + Late) ÷ sessions recorded. A rate below 80% is shown in red.</p>
        </form>
    </section>
<?php endif;

render_footer();
