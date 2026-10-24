<?php
// ==========================================================
// grades.php - class record: enter Prelim / Midterm / Final grades,
// see computed final grade, equivalent (1.00-5.00) and remarks.
// Instructors only see their own classes. Others are view-only.
// ==========================================================
require "auth_check.php";
require "db.php";
require "crud.php";
require "class_helpers.php";
require "layout.php";

require_access('grades');
$active = 'grades';
$canEdit = can_modify('grades');

$schedules = accessible_schedules($conn);
$selectedId = (int) ($_GET['schedule'] ?? $_POST['schedule'] ?? 0);
$class = $schedules[$selectedId] ?? null;          // null if not chosen OR not allowed for this user
if ($selectedId !== 0 && !$class) {
    flash_set('error', 'That class was not found or is not assigned to you.');
    header("Location: grades.php");
    exit;
}

// ---------- Save grades (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_grades') {
    if (!$canEdit || !$class) {
        flash_set('error', 'You do not have permission to enter grades for this class.');
        header("Location: grades.php");
        exit;
    }
    if (!csrf_valid()) {
        flash_set('error', 'Your session expired. Please try again.');
        header("Location: grades.php?schedule=$selectedId");
        exit;
    }

    $roster = class_roster($conn, $selectedId);
    $cols = ['prelim' => 'Prelim', 'midterm' => 'Midterm', 'finalterm' => 'Final'];
    $toSave = [];
    $error = '';

    foreach ($roster as $r) {
        $eid = (int) $r['EID'];
        $vals = [];
        foreach ($cols as $key => $label) {
            $raw = $_POST[$key][$eid] ?? '';
            $raw = is_string($raw) ? trim($raw) : '';
            if ($raw === '') { $vals[$key] = null; continue; }
            if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100) {
                $error = "Invalid $label grade for " . roster_name($r) . ". Grades must be between 0 and 100.";
                break 2;
            }
            $vals[$key] = number_format((float) $raw, 2, '.', '');
        }
        $toSave[$eid] = $vals;
    }

    if ($error === '') {
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare("INSERT INTO tbl_grades (ENROLLMENT_ID, PRELIM, MIDTERM, FINALTERM) VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE PRELIM = VALUES(PRELIM), MIDTERM = VALUES(MIDTERM), FINALTERM = VALUES(FINALTERM)");
            foreach ($toSave as $eid => $v) {
                $p = $v['prelim']; $m = $v['midterm']; $f = $v['finalterm'];
                $stmt->bind_param('isss', $eid, $p, $m, $f);
                $stmt->execute();
            }
            $conn->commit();
            flash_set('success', 'Grades saved for ' . count($toSave) . ' student' . (count($toSave) === 1 ? '' : 's') . '.');
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            flash_set('error', 'Grades could not be saved. Please try again.');
        }
    } else {
        flash_set('error', $error);
    }
    header("Location: grades.php?schedule=$selectedId");
    exit;
}

// ---------- Load roster + compute ----------
$rows = [];
$stats = ['students' => 0, 'complete' => 0, 'passed' => 0, 'failed' => 0, 'sum' => 0.0];
if ($class) {
    foreach (class_roster($conn, $selectedId) as $r) {
        $pct = grade_percent($r['PRELIM'], $r['MIDTERM'], $r['FINALTERM']);
        $r['PCT'] = $pct;
        $r['EQ'] = grade_equivalent($pct);
        $r['REMARKS'] = grade_remarks($pct);
        $stats['students']++;
        if ($pct !== null) {
            $stats['complete']++;
            $stats['sum'] += $pct;
            if ($pct >= PASSING_GRADE) $stats['passed']++; else $stats['failed']++;
        }
        $rows[] = $r;
    }
}

// ---------- Excel export ----------
if ($class && isset($_GET['export'])) {
    require "xlsx_writer.php";
    $headers = ['#', 'Student No.', 'Student', 'Prelim', 'Midterm', 'Final', 'Final Grade (%)', 'Equivalent', 'Remarks'];
    $out = [];
    foreach ($rows as $i => $r) {
        $out[] = [$i + 1, $r['STUDENT_NO'], roster_name($r),
            $r['PRELIM'] ?? '', $r['MIDTERM'] ?? '', $r['FINALTERM'] ?? '',
            $r['PCT'] ?? '', $r['EQ'] !== null ? number_format($r['EQ'], 2) : '', $r['REMARKS']];
    }
    download_xlsx('class_record_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $class['SUBJECTCODE'] . '_' . $class['SECTION']) . '.xlsx',
        'Class Record', $headers, $out, [0, 3, 4, 5, 6]);
}

