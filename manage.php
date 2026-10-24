<?php
// ==========================================================
// manage.php?module=<key>
// One page that lists / searches / filters / adds / edits / deletes
// any module defined in config.php.
// ==========================================================
require "auth_check.php";
require "db.php";
require "config.php";
require "crud.php";
require "layout.php";

$module = $_GET['module'] ?? '';
if (!is_string($module) || !isset($modules[$module])) {
    header("Location: dashboard.php");
    exit;
}
require_access($module);              // server-side permission check

$cfg        = $modules[$module];
$active     = $module;
$canModify  = can_modify($module);    // read-only roles get a view-only page
$table      = $cfg['table'];
$idField    = $cfg['id_field'] ?? 'ID';
$fields     = $cfg['fields'];
$perPage    = 15;

$formFields = array_values(array_filter($fields, function ($f) { return $f['form'] ?? true; }));
$listFields = array_values(array_filter($fields, function ($f) { return ($f['list'] ?? true) && $f['type'] !== 'password'; }));
if (!empty($cfg['list_order'])) {
    $byName = [];
    foreach ($listFields as $f) $byName[$f['name']] = $f;
    $ordered = [];
    foreach ($cfg['list_order'] as $n) {
        if (isset($byName[$n])) $ordered[] = $byName[$n];
    }
    $listFields = $ordered;
}

// ==========================================================
// Handle add / update / delete (POST)
// ==========================================================
$formError = '';
$reopen = null;      // set when a save fails, so the form re-opens with what the user typed

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $self = $_SERVER['REQUEST_URI'];

    if (!$canModify) {
        flash_set('error', 'You do not have permission to change these records.');
        header("Location: $self");
        exit;
    }
    if (!csrf_valid()) {
        flash_set('error', 'Your session expired. Please try again.');
        header("Location: $self");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // ---------- DELETE ----------
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if (!empty($cfg['protect_self']) && $id === (int) $_SESSION['user_id']) {
            flash_set('error', 'You cannot delete the account you are logged in with.');
        } else {
            try {
                $old = db_run($conn, "SELECT * FROM `$table` WHERE `$idField` = ?", 'i', [$id])->get_result()->fetch_assoc();
                db_run($conn, "DELETE FROM `$table` WHERE `$idField` = ?", 'i', [$id]);
                if ($old) {
                    foreach ($fields as $f) {
                        if ($f['type'] === 'image') delete_image(__DIR__ . '/' . $f['upload_dir'], $old[$f['name']] ?? null);
                    }
                }
                flash_set('success', $cfg['singular'] . ' deleted.');
            } catch (mysqli_sql_exception $ex) {
                flash_set('error', friendly_db_error($ex, $cfg, 'delete'));
            }
        }
        header("Location: $self");
        exit;
    }

    // ---------- ADD / UPDATE ----------
    if ($action === 'add' || $action === 'update') {
        $isUpdate = ($action === 'update');
        $id = $isUpdate ? (int) ($_POST['id'] ?? 0) : 0;

        $res = collect_values($cfg, $isUpdate);
        $values = $res['values'];
        $error = $res['error'];

        if ($error === '' && !empty($cfg['validate'])) {
            $validator = $cfg['validate'];
            $error = $validator($values, $conn, $id);
        }

        $oldRow = null;
        if ($isUpdate) {
            $oldRow = db_run($conn, "SELECT * FROM `$table` WHERE `$idField` = ?", 'i', [$id])->get_result()->fetch_assoc();
            if ($error === '' && !$oldRow) $error = 'That record no longer exists.';
        }

        if ($error === '') {
            try {
                if ($isUpdate) {
                    $sets = []; $types = ''; $params = [];
                    foreach ($values as $col => $v) { $sets[] = "`$col` = ?"; $types .= 's'; $params[] = $v; }
                    $types .= 'i'; $params[] = $id;
                    db_run($conn, "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$idField` = ?", $types, $params);
                    foreach ($res['images'] as $col => $newName) {          // photo replaced -> remove the old file
                        $f = find_field($cfg, $col);
                        delete_image(__DIR__ . '/' . $f['upload_dir'], $oldRow[$col] ?? null);
                    }
                    flash_set('success', $cfg['singular'] . ' updated.');
                } else {
                    $cols = []; $marks = []; $types = ''; $params = [];
                    foreach ($values as $col => $v) { $cols[] = "`$col`"; $marks[] = '?'; $types .= 's'; $params[] = $v; }
                    db_run($conn, "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $marks) . ")", $types, $params);
                    flash_set('success', $cfg['singular'] . ' added.');
                }
                header("Location: $self");
                exit;
            } catch (mysqli_sql_exception $ex) {
                $error = friendly_db_error($ex, $cfg, 'save');
            }
        }

        // Save failed: remove any photo we just stored and re-open the form with the user's input
        foreach ($res['images'] as $col => $newName) {
            $f = find_field($cfg, $col);
            delete_image(__DIR__ . '/' . $f['upload_dir'], $newName);
        }
        $formError = $error;
        $keep = [];
        foreach ($formFields as $f) {
            if ($f['type'] === 'password') continue;
            if ($f['type'] === 'image') { $keep[$f['name']] = $oldRow[$f['name']] ?? ''; continue; }
            $keep[$f['name']] = is_string($_POST[$f['name']] ?? null) ? $_POST[$f['name']] : '';
        }
        $keep['__id'] = $id;
        $reopen = ['mode' => $action, 'values' => $keep];
    }
}

