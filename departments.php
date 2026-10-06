<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit('Direct access not allowed.'); }

// Count how many employees are in each department (based on their
// employee_position rows, same source as the Employees list filter).
$positionRows = $conn->select('employee_position', [], null, null, 'position_department,employee_id');
$counts = [];
foreach ($positionRows as $row) {
    $dept = (string)($row['position_department'] ?? '');
    $empId = (int)($row['employee_id'] ?? 0);
    if ($dept === '' || $empId === 0) {
        continue;
    }
    $counts[$dept][$empId] = true;
}
?>
<?php
$departmentSearch = trim((string)($_GET['q'] ?? ''));
$departmentNames = get_department_list();
if ($departmentSearch !== '') {
    $departmentNames = array_values(array_filter(
        $departmentNames,
        static fn(string $d): bool => stripos($d, $departmentSearch) !== false
    ));
}
?>
<h1 class="page-title">Departments</h1>

<form method="GET" action="admin_dashboard.php" class="search-bar">
  <input type="hidden" name="page" value="departments">
  <input type="text" name="q" placeholder="Search departments" value="<?= e($departmentSearch) ?>">
  <button type="submit" class="btn btn-primary">Search</button>
</form>

<table class="data-table">
  <thead>
    <tr><th>Department</th><th>Employees</th><th></th></tr>
  </thead>
  <tbody>
    <?php if (!$departmentNames): ?>
      <tr><td colspan="3" class="empty-row">No departments found.</td></tr>
    <?php endif; ?>
    <?php foreach ($departmentNames as $departmentName): ?>
      <?php $employeeCount = isset($counts[$departmentName]) ? count($counts[$departmentName]) : 0; ?>
      <tr>
        <td><?= e($departmentName) ?></td>
        <td><?= $employeeCount ?></td>
        <td>
          <a class="btn btn-small btn-pink" href="<?= e(dash_url(['page' => 'employees', 'action' => 'list', 'department' => $departmentName])) ?>">View Employees</a>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