// ---------- Page ----------
ob_start();
if ($class): ?>
    <a class="btn btn-outline" href="grades.php?schedule=<?= $selectedId ?>&export=1"><?= icon('download', 16) ?> Export to Excel</a>
    <button type="button" class="btn btn-outline" onclick="window.print()"><?= icon('printer', 16) ?> Print</button>
<?php endif;
$actions = ob_get_clean();

render_header('Grades', 'grades', [
    'subtitle' => $class ? 'Class record — ' . schedule_label($class) : 'Choose a class to view or enter grades.' . ($canEdit ? '' : ' · View only'),
    'actions'  => $actions,
]);

render_class_picker($schedules, $selectedId, 'grades.php');

if ($class):
    render_class_info($class); ?>

    <div class="stat-row">
        <div class="stat"><span class="stat-label">Students</span><span class="stat-value"><?= $stats['students'] ?></span></div>
        <div class="stat"><span class="stat-label">Grades complete</span><span class="stat-value"><?= $stats['complete'] ?><small>/<?= $stats['students'] ?></small></span></div>
        <div class="stat"><span class="stat-label">Passed</span><span class="stat-value text-green"><?= $stats['passed'] ?></span></div>
        <div class="stat"><span class="stat-label">Failed</span><span class="stat-value text-red"><?= $stats['failed'] ?></span></div>
        <div class="stat"><span class="stat-label">Class average</span><span class="stat-value"><?= $stats['complete'] ? number_format($stats['sum'] / $stats['complete'], 2) : '—' ?></span></div>
    </div>

    <section class="card">
        <form method="post" action="grades.php?schedule=<?= $selectedId ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_grades">
            <input type="hidden" name="schedule" value="<?= $selectedId ?>">

            <div class="card-head">
                <h2>Class record</h2>
                <?php if ($canEdit && $rows): ?>
                    <button type="submit" class="btn btn-primary no-print"><?= icon('save', 16) ?> Save all grades</button>
                <?php endif; ?>
            </div>

            <div class="table-wrap">
                <table class="data" data-grade-table data-pass="<?= PASSING_GRADE ?>">
                    <thead>
                        <tr>
                            <th class="col-id">#</th><th>Student No.</th><th>Student</th>
                            <th class="num">Prelim</th><th class="num">Midterm</th><th class="num">Final</th>
                            <th class="num">Final Grade</th><th class="num">Equiv.</th><th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="9"><div class="empty"><?= icon('users', 28) ?><strong>No students enrolled</strong><span>Enroll students in this class first (Enrollments page).</span></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $i => $r): $eid = (int) $r['EID']; ?>
                        <tr>
                            <td class="col-id muted"><?= $i + 1 ?></td>
                            <td><?= e($r['STUDENT_NO']) ?></td>
                            <td><strong><?= e(roster_name($r)) ?></strong></td>
                            <?php foreach (['prelim' => 'PRELIM', 'midterm' => 'MIDTERM', 'finalterm' => 'FINALTERM'] as $key => $col): ?>
                                <td class="num">
                                <?php if ($canEdit): ?>
                                    <input class="grade-input" type="number" min="0" max="100" step="0.01" inputmode="decimal"
                                           name="<?= $key ?>[<?= $eid ?>]" value="<?= $r[$col] !== null ? e(rtrim(rtrim(number_format((float) $r[$col], 2, '.', ''), '0'), '.')) : '' ?>"
                                           aria-label="<?= e(ucfirst($key) . ' grade for ' . roster_name($r)) ?>">
                                <?php else: ?>
                                    <?= $r[$col] !== null ? e(number_format((float) $r[$col], 2)) : '<span class="muted">—</span>' ?>
                                <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="num cell-avg"><strong><?= $r['PCT'] !== null ? number_format($r['PCT'], 2) : '—' ?></strong></td>
                            <td class="num cell-eq"><?= $r['EQ'] !== null ? number_format($r['EQ'], 2) : '—' ?></td>
                            <td class="cell-remarks"><?= badge($r['REMARKS']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="hint">Final Grade = average of Prelim, Midterm and Final. Equivalent uses the 1.00–5.00 scale; passing is <?= PASSING_GRADE ?> and above. Leave a grade blank if it is not yet available.</p>
        </form>
    </section>
<?php endif;

render_footer();
