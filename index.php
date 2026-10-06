<?php
require_once __DIR__ . '/session.php';

if (!empty($_SESSION['role'])) {
    redirect($_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'employee_dashboard.php');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);

$old_role       = $_SESSION['old_role'] ?? 'employee';
$old_username   = $_SESSION['old_username'] ?? '';
unset($_SESSION['old_role'], $_SESSION['old_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HR System &mdash; Sign In</title>
<link rel="stylesheet" href="css/login.css?v=5">
</head>
<body>

<div class="wrap">
  <div class="card">

    <div class="tabs" role="tablist" aria-label="Choose sign-in role">
      <button type="button" class="tab" id="tab-employee" role="tab"
              aria-selected="true" aria-controls="form-employee">Employee</button>
      <button type="button" class="tab" id="tab-admin" role="tab"
              aria-selected="false" aria-controls="form-admin">Admin</button>
    </div>

    <?php if ($error): ?>
      <div class="alert" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="authenticate.php" id="loginForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="role" id="roleField" value="<?= htmlspecialchars($old_role, ENT_QUOTES, 'UTF-8') ?>">

      <div class="field">
        <label for="username" id="usernameLabel">Username</label>
        <input type="text" id="username" name="username" autocomplete="username" required
               value="<?= htmlspecialchars($old_username, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>

      <button type="submit" class="submit">Sign In</button>
    </form>

    <p class="foot-note" id="footNote">Signing in as an <strong>employee</strong> pulls only your own records.</p>
  </div>
</div>

<script>
(function () {
  const tabEmployee   = document.getElementById('tab-employee');
  const tabAdmin      = document.getElementById('tab-admin');
  const roleField     = document.getElementById('roleField');
  const usernameLabel = document.getElementById('usernameLabel');
  const usernameInput = document.getElementById('username');
  const footNote      = document.getElementById('footNote');

  function setRole(role) {
    roleField.value = role;
    const isEmployee = role === 'employee';

    tabEmployee.setAttribute('aria-selected', String(isEmployee));
    tabAdmin.setAttribute('aria-selected', String(!isEmployee));
    tabEmployee.classList.toggle('active', isEmployee);
    tabAdmin.classList.toggle('active', !isEmployee);

    // The same input is used for both roles -- just its label/placeholder
    // change, since an employee signs in with their Employee Number and
    // an admin signs in with their Username.
    usernameLabel.textContent = isEmployee ? 'Employee Number' : 'Username';
    usernameInput.placeholder = isEmployee ? 'e.g. 2023-0001' : '';
    usernameInput.autocomplete = isEmployee ? 'off' : 'username';

    footNote.innerHTML = isEmployee
      ? 'Signing in as an <strong>employee</strong> pulls only your own records.'
      : 'Signing in as an <strong>admin</strong> gives access to every employee record.';
  }

  tabEmployee.addEventListener('click', () => setRole('employee'));
  tabAdmin.addEventListener('click', () => setRole('admin'));

  setRole(roleField.value === 'admin' ? 'admin' : 'employee');
})();
</script>

</body>
</html>