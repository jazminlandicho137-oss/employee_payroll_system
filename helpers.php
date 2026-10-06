<?php
declare(strict_types=1);

/**
 * This file holds small, reusable functions that many other pages need.
 * It is loaded (with require_once) near the top of every page, right
 * after session.php and config.php.
 *
 * Load order in every entry file: session.php, config.php, helpers.php.
 *
 * Used by: admin_dashboard.php and everything it includes (employees.php,
 * employee_list.php, admin_profile.php, and the six "tab" files:
 * employee_info.php, emergency_contact.php, dependent.php,
 * educ_background.php, character_ref.php, employee_position.php).
 */

// Where uploaded employee photos are saved on disk (a real folder inside
// the project, next to this file), and the matching web-facing path used
// to actually display them in an <img src="..."> tag.
define('EMPLOYEE_PHOTO_UPLOAD_DIR', __DIR__ . '/uploads/employee_photos/');
define('EMPLOYEE_PHOTO_WEB_PATH', 'uploads/employee_photos/');

/**
 * Sends anyone who is NOT logged in as an admin back to the login page.
 * Called at the very top of every admin-only page (e.g. admin_dashboard.php,
 * print_employee.php) so nobody can open those pages without logging in.
 */
function require_admin(): void
{
    if (($_SESSION['role'] ?? null) !== 'admin') {
        redirect('index.php');
    }
}

/**
 * Makes text safe to print inside HTML (turns characters like < and >
 * into their harmless HTML versions, so a user cannot inject their own
 * HTML/JavaScript into the page). Used everywhere we print a value that
 * came from the database or from a form, e.g. <?= e($employee['first_name']) ?>.
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Makes sure a random CSRF ("Cross-Site Request Forgery") token exists
 * in the session, and returns it. A CSRF token stops other websites from
 * tricking a logged-in admin's browser into submitting forms on our site
 * without the admin knowing.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Prints a hidden <input> that carries the CSRF token. Every <form> in
 * this project should include <?= csrf_field() ?> right after the
 * opening <form> tag.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Checks that a submitted form's CSRF token matches the one we stored in
 * the session. If it does not match, the request is stopped immediately.
 * Called at the top of every form-handling block, e.g. inside
 * employee_info.php right after checking $_SERVER['REQUEST_METHOD'].
 */
function verify_csrf(): void
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        http_response_code(400);
        exit('Your session expired. Please refresh the page and try again.');
    }
}

/**
 * Looks up one row from the employee_info table by its employee_id.
 * Returns null if no employee with that id exists.
 * Used by: employees.php (to load the employee currently being viewed)
 * and print_employee.php.
 */
function get_employee(SupabaseClient $conn, int $employeeId): ?array
{
    return $conn->selectOne('employee_info', ['employee_id=eq.' . $employeeId]);
}

/**
 * Builds a link back to admin_dashboard.php with the given query-string
 * parameters, e.g. dash_url(['page' => 'employees', 'action' => 'list'])
 * becomes "admin_dashboard.php?page=employees&action=list".
 * Used everywhere we print a link or a <form action="..."> inside the
 * admin dashboard.
 */
function dash_url(array $params): string
{
    return 'admin_dashboard.php?' . http_build_query($params);
}

/**
 * Reads a one-time "flash" message out of the session and removes it, so
 * it is only shown once (right after a redirect). Used by employees.php
 * to show "Employee information updated." after a save.
 */
function flash_get(string $key): ?string
{
    $flashMessage = $_SESSION[$key] ?? null;
    unset($_SESSION[$key]);
    return $flashMessage;
}

/**
 * Writes one row into admin_action_log, recording that an admin added,
 * changed, deleted, or viewed a record belonging to a specific employee.
 * $actionType must be one of: VIEW, INSERT, UPDATE, DELETE.
 * If the log write fails for any reason, we only write it to the PHP
 * error log instead of stopping the page -- a logging problem should
 * never block the admin's actual request.
 * Used by: all six "tab" files, and print_employee.php.
 */
