<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    $current = currentUser();
    if ($current && $current['role'] === 'admin') {
        redirect((int) ($current['must_change_password'] ?? 0) === 1
            ? '/admin/change-password.php'
            : '/admin/index.php');
    }
    if ($current) {
        redirect('/student/dashboard.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = strtolower(trim((string) ($_POST['username'] ?? $_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash, role, status, must_change_password FROM users WHERE role = "admin" AND (username = ? OR email = ?) LIMIT 1');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin' || $user['status'] !== 'active' || !verifyPassword($password, $user['password_hash'])) {
        setFlash('error', 'Invalid administrator credentials.');
        redirect('/admin-login.php');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = 'admin';
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
    redirect((int) $user['must_change_password'] === 1
        ? '/admin/change-password.php'
        : '/admin/index.php');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Administrator Sign In | Nexora Institute</title>
  <link rel="icon" type="image/jpeg" href="images/logo/logo.jpg" />
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/auth.css" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <div class="auth-shell">
      <section class="auth-hero">
        <span class="eyebrow">NEXORA INSTITUTE</span>
        <h1>Administrator portal</h1>
        <p>Sign in with an active administrator account to manage institute operations.</p>
        <a class="auth-back" href="index.html" style="color:#e4efe6;">Back to Nexora Institute</a>
      </section>
      <section class="auth-form-wrap">
        <form class="auth-form" method="post" action="admin-login.php">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
          <span class="eyebrow">ADMIN ACCESS</span>
          <h2>Admin Sign In</h2>
          <p class="auth-note">This page only accepts administrator accounts.</p>
          <?php if ($flash): ?>
            <div class="auth-alert <?= e($flash['type']); ?>" role="alert"><?= e($flash['message']); ?></div>
          <?php endif; ?>
          <label for="username">Administrator Username or Email</label>
          <input id="username" name="username" type="text" maxlength="150" autocomplete="username" required />
          <label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required />
          <div class="auth-actions">
            <button type="submit" class="btn btn-primary wide">Sign In to Admin</button>
          </div>
          <p class="auth-switch"><a href="sign-in.php">Student sign in</a></p>
        </form>
      </section>
    </div>
  </main>
</body>
</html>
