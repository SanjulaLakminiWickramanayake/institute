<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('admin', true);

$user = currentUser();
if (!$user) {
    redirect('/admin-login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!verifyUserPassword((int) $user['id'], $currentPassword)) {
        setFlash('error', 'Your current password is incorrect.');
        redirect('/admin/change-password.php');
    }

    if ($password !== $confirm) {
        setFlash('error', 'The new passwords do not match.');
        redirect('/admin/change-password.php');
    }

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        setFlash('error', 'Use at least 8 characters, including an uppercase letter and a number.');
        redirect('/admin/change-password.php');
    }

    if (verifyUserPassword((int) $user['id'], $password)) {
        setFlash('error', 'Choose a password different from your current password.');
        redirect('/admin/change-password.php');
    }

    $stmt = db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, temp_password = 0 WHERE id = ?');
    $stmt->execute([hashPassword($password), $user['id']]);
    session_regenerate_id(true);
    setFlash('success', 'Your administrator password has been updated.');
    redirect('/admin/index.php');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Change Admin Password | Nexora Institute</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/auth.css" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <section class="auth-form-wrap">
      <form class="auth-form" method="post" action="change-password.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
        <span class="eyebrow">ADMIN ACCESS</span>
        <h1>Change your password</h1>
        <p class="auth-note">A new password is required before accessing the administrator dashboard.</p>
        <?php if ($flash): ?>
          <div class="auth-alert <?= e($flash['type']); ?>" role="alert"><?= e($flash['message']); ?></div>
        <?php endif; ?>
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required />
        <label for="password">New password</label>
        <input id="password" name="password" type="password" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}" autocomplete="new-password" required />
        <label for="confirm_password">Confirm new password</label>
        <input id="confirm_password" name="confirm_password" type="password" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}" autocomplete="new-password" required />
        <div class="auth-actions">
          <button type="submit" class="btn btn-primary wide">Update Password</button>
        </div>
        <p class="auth-switch"><a href="../sign-out.php">Sign Out</a></p>
      </form>
    </section>
  </main>
</body>
</html>
