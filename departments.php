<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit('Direct access not allowed.'); }

$errors = [];

// --- Add a department ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'department_add') {
    verify_csrf();
    $newName = trim((string)($_POST['department_name'] ?? ''));
    if ($newName === '') {
        $errors[] = 'Department name is required.';
    } else {
        try {
            $conn->insert('departments', ['name' => $newName]);
            $_SESSION['flash_success'] = 'Department added.';
            redirect(dash_url(['page' => 'departments', 'department' => $newName]));
        } catch (SupabaseException $ex) {
            $errors[] = $ex->pgCode === '23505' ? 'That department already exists.' : 'Could not add the department.';
        }
    }
}

// --- Edit (rename) a department ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'department_edit') {
    verify_csrf();
    $oldName = trim((string)($_POST['old_name'] ?? ''));
    $newName = trim((string)($_POST['department_name'] ?? ''));
    if ($newName === '') {
        $errors[] = 'Department name is required.';
    } else {
        try {
            $conn->update('departments', ['name=eq."' . $oldName . '"'], ['name' => $newName]);
            $conn->update('employee_position', ['position_department=eq."' . $oldName . '"'], ['position_department' => $newName]);
            $conn->update('department_positions', ['department=eq."' . $oldName . '"'], ['department' => $newName]);
            $_SESSION['flash_success'] = 'Department updated.';
            redirect(dash_url(['page' => 'departments', 'department' => $newName]));
        } catch (SupabaseException $ex) {
            $errors[] = $ex->pgCode === '23505' ? 'That department name already exists.' : 'Could not update the department.';
        }
    }
}

// --- Delete a department ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'department_delete') {
    verify_csrf();
    $name = trim((string)($_POST['department_name'] ?? ''));
    if ($name !== '') {
        try {
            $conn->delete('departments', ['name=eq."' . $name . '"']);
            $_SESSION['flash_success'] = 'Department deleted.';
        } catch (SupabaseException $ex) {
            $errors[] = 'Could not delete the department.';
        }
    }
    redirect(dash_url(['page' => 'departments']));
}

// --- Add a position (to the selected department) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'position_title_add') {
    verify_csrf();
    $dept  = trim((string)($_POST['department'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        $errors[] = 'Position title is required.';
    } else {
        try {
            $conn->insert('department_positions', ['department' => $dept, 'title' => $title]);
            $_SESSION['flash_success'] = 'Position added.';
            redirect(dash_url(['page' => 'departments', 'department' => $dept]));
        } catch (SupabaseException $ex) {
            $errors[] = 'Could not add the position. (Is the department_positions table created?)';
        }
    }
}

// --- Edit a position ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'position_title_edit') {
    verify_csrf();
    $id    = (int)($_POST['position_title_id'] ?? 0);
    $dept  = trim((string)($_POST['department'] ?? ''));
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') {
        $errors[] = 'Position title is required.';
    } else {
        try {
            $conn->update('department_positions', ['id=eq.' . $id], ['title' => $title]);
            $_SESSION['flash_success'] = 'Position updated.';
            redirect(dash_url(['page' => 'departments', 'department' => $dept]));
        } catch (SupabaseException $ex) {
            $errors[] = 'Could not update the position.';
        }
    }
}

// --- Delete a position ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'position_title_delete') {
    verify_csrf();
    $id   = (int)($_POST['position_title_id'] ?? 0);
    $dept = trim((string)($_POST['department'] ?? ''));
    try {
        $conn->delete('department_positions', ['id=eq.' . $id]);
        $_SESSION['flash_success'] = 'Position deleted.';
    } catch (SupabaseException $ex) {
        $errors[] = 'Could not delete the position.';
    }
    redirect(dash_url(['page' => 'departments', 'department' => $dept]));
}

// Employee counts per department.
$positionRows = $conn->select('employee_position', [], null, null, 'position_department,employee_id');
$counts = [];
foreach ($positionRows as $row) {
    $dept = (string)($row['position_department'] ?? '');
    $empId = (int)($row['employee_id'] ?? 0);
    if ($dept === '' || $empId === 0) { continue; }
    $counts[$dept][$empId] = true;
}

