<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        setFlash('error', 'Email and password are required.');
        redirect('/sign-in.php');
    }

    $stmt = db()->prepare('SELECT id, email, role, status, password_hash FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'student' || !verifyPassword($password, $user['password_hash'])) {
        setFlash('error', 'Invalid email or password.');
        redirect('/sign-in.php');
    }

    if (($user['status'] ?? 'inactive') !== 'active') {
        setFlash('error', 'Your account is currently not active. Please contact the administrator.');
        redirect('/sign-in.php');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];

    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

    redirect('/student/dashboard.php');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sign In | Nexora Institute</title>
  <link rel="icon" type="image/jpeg" href="images/logo/logo.jpg" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/auth.css" />
</head>
<body class="auth-body">
  <div class="auth-page">
    <div class="auth-shell">
      <div class="auth-hero">
        <span class="eyebrow" style="color:#dfeee2;">NEXORA INSTITUTE</span>
        <h1>Shape your future with better education</h1>
        <p>Learn • Grow • Succeed</p>
      </div>

      <div class="auth-form-wrap">
        <form class="auth-form" method="post" action="sign-in.php">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
          <span class="eyebrow">SIGN IN</span>
          <h2>Welcome back</h2>
          <p class="auth-note">Sign in to continue to your student dashboard.</p>

          <?php if ($flash): ?>
            <div class="auth-alert <?= e($flash['type']); ?>" role="alert"><?= e($flash['message']); ?></div>
          <?php endif; ?>

          <label for="email">Email</label>
          <input type="email" id="email" name="email" required />

          <label for="password">Password</label>
          <input type="password" id="password" name="password" required />

          <div class="auth-meta">
            <a href="forgot-password.php">Forgot Password?</a>
          </div>

          <div class="auth-actions">
            <button type="submit" class="btn btn-primary wide">Sign In</button>
          </div>
          <p class="auth-switch">Don't have an account? <a href="sign-up.php">Register here</a></p>
          <p class="auth-switch"><a href="admin-login.php">Administrator sign in</a></p>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
