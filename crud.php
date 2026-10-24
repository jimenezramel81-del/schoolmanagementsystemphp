<?php
// ==========================================================
// crud.php - the engine behind manage.php and export.php
// ==========================================================
if (!function_exists('e')) { http_response_code(403); exit('Forbidden'); }

/** Prepare + bind + execute in one call. Throws mysqli_sql_exception on error. */
function db_run(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

// ---------- Select fields (dropdowns that point at another table) ----------
function ref_sql(array $f): string {
    if (!empty($f['ref_sql'])) return $f['ref_sql'];
    return "SELECT `{$f['ref_id']}` AS id, `{$f['ref_label']}` AS label FROM `{$f['ref_table']}`";
}

function ref_options(mysqli $conn, array $f): array {
    $sql = $f['options_sql'] ?? ref_sql($f);
    $res = $conn->query("SELECT id, label FROM (" . $sql . ") r ORDER BY label");
    $out = [];
    while ($row = $res->fetch_assoc()) $out[] = $row;
    return $out;
}

/** Plain list of [value, label] pairs for a select or enum field (used by filters). */
function field_choices(mysqli $conn, array $f): array {
    $out = [];
    if ($f['type'] === 'select') {
        foreach (ref_options($conn, $f) as $o) $out[] = [$o['id'], $o['label']];
    } elseif ($f['type'] === 'enum') {
        foreach ($f['options'] as $o) $out[] = [$o, $o];
    }
    return $out;
}

function find_field(array $cfg, string $name): ?array {
    foreach ($cfg['fields'] as $f) {
        if ($f['name'] === $name) return $f;
    }
    return null;
}

/**
 * Builds the SELECT and COUNT queries for a module, including search and filters.
 * Select fields are LEFT JOINed so the table shows names instead of raw IDs.
 */
function build_query(array $cfg, string $q, array $filterVals): array {
    $select = ['m.*'];
    $joins = '';
    $where = [];
    $types = '';
    $params = [];
    $labelAlias = [];
    $n = 0;

    foreach ($cfg['fields'] as $f) {
        if ($f['type'] === 'select') {
            $n++;
            $alias = "r$n";
            $select[] = "$alias.label AS `{$f['name']}_LABEL`";
            $joins .= " LEFT JOIN (" . ref_sql($f) . ") $alias ON m.`{$f['name']}` = $alias.id";
            $labelAlias[$f['name']] = $alias;
        }
    }

    if ($q !== '') {
        $parts = [];
        foreach ($cfg['search_fields'] as $sf) {
            if ($sf[0] === '@') {
                $key = substr($sf, 1);
                if (!isset($labelAlias[$key])) continue;
                $col = $labelAlias[$key] . '.label';
            } elseif ($sf[0] === '=') {
                $col = '(' . substr($sf, 1) . ')';
            } else {
                $col = "m.`$sf`";
            }
            $parts[] = "$col LIKE ?";
            $types .= 's';
            $params[] = '%' . $q . '%';
        }
        if ($parts) $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    foreach ($cfg['filters'] ?? [] as $fname) {
        $v = $filterVals[$fname] ?? '';
        if ($v === '' || $v === null) continue;
        $where[] = "m.`$fname` = ?";
        $types .= 's';
        $params[] = $v;
    }

    $base = " FROM `{$cfg['table']}` m" . $joins . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
    return [
        'select' => 'SELECT ' . implode(', ', $select) . $base,
        'count'  => 'SELECT COUNT(*) AS c' . $base,
        'types'  => $types,
        'params' => $params,
    ];
}

/** Reads the filter values from the URL (?f_COURSE_ID=3 ...). */
function read_filters(array $cfg): array {
    $vals = [];
    foreach ($cfg['filters'] ?? [] as $fname) {
        $v = $_GET['f_' . $fname] ?? '';
        $vals[$fname] = is_string($v) ? trim($v) : '';
    }
    return $vals;
}

/** A short human name for a row, used in the delete confirmation. */
function row_title(array $cfg, array $row): string {
    $titleFields = $cfg['title'] ?? [$cfg['fields'][0]['name']];
    $parts = [];
    foreach ($titleFields as $t) {
        $v = ($t[0] === '@') ? ($row[substr($t, 1) . '_LABEL'] ?? '') : ($row[$t] ?? '');
        if ($v !== '' && $v !== null) $parts[] = $v;
    }
    if (!$parts) $parts[] = '#' . ($row[$cfg['id_field'] ?? 'ID'] ?? '');
    return implode(' ', $parts);
}

// ---------- Photo uploads ----------
/**
 * Validates and stores an uploaded image.
 * Returns: file name (saved) | null (nothing chosen) | false (error, message in $err)
 */
function save_image($file, string $dir, string &$err) {
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) { $err = "Photo upload failed. Please try again."; return false; }
    if ($file['size'] > 2 * 1024 * 1024)  { $err = "Photo must be 2 MB or smaller."; return false; }

    // Check the real image type from the file's contents, never its name
    $info = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) { $err = "Photo must be a JPG, PNG, GIF or WEBP image."; return false; }

    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$info[2]];   // random name - never trust the original
    if (!move_uploaded_file($file['tmp_name'], rtrim($dir, '/') . '/' . $name)) { $err = "Could not save the photo."; return false; }
    return $name;
}

function delete_image(string $dir, $name): void {
    if ($name) {
        $path = rtrim($dir, '/') . '/' . basename((string) $name);
        if (is_file($path)) @unlink($path);
    }
}

