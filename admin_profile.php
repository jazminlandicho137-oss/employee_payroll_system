<?php
declare(strict_types=1);
/** @var SupabaseClient $conn */
if (!defined('ADMIN_DASHBOARD')) { http_response_code(403); exit('Direct access not allowed.'); }

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'admin_profile') {
    verify_csrf();

    $newUsername = trim((string)($_POST['username'] ?? ''));
    $currentPass = (string)($_POST['current_password'] ?? '');
    $newPass     = (string)($_POST['new_password'] ?? '');
    $confirmPass = (string)($_POST['confirm_password'] ?? '');

    $row = $conn->selectOne('admin', ['admin_id=eq.' . (int)$_SESSION['admin_id']], 'password');

    if ($newUsername === '') {
        $errors[] = 'Username cannot be blank.';
    }
    if (!$row || !password_verify($currentPass, $row['password'])) {
        $errors[] = 'Current password is incorrect.';
    }
    if ($newPass !== '' && $newPass !== $confirmPass) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (!$errors) {
        try {
            if ($newPass !== '') {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $conn->update('admin', ['admin_id=eq.' . (int)$_SESSION['admin_id']], ['username' => $newUsername, 'password' => $hash]);
            } else {
                $conn->update('admin', ['admin_id=eq.' . (int)$_SESSION['admin_id']], ['username' => $newUsername]);
            }

            $_SESSION['username'] = $newUsername;
            $success = 'Profile updated.';
        } catch (SupabaseException $ex) {
            $errors[] = $ex->pgCode === '23505' ? 'That username is already taken.' : 'Could not update profile. Please try again.';
        }
    }
}
?>
<h1 class="page-title">Edit Profile</h1>

<?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
<?php if ($success): ?><div class="flash flash-success"><?= e($success) ?></div><?php endif; ?>

<form method="POST" action="<?= e(dash_url(['page' => 'profile'])) ?>" class="record-form" style="max-width:420px;">
  <?= csrf_field() ?>
  <input type="hidden" name="form" value="admin_profile">

  <label style="margin-bottom:16px;">Username
    <input type="text" name="username" value="<?= e($_SESSION['username'] ?? '') ?>" required>
  </label>
  <label style="margin-bottom:16px;">Current Password
    <input type="password" name="current_password" required>
  </label>
  <label style="margin-bottom:16px;">New Password <span style="font-weight:400;color:var(--text-muted);">(leave blank to keep current)</span>
    <input type="password" name="new_password">
  </label>
  <label style="margin-bottom:18px;">Confirm New Password
    <input type="password" name="confirm_password">
  </label>

  <button type="submit" class="btn btn-primary">Save Changes</button>
</form>