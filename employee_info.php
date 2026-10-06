<?php
declare(strict_types=1);
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit('Direct access not allowed.'); }

// This is the "Personal Information" tab. It does three things when the
// form is submitted:
//   1. Saves the employee's personal details into the employee_info table.
//      Every field on this tab is required.
//   2. Saves the "Formal Picture" the admin uploaded as a real file on
//      disk (in /uploads/employee_photos/), and remembers its filename in
//      employee_info.photo_filename so it can be shown again later.
//   3. If the admin typed something into the "Employee Password" field,
//      saves/updates that employee's login (see index.php / authenticate.php).
//      The login username is always their Employee Number, and the login
//      department is worked out automatically from the Employee Position tab.
//
// $employee, $employeeId, $conn come from employees.php's scope
// (this file is require'd, not called, so it shares that scope).

$errors = [];

// Does this employee already have employee-website access? (Used just to
// change the on-screen hint text below -- "set a password" vs "change it".)
$hasExistingAccount = $employeeId ? (get_employee_account($conn, $employeeId) !== null) : false;

// Which department would be used for their login right now, if any.
$currentPositionDepartment = $employeeId ? get_current_position_department($conn, $employeeId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'employee_info') {
    verify_csrf();

    // ---- Personal information fields (every one of these is required) ----
    $data = [
        'emp_num'           => trim((string)($_POST['emp_num'] ?? '')),
        'last_name'         => trim((string)($_POST['last_name'] ?? '')),
        'first_name'        => trim((string)($_POST['first_name'] ?? '')),
        'middle_name'       => trim((string)($_POST['middle_name'] ?? '')),
        'gender'            => $_POST['gender'] ?? '',
        'email_address'     => trim((string)($_POST['email_address'] ?? '')),
        'civil_status'      => $_POST['civil_status'] ?? '',
        'nationality'       => trim((string)($_POST['nationality'] ?? '')),
        'religion'          => trim((string)($_POST['religion'] ?? '')),
        'birthdate'         => trim((string)($_POST['birthdate'] ?? '')),
        'permanent_address' => trim((string)($_POST['permanent_address'] ?? '')),
        'present_address'   => trim((string)($_POST['present_address'] ?? '')),
        'contact_number'    => trim((string)($_POST['contact_number'] ?? '')),
        'sss_no'            => trim((string)($_POST['sss'] ?? '')),
        'philhealth_no'     => trim((string)($_POST['philhealth'] ?? '')),
        'pagibig_no'        => trim((string)($_POST['pagibig'] ?? '')),
        'atm_no'            => trim((string)($_POST['atm'] ?? '')),
        'tin_no'            => trim((string)($_POST['tin'] ?? '')),
    ];

    // ---- Employee Password field ----
    $employeePassword = (string)($_POST['employee_password'] ?? '');

    $validGenders  = ['Male', 'Female'];
    $validStatuses = ['Single', 'Married', 'Widowed', 'Legally Separated', 'Annulled'];

    // Every field on this tab is required -- check each one individually
    // so the admin gets a clear list of exactly what's missing.
    if ($data['emp_num'] === '')           $errors[] = 'Employee Number is required.';
    if ($data['last_name'] === '')         $errors[] = 'Last Name is required.';
    if ($data['first_name'] === '')        $errors[] = 'First Name is required.';
    if ($data['middle_name'] === '')       $errors[] = 'Middle Name is required.';
    if (!in_array($data['gender'], $validGenders, true))        $errors[] = 'Please choose a valid Gender.';
    if ($data['email_address'] === '')     $errors[] = 'Email Address is required.';
    if (!in_array($data['civil_status'], $validStatuses, true)) $errors[] = 'Please choose a valid Civil Status.';
    if ($data['nationality'] === '')       $errors[] = 'Nationality is required.';
    if ($data['religion'] === '')          $errors[] = 'Religion is required.';
    if ($data['birthdate'] === '')         $errors[] = 'Birthdate is required.';
    if ($data['permanent_address'] === '') $errors[] = 'Permanent Address is required.';
    if ($data['present_address'] === '')   $errors[] = 'Present Address is required.';
    if ($data['contact_number'] === '')    $errors[] = 'Contact Number is required.';
    if ($data['sss_no'] === '')            $errors[] = 'SSS No. is required.';
    if ($data['philhealth_no'] === '')     $errors[] = 'Philhealth No. is required.';
    if ($data['pagibig_no'] === '')        $errors[] = 'Pag-ibig No. is required.';
    if ($data['atm_no'] === '')            $errors[] = 'ATM No. is required.';
    if ($data['tin_no'] === '')            $errors[] = 'TIN is required.';

    if ($data['email_address'] !== '' && !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid Email Address.';
    }

    // A password can only be set once the employee has a department, since
    // the login needs one (a brand-new employee with no position yet can't
    // have a password set until a position is added).
    if ($employeePassword !== '' && $employeeId && $currentPositionDepartment === null) {
        $errors[] = 'Add an Employee Position for this employee (so they have a department) before setting a password.';
    }

    if (!$errors) {
        try {
            if ($employeeId) {
                $conn->update('employee_info', ['employee_id=eq.' . $employeeId], $data);

                log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'employee_info', 'UPDATE', $employeeId);
                $_SESSION['flash_success'] = 'Employee information updated.';
            } else {
                $newRow = $conn->insert('employee_info', $data);
                $employeeId = (int)($newRow['employee_id'] ?? 0);

                $_SESSION['current_employee_id'] = $employeeId;
                log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'employee_info', 'INSERT', $employeeId);
                $_SESSION['flash_success'] = 'New employee added.';
            }

            // ---- Save the uploaded photo, now that we know employeeId ----
            // This is a "best effort" step, same as the login save below: the
            // employee_info record above is already saved either way, so a
            // photo problem is shown as a warning rather than losing the
            // admin's other changes.
            try {
                $uploadedPhotoFile = $_FILES['employee_photo'] ?? ['error' => UPLOAD_ERR_NO_FILE];
                $oldPhotoFilename  = $employee['photo_filename'] ?? null;
                $newPhotoFilename  = save_uploaded_employee_photo($employeeId, $uploadedPhotoFile, $oldPhotoFilename);

                if ($newPhotoFilename !== null) {
                    $conn->update('employee_info', ['employee_id=eq.' . $employeeId], ['photo_filename' => $newPhotoFilename]);
                }
            } catch (RuntimeException $photoException) {
                $_SESSION['flash_error'] = 'Employee information was saved, but the photo was not: ' . $photoException->getMessage();
            }

            // ---- Save/update the employee's login, if there's anything to do ----
            $accountShouldBeSaved = $employeePassword !== '' || $hasExistingAccount;
            if ($accountShouldBeSaved) {
                $departmentForLogin = get_current_position_department($conn, $employeeId);
                if ($departmentForLogin !== null) {
                    upsert_employee_account(
                        $conn,
                        $employeeId,
                        $data['emp_num'],                                   // username = Employee Number
                        $departmentForLogin,                                // department = their current position
                        $employeePassword !== '' ? $employeePassword : null // null = keep the existing password
                    );
                }
            }

            redirect(dash_url(['page' => 'employees', 'action' => 'view', 'tab' => 'personal', 'employee_id' => $employeeId]));
        } catch (SupabaseException $ex) {
            if ($ex->pgCode === '23505') {
                $errors[] = 'That Employee Number or Email Address is already in use.';
            } else {
                error_log('employee_info save failed: ' . $ex->getMessage());
                $errors[] = 'Something went wrong while saving. Please try again.';
            }
        }
    }

    // Keep what the admin typed on screen if something went wrong.
    $employee = array_merge($employee ?? [], $data);
}

