<?php
require_once __DIR__ . '/includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
$flash = getFlash();
$canReset = false;
if (preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $validToken = db()->prepare('SELECT id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $validToken->execute([hash('sha256', $token)]);
    $canReset = (bool) $validToken->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $token = trim((string) ($_POST['token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($password === '' || $confirm === '') {
        setFlash('error', 'Both password fields are required.');
        redirect('/reset-password.php', ['token' => $token]);
    }

    if ($password !== $confirm) {
        setFlash('error', 'Passwords do not match.');
        redirect('/reset-password.php', ['token' => $token]);
    }

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        setFlash('error', 'Password must be at least 8 characters and include a number and uppercase letter.');
        redirect('/reset-password.php', ['token' => $token]);
    }

    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        setFlash('error', 'This password reset link is invalid or expired.');
        redirect('/forgot-password.php');
    }

    $tokenHash = hash('sha256', $token);
    $hash = hashPassword($password);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $resetStmt = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1 FOR UPDATE');
        $resetStmt->execute([$tokenHash]);
        $reset = $resetStmt->fetch();
        if (!$reset) {
            $pdo->rollBack();
            setFlash('error', 'This password reset link is invalid or expired.');
            redirect('/forgot-password.php');
        }

        $pdo->prepare('UPDATE users SET password_hash = ?, temp_password = 0, must_change_password = 0 WHERE id = ?')->execute([$hash, $reset['user_id']]);
        $used = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
        $used->execute([$reset['id']]);
        if ($used->rowCount() !== 1) {
            throw new PDOException('Password reset token was already consumed.');
        }
        $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$reset['user_id']]);
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Password reset failed: ' . $exception->getMessage());
        setFlash('error', 'Password reset failed. Please try again.');
        redirect('/forgot-password.php');
    }

    setFlash('success', 'Your password was updated successfully. You may sign in now.');
    redirect('/sign-in.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reset Password | Nexora Institute</title>
  <link rel="icon" type="image/jpeg" href="images/logo/logo.jpg" />
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/auth.css" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <div class="auth-shell">
      <section class="auth-hero">
        <span class="eyebrow">NEXORA INSTITUTE</span>
        <h1>Secure your student account</h1>
        <p>Choose a new password you have not used for this account before.</p>
        <a class="auth-back" href="index.html" style="color:#e4efe6;">Back to Nexora Institute</a>
      </section>
      <section class="auth-form-wrap">
        <div class="auth-form">
          <span class="eyebrow">PASSWORD RESET</span>
          <h2>Set a new password</h2>
          <p class="auth-note">Use at least 8 characters, including an uppercase letter and a number.</p>
          <?php if ($flash): ?>
            <div class="auth-alert <?= e($flash['type']); ?>" role="alert"><?= e($flash['message']); ?></div>
          <?php endif; ?>
          <?php if ($canReset): ?>
            <form method="post" action="reset-password.php">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
              <input type="hidden" name="token" value="<?= e($token); ?>" />
              <label for="password">New password</label>
              <input id="password" name="password" type="password" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Use at least 8 characters, including an uppercase letter and a number." autocomplete="new-password" required />
              <label for="confirm_password">Confirm password</label>
              <input id="confirm_password" name="confirm_password" type="password" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Use at least 8 characters, including an uppercase letter and a number." autocomplete="new-password" required />
              <div class="auth-actions">
                <button type="submit" class="btn btn-primary wide">Update Password</button>
              </div>
            </form>
          <?php else: ?>
            <p class="auth-note">This reset link is invalid, expired, or has already been used.</p>
            <div class="auth-actions">
              <a href="forgot-password.php" class="btn btn-primary wide">Request Another Link</a>
            </div>
          <?php endif; ?>
          <p class="auth-switch"><a href="sign-in.php">Back to Sign In</a></p>
        </div>
      </section>
    </div>
  </main>
</body>
</html>
