<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

try {
    initializeNexoraDatabase();
    fwrite(STDOUT, "Nexora database initialized and administrator account verified.\n");
} catch (Throwable $exception) {
    error_log('Nexora database initialization failed: ' . $exception->getMessage());
    fwrite(STDERR, "Database initialization failed. Check local MySQL settings and PHP error logs.\n");
    exit(1);
}
