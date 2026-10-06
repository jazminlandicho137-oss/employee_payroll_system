<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit('Direct access not allowed.'); }

// This file is the "Employees" page. It decides which of two things to show:
//   1. The Browse screen (departments on the left, employee table on the
//      right) -- this is now the FIRST thing an admin sees. See employee_list.php.
//   2. One employee's record, split into six tabs (Personal Information,
//      Emergency Contact, Dependent, Educational Background, Character
//      Reference, Employee Position).
//
// $conn (the database connection) is already available here because
// config.php was require_once'd by admin_dashboard.php before this file
// was require'd -- require/include share the caller's variable scope.

// action=list -> show the Browse screen (this is now the default).
// action=view -> show one employee's tabs.
// action=new  -> show a blank Personal Information tab for a new employee.
$action = $_GET['action'] ?? 'list';
$tab    = $_GET['tab'] ?? 'personal';

$validTabNames = ['personal', 'emergency', 'dependent', 'education', 'character', 'position'];
if (!in_array($tab, $validTabNames, true)) {
    $tab = 'personal';
}

// "New" always starts from a clean slate on the Personal Information tab --
// the other tabs need a saved employee_id to attach their records to.
if ($action === 'new') {
    $_SESSION['current_employee_id'] = null;
    $tab = 'personal';
}

// Figure out which employee (if any) we're currently working with.
$employeeId = null;
if (isset($_GET['employee_id'])) {
    $employeeId = (int)$_GET['employee_id'];
    $_SESSION['current_employee_id'] = $employeeId;
} elseif (!empty($_SESSION['current_employee_id'])) {
    $employeeId = (int)$_SESSION['current_employee_id'];
}

$employee = $employeeId ? get_employee($conn, $employeeId) : null;
if ($employeeId && !$employee) {
    // The id in the URL/session doesn't exist (e.g. it was deleted) -- reset.
    $employeeId = null;
    $_SESSION['current_employee_id'] = null;
}

$flashSuccessMessage = flash_get('flash_success');
$flashErrorMessage   = flash_get('flash_error');

// Which physical file handles each tab, and what label to show for it.
$tabFiles = [
    'personal'  => 'employee_info.php',
    'emergency' => 'emergency_contact.php',
    'dependent' => 'dependent.php',
    'education' => 'educ_background.php',
    'character' => 'character_ref.php',
    'position'  => 'employee_position.php',
];
$tabLabels = [
    'personal'  => 'Personal Information',
    'emergency' => 'Emergency Contact',
    'dependent' => 'Dependent',
    'education' => 'Educational Background',
    'character' => 'Character Reference',
    'position'  => 'Employee Position',
];
?>
<h1 class="page-title">Employee Information</h1>

<div class="action-bar">
  <a class="btn btn-primary" href="<?= e(dash_url(['page' => 'employees', 'action' => 'new'])) ?>">New</a>
  <a class="btn btn-ghost" href="<?= e(dash_url(['page' => 'employees', 'action' => 'list'])) ?>">Browse</a>
  <?php if ($action !== 'list'): ?>
    <?php // Shown only while adding/editing an employee (not on the Browse
          // screen itself, where it would be redundant). Goes to the same
          // place as "Browse" -- it just gives the admin an obvious way to
          // back out of a form without saving anything. ?>
    <a class="btn btn-ghost" href="<?= e(dash_url(['page' => 'employees', 'action' => 'list'])) ?>">Cancel</a>
  <?php endif; ?>
  <?php if ($employeeId): ?>
    <a class="btn btn-ghost" href="javascript:void(0)" onclick="window.open('print_employee.php?employee_id=<?= (int)$employeeId ?>','print_employee_<?= (int)$employeeId ?>','width=850,height=680,scrollbars=yes,resizable=yes')">Print</a>
  <?php else: ?>
    <span class="btn btn-ghost btn-disabled" title="Select an employee first">Print</span>
  <?php endif; ?>
</div>

<?php if ($flashSuccessMessage): ?><div class="flash flash-success"><?= e($flashSuccessMessage) ?></div><?php endif; ?>
<?php if ($flashErrorMessage): ?><div class="flash flash-error"><?= e($flashErrorMessage) ?></div><?php endif; ?>

<?php if ($action === 'list'): ?>

  <?php require __DIR__ . '/employee_list.php'; ?>

<?php else: ?>

  <?php if ($employee): ?>
    <p class="current-employee">
      Viewing <strong><?= e($employee['last_name'] . ', ' . $employee['first_name']) ?></strong>
      (Emp. #<?= e($employee['emp_num']) ?>)
    </p>
  <?php elseif ($action === 'new'): ?>
    <p class="current-employee">Adding a new employee.</p>
  <?php else: ?>
    <p class="current-employee">No employee selected. Click <strong>Browse</strong> to pick one, or <strong>New</strong> to add one.</p>
  <?php endif; ?>

  <nav class="tabs">
    <?php foreach ($tabLabels as $tabKey => $tabLabel): ?>
      <?php $tabIsLocked = ($employeeId === null && $tabKey !== 'personal'); ?>
      <?php if ($tabIsLocked): ?>
        <span class="tab-link tab-locked" title="Save personal information first"><?= e($tabLabel) ?></span>
      <?php else: ?>
        <?php
          // IMPORTANT: 'action' => 'view' must be included here. Without it,
          // $_GET['action'] is empty and employees.php falls back to its
          // default ('list'), which would silently bounce every tab click
          // back to the Browse screen instead of opening the tab.
          $tabLinkParams = ['page' => 'employees', 'action' => 'view', 'tab' => $tabKey];
          if ($employeeId) {
              $tabLinkParams['employee_id'] = $employeeId;
          }
        ?>
        <a class="tab-link <?= $tab === $tabKey ? 'active' : '' ?>" href="<?= e(dash_url($tabLinkParams)) ?>">
          <?= e($tabLabel) ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>

  <div class="tab-panel">
    <?php require __DIR__ . '/' . $tabFiles[$tab]; ?>
  </div>

<?php endif; ?>