<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $lastTest = (int) ($_SESSION['smtp_test_last_sent'] ?? 0);
    if (time() - $lastTest < 60) {
        setFlash('error', 'Please wait one minute before sending another test email.');
        redirect('/admin/email-test.php');
    }

    $recipient = strtolower(trim((string) ($_POST['recipient'] ?? '')));

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || strlen($recipient) > 150) {
        setFlash('error', 'Enter a valid recipient email address.');
        redirect('/admin/email-test.php');
    }

    $subject = 'Nexora Institute SMTP Test';
    $sent = sendEmail(
        $recipient,
        $subject,
        '<p>This test message confirms that the Nexora Institute application can send email using its configured SMTP service.</p>',
        'This test message confirms that Nexora Institute SMTP email delivery is working.'
    );
    $deliveryError = $sent ? '' : emailDeliveryError();
    $_SESSION['smtp_test_last_sent'] = time();
    logEmailAttempt($recipient, $subject, $sent ? 'sent' : 'failed', $sent ? 'Administrator SMTP test sent.' : 'Administrator SMTP test failed: ' . $deliveryError);

    setFlash($sent ? 'success' : 'error', $sent
        ? 'Test email sent successfully. Check the recipient mailbox.'
        : emailDeliveryFailureMessage('Test email could not be sent. Verify Composer dependencies, Gmail App Password, and SMTP settings in .env.'));
    redirect('/admin/email-test.php');
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SMTP Test | Nexora Admin</title>
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/auth.css" />
  <style>body{background:#f4f7f3}.admin-test{max-width:680px;margin:6vh auto;padding:24px}.admin-panel{background:#fff;border-radius:18px;padding:32px;box-shadow:0 16px 30px rgba(38,51,42,.06)}.auth-alert{margin:18px 0}</style>
</head>
<body>
  <main class="admin-test">
    <section class="admin-panel">
      <span class="eyebrow">ADMINISTRATION</span>
      <h1>Test email configuration</h1>
      <p class="auth-note">Send a branded test message through the configured institute SMTP service. SMTP credentials are never displayed here.</p>
      <?php if ($flash): ?>
        <div class="auth-alert <?= e($flash['type']); ?>" role="alert"><?= e($flash['message']); ?></div>
      <?php endif; ?>
      <form class="auth-form" method="post" action="email-test.php">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()); ?>" />
        <label for="recipient">Test recipient</label>
        <input id="recipient" name="recipient" type="email" maxlength="150" autocomplete="email" required />
        <div class="auth-actions">
          <button type="submit" class="btn btn-primary">Send Test Email</button>
          <a href="index.php" class="btn btn-secondary">Dashboard</a>
        </div>
      </form>
    </section>
  </main>
</body>
</html>