// Small helper used only inside this file's HTML below: prints a personal-info field's value safely.
$v = static fn(string $field): string => e((string)($employee[$field] ?? ''));

// The employee's current photo (if any), for the preview below.
$currentPhotoSrc = employee_photo_src($employee['photo_filename'] ?? null);
?>
<?php foreach ($errors as $err): ?>
  <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="POST"
      action="<?= e(dash_url($employeeId
          ? ['page' => 'employees', 'tab' => 'personal', 'action' => 'view', 'employee_id' => $employeeId]
          : ['page' => 'employees', 'tab' => 'personal', 'action' => 'new'])) ?>"
      class="record-form"
      enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="employee_info">

  <!-- Formal Picture -- shown before the employee's name, as requested. -->
  <div class="employee-photo-section">
    <div class="employee-photo-preview">
      <?php if ($currentPhotoSrc): ?>
        <img src="<?= e($currentPhotoSrc) ?>" alt="Employee photo" class="employee-photo-img">
      <?php else: ?>
        <span class="employee-photo-placeholder">No Photo</span>
      <?php endif; ?>
    </div>
    <?php if ($employeeId): ?>
      <label>Formal Picture
        <input type="file" name="employee_photo" accept=".jpg,.jpeg,.png,.webp">
        <span class="form-section-hint" style="margin:4px 0 0;">JPG, PNG, or WEBP, up to 2 MB.</span>
      </label>
    <?php else: ?>
      <p class="form-section-hint">Save this new employee first, then reopen them here to add a picture.</p>
    <?php endif; ?>
  </div>

  <div class="form-grid">
    <label>Employee Number
      <input type="text" name="emp_num" value="<?= $v('emp_num') ?>" required>
    </label>
    <label>Last Name
      <input type="text" name="last_name" value="<?= $v('last_name') ?>" required>
    </label>
    <label>First Name
      <input type="text" name="first_name" value="<?= $v('first_name') ?>" required>
    </label>
    <label>Middle Name
      <input type="text" name="middle_name" value="<?= $v('middle_name') ?>" required>
    </label>
    <label>Gender
      <select name="gender" required>
        <option value="">-- Select --</option>
        <option value="Male" <?= ($employee['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
        <option value="Female" <?= ($employee['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
      </select>
    </label>
    <label>Email Address
      <input type="email" name="email_address" value="<?= $v('email_address') ?>" required>
    </label>
    <label>Civil Status
      <select name="civil_status" required>
        <option value="">-- Select --</option>
        <?php foreach (['Single', 'Married', 'Widowed', 'Legally Separated', 'Annulled'] as $opt): ?>
          <option value="<?= $opt ?>" <?= ($employee['civil_status'] ?? '') === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Nationality
      <input type="text" name="nationality" value="<?= $v('nationality') ?>" required>
    </label>
    <label>Religion
      <input type="text" name="religion" value="<?= $v('religion') ?>" required>
    </label>
    <label>Birthdate
      <input type="date" name="birthdate" value="<?= $v('birthdate') ?>" required>
    </label>
    <label>Permanent Address
      <input type="text" name="permanent_address" value="<?= $v('permanent_address') ?>" required>
    </label>
    <label>Present Address
      <input type="text" name="present_address" value="<?= $v('present_address') ?>" required>
    </label>
    <label>Contact Number
      <input type="text"  name="contact_number" value="<?= $v('contact_number') ?>" maxlength="11" required>
    </label>
    <label>SSS No.
      <input type="text"  name="sss" value="<?= $v('sss_no') ?>" maxlength="10" required>
    </label>
    <label>Philhealth No.
      <input type="text"  name="philhealth" value="<?= $v('philhealth_no') ?>" maxlength="12" required>
    </label>
    <label>Pag-ibig No.
      <input type="text"  name="pagibig" value="<?= $v('pagibig_no') ?>" maxlength="12" required>
    </label>
    <label>ATM No.
      <input type="text"  name="atm" value="<?= $v('atm_no') ?>" maxlength="16" required>
    </label>
    <label>TIN
      <input type="text" name="tin" value="<?= $v('tin_no') ?>" maxlength="9" required>
    </label>
  </div>

  <?php if ($employeeId): ?>
    <h3 class="form-section-title">Employee Password</h3>
    <?php if ($currentPositionDepartment === null): ?>
      <p class="form-section-hint">Add an Employee Position first -- a password needs a department to sign in with.</p>
    <?php else: ?>
      <p class="form-section-hint">
        This is what the employee uses to sign in to the Employee website (their sign-in <strong>username is their Employee Number</strong>, shown above).
        <?= $hasExistingAccount ? ' Leave this blank to keep their current password.' : '' ?>
      </p>
      <div class="form-grid">
        <label>Password
          <input type="password" name="employee_password" autocomplete="new-password">
        </label>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <button type="submit" class="btn btn-primary"><?= $employeeId ? 'Save Changes' : 'Save New Employee' ?></button>
</form>