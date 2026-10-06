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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'character_ref_add') {
    verify_csrf();
    $name       = trim((string)($_POST['character_reference_name'] ?? ''));
    $occupation = trim((string)($_POST['character_reference_occupation'] ?? '')) ?: null;
    $address    = trim((string)($_POST['character_reference_address'] ?? '')) ?: null;

    if ($name === '') {
        $errors[] = 'Name is required.';
    } else {
        $newRow = $conn->insert('character_reference', [
            'employee_id' => $employeeId,
            'character_reference_name' => $name,
            'character_reference_occupation' => $occupation,
            'character_reference_address' => $address,
        ]);
        $newId = $newRow['character_ref_id'] ?? null;

        log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'character_reference', 'INSERT', $newId);
        redirect(dash_url(['page' => 'employees', 'tab' => 'character', 'action' => 'view', 'employee_id' => $employeeId]));
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'character_ref_delete') {
    verify_csrf();
    $deleteId = (int)($_POST['character_ref_id'] ?? 0);
    $conn->delete('character_reference', ['character_ref_id=eq.' . $deleteId, 'employee_id=eq.' . $employeeId]);

    log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'character_reference', 'DELETE', $deleteId);
    redirect(dash_url(['page' => 'employees', 'tab' => 'character', 'action' => 'view', 'employee_id' => $employeeId]));
}

$refs = $conn->select('character_reference', ['employee_id=eq.' . $employeeId], 'character_ref_id');
?>
<?php foreach ($errors as $err): ?>
  <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<table class="data-table">
  <thead><tr><th>Name</th><th>Occupation</th><th>Address</th><th></th></tr></thead>
  <tbody>
    <?php if (!$refs): ?>
      <tr><td colspan="4" class="empty-row">No character references added yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($refs as $r): ?>
      <tr>
        <td><?= e($r['character_reference_name']) ?></td>
        <td><?= e($r['character_reference_occupation']) ?></td>
        <td><?= e($r['character_reference_address']) ?></td>
        <td>
          <form method="POST" action="<?= e(dash_url(['page' => 'employees', 'tab' => 'character', 'action' => 'view', 'employee_id' => $employeeId])) ?>"
                onsubmit="return confirm('Remove this reference?');">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="character_ref_delete">
            <input type="hidden" name="character_ref_id" value="<?= (int)$r['character_ref_id'] ?>">
            <button type="submit" class="btn btn-small btn-danger">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h3 class="subheading">Add Character Reference</h3>
<form method="POST" action="<?= e(dash_url(['page' => 'employees', 'tab' => 'character', 'action' => 'view', 'employee_id' => $employeeId])) ?>" class="record-form">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="character_ref_add">
  <div class="form-grid">
    <label>Name <input type="text" name="character_reference_name" required></label>
    <label>Occupation <input type="text" name="character_reference_occupation" required></label>
    <label>Address <input type="text" name="character_reference_address" required></label>
  </div>
  <button type="submit" class="btn btn-primary">Add</button>
</form>