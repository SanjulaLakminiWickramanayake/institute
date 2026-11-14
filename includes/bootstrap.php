<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true
    ]);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/passwords.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/../database/initialize.php';

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

initializeNexoraDatabase();

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('SET time_zone = ' . $pdo->quote(date('P')));
    }

    return $pdo;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $path, array $params = []): void
{
    $url = APP_URL . '/' . ltrim($path, '/');

    if ($params) {
        $url .= '?' . http_build_query($params);
    }

    header('Location: ' . $url, true, 302);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        setFlash('error', 'Security validation failed. Please retry the page.');
        redirect('/sign-in.php');
    }
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, full_name, username, email, role, status, temp_password, must_change_password, created_at, last_login_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function requireAuth(?string $role = null, bool $allowForcedPasswordChange = false): void
{
    if (empty($_SESSION['user_id'])) {
        setFlash('error', 'Please sign in to continue.');
        redirect('/sign-in.php');
    }

    $user = currentUser();
    if (!$user) {
        unset($_SESSION['user_id'], $_SESSION['user_role']);
        session_regenerate_id(true);
        setFlash('error', 'Your session has expired. Please sign in again.');
        redirect('/sign-in.php');
    }

    if (($user['status'] ?? null) !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['user_role']);
        session_regenerate_id(true);
        setFlash('error', 'Your account is not active. Please sign in again or contact the administrator.');
        redirect('/sign-in.php');
    }

    if ($role && ($user['role'] ?? null) !== $role) {
        setFlash('error', 'You do not have permission to access that page.');
        if (($user['role'] ?? null) === 'admin') {
            redirect('/admin/index.php');
        }
        redirect('/student/dashboard.php');
    }

    if (!$allowForcedPasswordChange && ($user['role'] ?? null) === 'admin'
        && (int) ($user['must_change_password'] ?? 0) === 1) {
        setFlash('warning', 'Change the initial administrator password before continuing.');
        redirect('/admin/change-password.php');
    }
}

function maskedEmail(string $email): string
{
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return '***@***';
    }

    $local = $parts[0];
    $domain = $parts[1];
    $visibleLocal = substr($local, 0, 2);
    $visibleDomain = substr($domain, 0, 2);

    return $visibleLocal . '***@' . $visibleDomain . '***';
}

function generateOtp(): string
{
    return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function generateStudentNumber(): string
{
    $year = date('Y');
    $stmt = db()->prepare('SELECT COUNT(*) AS total FROM students');
    $stmt->execute();
    $total = (int) $stmt->fetchColumn();
    return 'NEX-' . $year . '-' . str_pad((string) ($total + 1), 4, '0', STR_PAD_LEFT);
}

function storeMessage(string $subject, string $message, string $senderName = 'System', string $senderEmail = 'noreply@nexora.example'): void
{
    $stmt = db()->prepare('INSERT INTO messages (subject, message, sender_name, sender_email, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$subject, $message, $senderName, $senderEmail]);
}

function logEmailAttempt(string $recipientEmail, string $subject, string $status, string $message = ''): void
{
    try {
        $stmt = db()->prepare('INSERT INTO email_logs (recipient_email, subject, status, message, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$recipientEmail, $subject, $status, $message]);
    } catch (Throwable $e) {
        error_log('Email log insertion failed: ' . $e->getMessage());
    }
}
