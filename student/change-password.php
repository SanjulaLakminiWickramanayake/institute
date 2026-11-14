<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('student');

$user = currentUser();
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($newPassword === '' || $confirmPassword === '') {
        setFlash('error', 'New password and confirmation are required.');
        redirect('/student/change-password.php');
    }

    if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        setFlash('error', 'Password must be at least 8 characters with a number and uppercase letter.');
        redirect('/student/change-password.php');
    }

    if ($newPassword !== $confirmPassword) {
        setFlash('error', 'Password confirmation does not match.');
        redirect('/student/change-password.php');
    }

    if (!verifyUserPassword((int) $user['id'], $currentPassword)) {
        setFlash('error', 'Current password is incorrect.');
        redirect('/student/change-password.php');
    }

    $hash = hashPassword($newPassword);
    db()->prepare('UPDATE users SET password_hash = ?, temp_password = 0, must_change_password = 0 WHERE id = ?')->execute([$hash, $user['id']]);
    setFlash('success', 'Password updated successfully.');
    redirect('/student/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Change Password | Nexora Institute</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    body { background:#f5f7f4; }
    .card { max-width:620px; margin:40px auto; background:#fff; border-radius:20px; padding:28px; box-shadow:0 20px 40px rgba(38,51,42,0.06); }
    .field { display:flex; flex-direction:column; margin-bottom:18px; }
    .field input { padding:14px 16px; border-radius:12px; border:1px solid rgba(38,51,42,0.12); }
    .alert { padding:14px 16px; border-radius:12px; margin-bottom:18px; }
    .alert.error { background:#fdebec; color:#8b2f2f; }
    .alert.success { background:#eafaf1; color:#235f3c; }
  </style>
</head>
<body>
  <div class="card">
    <span class="eyebrow">ACCOUNT SECURITY</span>
    <h1>Change Password</h1>
    <?php if ($flash): ?><div class="alert <?= e($flash['type']); ?>"><?= e($flash['message']); ?></div><?php endif; ?>
    <form method="post" action="change-password.php">
      <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
      <div class="field">
        <label for="current_password">Current password</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" required />
      </div>
      <div class="field">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="new_password" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Use at least 8 characters, including an uppercase letter and a number." autocomplete="new-password" required />
      </div>
      <div class="field">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="confirm_password" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Use at least 8 characters, including an uppercase letter and a number." autocomplete="new-password" required />
      </div>
      <button type="submit" class="btn btn-primary wide">Update Password</button>
    </form>
  </div>
</body>
</html>
