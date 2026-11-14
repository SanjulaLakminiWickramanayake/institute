<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    redirect('/contact.html', ['contact' => 'invalid']);
}

if (trim((string) ($_POST['company_website'] ?? '')) !== '') {
    redirect('/contact.html', ['contact' => 'sent']);
}

$name = trim((string) ($_POST['contact_name'] ?? ''));
$email = strtolower(trim((string) ($_POST['contact_email'] ?? '')));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if ($name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 150 || $subject === '' || strlen($subject) > 200 || preg_match('/[\r\n]/', $subject)
    || $message === '' || strlen($message) > 5000) {
    redirect('/contact.html', ['contact' => 'invalid']);
}

$lastSubmission = (int) ($_SESSION['contact_last_submission'] ?? 0);
if (time() - $lastSubmission < 30) {
    redirect('/contact.html', ['contact' => 'wait']);
}

$stmt = db()->prepare('INSERT INTO messages (subject, message, sender_name, sender_email, created_at) VALUES (?, ?, ?, ?, NOW())');
$stmt->execute([$subject, $message, $name, $email]);
$_SESSION['contact_last_submission'] = time();
$notificationSent = sendEmail(
    MAIL_FROM_EMAIL,
    'Website Contact: ' . $subject,
    '<p>A website visitor sent a message.</p><p><strong>Name:</strong> ' . e($name)
        . '<br><strong>Email:</strong> ' . e($email) . '</p><p>' . nl2br(e($message)) . '</p>',
    "Website contact message\nName: $name\nEmail: $email\n\n$message",
    [$email]
);
logEmailAttempt(MAIL_FROM_EMAIL, 'Website Contact: ' . $subject, $notificationSent ? 'sent' : 'failed', $notificationSent ? 'Contact notification sent.' : 'Contact notification delivery failed.');
redirect('/contact.html', ['contact' => 'sent']);