// ---------- Reading + validating a submitted form ----------
/**
 * Returns ['values' => [col => value], 'images' => [col => newFile], 'error' => string].
 * Blank optional fields become NULL. A blank password on edit keeps the old one.
 */
function collect_values(array $cfg, bool $isUpdate): array {
    $out = [];
    $images = [];
    $error = '';

    foreach ($cfg['fields'] as $f) {
        if (!($f['form'] ?? true)) continue;
        $name = $f['name'];
        $type = $f['type'];
        $label = $f['label'];
        $required = $f['required'] ?? true;

        if ($type === 'image') {
            $imgErr = '';
            $res = save_image($_FILES[$name] ?? null, __DIR__ . '/' . $f['upload_dir'], $imgErr);
            if ($res === false) { $error = $imgErr; break; }
            if ($res !== null) { $out[$name] = $res; $images[$name] = $res; }
            elseif (!$isUpdate) { $out[$name] = null; }   // no photo chosen: keep the old one on edit
            continue;
        }

        if ($type === 'password') {
            $raw = (string) ($_POST[$name] ?? '');
            if ($raw === '') {
                if (!$isUpdate && $required) { $error = "$label is required."; break; }
                continue;                                  // blank on edit = keep current password
            }
            if (strlen($raw) < 8) { $error = "$label must be at least 8 characters."; break; }
            $out[$name] = password_hash($raw, PASSWORD_DEFAULT);
            continue;
        }

        $val = $_POST[$name] ?? '';
        $val = is_string($val) ? trim($val) : '';

        if ($val === '') {
            if ($required) { $error = "$label is required."; break; }
            $out[$name] = null;
            continue;
        }

        if (!empty($f['maxlength']) && mb_strlen($val) > (int) $f['maxlength']) {
            $error = "$label must be at most {$f['maxlength']} characters."; break;
        }

        switch ($type) {
            case 'number':
                if (!is_numeric($val)) { $error = "$label must be a number."; break 2; }
                if (isset($f['min']) && $val < $f['min']) { $error = "$label must be at least {$f['min']}."; break 2; }
                if (isset($f['max']) && $val > $f['max']) { $error = "$label must be at most {$f['max']}."; break 2; }
                break;
            case 'email':
                if (!filter_var($val, FILTER_VALIDATE_EMAIL)) { $error = "$label is not a valid email address."; break 2; }
                break;
            case 'date':
                $d = DateTime::createFromFormat('Y-m-d', $val);
                if (!$d || $d->format('Y-m-d') !== $val) { $error = "$label is not a valid date."; break 2; }
                break;
            case 'time':
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $val)) { $error = "$label is not a valid time."; break 2; }
                break;
            case 'enum':
                if (!in_array($val, $f['options'], true)) { $error = "$label has an invalid value."; break 2; }
                break;
            case 'select':
                if (!ctype_digit($val)) { $error = "$label has an invalid selection."; break 2; }
                break;
        }
        $out[$name] = $val;
    }

    return ['values' => $out, 'images' => $images, 'error' => $error];
}

// ---------- Friendly database errors ----------
function friendly_db_error(mysqli_sql_exception $ex, array $cfg, string $op): string {
    switch ((int) $ex->getCode()) {
        case 1062: return $cfg['dup_message'] ?? 'A record with the same value already exists.';
        case 1451: return 'This record is in use by other records (for example classes or enrollments), so it cannot be deleted. Remove or reassign those first.';
        case 1452: return 'One of the selected options no longer exists. Please refresh the page and try again.';
        case 1406: return 'One of the values is too long.';
    }
    return ($op === 'delete' ? 'The record could not be deleted.' : 'The record could not be saved.') . ' Please try again.';
}

// ---------- Class schedule rules ----------
/** Makes sure end > start and that the instructor, section and room are not double-booked. */
function validate_schedule(array $v, mysqli $conn, int $id): string {
    $start = substr((string) $v['TIME_START'], 0, 5);
    $end   = substr((string) $v['TIME_END'], 0, 5);
    if ($start >= $end) return 'End time must be later than the start time.';

    $stmt = db_run($conn,
        "SELECT ID, DAYS, TIME_START, TIME_END, INSTRUCTOR_ID, SECTION_ID, ROOM
         FROM tbl_schedules WHERE SCHOOLYEAR_ID = ? AND TERM_ID = ? AND ID <> ?",
        'iii', [(int) $v['SCHOOLYEAR_ID'], (int) $v['TERM_ID'], $id]);
    $res = $stmt->get_result();
    $myDays = explode('/', (string) $v['DAYS']);

    while ($r = $res->fetch_assoc()) {
        if (!array_intersect($myDays, explode('/', $r['DAYS']))) continue;               // different days
        if (!($start < substr($r['TIME_END'], 0, 5) && substr($r['TIME_START'], 0, 5) < $end)) continue;   // no time overlap

        if ((int) $r['INSTRUCTOR_ID'] === (int) $v['INSTRUCTOR_ID']) return 'Schedule conflict: this instructor already has a class at that day and time.';
        if ((int) $r['SECTION_ID'] === (int) $v['SECTION_ID'])       return 'Schedule conflict: this section already has a class at that day and time.';
        $room = trim((string) ($v['ROOM'] ?? ''));
        if ($room !== '' && strcasecmp(trim((string) $r['ROOM']), $room) === 0) return 'Schedule conflict: that room is already booked at that day and time.';
    }
    return '';
}
