<?php
// ==========================================================
// class_helpers.php - shared by grades, attendance, reports, bulk_enroll
// ==========================================================
if (!function_exists('e')) { http_response_code(403); exit('Forbidden'); }

/**
 * Every class (schedule) the logged-in user may work with.
 * Instructors only get the classes assigned to them; other roles get all.
 */
function accessible_schedules(mysqli $conn): array {
    $sql = "SELECT sc.ID, sc.DAYS, sc.TIME_START, sc.TIME_END, sc.ROOM, sc.INSTRUCTOR_ID,
                   sub.SUBJECTCODE, sub.SUBJECTNAME, sub.UNITS, sec.SECTION, sec.ID AS SECTION_ID,
                   sy.SCHOOLYEAR, t.TERM,
                   CONCAT_WS(' ', i.SALUTATION, i.FIRSTNAME, i.LASTNAME) AS INSTRUCTOR,
                   (SELECT COUNT(*) FROM tbl_enrollments e WHERE e.SCHEDULE_ID = sc.ID) AS ENROLLED
            FROM tbl_schedules sc
            JOIN tbl_subjects sub   ON sub.ID = sc.SUBJECT_ID
            JOIN tbl_section sec    ON sec.ID = sc.SECTION_ID
            JOIN tbl_schoolyear sy  ON sy.ID  = sc.SCHOOLYEAR_ID
            JOIN tbl_term t         ON t.ID   = sc.TERM_ID
            JOIN tbl_instructors i  ON i.ID   = sc.INSTRUCTOR_ID";
    $types = ''; $params = [];
    if (current_role() === 'Instructor') {
        $iid = my_instructor_id($conn);
        if (!$iid) return [];
        $sql .= " WHERE sc.INSTRUCTOR_ID = ?";
        $types = 'i'; $params = [$iid];
    }
    $sql .= " ORDER BY sy.SCHOOLYEAR DESC, t.ID, sub.SUBJECTCODE, sec.SECTION";
    $res = db_run($conn, $sql, $types, $params)->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[(int) $r['ID']] = $r;
    return $out;
}

function schedule_label(array $s): string {
    return $s['SUBJECTCODE'] . ' · ' . $s['SECTION'] . ' · ' . $s['SCHOOLYEAR'] . ' ' . $s['TERM'];
}

function schedule_when(array $s): string {
    return $s['DAYS'] . ' ' . fmt_time($s['TIME_START']) . ' – ' . fmt_time($s['TIME_END']) . ($s['ROOM'] ? ' · ' . $s['ROOM'] : '');
}

/** Students enrolled in a class, with their grade row (if any). */
function class_roster(mysqli $conn, int $scheduleId): array {
    $res = db_run($conn,
        "SELECT e.ID AS EID, s.ID AS SID, s.STUDENT_NO, s.FIRSTNAME, s.MIDDLENAME, s.LASTNAME, s.GENDER, s.PHOTO, s.STATUS,
                g.PRELIM, g.MIDTERM, g.FINALTERM
         FROM tbl_enrollments e
         JOIN tbl_students s ON s.ID = e.STUDENT_ID
         LEFT JOIN tbl_grades g ON g.ENROLLMENT_ID = e.ID
         WHERE e.SCHEDULE_ID = ?
         ORDER BY s.LASTNAME, s.FIRSTNAME", 'i', [$scheduleId])->get_result();
    $out = [];
    while ($r = $res->fetch_assoc()) $out[] = $r;
    return $out;
}

/** "Santos, Maria D." */
function roster_name(array $r): string {
    $mi = !empty($r['MIDDLENAME']) ? ' ' . strtoupper(substr($r['MIDDLENAME'], 0, 1)) . '.' : '';
    return $r['LASTNAME'] . ', ' . $r['FIRSTNAME'] . $mi;
}

/**
 * The "choose a class" bar. When no class is selected it also lists all classes
 * as a table so the user can open one with a single click.
 * $page = 'grades.php' etc.; $hidden = extra query values to keep (e.g. date).
 */
function render_class_picker(array $list, int $selectedId, string $page, array $hidden = []): void {
    ?>
    <section class="card no-print">
        <form class="toolbar" method="get" action="<?= e($page) ?>">
            <?php foreach ($hidden as $k => $v): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
            <div class="picker-field">
                <label for="schedulePick">Class</label>
                <select id="schedulePick" name="schedule" data-autosubmit>
                    <option value="">— Select a class —</option>
                    <?php foreach ($list as $s): ?>
                        <option value="<?= (int) $s['ID'] ?>" <?= $selectedId === (int) $s['ID'] ? 'selected' : '' ?>>
                            <?= e(schedule_label($s)) ?> (<?= e($s['SUBJECTNAME']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" type="submit">Open class</button>
        </form>
    </section>

    <?php if ($selectedId === 0): ?>
    <section class="card no-print">
        <div class="card-head"><h2>Your classes</h2><span class="muted"><?= count($list) ?> available</span></div>
        <?php if (!$list): ?>
            <div class="empty">
                <?= icon('calendar', 28) ?>
                <strong>No classes found</strong>
                <span><?= current_role() === 'Instructor'
                    ? 'No class schedules are assigned to your account yet. Ask the Dean or an Admin to assign classes and link your login to your instructor record.'
                    : 'Create class schedules first, then come back here.' ?></span>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Subject</th><th>Section</th><th>Term</th><th>Schedule</th><th>Instructor</th><th class="num">Students</th><th class="col-actions"></th></tr></thead>
                <tbody>
                <?php foreach ($list as $s): ?>
                    <tr>
                        <td><strong><?= e($s['SUBJECTCODE']) ?></strong><div class="muted small"><?= e($s['SUBJECTNAME']) ?></div></td>
                        <td><?= e($s['SECTION']) ?></td>
                        <td><?= e($s['SCHOOLYEAR']) ?><div class="muted small"><?= e($s['TERM']) ?></div></td>
                        <td><?= e(schedule_when($s)) ?></td>
                        <td><?= e($s['INSTRUCTOR']) ?></td>
                        <td class="num"><?= (int) $s['ENROLLED'] ?></td>
                        <td class="col-actions"><a class="act act-view" href="<?= e($page . '?' . http_build_query(array_merge($hidden, ['schedule' => $s['ID']]))) ?>"><?= icon('eye', 15) ?><span>Open</span></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
    <?php endif;
}

/** The little info card shown at the top of a selected class. */
function render_class_info(array $s): void {
    ?>
    <section class="card info-strip">
        <div><span class="lbl">Subject</span><strong><?= e($s['SUBJECTCODE']) ?> — <?= e($s['SUBJECTNAME']) ?></strong></div>
        <div><span class="lbl">Section</span><strong><?= e($s['SECTION']) ?></strong></div>
        <div><span class="lbl">Term</span><strong><?= e($s['SCHOOLYEAR']) ?> · <?= e($s['TERM']) ?></strong></div>
        <div><span class="lbl">Schedule</span><strong><?= e(schedule_when($s)) ?></strong></div>
        <div><span class="lbl">Instructor</span><strong><?= e($s['INSTRUCTOR']) ?></strong></div>
        <div><span class="lbl">Units</span><strong><?= (int) $s['UNITS'] ?></strong></div>
    </section>
    <?php
}