// ==========================================================
// Search + filters + sorting + paging (GET)
// ==========================================================
$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$filterVals = read_filters($cfg);
$qry = build_query($cfg, $q, $filterVals);

$total = (int) db_run($conn, $qry['count'], $qry['types'], $qry['params'])->get_result()->fetch_assoc()['c'];
$pages = max(1, (int) ceil($total / $perPage));
$page  = max(1, min($pages, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

$sort = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : '';
$dir  = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
$orderSql = "m.`$idField` DESC";
if ($sort === $idField) {
    $orderSql = "m.`$idField` $dir";
} else {
    foreach ($listFields as $f) {
        if ($f['name'] === $sort) {
            $orderSql = ($f['type'] === 'select') ? "`{$f['name']}_LABEL` $dir" : "m.`{$f['name']}` $dir";
            break;
        }
    }
}
$listSql = $qry['select'] . " ORDER BY $orderSql, m.`$idField` DESC LIMIT $perPage OFFSET $offset";
$listResult = db_run($conn, $listSql, $qry['types'], $qry['params'])->get_result();

$hasFilter = ($q !== '');
foreach ($filterVals as $v) { if ($v !== '') $hasFilter = true; }

// Extra per-row buttons (e.g. "View", "Grades"), only those this role may use
$rowLinks = [];
foreach ($cfg['row_links'] ?? [] as $lk) {
    $perm = $lk['perm'] ?? $module;
    if (!can_access($perm)) continue;
    if (!empty($lk['write']) && !can_modify($perm)) continue;
    $rowLinks[] = $lk;
}
$primaryLinks = array_values(array_filter($rowLinks, function ($l) { return !empty($l['primary']); }));
$toolLinks    = array_values(array_filter($rowLinks, function ($l) { return empty($l['primary']); }));
$hasActionsCol = $canModify || $primaryLinks;
$colCount = count($listFields) + 1 + ($toolLinks ? 1 : 0) + ($hasActionsCol ? 1 : 0);

// ==========================================================
// Small view helpers
// ==========================================================
function cell_html(array $f, array $row): string {
    $name = $f['name'];
    $v = $row[$name] ?? null;
    $dash = '<span class="muted">—</span>';
    switch ($f['type']) {
        case 'select':
            $t = $row[$name . '_LABEL'] ?? '';
            return $t === '' ? $dash : e($t);
        case 'image':
            if ($v) return '<img class="thumb" src="' . e($f['upload_dir'] . $v) . '" alt="">';
            $nm = trim(($row['FIRSTNAME'] ?? '') . ' ' . ($row['LASTNAME'] ?? ''));
            return '<span class="mini-avatar">' . e(initials($nm)) . '</span>';
        case 'time':     return $v ? e(fmt_time($v)) : $dash;
        case 'date':     return $v ? e(fmt_date($v)) : $dash;
        case 'datetime': return $v ? e(fmt_datetime($v)) : $dash;
        case 'enum':
            if (!empty($f['badge']) && $v) return badge($v);
            break;
    }
    return ($v === null || $v === '') ? $dash : e($v);
}

function field_html(array $f, mysqli $conn): string {
    $name = e($f['name']);
    $required = $f['required'] ?? true;
    $type = $f['type'];
    $req = ($required && $type !== 'password' && $type !== 'image') ? ' required' : '';
    $star = $required ? ' <span class="req">*</span>' : '';
    $ph = !empty($f['placeholder']) ? ' placeholder="' . e($f['placeholder']) . '"' : '';
    $ml = !empty($f['maxlength']) ? ' maxlength="' . (int) $f['maxlength'] . '"' : '';
    $id = 'fld_' . $name;

    $html = '';
    if (!empty($f['section'])) $html .= '<div class="form-section">' . e($f['section']) . '</div>';
    $html .= '<div class="field' . (!empty($f['full']) ? ' full' : '') . '"><label for="' . $id . '">' . e($f['label']) . $star . '</label>';

    switch ($type) {
        case 'select':
            $html .= '<select id="' . $id . '" name="' . $name . '"' . $req . '><option value="">' . ($required ? '— Select —' : '— None —') . '</option>';
            foreach (ref_options($conn, $f) as $o) {
                $html .= '<option value="' . e($o['id']) . '">' . e($o['label']) . '</option>';
            }
            $html .= '</select>';
            break;
        case 'enum':
            $html .= '<select id="' . $id . '" name="' . $name . '"' . $req . '><option value="">' . ($required ? '— Select —' : '— None —') . '</option>';
            foreach ($f['options'] as $o) $html .= '<option value="' . e($o) . '">' . e($o) . '</option>';
            $html .= '</select>';
            break;
        case 'textarea':
            $html .= '<textarea id="' . $id . '" name="' . $name . '" rows="2"' . $ml . $ph . $req . '></textarea>';
            break;
        case 'password':
            $html .= '<input type="password" id="' . $id . '" name="' . $name . '" autocomplete="new-password" minlength="8" data-required-add placeholder="At least 8 characters">';
            break;
        case 'image':
            $html .= '<input type="file" id="' . $id . '" name="' . $name . '" accept="image/jpeg,image/png,image/gif,image/webp">'
                  .  '<div class="photo-current" data-field="' . $name . '" data-dir="' . e($f['upload_dir']) . '" hidden><img alt=""><span>Current photo — choose a new file to replace it</span></div>';
            break;
        case 'number':
            $attrs = (isset($f['min']) ? ' min="' . (float) $f['min'] . '"' : '') . (isset($f['max']) ? ' max="' . (float) $f['max'] . '"' : '');
            $html .= '<input type="number" step="1" id="' . $id . '" name="' . $name . '"' . $attrs . $ph . $req . '>';
            break;
        default:   // text, email, tel, date, time
            $html .= '<input type="' . e($type) . '" id="' . $id . '" name="' . $name . '"' . $ml . $ph . $req . '>';
    }
    return $html . '</div>';
}

function render_pager(int $page, int $pages): void {
    if ($pages <= 1) return;
    $from = max(1, $page - 2);
    $to = min($pages, $page + 2);
    ?>
    <nav class="pager" aria-label="Pages">
        <?php if ($page > 1): ?><a class="pg" href="<?= e(url_with(['page' => $page - 1])) ?>" aria-label="Previous"><?= icon('chevron-left', 16) ?></a><?php endif; ?>
        <?php if ($from > 1): ?><a class="pg" href="<?= e(url_with(['page' => 1])) ?>">1</a><?php if ($from > 2): ?><span class="pg-gap">…</span><?php endif; endif; ?>
        <?php for ($i = $from; $i <= $to; $i++): ?>
            <a class="pg <?= $i === $page ? 'current' : '' ?>" href="<?= e(url_with(['page' => $i])) ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($to < $pages): ?><?php if ($to < $pages - 1): ?><span class="pg-gap">…</span><?php endif; ?><a class="pg" href="<?= e(url_with(['page' => $pages])) ?>"><?= $pages ?></a><?php endif; ?>
        <?php if ($page < $pages): ?><a class="pg" href="<?= e(url_with(['page' => $page + 1])) ?>" aria-label="Next"><?= icon('chevron-right', 16) ?></a><?php endif; ?>
    </nav>
    <?php
}

// ==========================================================
// Page
// ==========================================================
$exportParams = ['module' => $module, 'q' => $q];
foreach ($filterVals as $fk => $fv) { $exportParams['f_' . $fk] = $fv; }
$exportQuery = http_build_query($exportParams);

ob_start();
?>
    <a class="btn btn-outline" href="export.php?<?= e($exportQuery) ?>"><?= icon('download', 16) ?> Export to Excel</a>
    <?php if ($canModify): ?>
        <button type="button" class="btn btn-primary" data-add><?= icon('plus', 16) ?> Add <?= e($cfg['singular']) ?></button>
    <?php endif; ?>
<?php
$actions = ob_get_clean();

$subtitle = number_format($total) . ' ' . ($total === 1 ? 'record' : 'records') . ($hasFilter ? ' match your search' : ' in total')
          . ($canModify ? '' : ' · View only');

render_header($cfg['label'], $module, ['subtitle' => $subtitle, 'actions' => $actions]);
?>

<section class="card">
    <!-- Search + filters: always visible above the table -->
    <form class="toolbar" method="get" action="manage.php">
        <input type="hidden" name="module" value="<?= e($module) ?>">
        <?php if ($sort !== ''): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><input type="hidden" name="dir" value="<?= $dir === 'ASC' ? 'asc' : 'desc' ?>"><?php endif; ?>

        <div class="search-field">
            <?= icon('search', 18) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search <?= e(strtolower($cfg['label'])) ?>…" aria-label="Search <?= e($cfg['label']) ?>">
        </div>

        <?php foreach ($cfg['filters'] ?? [] as $fname):
            $ff = find_field($cfg, $fname);
            if (!$ff) continue; ?>
            <select class="filter" name="f_<?= e($fname) ?>" data-autosubmit aria-label="<?= e($ff['label']) ?>">
                <option value=""><?= e($ff['filter_label'] ?? ('All ' . strtolower($ff['label']))) ?></option>
                <?php foreach (field_choices($conn, $ff) as $c): ?>
                    <option value="<?= e($c[0]) ?>" <?= (string) ($filterVals[$fname] ?? '') === (string) $c[0] ? 'selected' : '' ?>><?= e($c[1]) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endforeach; ?>

        <button class="btn btn-primary" type="submit"><?= icon('search', 16) ?> Search</button>
        <?php if ($hasFilter): ?><a class="btn btn-ghost" href="manage.php?module=<?= e($module) ?>"><?= icon('x', 16) ?> Clear</a><?php endif; ?>
    </form>

    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <?php
                    $sortLink = function (string $name, string $label) use ($sort, $dir) {
                        $isCur = ($sort === $name);
                        $next = ($isCur && $dir === 'ASC') ? 'desc' : 'asc';
                        $arrow = $isCur ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
                        return '<a class="th-sort" href="' . e(url_with(['sort' => $name, 'dir' => $next, 'page' => null])) . '">' . e($label) . '<span class="arrow">' . $arrow . '</span></a>';
                    };
                    ?>
                    <th class="col-id"><?= $sortLink($idField, 'ID') ?></th>
                    <?php foreach ($listFields as $f): ?>
                        <th><?= $f['type'] === 'image' ? '' : $sortLink($f['name'], $f['label']) ?></th>
                    <?php endforeach; ?>
                    <?php if ($toolLinks): ?><th>Tools</th><?php endif; ?>
                    <?php if ($hasActionsCol): ?><th class="col-actions">Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($listResult->num_rows > 0): while ($row = $listResult->fetch_assoc()):
                $rid = (int) $row[$idField];

                $rec = ['__id' => $rid];
                foreach ($formFields as $f) {
                    if ($f['type'] === 'password') continue;
                    $v = $row[$f['name']] ?? '';
                    if ($f['type'] === 'time' && $v) $v = substr($v, 0, 5);
                    $rec[$f['name']] = ($v === null) ? '' : (string) $v;
                }
                $recJson = json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            ?>
                <tr>
                    <td class="col-id muted"><?= $rid ?></td>
                    <?php foreach ($listFields as $f): ?>
                        <td><?= cell_html($f, $row) ?></td>
                    <?php endforeach; ?>

                    <?php if ($toolLinks): ?>
                    <td>
                        <div class="tool-links">
                            <?php foreach ($toolLinks as $lk): ?>
                                <a href="<?= e(str_replace('{id}', (string) $rid, $lk['url'])) ?>"><?= icon($lk['icon'], 15) ?><?= e($lk['label']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <?php endif; ?>

                    <?php if ($hasActionsCol): ?>
                    <td class="col-actions">
                        <div class="row-actions">
                            <?php foreach ($primaryLinks as $lk): ?>
                                <a class="act act-view" href="<?= e(str_replace('{id}', (string) $rid, $lk['url'])) ?>"><?= icon($lk['icon'], 15) ?><span><?= e($lk['label']) ?></span></a>
                            <?php endforeach; ?>
                            <?php if ($canModify): ?>
                                <button type="button" class="act act-edit" data-edit="<?= e($recJson) ?>"><?= icon('edit', 15) ?><span>Edit</span></button>
                                <button type="button" class="act act-delete" data-delete-id="<?= $rid ?>" data-delete-name="<?= e(row_title($cfg, $row)) ?>"><?= icon('trash', 15) ?><span>Delete</span></button>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="<?= $colCount ?>">
                    <div class="empty">
                        <?= icon('search', 28) ?>
                        <strong>No <?= e(strtolower($cfg['label'])) ?> found</strong>
                        <span><?= $hasFilter ? 'Try a different search or clear the filters.' : ($canModify ? 'Click “Add ' . e($cfg['singular']) . '” to create the first one.' : 'Nothing to show yet.') ?></span>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-foot">
        <span class="muted">
            <?php if ($total > 0): ?>Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $total)) ?> of <?= number_format($total) ?><?php endif; ?>
        </span>
        <?php render_pager($page, $pages); ?>
    </div>
</section>

<?php if ($canModify): ?>
<!-- ================= Add / Edit form ================= -->
<dialog id="formModal" class="modal" data-singular="<?= e($cfg['singular']) ?>">
    <form method="post" action="<?= e($_SERVER['REQUEST_URI']) ?>" enctype="multipart/form-data" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="id" id="formId" value="">

        <div class="modal-head">
            <h3 id="formTitle">Add <?= e($cfg['singular']) ?></h3>
            <button type="button" class="icon-btn" data-close aria-label="Close"><?= icon('x', 18) ?></button>
        </div>

        <div class="modal-body">
            <div class="form-error" id="formError" <?= $formError === '' ? 'hidden' : '' ?>><?= icon('alert', 18) ?><span><?= e($formError) ?></span></div>
            <div class="form-grid">
                <?php foreach ($formFields as $f) echo field_html($f, $conn); ?>
            </div>
        </div>

        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close>Cancel</button>
            <button type="submit" class="btn btn-primary"><?= icon('save', 16) ?> Save <?= e($cfg['singular']) ?></button>
        </div>
    </form>
</dialog>

<!-- ================= Delete confirmation ================= -->
<dialog id="deleteModal" class="modal modal-sm">
    <form method="post" action="<?= e($_SERVER['REQUEST_URI']) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId" value="">
        <div class="modal-body confirm">
            <span class="confirm-icon"><?= icon('trash', 26) ?></span>
            <h3>Delete this <?= e(strtolower($cfg['singular'])) ?>?</h3>
            <p>You are about to delete <strong id="deleteName"></strong>. This action cannot be undone.</p>
        </div>
        <div class="modal-foot center">
            <button type="button" class="btn btn-ghost" data-close>Cancel</button>
            <button type="submit" class="btn btn-danger"><?= icon('trash', 16) ?> Yes, delete</button>
        </div>
    </form>
</dialog>

<script>window.SMS_REOPEN = <?= json_encode($reopen, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php endif; ?>

<?php render_footer(); ?>