$departmentSearch = trim((string)($_GET['q'] ?? ''));
$departmentNames = get_department_list();
if ($departmentSearch !== '') {
    $departmentNames = array_values(array_filter($departmentNames, static fn(string $d): bool => stripos($d, $departmentSearch) !== false));
}

$selectedDepartment = trim((string)($_GET['department'] ?? ''));
$editingDepartment = trim((string)($_GET['edit'] ?? ''));
$editingPositionId = (int)($_GET['edit_position'] ?? 0);

$positions = [];
try {
    if ($selectedDepartment !== '') {
        $positions = $conn->select('department_positions', ['department=eq."' . $selectedDepartment . '"'], 'title');
    }
} catch (SupabaseException $ex) {
    $errors[] = 'Could not load positions. (Is the department_positions table created?)';
}
$positionSearch = trim((string)($_GET['pq'] ?? ''));
if ($positionSearch !== '') {
    $positions = array_values(array_filter($positions, static fn($p) => stripos((string)$p['title'], $positionSearch) !== false));
}
$flashSuccess = flash_get('flash_success');
?>
<h1 class="page-title">Departments</h1>

<?php if ($flashSuccess): ?><div class="flash flash-success"><?= e($flashSuccess) ?></div><?php endif; ?>
<?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="home-columns">
  <!-- LEFT: departments -->
  <aside class="department-nav" style="width:240px;">
    <p class="department-nav-title">Departments</p>

    <form method="GET" action="admin_dashboard.php" style="margin-bottom:10px;">
      <input type="hidden" name="page" value="departments">
      <input type="text" name="q" placeholder="Search..." value="<?= e($departmentSearch) ?>" style="width:100%;font-size:13px;padding:7px 9px;border:1px solid var(--paper-line);border-radius:7px;">
    </form>

    <?php foreach ($departmentNames as $departmentName): ?>
      <?php if ($editingDepartment === $departmentName): ?>
        <form method="POST" action="<?= e(dash_url(['page' => 'departments'])) ?>" style="margin-bottom:6px;">
          <?= csrf_field() ?>
          <input type="hidden" name="form" value="department_edit">
          <input type="hidden" name="old_name" value="<?= e($departmentName) ?>">
          <input type="text" name="department_name" value="<?= e($departmentName) ?>" required style="width:100%;font-size:13px;padding:6px;">
          <button type="submit" class="btn btn-small btn-primary" style="margin-top:4px;">Save</button>
          <a class="btn btn-small btn-ghost" href="<?= e(dash_url(['page' => 'departments'])) ?>">Cancel</a>
        </form>
      <?php else: ?>
        <div style="display:flex;align-items:center;gap:4px;">
          <a class="department-link <?= $selectedDepartment === $departmentName ? 'active' : '' ?>" style="flex:1;"
             href="<?= e(dash_url(['page' => 'departments', 'department' => $departmentName])) ?>">
            <?= e($departmentName) ?> <span style="opacity:.6;font-weight:400;">(<?= isset($counts[$departmentName]) ? count($counts[$departmentName]) : 0 ?>)</span>
          </a>
          <a title="Edit" href="<?= e(dash_url(['page' => 'departments', 'edit' => $departmentName])) ?>" style="color:var(--forest);padding:4px;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </a>
          <form method="POST" action="<?= e(dash_url(['page' => 'departments'])) ?>" style="display:inline;" onsubmit="return confirm('Delete this department?');">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="department_delete">
            <input type="hidden" name="department_name" value="<?= e($departmentName) ?>">
            <button type="submit" title="Delete" style="background:none;border:none;cursor:pointer;color:#C0392B;padding:4px;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          </form>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <button type="button" class="btn btn-small btn-pink" style="margin-top:10px;width:100%;" onclick="document.getElementById('addDeptForm').style.display='block'; this.style.display='none';">+ Add Department</button>
    <form id="addDeptForm" method="POST" action="<?= e(dash_url(['page' => 'departments'])) ?>" style="display:none;margin-top:10px;">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="department_add">
      <input type="text" name="department_name" placeholder="New department" required style="width:100%;font-size:13px;padding:7px 9px;margin-bottom:6px;">
      <button type="submit" class="btn btn-small btn-primary">Save</button>
    </form>
  </aside>

  <!-- RIGHT: positions of the selected department -->
  <div style="flex:1;min-width:0;">
    <?php if ($selectedDepartment === ''): ?>
      <p class="current-employee">Select a department on the left to see and manage its positions.</p>
    <?php else: ?>
      <h2 class="subheading" style="margin-top:0;">Positions in <?= e($selectedDepartment) ?></h2>

      <div class="action-bar" style="justify-content:space-between;flex-wrap:wrap;">
        <form method="GET" action="admin_dashboard.php" class="search-bar" style="flex:1;min-width:220px;">
          <input type="hidden" name="page" value="departments">
          <input type="hidden" name="department" value="<?= e($selectedDepartment) ?>">
          <input type="text" name="pq" placeholder="Search positions" value="<?= e($positionSearch) ?>">
          <button type="submit" class="btn btn-primary">Search</button>
        </form>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('addPosForm').style.display='block'; this.style.display='none';">+ Add Position</button>
      </div>

      <form id="addPosForm" method="POST" action="<?= e(dash_url(['page' => 'departments', 'department' => $selectedDepartment])) ?>" class="record-form" style="display:none;margin-bottom:16px;">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="position_title_add">
        <input type="hidden" name="department" value="<?= e($selectedDepartment) ?>">
        <label style="margin-bottom:12px;">Position Title
          <input type="text" name="title" required>
        </label>
        <button type="submit" class="btn btn-primary">Save</button>
      </form>

      <table class="data-table">
        <thead><tr><th>Position</th><th></th></tr></thead>
        <tbody>
          <?php if (!$positions): ?>
            <tr><td colspan="2" class="empty-row">No positions yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($positions as $position): ?>
            <?php if ($editingPositionId === (int)$position['id']): ?>
            <tr>
              <td colspan="2">
                <form method="POST" action="<?= e(dash_url(['page' => 'departments', 'department' => $selectedDepartment])) ?>" style="display:flex;gap:8px;align-items:center;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="form" value="position_title_edit">
                  <input type="hidden" name="position_title_id" value="<?= (int)$position['id'] ?>">
                  <input type="hidden" name="department" value="<?= e($selectedDepartment) ?>">
                  <input type="text" name="title" value="<?= e($position['title']) ?>" required style="flex:1;font-size:14px;">
                  <button type="submit" class="btn btn-small btn-primary">Save</button>
                  <a class="btn btn-small btn-ghost" href="<?= e(dash_url(['page' => 'departments', 'department' => $selectedDepartment])) ?>">Cancel</a>
                </form>
              </td>
            </tr>
            <?php else: ?>
            <tr>
              <td><?= e($position['title']) ?></td>
              <td style="white-space:nowrap;">
                <a class="btn btn-small" title="Edit" href="<?= e(dash_url(['page' => 'departments', 'department' => $selectedDepartment, 'edit_position' => (int)$position['id']])) ?>" style="color:var(--forest);">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </a>
                <form method="POST" action="<?= e(dash_url(['page' => 'departments', 'department' => $selectedDepartment])) ?>" style="display:inline;" onsubmit="return confirm('Delete this position?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="form" value="position_title_delete">
                  <input type="hidden" name="position_title_id" value="<?= (int)$position['id'] ?>">
                  <input type="hidden" name="department" value="<?= e($selectedDepartment) ?>">
                  <button type="submit" class="btn btn-small" title="Delete" style="color:#C0392B;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C0392B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                  </button>
                </form>
              </td>
            </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
