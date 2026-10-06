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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'emergency_contact_add') {
    verify_csrf();
    $name         = trim((string)($_POST['emergency_contact_name'] ?? ''));
    $relationship = trim((string)($_POST['emergency_contact_relationship'] ?? '')) ?: null;
    $contact      = trim((string)($_POST['emergency_contact'] ?? '')) ?: null;
    $address      = trim((string)($_POST['emergency_contact_address'] ?? '')) ?: null;

    if ($name === '') {
        $errors[] = 'Name is required.';
    } else {
        $newRow = $conn->insert('emergency_contact', [
            'employee_id' => $employeeId,
            'emergency_contact_name' => $name,
            'emergency_contact_relationship' => $relationship,
            'emergency_contact' => $contact,
            'emergency_contact_address' => $address,
        ]);
        $newId = $newRow['emergency_contact_id'] ?? null;

        log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'emergency_contact', 'INSERT', $newId);
        redirect(dash_url(['page' => 'employees', 'tab' => 'emergency', 'action' => 'view', 'employee_id' => $employeeId]));
    }
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'emergency_contact_delete') {
    verify_csrf();
    $deleteId = (int)($_POST['emergency_contact_id'] ?? 0);
    $conn->delete('emergency_contact', ['emergency_contact_id=eq.' . $deleteId, 'employee_id=eq.' . $employeeId]);

    log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'emergency_contact', 'DELETE', $deleteId);
    redirect(dash_url(['page' => 'employees', 'tab' => 'emergency', 'action' => 'view', 'employee_id' => $employeeId]));
}

$contacts = $conn->select('emergency_contact', ['employee_id=eq.' . $employeeId], 'emergency_contact_id');
?>
<?php foreach ($errors as $err): ?>
  <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<table class="data-table">
  <thead><tr><th>Name</th><th>Relationship</th><th>Contact</th><th>Address</th><th></th></tr></thead>
  <tbody>
    <?php if (!$contacts): ?>
      <tr><td colspan="5" class="empty-row">No emergency contacts added yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($contacts as $c): ?>
      <tr>
        <td><?= e($c['emergency_contact_name']) ?></td>
        <td><?= e($c['emergency_contact_relationship']) ?></td>
        <td><?= e($c['emergency_contact']) ?></td>
        <td><?= e($c['emergency_contact_address']) ?></td>
        <td>
          <form method="POST" action="<?= e(dash_url(['page' => 'employees', 'tab' => 'emergency', 'action' => 'view', 'employee_id' => $employeeId])) ?>"
                onsubmit="return confirm('Remove this emergency contact?');">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="emergency_contact_delete">
            <input type="hidden" name="emergency_contact_id" value="<?= (int)$c['emergency_contact_id'] ?>">
            <button type="submit" class="btn btn-small btn-danger">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h3 class="subheading">Add Emergency Contact</h3>
<form method="POST" action="<?= e(dash_url(['page' => 'employees', 'tab' => 'emergency', 'action' => 'view', 'employee_id' => $employeeId])) ?>" class="record-form">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="emergency_contact_add">
  <div class="form-grid">
    <label>Name <input type="text" name="emergency_contact_name" required></label>
    <label>Relationship <input type="text" name="emergency_contact_relationship" required></label>
    <label>Contact <input type="text" name="emergency_contact" maxlength="11" required></label>
    <label>Address <input type="text" name="emergency_contact_address" required></label>
  </div>
  <button type="submit" class="btn btn-primary">Add</button>
</form>