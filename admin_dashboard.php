<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_admin();

// Tells employees.php and the six tab files that they were reached the
// correct way (through this dashboard) rather than being opened directly.
define('ADMIN_DASHBOARD', true);

$page = $_GET['page'] ?? 'employees';
$allowedPages = ['employees', 'departments', 'loan', 'payroll', 'reports', 'profile'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'employees';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HR System &mdash; Admin Dashboard</title>
<link rel="stylesheet" href="css/admin_dashboard.css?v=5">
</head>
<body>

<header class="topbar">
  <a class="brand" href="<?= e(dash_url(['page' => 'employees'])) ?>">
    <img class="brand-logo" src="uploads/photos/logo.PNG" alt="HR System logo" style="width:30px;height:30px;object-fit:contain;border-radius:6px;">
    HR System
  </a>
  <nav class="main-nav">
    <a href="<?= e(dash_url(['page' => 'departments'])) ?>" class="<?= $page === 'departments' ? 'active' : '' ?>">Departments</a>
    <a href="<?= e(dash_url(['page' => 'employees'])) ?>" class="<?= $page === 'employees' ? 'active' : '' ?>">Employees</a>
    <a href="<?= e(dash_url(['page' => 'loan'])) ?>" class="<?= $page === 'loan' ? 'active' : '' ?>">Loan</a>
    <a href="<?= e(dash_url(['page' => 'payroll'])) ?>" class="<?= $page === 'payroll' ? 'active' : '' ?>">Payroll</a>
    <a href="<?= e(dash_url(['page' => 'reports'])) ?>" class="<?= $page === 'reports' ? 'active' : '' ?>">Reports</a>
  </nav>
  <div class="topbar-right">
    <button type="button" class="user-btn" id="userBtn"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?= e($_SESSION['username'] ?? '') ?> &#9662;</button>
    <div class="user-menu" id="userMenu">
      <a href="<?= e(dash_url(['page' => 'profile'])) ?>">Edit Profile</a>
    </div>
    <a href="logout.php" class="logout-btn" title="Log Out">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:5px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log Out
    </a>
  </div>
</header>

<main class="content">
<?php
switch ($page) {
    case 'employees':
        require __DIR__ . '/employees.php';
        break;
    case 'departments':
        require __DIR__ . '/departments.php';
        break;
    case 'profile':
        require __DIR__ . '/admin_profile.php';
        break;
    case 'loan':
        echo '<h1 class="page-title">Loan</h1><p class="current-employee">This section is not built yet.</p>';
        break;
    case 'payroll':
        echo '<h1 class="page-title">Payroll</h1><p class="current-employee">This section is not built yet.</p>';
        break;
    case 'reports':
        echo '<h1 class="page-title">Reports</h1><p class="current-employee">This section is not built yet.</p>';
        break;
}
?>
</main>

<script>
  const userBtn = document.getElementById('userBtn');
  const userMenu = document.getElementById('userMenu');
  userBtn.addEventListener('click', () => userMenu.classList.toggle('open'));
  document.addEventListener('click', (e) => {
    if (!userBtn.contains(e.target) && !userMenu.contains(e.target)) {
      userMenu.classList.remove('open');
    }
  });
</script>

</body>
</html>