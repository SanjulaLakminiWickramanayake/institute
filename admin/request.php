<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('admin');

$appId = (int) ($_GET['id'] ?? 0);
if ($appId <= 0) {
    setFlash('error', 'Application not found.');
    redirect('/admin/requests.php');
}

$stmt = db()->prepare('SELECT a.*, c.title AS course_name, i.name AS intake_name FROM applications a JOIN courses c ON c.id = a.course_id JOIN intakes i ON i.id = a.intake_id WHERE a.id = ? LIMIT 1');
$stmt->execute([$appId]);
$app = $stmt->fetch();

if (!$app) {
    setFlash('error', 'Application not found.');
    redirect('/admin/requests.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $decision = $_POST['decision'] ?? '';

    if (!in_array($decision, ['approved', 'rejected'], true)) {
        setFlash('error', 'Invalid review decision.');
        redirect('/admin/request.php?id=' . $appId);
    }

    if ($app['status'] !== 'pending_review') {
        setFlash('error', 'Only email-verified applications awaiting review can be processed.');
        redirect('/admin/requests.php');
    }

    if ($decision === 'approved') {
        $pdo = db();
        $setupToken = null;
        $emailSubject = 'Nexora Institute Registration Approved';
        $pdo->beginTransaction();
        try {
            $existingUser = $pdo->prepare('SELECT id, role, status FROM users WHERE email = ? LIMIT 1 FOR UPDATE');
            $existingUser->execute([$app['email']]);
            $userRow = $existingUser->fetch();

            if ($userRow && $userRow['role'] !== 'student') {
                $pdo->rollBack();
                setFlash('error', 'This email belongs to a non-student account and cannot be enrolled.');
                redirect('/admin/request.php?id=' . $appId);
            }

            if ($userRow && !in_array($userRow['status'], ['active', 'pending'], true)) {
                $pdo->rollBack();
                setFlash('error', 'The existing student account is inactive or locked and must be resolved before approval.');
                redirect('/admin/request.php?id=' . $appId);
            }

            if ($userRow) {
                $userId = (int) $userRow['id'];
                if ($userRow['status'] === 'pending') {
                    $pdo->prepare('UPDATE users SET status = "active" WHERE id = ?')->execute([$userId]);
                }
            } else {
                $initialSecret = bin2hex(random_bytes(32));
                $userStmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, status, temp_password, must_change_password, created_at) VALUES (?, ?, ?, "student", "active", 0, 0, NOW())');
                $userStmt->execute([$app['full_name'], $app['email'], hashPassword($initialSecret)]);
                $userId = (int) $pdo->lastInsertId();
                $setupToken = bin2hex(random_bytes(32));
                $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())')
                    ->execute([$userId, hash('sha256', $setupToken), date('Y-m-d H:i:s', time() + 3600)]);
            }

            $studentNumber = generateStudentNumber();
            $studentStmt = $pdo->prepare('INSERT INTO students (user_id, application_id, student_number, full_name, email, phone, nic_number, education_level, course_id, intake_id, registration_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), "active", NOW())');
            $studentStmt->execute([$userId, $appId, $studentNumber, $app['full_name'], $app['email'], $app['phone'], $app['nic_number'], $app['education_level'], $app['course_id'], $app['intake_id']]);

            $approved = $pdo->prepare('UPDATE applications SET status = ?, email_verified_at = IFNULL(email_verified_at, NOW()) WHERE id = ? AND status = "pending_review"');
            $approved->execute(['approved', $appId]);
            if ($approved->rowCount() !== 1) {
                throw new PDOException('Application status changed before approval completed.');
            }
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Application approval failed: ' . $exception->getMessage());
            setFlash('error', 'The application could not be approved. Check the student record and try again.');
            redirect('/admin/request.php?id=' . $appId);
        }

        if ($setupToken !== null) {
            $setupUrl = APP_URL . '/reset-password.php?token=' . urlencode($setupToken);
            $body = '<p>Dear ' . e($app['full_name']) . ',</p><p>Your application has been approved and your Nexora student account is ready.</p><p><a href="' . e($setupUrl) . '" style="display:inline-block;padding:12px 20px;background:#7fa88a;color:#fff;text-decoration:none;border-radius:999px;">Set your account password</a></p><p>This one-time link expires in one hour. If it expires, request a new link from Forgot Password on the sign-in page.</p>';
            $sent = sendEmail($app['email'], $emailSubject, $body, 'Your application has been approved and your student account is ready. Set your password using this one-time link (expires in one hour): ' . $setupUrl);
            logEmailAttempt($app['email'], $emailSubject, $sent ? 'sent' : 'failed', $sent ? 'Student account created and password setup email sent.' : 'Student account created; password setup email delivery failed.');
        } else {
            $sent = sendEmail($app['email'], $emailSubject, '<p>Dear ' . e($app['full_name']) . ',</p><p>Your application has been approved. You can now sign in to your Nexora student account.</p>', 'Your Nexora Institute application has been approved. Sign in to your student account.');
            logEmailAttempt($app['email'], $emailSubject, $sent ? 'sent' : 'failed', $sent ? 'Application approval notification sent.' : 'Approval notification delivery failed.');
        }
        setFlash('success', $sent
            ? 'Application approved, student record created, and notification email sent.'
            : 'Application approved and student record created. Email delivery failed; check the email log and SMTP configuration.');
    } else {
        $rejected = db()->prepare('UPDATE applications SET status = ? WHERE id = ? AND status = "pending_review"');
        $rejected->execute(['rejected', $appId]);
        if ($rejected->rowCount() !== 1) {
            setFlash('error', 'The application status changed before the rejection could be saved.');
            redirect('/admin/requests.php');
        }
        $subject = 'Nexora Institute Application Update';
        $sent = sendEmail(
            $app['email'],
            $subject,
            '<p>Dear ' . e($app['full_name']) . ',</p><p>Thank you for applying to Nexora Institute. After review, we are unable to approve your application at this time.</p><p>Please contact the institute if you would like to discuss your application.</p>',
            'Thank you for applying to Nexora Institute. After review, we are unable to approve your application at this time. Please contact the institute if you would like to discuss your application.'
        );
        logEmailAttempt($app['email'], $subject, $sent ? 'sent' : 'failed', $sent ? 'Application rejection notification sent.' : 'Application rejection notification delivery failed.');
        setFlash('success', $sent
            ? 'Application rejected and notification email sent.'
            : 'Application rejected. Email delivery failed; check the email log and SMTP configuration.');
    }

    redirect('/admin/requests.php');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Review Application | Nexora Admin</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    body { background:#f4f7f3; margin:0; }
    .admin-shell { display:grid; grid-template-columns:260px 1fr; min-height:100vh; }
    .admin-sidebar { background:#1d2d24; color:#fff; padding:24px 18px; }
    .admin-brand { display:flex; align-items:center; gap:12px; margin-bottom:30px; }
    .admin-sidebar nav a { display:block; color: rgba(255,255,255,0.82); padding:12px 14px; border-radius:10px; margin-bottom:8px; text-decoration:none; }
    .admin-sidebar nav a.active, .admin-sidebar nav a:hover { background: rgba(255,255,255,0.12); color:#fff; }
    .admin-main { padding:28px; }
    .panel { background:#fff; border:1px solid rgba(38,51,42,0.06); border-radius:18px; padding:24px; box-shadow:0 16px 30px rgba(38,51,42,0.04); }
    .info-grid { display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:18px; }
    .info-item { background:#f8faf8; border-radius:12px; padding:16px; }
    .info-item strong { display:block; margin-bottom:8px; }
    .alert { padding:12px 14px; border-radius:12px; margin-bottom:18px; }
    .alert.error { background:#fdebec; color:#8b2f2f; }
    .alert.success { background:#eafaf1; color:#235f3c; }
    textarea { width:100%; min-height:120px; border-radius:12px; border:1px solid rgba(38,51,42,0.12); padding:14px 16px; }
    @media (max-width:900px){ .admin-shell{grid-template-columns:1fr;} .admin-sidebar{padding-bottom:10px;} .info-grid{grid-template-columns:1fr;} }
  </style>
</head>
<body>
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <div class="admin-brand">
        <img src="../images/logo/logo.jpg" alt="Nexora" style="width:42px;height:42px;border-radius:12px;" />
        <div><strong>Nexora</strong><br /><small>Admin</small></div>
      </div>
      <nav>
        <a href="index.php">Dashboard</a>
        <a href="courses.php">Courses</a>
        <a href="categories.php">Categories</a>
        <a href="intakes.php">Intakes</a>
        <a href="requests.php" class="active">Registration Requests</a>
        <a href="students.php">Students</a>
        <a href="messages.php">Messages</a>
        <a href="email-log.php">Email Log</a>
        <a href="../sign-out.php">Sign Out</a>
      </nav>
    </aside>
    <main class="admin-main">
      <div class="section-heading left" style="margin-bottom:18px;">
        <span class="eyebrow">APPLICATION REVIEW</span>
        <h1><?= e($app['full_name']); ?></h1>
      </div>

      <?php if ($flash): ?>
        <div class="alert <?= e($flash['type']); ?>"><?= e($flash['message']); ?></div>
      <?php endif; ?>

      <div class="panel">
        <div class="info-grid">
          <div class="info-item"><strong>Course</strong> <?= e($app['course_name']); ?></div>
          <div class="info-item"><strong>Intake</strong> <?= e($app['intake_name']); ?></div>
          <div class="info-item"><strong>Email</strong> <?= e($app['email']); ?></div>
          <div class="info-item"><strong>Phone</strong> <?= e($app['phone']); ?></div>
          <div class="info-item"><strong>NIC</strong> <?= e($app['nic_number']); ?></div>
          <div class="info-item"><strong>Education level</strong> <?= e($app['education_level']); ?></div>
          <div class="info-item" style="grid-column:1 / -1;"><strong>Applicant message</strong> <?= nl2br(e($app['message'] ?? 'No message provided.')); ?></div>
        </div>

        <form method="post" action="request.php?id=<?= (int) $appId; ?>" style="margin-top:24px;">
          <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
          <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button type="submit" name="decision" value="approved" class="btn btn-primary">Approve application</button>
            <button type="submit" name="decision" value="rejected" class="btn btn-secondary">Reject application</button>
          </div>
        </form>
      </div>
    </main>
  </div>
</body>
</html>
