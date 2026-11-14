<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    $user = currentUser();
    if ($user && $user['role'] === 'admin') {
        redirect('/admin/index.php');
    }
    if ($user) {
        redirect('/student/dashboard.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $nic = trim((string) ($_POST['nic_number'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($fullName === '' || strlen($fullName) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150 || !preg_match('/^[+0-9().\-\s]{7,30}$/', $phone)
        || strlen($nic) < 6 || strlen($nic) > 50) {
        setFlash('error', 'Please provide valid details in all required fields.');
        redirect('/sign-up.php');
    }

    if ($password !== $confirmPassword) {
        setFlash('error', 'Passwords do not match.');
        redirect('/sign-up.php');
    }

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        setFlash('error', 'Password must be at least 8 characters and include an uppercase letter and a number.');
        redirect('/sign-up.php');
    }

    $existing = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $existing->execute([$email]);
    if ($existing->fetch()) {
        setFlash('error', 'An account already uses this email. Please sign in or reset your password.');
        redirect('/sign-in.php');
    }

    $otp = generateOtp();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, status, temp_password, must_change_password, created_at) VALUES (?, ?, ?, "student", "pending", 0, 0, NOW())');
        $stmt->execute([$fullName, $email, hashPassword($password)]);
        $userId = (int) $pdo->lastInsertId();

        $profile = $pdo->prepare('INSERT INTO student_signup_details (user_id, phone, nic_number) VALUES (?, ?, ?)');
        $profile->execute([$userId, $phone, $nic]);

        $verification = $pdo->prepare('INSERT INTO signup_verifications (user_id, code_hash, expires_at, attempts, last_sent_at, created_at) VALUES (?, ?, ?, 0, NOW(), NOW())');
        $verification->execute([$userId, hashPassword($otp), date('Y-m-d H:i:s', time() + 900)]);
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Student sign-up failed: ' . $exception->getMessage());
        setFlash('error', 'We could not create your account right now. Please try again.');
        redirect('/sign-up.php');
    }

    $_SESSION['pending_signup_user_id'] = $userId;
    $subject = 'Verify your Nexora Institute account';
    $body = '<p>Your six-digit verification code is <strong>' . e($otp) . '</strong>. It expires in 15 minutes.</p>';
    $sent = sendEmail($email, $subject, $body, 'Your verification code is ' . $otp . '. It expires in 15 minutes.');
    $deliveryError = $sent ? '' : emailDeliveryError();
    logEmailAttempt($email, $subject, $sent ? 'sent' : 'failed', $sent ? 'Student signup verification email sent.' : 'Student signup verification delivery failed: ' . $deliveryError);

    setFlash($sent ? 'success' : 'warning', $sent
        ? 'Account created. Enter the verification code sent to your email.'
        : emailDeliveryFailureMessage('Account created, but the verification email could not be delivered. Configure email delivery and use Resend OTP.'));
    redirect('/verify-email.php?mode=signup');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Sign Up | Nexora Institute</title>
  <link rel="icon" type="image/jpeg" href="images/logo/logo.jpg" />
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/auth.css" />
</head>
<body class="auth-body">
  <main class="auth-page">
    <div class="auth-shell">
      <section class="auth-hero">
        <span class="eyebrow">NEXORA INSTITUTE</span>
        <h1>Begin your learning journey</h1>
        <p>Create a student account to access your dashboard and continue with an online course application.</p>
        <a class="auth-back" href="index.html" style="color:#e4efe6;">Back to Nexora Institute</a>
      </section>
      <section class="auth-form-wrap">
        <form class="auth-form" method="post" action="sign-up.php">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
          <span class="eyebrow">STUDENT SIGN UP</span>
          <h2>Create your account</h2>
          <p class="auth-note">We’ll verify your email before activating your account.</p>
          <?php if ($flash): ?>
            <div class="auth-alert <?= e($flash['type']); ?>" role="alert"><?= e($flash['message']); ?></div>
          <?php endif; ?>
          <div class="auth-grid">
            <div class="field field-full">
              <label for="full_name">Full Name</label>
              <input id="full_name" name="full_name" type="text" maxlength="150" autocomplete="name" required />
            </div>
            <div class="field field-full">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" maxlength="150" autocomplete="email" required />
            </div>
            <div class="field">
              <label for="phone">Phone Number</label>
              <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" required />
            </div>
            <div class="field">
              <label for="nic_number">NIC</label>
              <input id="nic_number" name="nic_number" type="text" minlength="6" maxlength="50" required />
            </div>
            <div class="field">
              <label for="password">Password</label>
              <input id="password" name="password" type="password" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Use at least 8 characters, including an uppercase letter and a number." autocomplete="new-password" required />
            </div>
            <div class="field">
              <label for="confirm_password">Confirm Password</label>
              <input id="confirm_password" name="confirm_password" type="password" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}" title="Use at least 8 characters, including an uppercase letter and a number." autocomplete="new-password" required />
            </div>
          </div>
          <div class="auth-actions">
            <button type="submit" class="btn btn-primary wide">Sign Up</button>
          </div>
          <p class="auth-switch">Already have an account? <a href="sign-in.php">Sign In</a></p>
          <p class="auth-switch"><a href="admin-login.php">Administrator sign in</a></p>
        </form>
      </section>
    </div>
  </main>
</body>
</html>
