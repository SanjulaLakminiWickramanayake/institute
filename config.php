<?php

function loadProjectEnvironment(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read the application environment file.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '' || !preg_match('/^[A-Z0-9_]+$/i', $name)) {
            continue;
        }

        if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"')
            || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        } else {
            $value = preg_replace('/\s+#.*$/', '', $value);
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

function projectEnv(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : (string) $value;
}

$environmentPath = __DIR__ . '/.env';
if (!is_file($environmentPath) && is_file(__DIR__ . '/.env.example')
    && !copy(__DIR__ . '/.env.example', $environmentPath)) {
    throw new RuntimeException('Unable to create the local .env configuration file.');
}

loadProjectEnvironment($environmentPath);

define('APP_NAME', projectEnv('APP_NAME', 'Nexora Institute'));
define('APP_ENV', projectEnv('APP_ENV', 'local'));
define('APP_DEBUG', filter_var(projectEnv('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));
define('APP_URL', rtrim(projectEnv('APP_URL', 'http://localhost/institute'), '/'));

define('DB_HOST', projectEnv('DB_HOST', 'localhost'));
define('DB_PORT', projectEnv('DB_PORT', '3306'));
define('DB_NAME', projectEnv('DB_DATABASE', projectEnv('DB_NAME', 'nexora_institute')));
define('DB_USER', projectEnv('DB_USERNAME', projectEnv('DB_USER', 'root')));
define('DB_PASS', projectEnv('DB_PASSWORD', projectEnv('DB_PASS', '')));

define('MAIL_MAILER', projectEnv('MAIL_MAILER', 'smtp'));
define('MAIL_HOST', projectEnv('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', projectEnv('MAIL_PORT', '587'));
define('MAIL_USERNAME', projectEnv('MAIL_USERNAME', 'nexorainstitute026@gmail.com'));
define('MAIL_PASSWORD', preg_replace('/\s+/', '', projectEnv('MAIL_PASSWORD')));
define('MAIL_SMTP_SECURE', strtolower(projectEnv('MAIL_ENCRYPTION', projectEnv('MAIL_SMTP_SECURE', 'tls'))));
define('MAIL_FROM_EMAIL', projectEnv('MAIL_FROM_ADDRESS', projectEnv('MAIL_FROM_EMAIL', 'nexorainstitute026@gmail.com')));
define('MAIL_FROM_NAME', projectEnv('MAIL_FROM_NAME', APP_NAME));

define('ADMIN_EMAIL', strtolower(projectEnv('ADMIN_EMAIL', 'admin@gmail.com')));
define('ADMIN_INITIAL_PASSWORD', projectEnv('ADMIN_INITIAL_PASSWORD'));
