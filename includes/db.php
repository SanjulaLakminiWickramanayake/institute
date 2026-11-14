<?php

require_once __DIR__ . '/bootstrap.php';

function getDbConnection(): PDO
{
    return db();
}
