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

// Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'dependent_add') {
    verify_csrf();
    $name         = trim((string)($_POST['dependent_name'] ?? ''));
    $bdate        = ($_POST['dependent_bdate'] ?? '') !== '' ? $_POST['dependent_bdate'] : null;
    $relationship = trim((string)($_POST['dependent_relationship'] ?? '')) ?: null;

    if ($name === '') {
        $errors[] = 'Name is required.';
    } else {
        $newRow = $conn->insert('dependent', [
            'employee_id' => $employeeId,
            'dependent_name' => $name,
            'dependent_bdate' => $bdate,
            'dependent_relationship' => $relationship,
        ]);
        $newId = $newRow['dependent_id'] ?? null;

        log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'dependent', 'INSERT', $newId);
        redirect(dash_url(['page' => 'employees', 'tab' => 'dependent', 'action' => 'view', 'employee_id' => $employeeId]));
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'dependent_delete') {
    verify_csrf();
    $deleteId = (int)($_POST['dependent_id'] ?? 0);
    $conn->delete('dependent', ['dependent_id=eq.' . $deleteId, 'employee_id=eq.' . $employeeId]);

    log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'dependent', 'DELETE', $deleteId);
    redirect(dash_url(['page' => 'employees', 'tab' => 'dependent', 'action' => 'view', 'employee_id' => $employeeId]));
}

$dependents = $conn->select('dependent', ['employee_id=eq.' . $employeeId], 'dependent_id');
?>
<?php foreach ($errors as $err): ?>
  <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<table class="data-table">
  <thead><tr><th>Name</th><th>Birthdate</th><th>Relationship</th><th></th></tr></thead>
  <tbody>
    <?php if (!$dependents): ?>
      <tr><td colspan="4" class="empty-row">No dependents added yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($dependents as $d): ?>
      <tr>
        <td><?= e($d['dependent_name']) ?></td>
        <td><?= e($d['dependent_bdate']) ?></td>
        <td><?= e($d['dependent_relationship']) ?></td>
        <td>
          <form method="POST" action="<?= e(dash_url(['page' => 'employees', 'tab' => 'dependent', 'action' => 'view', 'employee_id' => $employeeId])) ?>"
                onsubmit="return confirm('Remove this dependent?');">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="dependent_delete">
            <input type="hidden" name="dependent_id" value="<?= (int)$d['dependent_id'] ?>">
            <button type="submit" class="btn btn-small btn-danger">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h3 class="subheading">Add Dependent</h3>
<form method="POST" action="<?= e(dash_url(['page' => 'employees', 'tab' => 'dependent', 'action' => 'view', 'employee_id' => $employeeId])) ?>" class="record-form">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="dependent_add">
  <div class="form-grid">
    <label>Name <input type="text" name="dependent_name" required></label>
    <label>Birthdate <input type="date" name="dependent_bdate" required></label>
    <label>Relationship <input type="text" name="dependent_relationship" required></label>
  </div>
  <button type="submit" class="btn btn-primary">Add</button>
</form>