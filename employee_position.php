<?php
declare(strict_types=1);
/** @var SupabaseClient $conn */
/** @var int|null $employeeId */
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit('Direct access not allowed.'); }

if (!$employeeId) {
    echo '<p class="empty-row">Save the employee\'s personal information first.</p>';
    return;
}

$errors = [];
// Shared list defined once in helpers.php so it always matches the
// Employees list's department filter and the login Department field.
$departments = get_department_list();

// Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'employee_position_add') {
    verify_csrf();
    $department = $_POST['position_department'] ?? '';
    $start      = ($_POST['position_start'] ?? '') !== '' ? $_POST['position_start'] : null;
    $end        = ($_POST['position_end'] ?? '') !== '' ? $_POST['position_end'] : null;
    $monthly    = ($_POST['position_monthly'] ?? '') !== '' ? (float)$_POST['position_monthly'] : null;
    $daily      = ($_POST['position_daily'] ?? '') !== '' ? (float)$_POST['position_daily'] : null;
    $hourly     = ($_POST['position_hourly'] ?? '') !== '' ? (float)$_POST['position_hourly'] : null;

    if (!in_array($department, $departments, true)) {
        $errors[] = 'Please choose a valid Department.';
    } elseif ($start === null) {
        $errors[] = 'Start date is required.';
    } else {
        $newRow = $conn->insert('employee_position', [
            'employee_id' => $employeeId,
            'position_department' => $department,
            'position_start' => $start,
            'position_end' => $end,
            'position_monthly' => $monthly,
            'position_daily' => $daily,
            'position_hourly' => $hourly,
        ]);
        $newId = $newRow['position_id'] ?? null;

        log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'employee_position', 'INSERT', $newId);
        redirect(dash_url(['page' => 'employees', 'tab' => 'position', 'action' => 'view', 'employee_id' => $employeeId]));
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'employee_position_delete') {
    verify_csrf();
    $deleteId = (int)($_POST['position_id'] ?? 0);
    $conn->delete('employee_position', ['position_id=eq.' . $deleteId, 'employee_id=eq.' . $employeeId]);

    log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'employee_position', 'DELETE', $deleteId);
    redirect(dash_url(['page' => 'employees', 'tab' => 'position', 'action' => 'view', 'employee_id' => $employeeId]));
}

$positions = $conn->select('employee_position', ['employee_id=eq.' . $employeeId], 'position_start.desc');
?>
<?php foreach ($errors as $err): ?>
  <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<table class="data-table">
  <thead><tr><th>Department</th><th>Start</th><th>End</th><th>Monthly</th><th>Daily</th><th>Hourly</th><th></th></tr></thead>
  <tbody>
    <?php if (!$positions): ?>
      <tr><td colspan="7" class="empty-row">No positions added yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($positions as $p): ?>
      <tr>
        <td><?= e($p['position_department']) ?></td>
        <td><?= e($p['position_start']) ?></td>
        <td><?= e($p['position_end'] ?? 'Present') ?></td>
        <td><?= $p['position_monthly'] !== null ? number_format((float)$p['position_monthly'], 2) : '—' ?></td>
        <td><?= $p['position_daily'] !== null ? number_format((float)$p['position_daily'], 2) : '—' ?></td>
        <td><?= $p['position_hourly'] !== null ? number_format((float)$p['position_hourly'], 2) : '—' ?></td>
        <td>
          <form method="POST" action="<?= e(dash_url(['page' => 'employees', 'tab' => 'position', 'action' => 'view', 'employee_id' => $employeeId])) ?>"
                onsubmit="return confirm('Remove this position record?');">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="employee_position_delete">
            <input type="hidden" name="position_id" value="<?= (int)$p['position_id'] ?>">
            <button type="submit" class="btn btn-small btn-danger">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h3 class="subheading">Add Employee Position</h3>
<form method="POST" action="<?= e(dash_url(['page' => 'employees', 'tab' => 'position', 'action' => 'view', 'employee_id' => $employeeId])) ?>" class="record-form">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="employee_position_add">
  <div class="form-grid">
    <label>Department
      <select name="position_department" required>
        <option value="">-- Select --</option>
        <?php foreach ($departments as $dept): ?>
          <option value="<?= $dept ?>"><?= $dept ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Start <input type="date" name="position_start" required></label>
    <label>End <input type="date" name="position_end"></label>
    <label>Monthly <input type="number" step="0.01" name="position_monthly"></label>
    <label>Daily <input type="number" step="0.01" name="position_daily"></label>
    <label>Hourly <input type="number" step="0.01" name="position_hourly"></label>
  </div>
  <button type="submit" class="btn btn-primary">Add</button>
</form>