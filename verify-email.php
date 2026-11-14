<?php
require_once __DIR__ . '/includes/bootstrap.php';

$mode = (string) ($_GET['mode'] ?? $_POST['mode'] ?? 'application');
$isSignup = $mode === 'signup';
$appId = (int) ($_GET['app_id'] ?? $_POST['app_id'] ?? 0);
$app = null;
$signup = null;
$verification = null;

if ($isSignup) {
    $signupId = (int) ($_SESSION['pending_signup_user_id'] ?? 0);
    if ($signupId <= 0) {
        setFlash('error', 'Start by creating a student account.');
        redirect('/sign-up.php');
    }

    $stmt = db()->prepare('SELECT u.id, u.email, u.status, v.code_hash, v.expires_at, v.attempts, v.last_sent_at FROM users u JOIN signup_verifications v ON v.user_id = u.id WHERE u.id = ? AND u.role = "student" LIMIT 1');
    $stmt->execute([$signupId]);
    $verification = $stmt->fetch();
    if (!$verification) {
        unset($_SESSION['pending_signup_user_id']);
        setFlash('success', 'Your account is already verified. Please sign in.');
        redirect('/sign-in.php');
    }
    $signup = $verification;
}

if (!$isSignup && $appId > 0) {
    $stmt = db()->prepare('SELECT * FROM applications WHERE id = ? LIMIT 1');
    $stmt->execute([$appId]);
    $app = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? 'verify');

    if ($isSignup) {
        if (!$verification || !$signup) {
            setFlash('error', 'The verification session is no longer available. Please sign up again.');
            redirect('/sign-up.php');
        }

        if ($action === 'resend') {
            $lastSent = strtotime($verification['last_sent_at']);
            if ($lastSent !== false && time() < $lastSent + 60) {
                setFlash('error', 'Please wait one minute between verification code requests.');
            } else {
                $otp = generateOtp();
                $subject = 'Verify your Nexora Institute account';
                $expiresAt = date('Y-m-d H:i:s', time() + 900);
                db()->prepare('UPDATE signup_verifications SET code_hash = ?, expires_at = ?, attempts = 0, last_sent_at = NOW() WHERE user_id = ?')
                    ->execute([hashPassword($otp), $expiresAt, $signup['id']]);
                $sent = sendEmail($signup['email'], $subject, '<p>Your six-digit verification code is <strong>' . e($otp) . '</strong>. It expires in 15 minutes.</p>', 'Your verification code is ' . $otp . '. It expires in 15 minutes.');
                $deliveryError = $sent ? '' : emailDeliveryError();
                logEmailAttempt($signup['email'], $subject, $sent ? 'sent' : 'failed', $sent ? 'Signup verification code resent.' : 'Signup verification resend failed: ' . $deliveryError);
                setFlash($sent ? 'success' : 'warning', $sent
                    ? 'A new verification code has been sent.'
                    : emailDeliveryFailureMessage('The verification email could not be delivered. Check your email settings and try again.'));
            }
            redirect('/verify-email.php?mode=signup');
        }

        $otp = trim((string) ($_POST['otp'] ?? ''));
        if (!preg_match('/^\d{6}$/', $otp)) {
            setFlash('error', 'Enter the six-digit verification code.');
            redirect('/verify-email.php?mode=signup');
        }
        if ((int) $verification['attempts'] >= 5) {
            setFlash('error', 'Too many incorrect attempts. Request a new code and try again.');
            redirect('/verify-email.php?mode=signup');
        }
        if (strtotime($verification['expires_at']) < time()) {
            setFlash('error', 'Your verification code has expired. Request a new code to continue.');
            redirect('/verify-email.php?mode=signup');
        }
        if (!verifyPassword($otp, $verification['code_hash'])) {
            db()->prepare('UPDATE signup_verifications SET attempts = attempts + 1 WHERE user_id = ?')->execute([$signup['id']]);
            setFlash('error', 'The verification code is incorrect.');
            redirect('/verify-email.php?mode=signup');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $activate = $pdo->prepare('UPDATE users SET status = "active" WHERE id = ? AND role = "student" AND status = "pending"');
            $activate->execute([$signup['id']]);
            if ($activate->rowCount() !== 1) {
                $pdo->rollBack();
                setFlash('error', 'This account cannot be activated. Please contact the institute.');
                redirect('/sign-in.php');
            }
            $pdo->prepare('DELETE FROM signup_verifications WHERE user_id = ?')->execute([$signup['id']]);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Student email verification failed: ' . $exception->getMessage());
            setFlash('error', 'We could not verify your account. Please try again.');
            redirect('/verify-email.php?mode=signup');
        }

        unset($_SESSION['pending_signup_user_id']);
        $verifiedSent = sendEmail(
            $signup['email'],
            'Nexora Institute Email Verified',
            '<p>Your email address has been verified and your student account is now active. Sign in to submit and track a course application.</p>',
            'Your email address has been verified and your student account is active. Sign in to submit and track a course application.'
        );
        logEmailAttempt($signup['email'], 'Nexora Institute Email Verified', $verifiedSent ? 'sent' : 'failed', $verifiedSent ? 'Signup email verification confirmation sent.' : 'Signup email verification confirmation delivery failed.');
        setFlash('success', 'Your email is verified and your account is active. Please sign in.');
        redirect('/sign-in.php');
    }

    if (!$app || $app['status'] !== 'pending_verification') {
        setFlash('error', 'The application could not be found or has already been processed.');
        redirect('/apply.php');
    }

    if ($action === 'resend') {
        $lastSent = $app['otp_resend_at'] ? strtotime($app['otp_resend_at']) : false;
        if ($lastSent !== false && time() < $lastSent + 60) {
            setFlash('error', 'Please wait before resending the verification code.');
        } else {
            $otp = generateOtp();
            $subject = 'Nexora Institute Email Verification';
            $expiresAt = date('Y-m-d H:i:s', time() + 900);
            db()->prepare('UPDATE applications SET otp_hash = ?, otp_expires_at = ?, otp_attempts = 0, otp_resend_at = NOW() WHERE id = ?')
                ->execute([hashPassword($otp), $expiresAt, $appId]);
            $sent = sendEmail($app['email'], $subject, '<p>Your new six-digit code is <strong>' . e($otp) . '</strong>. It expires in 15 minutes.</p>', 'Your new verification code is ' . $otp . '. It expires in 15 minutes.');
            $deliveryError = $sent ? '' : emailDeliveryError();
            logEmailAttempt($app['email'], $subject, $sent ? 'sent' : 'failed', $sent ? 'Application OTP resent.' : 'Application OTP resend failed: ' . $deliveryError);
            setFlash($sent ? 'success' : 'warning', $sent
                ? 'A new verification code has been sent.'
                : emailDeliveryFailureMessage('The verification email could not be delivered. Check your email settings and try again.'));
        }
        redirect('/verify-email.php', ['app_id' => $appId]);
    }

    $otp = trim((string) ($_POST['otp'] ?? ''));
    if ((int) $app['otp_attempts'] >= 5) {
        setFlash('error', 'Too many incorrect attempts. Request a new code and try again.');
        redirect('/verify-email.php', ['app_id' => $appId]);
    }
    if (empty($app['otp_expires_at']) || strtotime($app['otp_expires_at']) < time()) {
        setFlash('error', 'Your verification code has expired. Please request a new code.');
        redirect('/verify-email.php', ['app_id' => $appId]);
    }
    if (!preg_match('/^\d{6}$/', $otp) || empty($app['otp_hash']) || !verifyPassword($otp, $app['otp_hash'])) {
        db()->prepare('UPDATE applications SET otp_attempts = otp_attempts + 1 WHERE id = ?')->execute([$appId]);
        setFlash('error', 'The verification code is incorrect.');
        redirect('/verify-email.php', ['app_id' => $appId]);
    }

    db()->prepare('UPDATE applications SET status = ?, email_verified_at = NOW(), otp_hash = NULL, otp_expires_at = NULL, otp_attempts = 0 WHERE id = ?')->execute(['pending_review', $appId]);
    setFlash('success', 'Your email has been verified successfully. Your application is now pending administrator review.');
    redirect('/sign-in.php');
}

