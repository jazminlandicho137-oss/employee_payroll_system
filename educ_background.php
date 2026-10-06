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
$levels = ['Primary', 'Secondary', 'Tertiary', 'Transfer'];

// Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'educ_background_add') {
    verify_csrf();
    $school  = trim((string)($_POST['educ_school'] ?? ''));
    $address = trim((string)($_POST['educ_address'] ?? '')) ?: null;
    $sy      = trim((string)($_POST['educ_sy'] ?? '')) ?: null;
    $level   = $_POST['educ_level'] ?? '';

    if ($school === '') {
        $errors[] = 'School is required.';
    } elseif (!in_array($level, $levels, true)) {
        $errors[] = 'Please choose a valid Level.';
    } else {
        $newRow = $conn->insert('educ_background', [
            'employee_id' => $employeeId,
            'educ_school' => $school,
            'educ_address' => $address,
            'educ_sy' => $sy,
            'educ_level' => $level,
        ]);
        $newId = $newRow['educ_id'] ?? null;

        log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'educ_background', 'INSERT', $newId);
        redirect(dash_url(['page' => 'employees', 'tab' => 'education', 'action' => 'view', 'employee_id' => $employeeId]));
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'educ_background_delete') {
    verify_csrf();
    $deleteId = (int)($_POST['educ_id'] ?? 0);
    $conn->delete('educ_background', ['educ_id=eq.' . $deleteId, 'employee_id=eq.' . $employeeId]);

    log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'educ_background', 'DELETE', $deleteId);
    redirect(dash_url(['page' => 'employees', 'tab' => 'education', 'action' => 'view', 'employee_id' => $employeeId]));
}

$records = $conn->select('educ_background', ['employee_id=eq.' . $employeeId], 'educ_id');
?>
<?php foreach ($errors as $err): ?>
  <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<table class="data-table">
  <thead><tr><th>Level</th><th>School</th><th>Address</th><th>S.Y.</th><th></th></tr></thead>
  <tbody>
    <?php if (!$records): ?>
      <tr><td colspan="5" class="empty-row">No educational background added yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($records as $r): ?>
      <tr>
        <td><?= e($r['educ_level']) ?></td>
        <td><?= e($r['educ_school']) ?></td>
        <td><?= e($r['educ_address']) ?></td>
        <td><?= e($r['educ_sy']) ?></td>
        <td>
          <form method="POST" action="<?= e(dash_url(['page' => 'employees', 'tab' => 'education', 'action' => 'view', 'employee_id' => $employeeId])) ?>"
                onsubmit="return confirm('Remove this entry?');">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="educ_background_delete">
            <input type="hidden" name="educ_id" value="<?= (int)$r['educ_id'] ?>">
            <button type="submit" class="btn btn-small btn-danger">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h3 class="subheading">Add Educational Background</h3>
<form method="POST" action="<?= e(dash_url(['page' => 'employees', 'tab' => 'education', 'action' => 'view', 'employee_id' => $employeeId])) ?>" class="record-form">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="educ_background_add">
  <div class="form-grid">
    <label>School <input type="text" name="educ_school" required></label>
    <label>Address <input type="text" name="educ_address" required></label>
    <label>S.Y. <input type="text" name="educ_sy" placeholder="e.g. 2019-2020" required></label>
    <label>Level
      <select name="educ_level" required>
        <option value="">-- Select --</option>
        <?php foreach ($levels as $lvl): ?>
          <option value="<?= $lvl ?>"><?= $lvl ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <button type="submit" class="btn btn-primary">Add</button>
</form>