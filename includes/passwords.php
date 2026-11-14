<?php

function hashPassword(string $password): string
{
    if ($password === '') {
        throw new InvalidArgumentException('Passwords cannot be empty.');
    }

    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword(string $password, string $hash): bool
{
    return $password !== '' && $hash !== '' && password_verify($password, $hash);
}

function verifyUserPassword(int $userId, string $password): bool
{
    $statement = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$userId]);
    $hash = $statement->fetchColumn();

    return is_string($hash) && verifyPassword($password, $hash);
}
