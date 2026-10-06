<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit('Direct access not allowed.'); }

// This is the "Browse Employees" screen -- the first thing an admin sees
// when they open the Employees page.
//
//   LEFT SIDE  = a clickable list of departments.
//   RIGHT SIDE = a table of employees. Before a department is clicked,
//                every employee is listed. After a department is clicked,
//                only employees who have a position record in that
//                department are listed.
//
// It is require'd by employees.php whenever $action === 'list'.

// If the admin clicked "Delete" on a row, remove that employee and go
// straight back to this same screen.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'employee_delete') {
    verify_csrf();
    $deleteEmployeeId = (int)($_POST['employee_id'] ?? 0);

    log_action($conn, (int)$_SESSION['admin_id'], $deleteEmployeeId, 'employee_info', 'DELETE', $deleteEmployeeId);
    try {
        $conn->delete('employee_info', ['employee_id=eq.' . $deleteEmployeeId]);
        $_SESSION['flash_success'] = 'Employee deleted.';
    } catch (SupabaseException $e) {
        $_SESSION['flash_error'] = 'Could not delete this employee.';
    }
    redirect(dash_url(['page' => 'employees', 'action' => 'list']));
}

// Which department is currently selected? An empty string means
// "no department chosen yet -- show everyone".
$selectedDepartment = trim((string)($_GET['department'] ?? ''));

// What is the admin typing into the search box (name or employee number)?
$searchText = trim((string)($_GET['q'] ?? ''));

// The full list of department names, so the left-hand menu always shows
// all of them (defined once in helpers.php so every page agrees on it).
$allDepartments = get_department_list();

// ---------------------------------------------------------------------
// Build the employee list.
// ---------------------------------------------------------------------
$listColumns = 'employee_id,emp_num,last_name,first_name,middle_name,email_address,photo_filename';

if ($selectedDepartment !== '') {
    // Only employees with a position record in that department.
    $positionRows = $conn->select('employee_position', ['position_department=eq.' . $selectedDepartment], 'employee_id');
    $deptIds = array_values(array_unique(array_map(fn($r) => (int)$r['employee_id'], $positionRows)));

    if ($deptIds === []) {
        $employeeRows = [];
    } else {
        $filters = ['employee_id=in.(' . implode(',', $deptIds) . ')'];
        if ($searchText !== '') {
            $like = '*' . str_replace([',', '%', '*'], '', $searchText) . '*';
            $filters[] = "or=(emp_num.ilike.$like,last_name.ilike.$like,first_name.ilike.$like)";
        }
        $employeeRows = $conn->select('employee_info', $filters, 'last_name,first_name', null, $listColumns);
    }
} elseif ($searchText !== '') {
    // No department chosen, but the admin is searching across everyone.
    $like = '*' . str_replace([',', '%', '*'], '', $searchText) . '*';
    $employeeRows = $conn->select(
        'employee_info',
        ["or=(emp_num.ilike.$like,last_name.ilike.$like,first_name.ilike.$like)"],
        'last_name,first_name',
        null,
        $listColumns
    );
} else {
    // No department chosen and no search -- show every employee.
    $employeeRows = $conn->select('employee_info', [], 'last_name,first_name', null, $listColumns);
}

$currentEmployeeId = (int)($_SESSION['current_employee_id'] ?? 0);
?>
<div class="employee-browser">

  <!-- LEFT SIDE: click a department to filter the table on the right -->
  <aside class="department-nav">
    <p class="department-nav-title">Departments</p>

    <a class="department-link <?= $selectedDepartment === '' ? 'active' : '' ?>"
       href="<?= e(dash_url(['page' => 'employees', 'action' => 'list'])) ?>">
      All Employees
    </a>

    <?php foreach ($allDepartments as $departmentName): ?>
      <a class="department-link <?= $selectedDepartment === $departmentName ? 'active' : '' ?>"
         href="<?= e(dash_url(['page' => 'employees', 'action' => 'list', 'department' => $departmentName])) ?>">
        <?= e($departmentName) ?>
      </a>
    <?php endforeach; ?>
  </aside>

  <!-- RIGHT SIDE: search box + employee table -->
  <div class="employee-browser-main">

    <form method="GET" action="admin_dashboard.php" class="search-bar">
      <input type="hidden" name="page" value="employees">
      <input type="hidden" name="action" value="list">
      <input type="hidden" name="department" value="<?= e($selectedDepartment) ?>">
      <input type="text" name="q" placeholder="Search by name or employee number" value="<?= e($searchText) ?>">
      <button type="submit" class="btn btn-primary">Search</button>
    </form>

    <?php if ($selectedDepartment !== ''): ?>
      <p class="current-employee">Showing employees in <strong><?= e($selectedDepartment) ?></strong>.</p>
    <?php endif; ?>

    <table class="data-table">
      <thead>
        <tr><th>Emp. #</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Email</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$employeeRows): ?>
          <tr><td colspan="6" class="empty-row">No employees found.</td></tr>
        <?php endif; ?>
        <?php foreach ($employeeRows as $employeeRow): ?>
          <?php $editUrl = dash_url(['page' => 'employees', 'action' => 'view', 'tab' => 'personal', 'employee_id' => $employeeRow['employee_id']]); ?>
          <tr class="<?= $currentEmployeeId === (int)$employeeRow['employee_id'] ? 'row-selected' : '' ?>"
              style="cursor:pointer;" onclick="window.location='<?= e($editUrl) ?>';">
            <td><?= e($employeeRow['emp_num']) ?></td>
            <td>
              <?php $rowPhotoSrc = employee_photo_src($employeeRow['photo_filename'] ?? null); ?>
              <?php if ($rowPhotoSrc): ?>
                <img src="<?= e($rowPhotoSrc) ?>" alt="" class="employee-photo-thumb">
              <?php endif; ?>
              <?= e($employeeRow['last_name']) ?>
            </td>
            <td><?= e($employeeRow['first_name']) ?></td>
            <td><?= e($employeeRow['middle_name']) ?></td>
            <td><?= e($employeeRow['email_address']) ?></td>
            <td>
              <a class="btn btn-small" title="Edit" href="javascript:void(0)" onclick="window.open('<?= e($editUrl) ?>','employee_<?= (int)$employeeRow['employee_id'] ?>','width=1000,height=720,scrollbars=yes,resizable=yes')" style="color:var(--forest);">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </a>
              <form method="POST" action="<?= e(dash_url(['page' => 'employees', 'action' => 'list'])) ?>"
                    style="display:inline;" onclick="event.stopPropagation();"
                    onsubmit="return confirm('Delete this employee? This decision cannot be undone.');">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="employee_delete">
                <input type="hidden" name="employee_id" value="<?= (int)$employeeRow['employee_id'] ?>">
                <button type="submit" class="btn btn-small" title="Delete" style="color:#C0392B;">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C0392B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

  </div>
</div>