<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/passwords.php';

function splitDatabaseStatements(string $sql): array
{
    $statements = [];
    $statement = '';
    $quote = null;
    $length = strlen($sql);

    for ($index = 0; $index < $length; $index++) {
        $character = $sql[$index];

        if ($quote !== null) {
            $statement .= $character;
            if ($character === '\\' && $quote !== '`' && $index + 1 < $length) {
                $statement .= $sql[++$index];
                continue;
            }
            if ($character === $quote) {
                if ($index + 1 < $length && $sql[$index + 1] === $quote) {
                    $statement .= $sql[++$index];
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if ($character === "'" || $character === '"' || $character === '`') {
            $quote = $character;
            $statement .= $character;
        } elseif ($character === ';') {
            if (trim($statement) !== '') {
                $statements[] = trim($statement);
            }
            $statement = '';
        } else {
            $statement .= $character;
        }
    }

    if (trim($statement) !== '') {
        $statements[] = trim($statement);
    }

    return $statements;
}

function createDatabaseConnection(bool $selectDatabase): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;%scharset=utf8mb4',
        DB_HOST,
        DB_PORT,
        $selectDatabase ? 'dbname=' . DB_NAME . ';' : ''
    );

    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function ensureNexoraUserSchema(PDO $pdo): bool
{
    $changed = false;
    $column = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "users" AND COLUMN_NAME = "username"'
    );
    $column->execute();
    if ((int) $column->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE users ADD COLUMN username VARCHAR(150) NULL AFTER full_name');
        $changed = true;
    }

    $index = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "users" AND INDEX_NAME = "uq_users_username"'
    );
    $index->execute();
    if ((int) $index->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_username (username)');
        $changed = true;
    }

    return $changed;
}

function migratePlaintextUserPasswords(PDO $pdo): void
{
    $users = $pdo->query('SELECT id, password_hash FROM users')->fetchAll();
    $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');

    foreach ($users as $user) {
        $stored = (string) $user['password_hash'];
        if (($stored === '' || (password_get_info($stored)['algoName'] ?? 'unknown') === 'unknown')) {
            $update->execute([hashPassword($stored !== '' ? $stored : bin2hex(random_bytes(32))), $user['id']]);
        }
    }
}

function ensureInitialAdministrator(PDO $pdo): void
{
    $username = strtolower(trim(ADMIN_EMAIL));
    if ($username !== 'admin@gmail.com' || !filter_var($username, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('ADMIN_EMAIL must be admin@gmail.com to initialize the administrator account.');
    }

    $pdo->beginTransaction();
    try {
        $lookup = $pdo->prepare('SELECT id, full_name, role, username, email, status FROM users WHERE email = ? OR username = ? FOR UPDATE');
        $lookup->execute([$username, $username]);
        $matches = $lookup->fetchAll();

        if ($matches) {
            $ids = array_unique(array_map(static function (array $row): int {
                return (int) $row['id'];
            }, $matches));
            if (count($ids) !== 1 || $matches[0]['role'] !== 'admin') {
                throw new RuntimeException('The configured administrator username or email conflicts with another user account.');
            }

            $existingAdmin = $matches[0];
            if ($existingAdmin['full_name'] !== 'Nexora Administrator'
                || $existingAdmin['username'] !== $username
                || $existingAdmin['email'] !== $username
                || $existingAdmin['status'] !== 'active') {
                $pdo->prepare('UPDATE users SET full_name = ?, username = ?, email = ?, status = "active" WHERE id = ?')
                    ->execute(['Nexora Administrator', $username, $username, $existingAdmin['id']]);
            }
            $pdo->commit();
            return;
        }

        $password = ADMIN_INITIAL_PASSWORD;
        if ($password === '' || $password === 'SET_THE_PROVIDED_INITIAL_ADMIN_PASSWORD_LOCALLY'
            || strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new RuntimeException('ADMIN_INITIAL_PASSWORD is not configured with a valid initial password.');
        }

        $insert = $pdo->prepare(
            'INSERT INTO users (full_name, username, email, password_hash, role, status, must_change_password, created_at)
             VALUES (?, ?, ?, ?, "admin", "active", 1, NOW())'
        );
        $insert->execute(['Nexora Administrator', $username, $username, hashPassword($password)]);

        $verify = $pdo->prepare(
            'SELECT full_name, username, email, password_hash, role, status, temp_password, must_change_password
             FROM users WHERE username = ? AND email = ? LIMIT 1'
        );
        $verify->execute([$username, $username]);
        $createdAdmin = $verify->fetch();
        if (!$createdAdmin
            || $createdAdmin['full_name'] !== 'Nexora Administrator'
            || $createdAdmin['role'] !== 'admin'
            || $createdAdmin['status'] !== 'active'
            || (int) $createdAdmin['temp_password'] !== 0
            || (int) $createdAdmin['must_change_password'] !== 1
            || !verifyPassword($password, $createdAdmin['password_hash'])) {
            throw new RuntimeException('The initial administrator record could not be verified after insertion.');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function initializeNexoraDatabase(): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', DB_NAME)) {
        throw new RuntimeException('DB_DATABASE contains invalid characters.');
    }

    $server = createDatabaseConnection(false);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = createDatabaseConnection(true);
    $pdo->exec('SET time_zone = ' . $pdo->quote(date('P')));

    $requiredTables = [
        'categories', 'intakes', 'courses', 'users', 'student_signup_details',
        'signup_verifications', 'applications', 'students', 'messages',
        'email_logs', 'password_resets',
    ];
    $tableQuery = $pdo->prepare(
        'SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = "BASE TABLE"'
    );
    $tableQuery->execute();
    $existingTables = array_fill_keys($tableQuery->fetchAll(PDO::FETCH_COLUMN), true);

    $missingTables = array_diff($requiredTables, array_keys($existingTables));
    if ($missingTables) {
        $schemaPath = __DIR__ . '/schema.sql';
        $schema = file_get_contents($schemaPath);
        if ($schema === false) {
            throw new RuntimeException('Unable to read database/schema.sql.');
        }

        foreach (splitDatabaseStatements($schema) as $statement) {
            if (preg_match('/^\s*(?:CREATE\s+DATABASE|USE)\b/i', $statement)) {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    $userSchemaChanged = ensureNexoraUserSchema($pdo);
    if ($missingTables || $userSchemaChanged) {
        migratePlaintextUserPasswords($pdo);
    }
    ensureInitialAdministrator($pdo);
    $initialized = true;
}

if (PHP_SAPI === 'cli'
    && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        initializeNexoraDatabase();
        fwrite(STDOUT, "Nexora database initialized and administrator account verified.\n");
    } catch (Throwable $exception) {
        error_log('Nexora database initialization failed: ' . $exception->getMessage());
        fwrite(STDERR, "Database initialization failed. Check local MySQL settings and PHP error logs.\n");
        exit(1);
    }
}