function log_action(SupabaseClient $conn, int $adminId, int $employeeId, string $tableAffected, string $actionType, ?int $recordId = null): void
{
    $validActionTypes = ['VIEW', 'INSERT', 'UPDATE', 'DELETE'];
    if (!in_array($actionType, $validActionTypes, true)) {
        return;
    }
    try {
        $conn->insert('admin_action_log', [
            'admin_id'       => $adminId,
            'employee_id'    => $employeeId,
            'table_affected' => $tableAffected,
            'record_id'      => $recordId,
            'action_type'    => $actionType,
            'action_at'      => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $exception) {
        error_log('admin_action_log insert skipped: ' . $exception->getMessage());
    }
}

/**
 * The fixed list of department names used across the project:
 *   - the Position tab's "Department" dropdown (employee_position.php)
 *   - the new "Employee Login" section's "Department" dropdown (employee_info.php)
 *   - the department filter on the left side of the Employees list (employee_list.php)
 * Keeping the list in one place means we only have to update it here if a
 * department is ever added or renamed.
 */
function get_department_list(): array
{
    // Departments are stored in the `departments` table (manageable from the
    // Departments page). Fall back to the built-in list if the table does not
    // exist yet or is unreachable, so the rest of the site keeps working.
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        global $conn;
        $rows = $conn->select('departments', [], 'name', null, 'name');
        $names = array_values(array_filter(array_map(static fn($r) => (string)($r['name'] ?? ''), $rows)));
        if ($names) {
            return $cache = $names;
        }
    } catch (Throwable $e) {
        // fall through to the default list
    }
    return ['CCTE', 'CBA', 'CCJE', 'CITHM', 'CON', 'CBEA', 'CELA', 'Accounting', 'Marketing', 'LMS'];
}

/**
 * Looks up one employee's login row from the employee_account table
 * (the table the Employee sign-in form checks). Returns null if this
 * employee does not have employee-website access set up yet.
 * Used by: employee_info.php, to show the employee's current login
 * username/department when the Personal Information tab is opened.
 */
function get_employee_account(SupabaseClient $conn, int $employeeId): ?array
{
    return $conn->selectOne('employee_account', ['employee_id=eq.' . $employeeId]);
}

/**
 * Finds which department an employee currently belongs to, by looking at
 * their employee_position rows: prefers a position with no end date (their
 * current job), and otherwise falls back to their most recently started
 * position. Returns null if the employee has no position rows yet.
 * Used by: employee_info.php, to automatically fill in the "department"
 * needed for the employee's website login (the admin no longer has to
 * type a department -- it's taken from the Employee Position tab).
 */
function get_current_position_department(SupabaseClient $conn, int $employeeId): ?string
{
    // Prefer a position with no end date (the current job); otherwise the
    // most recently started position.
    $rows = $conn->select(
        'employee_position',
        ['employee_id=eq.' . $employeeId],
        'position_end.nullsfirst,position_start.desc',
        1,
        'position_department'
    );
    return $rows[0]['position_department'] ?? null;
}

/**
 * Creates or updates the employee_account row that lets one employee log
 * in to the employee website (see authenticate.php). The "username" for
 * an employee login is always their Employee Number (emp_num) -- see
 * get_current_position_department() for how the department is worked out.
 *   - If the employee does not have a login yet, a new row is inserted.
 *     $plainPassword is required in this case.
 *   - If the employee already has a login, their username/department are
 *     updated. The password is only changed when $plainPassword is a
 *     non-empty string -- passing null or '' means "keep the password
 *     the employee already has".
 * The plain-text password is never stored: it is hashed with
 * password_hash() before it touches the database.
 * Used by: employee_info.php, after the Personal Information form (which
 * now includes a "Employee Login" section) is saved.
 */
function upsert_employee_account(SupabaseClient $conn, int $employeeId, string $username, string $department, ?string $plainPassword): void
{
    $existingAccount = get_employee_account($conn, $employeeId);

    if ($existingAccount) {
        $fields = ['username' => $username, 'department' => $department];
        if ($plainPassword !== null && $plainPassword !== '') {
            $fields['password'] = password_hash($plainPassword, PASSWORD_DEFAULT);
        }
        $conn->update('employee_account', ['employee_id=eq.' . $employeeId], $fields);
    } else {
        $conn->insert('employee_account', [
            'employee_id' => $employeeId,
            'department'  => $department,
            'username'    => $username,
            'password'    => password_hash((string)$plainPassword, PASSWORD_DEFAULT),
        ]);
    }
}

/**
 * Turns a stored photo filename (from employee_info.photo_filename) into
 * a path a browser can load in an <img src="..."> tag. Returns null if
 * the employee doesn't have a photo yet.
 * Used by: employee_info.php, employees.php, employee_list.php.
 */
function employee_photo_src(?string $filename): ?string
{
    return $filename ? EMPLOYEE_PHOTO_WEB_PATH . $filename : null;
}

/**
 * Saves one employee's uploaded photo (from a <input type="file"
 * name="employee_photo"> field) to disk as a real file, and returns the
 * filename to store in employee_info.photo_filename.
 *   - Returns null if the admin didn't choose a new file -- there is
 *     nothing to save, and the employee keeps whatever photo they had.
 *   - Throws a RuntimeException with a human-readable message if the
 *     chosen file isn't a valid photo (wrong type or too large).
 *     employee_info.php catches this and shows it like any other error.
 * The file is saved as "emp_<employeeId>.<extension>" so each employee
 * has exactly one photo file. If they already had a photo with a
 * different extension, the old file is deleted.
 * Used by: employee_info.php.
 */
function save_uploaded_employee_photo(int $employeeId, array $uploadedFile, ?string $oldFilename): ?string
{
    // No file was chosen in the form -- nothing to do.
    if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The photo could not be uploaded. Please try again.');
    }

    $allowedExtensions = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'];
    $originalExtension = strtolower((string)pathinfo((string)$uploadedFile['name'], PATHINFO_EXTENSION));
    if (!isset($allowedExtensions[$originalExtension])) {
        throw new RuntimeException('Photo must be a JPG, PNG, or WEBP image.');
    }

    $maxAllowedBytes = 2 * 1024 * 1024; // 2 MB
    if ((int)$uploadedFile['size'] > $maxAllowedBytes) {
        throw new RuntimeException('Photo must be smaller than 2 MB.');
    }

    if (!is_dir(EMPLOYEE_PHOTO_UPLOAD_DIR)) {
        mkdir(EMPLOYEE_PHOTO_UPLOAD_DIR, 0755, true);
    }

    $newFilename = 'emp_' . $employeeId . '.' . $allowedExtensions[$originalExtension];
    $destinationPath = EMPLOYEE_PHOTO_UPLOAD_DIR . $newFilename;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $destinationPath)) {
        throw new RuntimeException('The photo could not be saved. Please try again.');
    }

    // If the employee's old photo used a different file extension, its
    // old file is now an orphan on disk -- clean it up.
    if ($oldFilename !== null && $oldFilename !== $newFilename) {
        $oldPath = EMPLOYEE_PHOTO_UPLOAD_DIR . $oldFilename;
        if (is_file($oldPath)) {
            unlink($oldPath);
        }
    }

    return $newFilename;
}