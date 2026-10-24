<?php
// ==========================================================
// bulk_enroll.php?schedule=N
// Enrolls every ACTIVE student of the class's section into that class
// in one click. Students already enrolled are skipped.
// ==========================================================
require "auth_check.php";
require "db.php";
require "crud.php";
require "class_helpers.php";
require "layout.php";

require_access('enrollments');
if (!can_modify('enrollments')) {
    flash_set('error', 'You do not have permission to enroll students.');
    header("Location: manage.php?module=enrollments");
    exit;
}
$active = 'enrollments';

$id = (int) ($_GET['schedule'] ?? $_POST['schedule'] ?? 0);
$class = accessible_schedules($conn)[$id] ?? null;
if (!$class) {
    flash_set('error', 'Class not found.');
    header("Location: manage.php?module=schedules");
    exit;
}

// Active students in the section, and how many are not yet enrolled
$stmt = db_run($conn,
    "SELECT COUNT(*) AS total,
            SUM(NOT EXISTS (SELECT 1 FROM tbl_enrollments e WHERE e.STUDENT_ID = s.ID AND e.SCHEDULE_ID = ?)) AS pending
     FROM tbl_students s WHERE s.SECTION_ID = ? AND s.STATUS = 'Active'",
    'ii', [$id, (int) $class['SECTION_ID']]);
$counts = $stmt->get_result()->fetch_assoc();
$total = (int) $counts['total'];
$pending = (int) $counts['pending'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        flash_set('error', 'Your session expired. Please try again.');
        header("Location: bulk_enroll.php?schedule=$id");
        exit;
    }
    try {
        $s = db_run($conn,
            "INSERT IGNORE INTO tbl_enrollments (STUDENT_ID, SCHEDULE_ID)
             SELECT s.ID, ? FROM tbl_students s WHERE s.SECTION_ID = ? AND s.STATUS = 'Active'",
            'ii', [$id, (int) $class['SECTION_ID']]);
        flash_set('success', $s->affected_rows . ' student(s) enrolled in ' . $class['SUBJECTCODE'] . ' (' . $class['SECTION'] . ').');
    } catch (mysqli_sql_exception $ex) {
        flash_set('error', 'Students could not be enrolled. Please try again.');
    }
    header("Location: manage.php?module=enrollments");
    exit;
}

render_header('Enroll Section', 'enrollments', ['subtitle' => 'Enroll a whole section into a class.']);
render_class_info($class);
?>
<section class="card">
    <div class="card-head"><h2>Confirm enrollment</h2></div>
    <p class="lead">
        <strong><?= $pending ?></strong> of <strong><?= $total ?></strong> active student<?= $total === 1 ? '' : 's' ?> in section
        <strong><?= e($class['SECTION']) ?></strong> will be enrolled in
        <strong><?= e($class['SUBJECTCODE']) ?></strong>. Students already enrolled are skipped.
    </p>
    <form method="post" action="bulk_enroll.php?schedule=<?= $id ?>" class="form-row">
        <?= csrf_field() ?>
        <input type="hidden" name="schedule" value="<?= $id ?>">
        <a class="btn btn-ghost" href="manage.php?module=schedules">Cancel</a>
        <button type="submit" class="btn btn-primary" <?= $pending === 0 ? 'disabled' : '' ?>><?= icon('user-plus', 16) ?> Enroll <?= $pending ?> student<?= $pending === 1 ? '' : 's' ?></button>
    </form>
</section>
<?php render_footer(); ?>
