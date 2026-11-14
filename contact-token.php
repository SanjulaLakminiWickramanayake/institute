<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');
echo json_encode(['token' => csrfToken()]);
