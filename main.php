<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit('Direct access not allowed.'); }

// The Home page: a simple two-column overview.
//   LEFT  = every Department. Clicking one jumps to the Browse Employees
//           screen already filtered to that department.
//   RIGHT = every Position (job title) an employee can hold.
// Both lists come from helpers.php so they always match what's offered
// on the Employee Position tab (employee_position.php).

$allDepartments    = get_department_list();
$allPositionTitles = get_position_title_list();
?>
<h1 class="page-title">Home</h1>

<div class="home-columns">
  <aside class="department-nav">
    <p class="department-nav-title">Departments</p>
    <?php foreach ($allDepartments as $departmentName): ?>
      <a class="department-link" href="<?= e(dash_url(['page' => 'employees', 'action' => 'list', 'department' => $departmentName])) ?>">
        <?= e($departmentName) ?>
      </a>
    <?php endforeach; ?>
  </aside>

  <aside class="department-nav">
    <p class="department-nav-title">Positions</p>
    <?php foreach ($allPositionTitles as $positionTitle): ?>
      <span class="department-link" style="cursor:default;">
        <?= e($positionTitle) ?>
      </span>
    <?php endforeach; ?>
  </aside>
</div>