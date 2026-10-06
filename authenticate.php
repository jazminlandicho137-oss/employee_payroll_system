<?php
/**
 * admin table:         admin_id, username, password
 * employee_info table: ...(personal info columns)..., password
 *
 * Admins sign in with a Username + Password (checked against the admin table).
 * Employees sign in with their Employee Number + Password (checked against
 * the "emp_num" and "password" columns of employee_info -- there is no
 * separate employee_account table or login username/department anymore).
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    $_SESSION['login_error'] = 'Your session expired. Please try again.';
    redirect('index.php');
}

$role = ($_POST['role'] ?? 'employee') === 'admin' ? 'admin' : 'employee';

// The same form field ("username") is reused for both roles: for an admin
// it holds their admin Username, for an employee it holds their Employee
// Number. $loginIdentifier is just a generic name for "whichever one was
// typed in".
$loginIdentifier = trim((string)($_POST['username'] ?? ''));
$password         = (string)($_POST['password'] ?? '');

$_SESSION['old_role']     = $role;
$_SESSION['old_username'] = $loginIdentifier;

$genericError = 'Incorrect credentials. Please check your details and try again.';

if ($loginIdentifier === '' || $password === '') {
    $_SESSION['login_error'] = $role === 'employee'
        ? 'Please fill in your Employee Number and Password.'
        : 'Please fill in your Username and Password.';
    redirect('index.php');
}

// --- Admin login ---
if ($role === 'admin') {
    $admin = $conn->selectOne(
        'admin',
        ['username=eq.' . $loginIdentifier],
        'admin_id,username,password'
    );

    if (!$admin || !password_verify($password, $admin['password'])) {
        $_SESSION['login_error'] = $genericError;
        redirect('index.php');
    }

    regenerate_session();
    unset($_SESSION['old_role'], $_SESSION['old_username']);

    $_SESSION['role']     = 'admin';
    $_SESSION['admin_id'] = (int)$admin['admin_id'];
    $_SESSION['username'] = $admin['username'];

    // Note: admin_action_log.employee_id is NOT NULL, so a bare "admin logged in"
    // event (which isn't about any one employee record) isn't logged here.
    // log_action() in helpers.php is used instead inside the employee tabs,
    // where every action already has a real employee_id to attach to.

    redirect('admin_dashboard.php');
}

// --- Employee login ---
// $loginIdentifier holds the Employee Number the employee typed in.
// A password of NULL means the admin hasn't set a login password for this
// employee yet, so password_verify() is skipped in that case (it would
// throw a TypeError if given null) and the login is simply rejected.
$employeeRow = $conn->selectOne(
    'employee_info',
    ['emp_num=eq.' . $loginIdentifier],
    'employee_id,emp_num,first_name,last_name,password'
);

if (!$employeeRow || $employeeRow['password'] === null || !password_verify($password, $employeeRow['password'])) {
    $_SESSION['login_error'] = $genericError;
    redirect('index.php');
}

regenerate_session();
unset($_SESSION['old_role'], $_SESSION['old_username']);

$_SESSION['role']        = 'employee';
$_SESSION['employee_id'] = (int)$employeeRow['employee_id'];
$_SESSION['emp_num']     = $employeeRow['emp_num'];
$_SESSION['username']    = trim($employeeRow['first_name'] . ' ' . $employeeRow['last_name']);

redirect('employee_dashboard.php');