$flash = getFlash();
$maskedEmail = $isSignup && $signup ? maskedEmail($signup['email']) : ($app ? maskedEmail($app['email']) : '');
$expired = $isSignup && $verification
    ? strtotime($verification['expires_at']) < time()
    : ($app && !empty($app['otp_expires_at']) && strtotime($app['otp_expires_at']) < time());
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verify Your Email | Nexora Institute</title>
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/auth.css" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <div class="auth-shell">
      <section class="auth-hero">
        <span class="eyebrow">EMAIL VERIFICATION</span>
        <h1>Verify your email</h1>
        <p>Enter the six-digit code sent to your email address to continue securely.</p>
        <a class="auth-back" href="index.html" style="color:#e4efe6;">Back to Nexora Institute</a>
      </section>
      <section class="auth-form-wrap">
        <div class="auth-form">
          <span class="eyebrow"><?= $isSignup ? 'STUDENT ACCOUNT' : 'APPLICATION'; ?></span>
          <h2>Enter your code</h2>
          <?php if ($maskedEmail !== ''): ?>
            <p class="auth-note">A six-digit code was sent to <strong><?= e($maskedEmail); ?></strong>.</p>
          <?php endif; ?>
          <?php if ($flash): ?>
            <div class="auth-alert <?= e($flash['type']); ?>" role="alert"><?= e($flash['message']); ?></div>
          <?php endif; ?>

          <?php if (($isSignup && $signup) || (!$isSignup && $app)): ?>
            <form method="post" action="verify-email.php<?= $isSignup ? '?mode=signup' : ''; ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
              <input type="hidden" name="mode" value="<?= $isSignup ? 'signup' : 'application'; ?>" />
              <?php if (!$isSignup): ?>
                <input type="hidden" name="app_id" value="<?= (int) $app['id']; ?>" />
              <?php endif; ?>
              <label for="otp">Six-digit code</label>
              <input class="auth-otp" id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" minlength="6" required />
              <p class="auth-note" style="margin-top:10px;">
                <?php if ($expired): ?>
                  This code has expired. Use Resend OTP to get a new code.
                <?php else: ?>
                  Your code expires 15 minutes after it was sent.
                <?php endif; ?>
              </p>
              <div class="auth-actions">
                <button type="submit" name="action" value="verify" class="btn btn-primary wide">Verify</button>
                <button type="submit" name="action" value="resend" class="btn btn-secondary wide" formnovalidate>Resend OTP</button>
              </div>
            </form>
          <?php else: ?>
            <p class="auth-note">No verification request is available. Sign up or submit an application to receive a code.</p>
            <div class="auth-actions">
              <a href="sign-up.php" class="btn btn-primary wide">Student Sign Up</a>
              <a href="apply.php" class="btn btn-secondary wide">Apply Online</a>
            </div>
          <?php endif; ?>
          <p class="auth-switch"><a href="sign-in.php">Return to Sign In</a></p>
        </div>
      </section>
    </div>
  </main>
</body>
</html>
