<?php
// ==========================================================
// student_view.php?id=N   (staff)  /  student_view.php?me=1  (a Student's own record)
// Profile + enrolled classes + grades + GWA + attendance. Printable.
// ==========================================================
require "auth_check.php";
require "db.php";
require "crud.php";
require "layout.php";

$isStudent = (current_role() === 'Student');
if ($isStudent) {
    require_access('my_record');
    $active = 'my_record';
    $sid = my_student_id($conn) ?? 0;          // a student can only ever open their OWN record
} else {
    require_access('students');
    $active = 'students';
    $sid = (int) ($_GET['id'] ?? 0);
}

$student = null;
if ($sid > 0) {
    $student = db_run($conn,
        "SELECT s.*, c.COURSE, c.DESCRIPTION AS COURSE_DESC, y.YEARLEVEL, sec.SECTION
         FROM tbl_students s
         LEFT JOIN tbl_course c    ON c.ID = s.COURSE_ID
         LEFT JOIN tbl_yearlevel y ON y.ID = s.YEARLEVEL_ID
         LEFT JOIN tbl_section sec ON sec.ID = s.SECTION_ID
         WHERE s.ID = ?", 'i', [$sid])->get_result()->fetch_assoc();
}

if (!$student) {
    if (!$isStudent) {
        flash_set('error', 'Student not found.');
        header("Location: manage.php?module=students");
        exit;
    }
    render_header('My Record', 'my_record');
    ?>
    <section class="card"><div class="empty">
        <?= icon('user', 28) ?>
        <strong>Your account is not linked to a student record yet</strong>
        <span>Please contact the Registrar so your login can be linked to your student profile.</span>
    </div></section>
    <?php
    render_footer();
    exit;
}

// ---------- Classes + grades ----------
$sql = "SELECT e.ID AS EID, sub.SUBJECTCODE, sub.SUBJECTNAME, sub.UNITS,
               sc.DAYS, sc.TIME_START, sc.TIME_END, sc.ROOM, sy.SCHOOLYEAR, t.TERM,
               CONCAT_WS(' ', i.SALUTATION, i.FIRSTNAME, i.LASTNAME) AS INSTRUCTOR,
               g.PRELIM, g.MIDTERM, g.FINALTERM,
               (SELECT COUNT(*) FROM tbl_attendance a WHERE a.ENROLLMENT_ID = e.ID) AS ATT_T,
               (SELECT COUNT(*) FROM tbl_attendance a WHERE a.ENROLLMENT_ID = e.ID AND a.STATUS IN ('Present', 'Late')) AS ATT_OK
        FROM tbl_enrollments e
        JOIN tbl_schedules sc   ON sc.ID = e.SCHEDULE_ID
        JOIN tbl_subjects sub   ON sub.ID = sc.SUBJECT_ID
        JOIN tbl_schoolyear sy  ON sy.ID = sc.SCHOOLYEAR_ID
        JOIN tbl_term t         ON t.ID = sc.TERM_ID
        JOIN tbl_instructors i  ON i.ID = sc.INSTRUCTOR_ID
        LEFT JOIN tbl_grades g  ON g.ENROLLMENT_ID = e.ID
        WHERE e.STUDENT_ID = ?";
$types = 'i'; $params = [$sid];
if (current_role() === 'Instructor') {                // instructors only see results for their own classes
    $sql .= " AND sc.INSTRUCTOR_ID = ?";
    $types .= 'i'; $params[] = (int) my_instructor_id($conn);
}
$sql .= " ORDER BY sy.SCHOOLYEAR DESC, t.ID, sub.SUBJECTCODE";
$res = db_run($conn, $sql, $types, $params)->get_result();

$classes = [];
$unitsEnrolled = 0; $unitsEarned = 0; $weighted = 0.0; $gradedUnits = 0;
while ($r = $res->fetch_assoc()) {
    $pct = grade_percent($r['PRELIM'], $r['MIDTERM'], $r['FINALTERM']);
    $r['PCT'] = $pct;
    $r['EQ'] = grade_equivalent($pct);
    $r['REMARKS'] = grade_remarks($pct);
    $unitsEnrolled += (int) $r['UNITS'];
    if ($pct !== null) {
        $weighted += $r['EQ'] * (int) $r['UNITS'];
        $gradedUnits += (int) $r['UNITS'];
        if ($pct >= PASSING_GRADE) $unitsEarned += (int) $r['UNITS'];
    }
    $classes[] = $r;
}
$gwa = $gradedUnits > 0 ? $weighted / $gradedUnits : null;

$fullName = trim($student['FIRSTNAME'] . ' ' . ($student['MIDDLENAME'] ? $student['MIDDLENAME'] . ' ' : '') . $student['LASTNAME']);

ob_start(); ?>
    <?php if (!$isStudent): ?><a class="btn btn-outline" href="manage.php?module=students"><?= icon('chevron-left', 16) ?> Back to students</a><?php endif; ?>
    <button type="button" class="btn btn-primary" onclick="window.print()"><?= icon('printer', 16) ?> Print record</button>
<?php
$actions = ob_get_clean();

render_header($isStudent ? 'My Record' : 'Student Record', $active, [
    'subtitle' => $isStudent ? 'Your profile, classes and grades.' : $fullName . ' · ' . $student['STUDENT_NO'],
    'actions' => $actions,
]);

$info = function (string $label, $value) {
    echo '<div><span class="lbl">' . e($label) . '</span><strong>' . (($value === null || $value === '') ? '<span class="muted">—</span>' : e($value)) . '</strong></div>';
};
?>

<div class="print-only print-head">
    <h2><?= e(SCHOOL_NAME) ?> — Student Academic Record</h2>
    <p>Printed on <?= e(date('F j, Y')) ?></p>
</div>

<section class="card profile">
    <div class="profile-top">
        <?php if ($student['PHOTO']): ?>
            <img class="profile-photo" src="uploads/students/<?= e($student['PHOTO']) ?>" alt="">
        <?php else: ?>
            <span class="profile-photo placeholder"><?= e(initials($fullName)) ?></span>
        <?php endif; ?>
        <div class="profile-id">
            <h2><?= e($fullName) ?></h2>
            <p><?= e($student['STUDENT_NO']) ?> · <?= e($student['COURSE'] ?? '—') ?> · <?= e($student['YEARLEVEL'] ?? '—') ?> · <?= e($student['SECTION'] ?? 'No section') ?></p>
            <?= badge($student['STATUS']) ?>
        </div>
    </div>
    <div class="info-grid">
        <?php
        $info('Course', trim(($student['COURSE'] ?? '') . ($student['COURSE_DESC'] ? ' — ' . $student['COURSE_DESC'] : '')));
        $info('Gender', $student['GENDER']);
        $info('Date of birth', $student['BIRTHDATE'] ? fmt_date($student['BIRTHDATE']) : '');
        $info('Email', $student['EMAIL']);
        $info('Contact no.', $student['CONTACT']);
        $info('Address', $student['ADDRESS']);
        $info('Parent / Guardian', $student['GUARDIAN_NAME']);
        $info('Guardian contact', $student['GUARDIAN_CONTACT']);
        ?>
    </div>
</section>

<div class="stat-row">
    <div class="stat"><span class="stat-label">Classes enrolled</span><span class="stat-value"><?= count($classes) ?></span></div>
    <div class="stat"><span class="stat-label">Units enrolled</span><span class="stat-value"><?= $unitsEnrolled ?></span></div>
    <div class="stat"><span class="stat-label">Units earned</span><span class="stat-value text-green"><?= $unitsEarned ?></span></div>
    <div class="stat"><span class="stat-label">General Weighted Average</span><span class="stat-value"><?= $gwa !== null ? number_format($gwa, 2) : '—' ?></span></div>
</div>

<section class="card">
    <div class="card-head"><h2>Classes &amp; grades</h2><span class="muted"><?= count($classes) ?> class<?= count($classes) === 1 ? '' : 'es' ?></span></div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Term</th><th>Subject</th><th class="num">Units</th><th>Schedule</th><th>Instructor</th>
                    <th class="num">Prelim</th><th class="num">Midterm</th><th class="num">Final</th>
                    <th class="num">Grade</th><th class="num">Equiv.</th><th>Remarks</th><th class="num">Attend.</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$classes): ?>
                <tr><td colspan="12"><div class="empty"><?= icon('book-open', 28) ?><strong>No classes yet</strong><span>This student has not been enrolled in any class.</span></div></td></tr>
            <?php endif; ?>
            <?php foreach ($classes as $c): ?>
                <tr>
                    <td><?= e($c['SCHOOLYEAR']) ?><div class="muted small"><?= e($c['TERM']) ?></div></td>
                    <td><strong><?= e($c['SUBJECTCODE']) ?></strong><div class="muted small"><?= e($c['SUBJECTNAME']) ?></div></td>
                    <td class="num"><?= (int) $c['UNITS'] ?></td>
                    <td><?= e($c['DAYS']) ?><div class="muted small"><?= e(fmt_time($c['TIME_START'])) ?> – <?= e(fmt_time($c['TIME_END'])) ?><?= $c['ROOM'] ? ' · ' . e($c['ROOM']) : '' ?></div></td>
                    <td><?= e($c['INSTRUCTOR']) ?></td>
                    <td class="num"><?= fmt_num($c['PRELIM']) ?></td>
                    <td class="num"><?= fmt_num($c['MIDTERM']) ?></td>
                    <td class="num"><?= fmt_num($c['FINALTERM']) ?></td>
                    <td class="num"><strong><?= fmt_num($c['PCT']) ?></strong></td>
                    <td class="num"><?= fmt_num($c['EQ']) ?></td>
                    <td><?= badge($c['REMARKS']) ?></td>
                    <td class="num"><?= $c['ATT_T'] > 0 ? round(($c['ATT_OK'] / $c['ATT_T']) * 100) . '%' : '<span class="muted">—</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="hint">Grade = average of Prelim, Midterm and Final. Equivalent uses the 1.00–5.00 scale (passing: <?= PASSING_GRADE ?>+). GWA is weighted by units over classes that already have a complete grade.</p>
</section>

<?php render_footer(); ?>
