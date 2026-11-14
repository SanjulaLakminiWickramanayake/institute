<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $intakeId = (int) ($_POST['intake_id'] ?? 0);
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $nic = trim((string) ($_POST['nic_number'] ?? ''));
    $education = trim((string) ($_POST['education_level'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($courseId <= 0 || $intakeId <= 0 || $fullName === '' || $email === '' || $phone === '' || $nic === '' || $education === '') {
        setFlash('error', 'Please complete all required fields.');
        redirect('/apply.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'Please enter a valid email address.');
        redirect('/apply.php');
    }

    $course = db()->prepare('SELECT id, title, status FROM courses WHERE id = ? AND status = ? LIMIT 1');
    $course->execute([$courseId, 'published']);
    $courseRow = $course->fetch();

    $intake = db()->prepare('SELECT id, name, status FROM intakes WHERE id = ? AND status = ? LIMIT 1');
    $intake->execute([$intakeId, 'active']);
    $intakeRow = $intake->fetch();

    if (!$courseRow || !$intakeRow) {
        setFlash('error', 'The selected course or intake is not currently available.');
        redirect('/apply.php');
    }

    $duplicate = db()->prepare('SELECT id FROM applications WHERE email = ? AND course_id = ? AND intake_id = ? AND status IN (\'pending_verification\', \'pending_review\', \'approved\') LIMIT 1');
    $duplicate->execute([$email, $courseId, $intakeId]);
    if ($duplicate->fetch()) {
        setFlash('error', 'A similar application already exists for this course and intake.');
        redirect('/apply.php');
    }

    $otp = generateOtp();
    $otpHash = hashPassword($otp);
    $expiresAt = date('Y-m-d H:i:s', time() + 900);

    $stmt = db()->prepare('INSERT INTO applications (course_id, intake_id, full_name, email, phone, nic_number, education_level, message, status, otp_hash, otp_expires_at, otp_attempts, otp_resend_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())');
    $stmt->execute([$courseId, $intakeId, $fullName, $email, $phone, $nic, $education, $message, 'pending_verification', $otpHash, $expiresAt]);
    $applicationId = (int) db()->lastInsertId();

    $subject = 'Nexora Institute Email Verification';
    $body = '<p>Your verification code is <strong>' . e($otp) . '</strong>. It expires in 15 minutes.</p>';
    $confirmationSent = sendEmail(
        $email,
        'Nexora Institute Application Received',
        '<p>Dear ' . e($fullName) . ',</p><p>We received your application for <strong>' . e($courseRow['title']) . '</strong> (' . e($intakeRow['name']) . '). Please verify your email using the separate six-digit code message. Your application will then be available for administrator review.</p>',
        'We received your Nexora Institute application for ' . $courseRow['title'] . ' (' . $intakeRow['name'] . '). Please use the separate verification code email to verify your email address.'
    );
    $confirmationError = $confirmationSent ? '' : emailDeliveryError();
    logEmailAttempt($email, 'Nexora Institute Application Received', $confirmationSent ? 'sent' : 'failed', $confirmationSent ? 'Application receipt sent.' : 'Application receipt delivery failed: ' . $confirmationError);
    $sent = sendEmail($email, $subject, $body, 'Your verification code is ' . $otp . '. It expires in 15 minutes.');
    $otpError = $sent ? '' : emailDeliveryError();
    logEmailAttempt($email, $subject, $sent ? 'sent' : 'failed', $sent ? 'OTP email sent' : 'OTP email delivery failed: ' . $otpError);

    setFlash($sent ? 'success' : 'warning', $sent
        ? 'Your application has been submitted. Please verify your email address to continue.'
        : emailDeliveryFailureMessage('Your application was saved, but the verification email could not be delivered. Configure email delivery and use Resend OTP.'));

    redirect('/verify-email.php', ['app_id' => $applicationId]);
}

$flash = getFlash();
$courseStmt = db()->query('SELECT c.id, c.title, c.duration, cat.name AS category_name FROM courses c JOIN categories cat ON cat.id = c.category_id WHERE c.status = "published" ORDER BY c.title ASC');
$courses = $courseStmt->fetchAll();
$intakes = db()->query('SELECT * FROM intakes WHERE status = "active" ORDER BY id ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Apply Online | Nexora Institute</title>
  <link rel="stylesheet" href="css/style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    body { background:#f4f7f3; }
    .apply-wrap { max-width: 1180px; margin: 0 auto; padding: 48px 20px 64px; }
    .apply-header { text-align:center; margin-bottom:32px; }
    .progress { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:14px; margin:26px 0 26px; }
    .step { background:#fff; border-radius:16px; padding:16px 18px; border:1px solid rgba(38,51,42,0.08); font-weight:600; }
    .step.current { background:#e9f5ea; border-color: rgba(67,121,87,0.25); color:#1d5638; }
    .panel { background:#fff; border:1px solid rgba(38,51,42,0.06); border-radius:24px; padding:24px; box-shadow:0 18px 32px rgba(38,51,42,0.04); }
    .form-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:18px; }
    .field { display:flex; flex-direction:column; gap:8px; }
    .field input, .field select, .field textarea { width:100%; padding:14px 16px; border:1px solid rgba(38,51,42,0.12); border-radius:12px; } 
    .field textarea { min-height: 120px; }
    .alert { padding:12px 14px; border-radius:12px; margin-bottom:16px; }
    .alert.error { background: #fdebec; color:#8b2f2f; }
    .alert.warning { background: #fff7e8; color:#8a6500; }
    .alert.success { background: #eafaf1; color:#235f3c; }
    @media (max-width: 760px) { .form-grid { grid-template-columns:1fr; } .progress { grid-template-columns:1fr; } }
  </style>
</head>
<body>
  <div class="apply-wrap">
    <div class="apply-header">
      <span class="eyebrow">APPLICATION</span>
      <h1>Apply online</h1>
      <p>Select a course, provide your details, and verify your email to continue.</p>
      <div class="progress">
        <div class="step current">1. Course and details</div>
        <div class="step">2. Verify email</div>
        <div class="step">3. Wait for approval</div>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="alert <?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
    <?php endif; ?>

    <div class="panel">
      <form method="post" action="apply.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
        <div class="form-grid">
          <div class="field">
            <label for="course_id">Course</label>
            <select id="course_id" name="course_id" required>
              <option value="">Select a course</option>
              <?php foreach ($courses as $course): ?>
                <option value="<?= (int) $course['id']; ?>"><?= e($course['title']); ?> (<?= e($course['category_name']); ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="intake_id">Preferred intake</label>
            <select id="intake_id" name="intake_id" required>
              <option value="">Select an intake</option>
              <?php foreach ($intakes as $intake): ?>
                <option value="<?= (int) $intake['id']; ?>"><?= e($intake['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" type="text" required />
          </div>
          <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required />
          </div>
          <div class="field">
            <label for="phone">Phone number</label>
            <input id="phone" name="phone" type="text" required />
          </div>
          <div class="field">
            <label for="nic_number">NIC number</label>
            <input id="nic_number" name="nic_number" type="text" required />
          </div>
          <div class="field" style="grid-column: 1 / -1;">
            <label for="education_level">Education level</label>
            <input id="education_level" name="education_level" type="text" required />
          </div>
          <div class="field" style="grid-column: 1 / -1;">
            <label for="message">Optional message</label>
            <textarea id="message" name="message"></textarea>
          </div>
        </div>

        <div style="margin-top:22px; display:flex; gap:12px; flex-wrap:wrap; justify-content:flex-end;">
          <a href="index.html" class="btn btn-secondary">Back to Home</a>
          <button type="submit" class="btn btn-primary">Submit application</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
