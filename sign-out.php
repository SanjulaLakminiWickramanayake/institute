<?php
require_once __DIR__ . '/includes/bootstrap.php';

$_SESSION = [];
session_regenerate_id(true);
setFlash('success', 'You have been signed out.');
redirect('/sign-in.php');
