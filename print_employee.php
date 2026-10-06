<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

require_admin();

$employeeId = (int)($_GET['employee_id'] ?? 0);
$employee = $employeeId ? get_employee($conn, $employeeId) : null;
if (!$employee) {
    http_response_code(404);
    exit('Employee not found.');
}

function fetch_all_for(SupabaseClient $conn, string $table, string $order, int $employeeId): array
{
    return $conn->select($table, ['employee_id=eq.' . $employeeId], $order);
}

$emergencyContacts = fetch_all_for($conn, 'emergency_contact', 'emergency_contact_id', $employeeId);
$dependents        = fetch_all_for($conn, 'dependent', 'dependent_id', $employeeId);
$education         = fetch_all_for($conn, 'educ_background', 'educ_id', $employeeId);
$characterRefs     = fetch_all_for($conn, 'character_reference', 'character_ref_id', $employeeId);
$positions         = fetch_all_for($conn, 'employee_position', 'position_start', $employeeId);

log_action($conn, (int)$_SESSION['admin_id'], $employeeId, 'employee_info', 'VIEW', $employeeId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Employee Record &mdash; <?= e($employee['last_name'] . ', ' . $employee['first_name']) ?></title>
<style>
  body { font-family: -apple-system, Segoe UI, sans-serif; color:#25182d; background:#faf3fc; padding: 32px; max-width: 800px; margin: 0 auto; }
  h1 { font-size: 20px; border-bottom: 2px solid #7b3fa3; padding-bottom: 8px; }
  h2 { font-size: 15px; color:#7b3fa3; margin-top: 28px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid rgba(68,33,82,0.13); font-size: 13px; }
  .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; font-size: 13px; }
  .grid div span { color:#74667c; display:block; font-size: 11px; text-transform:uppercase; letter-spacing:.04em; }
  .print-btn { background:#7b3fa3; color:#ffffff; border:none; border-radius:7px; padding:9px 16px; font-size:13.5px; cursor:pointer; }
  .cancel-btn { background:none; color:#7b3fa3; border:1.5px solid #7b3fa3; border-radius:7px; padding:9px 16px; font-size:13.5px; cursor:pointer; margin-left:8px; }
  @media print { .no-print { display: none; } body { background:#fff; color:#000; } h1, h2 { color:#333; border-color:#ccc; } th, td { border-color:#ccc; } }
</style>
</head>
<body>
  <button class="no-print print-btn" onclick="window.print()">Print</button>
  <?php // This page always opens in its own new tab (see the "Print" link on
        // the employee's tabs), so "Cancel" just closes that tab and goes
        // back to whatever the admin was doing. If for some reason the tab
        // can't be closed by script (e.g. it wasn't opened via JS/target),
        // window.close() simply does nothing and the admin can close it
        // manually or use the browser's back button. ?>
  <button class="no-print cancel-btn" onclick="window.close()">Cancel</button>

  <h1><?= e(trim($employee['last_name'] . ', ' . $employee['first_name'] . ' ' . ($employee['middle_name'] ?? ''))) ?> &mdash; Emp. #<?= e($employee['emp_num']) ?></h1>

  <h2>Personal Information</h2>
  <div class="grid">
    <div><span>Gender</span><?= e($employee['gender']) ?></div>
    <div><span>Civil Status</span><?= e($employee['civil_status']) ?></div>
    <div><span>Email</span><?= e($employee['email_address']) ?></div>
    <div><span>Contact Number</span><?= e($employee['contact_number']) ?></div>
    <div><span>Nationality</span><?= e($employee['nationality']) ?></div>
    <div><span>Religion</span><?= e($employee['religion']) ?></div>
    <div><span>Birthdate</span><?= e($employee['birthdate']) ?></div>
    <div><span>Permanent Address</span><?= e($employee['permanent_address']) ?></div>
    <div><span>Present Address</span><?= e($employee['present_address']) ?></div>
    <div><span>SSS No.</span><?= e($employee['sss_no']) ?></div>
    <div><span>Philhealth No.</span><?= e($employee['philhealth_no']) ?></div>
    <div><span>Pag-ibig No.</span><?= e($employee['pagibig_no']) ?></div>
    <div><span>ATM No.</span><?= e($employee['atm_no']) ?></div>
    <div><span>TIN</span><?= e($employee['tin_no']) ?></div>
  </div>

  <h2>Emergency Contacts</h2>
  <table>
    <tr><th>Name</th><th>Relationship</th><th>Contact</th><th>Address</th></tr>
    <?php foreach ($emergencyContacts as $c): ?>
      <tr><td><?= e($c['emergency_contact_name']) ?></td><td><?= e($c['emergency_contact_relationship']) ?></td><td><?= e($c['emergency_contact']) ?></td><td><?= e($c['emergency_contact_address']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$emergencyContacts): ?><tr><td colspan="4">None on file.</td></tr><?php endif; ?>
  </table>

  <h2>Dependents</h2>
  <table>
    <tr><th>Name</th><th>Birthdate</th><th>Relationship</th></tr>
    <?php foreach ($dependents as $d): ?>
      <tr><td><?= e($d['dependent_name']) ?></td><td><?= e($d['dependent_bdate']) ?></td><td><?= e($d['dependent_relationship']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$dependents): ?><tr><td colspan="3">None on file.</td></tr><?php endif; ?>
  </table>

  <h2>Educational Background</h2>
  <table>
    <tr><th>Level</th><th>School</th><th>Address</th><th>S.Y.</th></tr>
    <?php foreach ($education as $ed): ?>
      <tr><td><?= e($ed['educ_level']) ?></td><td><?= e($ed['educ_school']) ?></td><td><?= e($ed['educ_address']) ?></td><td><?= e($ed['educ_sy']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$education): ?><tr><td colspan="4">None on file.</td></tr><?php endif; ?>
  </table>

  <h2>Character References</h2>
  <table>
    <tr><th>Name</th><th>Occupation</th><th>Address</th></tr>
    <?php foreach ($characterRefs as $r): ?>
      <tr><td><?= e($r['character_reference_name']) ?></td><td><?= e($r['character_reference_occupation']) ?></td><td><?= e($r['character_reference_address']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$characterRefs): ?><tr><td colspan="3">None on file.</td></tr><?php endif; ?>
  </table>

  <h2>Employee Position History</h2>
  <table>
    <tr><th>Department</th><th>Start</th><th>End</th><th>Monthly</th><th>Daily</th><th>Hourly</th></tr>
    <?php foreach ($positions as $p): ?>
      <tr>
        <td><?= e($p['position_department']) ?></td>
        <td><?= e($p['position_start']) ?></td>
        <td><?= e($p['position_end'] ?? 'Present') ?></td>
        <td><?= $p['position_monthly'] !== null ? number_format((float)$p['position_monthly'], 2) : '—' ?></td>
        <td><?= $p['position_daily'] !== null ? number_format((float)$p['position_daily'], 2) : '—' ?></td>
        <td><?= $p['position_hourly'] !== null ? number_format((float)$p['position_hourly'], 2) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$positions): ?><tr><td colspan="6">None on file.</td></tr><?php endif; ?>
  </table>
</body>
</html>