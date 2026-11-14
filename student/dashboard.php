<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('student');

$user = currentUser();
$student = db()->prepare('SELECT s.*, c.title AS course_title, c.duration AS course_duration, i.name AS intake_name FROM students s JOIN courses c ON c.id = s.course_id JOIN intakes i ON i.id = s.intake_id WHERE s.user_id = ? LIMIT 1');
$student->execute([$user['id']]);
$student = $student->fetch();
$signupDetails = db()->prepare('SELECT phone, nic_number FROM student_signup_details WHERE user_id = ? LIMIT 1');
$signupDetails->execute([$user['id']]);
$signupDetails = $signupDetails->fetch();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard | Nexora Institute</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    body { background:#f4f7f3; }
    .dashboard-shell { max-width:1200px; margin:0 auto; padding:32px 20px 60px; }
    .welcome-card { background:linear-gradient(135deg,#25402f,#315943); color:#fff; border-radius:26px; padding:30px; box-shadow:0 26px 54px rgba(31,45,37,0.16); }
    .content-grid { display:grid; grid-template-columns: 1.2fr 0.8fr; gap:24px; margin-top:28px; }
    .panel { background:#fff; border-radius:22px; padding:24px; box-shadow:0 12px 24px rgba(38,51,42,0.04); border:1px solid rgba(38,51,42,0.05); }
    .info-list { list-style:none; margin:0; padding:0; }
    .info-list li { display:flex; justify-content:space-between; gap:16px; padding:12px 0; border-bottom:1px solid rgba(38,51,42,0.06); }
    .info-list strong { color: #1d2d24; }
    .pill { display:inline-block; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:700; }
    .pill.status-active { background:#dff7ea; color:#1b7a49; }
    .pill.warning { background:#fff4d5; color:#a96a00; }
    .action-row { display:flex; gap:12px; flex-wrap:wrap; margin-top:16px; }
    .alert { padding:14px 16px; border-radius:12px; margin-bottom:18px; }
    .alert.error { background:#fdebec; color:#8b2f2f; }
    .alert.success { background:#eafaf1; color:#235f3c; }
    .warning-box { background:#fff8ec; border:1px solid #f2dfb0; color:#73560d; border-radius:16px; padding:16px; }
    @media (max-width: 900px) { .content-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="dashboard-shell">
    <div class="welcome-card">
      <span class="eyebrow" style="color:#dfeee2;">STUDENT DASHBOARD</span>
      <h1 style="margin:10px 0 0;">Welcome, <?= e($user['full_name'] ?? 'Student'); ?></h1>
    </div>

    <?php if ($flash): ?><div class="alert <?= e($flash['type']); ?>"><?= e($flash['message']); ?></div><?php endif; ?>

    <div class="content-grid">
      <section class="panel">
        <h2>Registration status</h2>
        <?php if ($student): ?>
          <ul class="info-list">
            <li><strong>Student number</strong><span><?= e($student['student_number']); ?></span></li>
            <li><strong>Status</strong><span><span class="pill status-active"><?= e($student['status']); ?></span></span></li>
            <li><strong>Course</strong><span><?= e($student['course_title']); ?></span></li>
            <li><strong>Intake</strong><span><?= e($student['intake_name']); ?></span></li>
            <li><strong>Course duration</strong><span><?= e($student['course_duration']); ?></span></li>
            <li><strong>Registration date</strong><span><?= e(date('d M Y', strtotime($student['registration_date']))); ?></span></li>
          </ul>
        <?php else: ?>
          <p>Your student account is active. Submit an online course application to begin the admissions review process.</p>
          <?php if ($signupDetails): ?>
            <ul class="info-list">
              <li><strong>Phone number</strong><span><?= e($signupDetails['phone']); ?></span></li>
              <li><strong>NIC</strong><span><?= e($signupDetails['nic_number']); ?></span></li>
            </ul>
          <?php endif; ?>
          <div class="action-row"><a href="../apply.php" class="btn btn-primary">Apply Online</a></div>
        <?php endif; ?>
      </section>

      <aside class="panel">
        <h2>Account</h2>
        <?php if ((int) ($user['temp_password'] ?? 0) === 1): ?>
          <div class="warning-box">
            <strong>Temporary password warning:</strong>
            <p>Your account is using a temporary password. Please change it now.</p>
          </div>
        <?php endif; ?>
        <div class="action-row">
          <a href="change-password.php" class="btn btn-primary">Change Password</a>
          <a href="../sign-out.php" class="btn btn-secondary">Sign Out</a>
        </div>
      </aside>
    </div>
  </div>
</body>
</html>
