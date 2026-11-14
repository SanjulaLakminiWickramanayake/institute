<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Please enter a valid email address.');
        redirect('/forgot-password.php');
    }

    $user = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $user->execute([$email]);
    $userRow = $user->fetch();

    if ($userRow) {
        $recent = db()->prepare('SELECT id FROM password_resets WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1');
        $recent->execute([$userRow['id']]);
        if (!$recent->fetch()) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$userRow['id']]);
                $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())')->execute([$userRow['id'], $tokenHash, $expires]);
                $pdo->commit();
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Password reset request failed: ' . $exception->getMessage());
                setFlash('error', 'We could not process the reset request. Please try again.');
                redirect('/forgot-password.php');
            }

            $resetUrl = APP_URL . '/reset-password.php?token=' . urlencode($token);
            $subject = 'Reset your Nexora password';
            $sent = sendEmail($email, $subject, '<p>Reset your password here: <a href="' . e($resetUrl) . '">' . e($resetUrl) . '</a></p>');
            logEmailAttempt($email, $subject, $sent ? 'sent' : 'failed', $sent ? 'Password reset email sent.' : 'Password reset email delivery failed.');
        }
    }

    setFlash('success', 'If an account exists for that email, a password reset link has been sent.');
    redirect('/sign-in.php');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Forgot Password | Nexora Institute</title>
  <link rel="icon" type="image/jpeg" href="images/logo/logo.jpg" />
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/auth.css" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <div class="auth-shell">
      <section class="auth-hero">
        <span class="eyebrow">NEXORA INSTITUTE</span>
        <h1>Let’s get you back to learning</h1>
        <p>Request a secure password reset link using the email address on your student account.</p>
        <a class="auth-back" href="index.html" style="color:#e4efe6;">Back to Nexora Institute</a>
      </section>
      <section class="auth-form-wrap">
        <form class="auth-form" method="post" action="forgot-password.php">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
          <span class="eyebrow">PASSWORD RECOVERY</span>
          <h2>Forgot your password?</h2>
          <p class="auth-note">If the address belongs to an active account, we’ll send a reset link.</p>
          <?php if ($flash): ?>
            <div class="auth-alert <?= e($flash['type']); ?>" role="alert"><?= e($flash['message']); ?></div>
          <?php endif; ?>
          <label for="email">Email address</label>
          <input id="email" name="email" type="email" maxlength="150" autocomplete="email" required />
          <div class="auth-actions">
            <button type="submit" class="btn btn-primary wide">Send Reset Link</button>
          </div>
          <p class="auth-switch"><a href="sign-in.php">Back to Sign In</a></p>
        </form>
      </section>
    </div>
  </main>
</body>
</html>
